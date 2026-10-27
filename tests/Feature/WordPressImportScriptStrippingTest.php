<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Support\StacheIsolatedTestCase;
use UAMSWeb\WordPressImporter\Services\WordPressImport\Assets\AssetImporter;
use UAMSWeb\WordPressImporter\Services\WordPressImport\Parsers\XmlParser;
use UAMSWeb\WordPressImporter\Services\WordPressImport\Utilities\TextProcessor;
use UAMSWeb\WordPressImporter\Services\WordPressImport\Utilities\XpathHelper;

/**
 * End-to-end cover for the import path's script-stripping invariant (UAMS-Web/uams-statamic-archive#1742).
 *
 * {@see BardParserScriptStrippingTest} pins the parser in isolation; this pins
 * the path a real WordPress post actually travels — WXR file → `XmlParser` →
 * `TextProcessor::processForBard()` (with `WpAutoP` on, as the WYSIWYG call
 * sites configure it) → a saved Statamic entry — and asserts on the YAML that
 * lands on disk.
 *
 * Covering the call site rather than the parser matters because `WpAutoP`
 * *deliberately* preserves `<script>`, `<style>`, `<svg>` and `<math>` through
 * formatting so the parser downstream can see them intact. Read alone that
 * looks like a hole; it is safe only because of what happens next. This test is
 * what makes that composition an asserted property instead of a coincidence.
 *
 * @command  php artisan test --compact tests/Feature/WordPressImportScriptStrippingTest.php
 */
uses(StacheIsolatedTestCase::class);

const SCRIPT_STRIPPING_COLLECTION = 'script_stripping_posts';

/**
 * The YAML a saved entry actually wrote to disk.
 *
 * `Entry::path()` is untyped on the contract, so narrow it here rather than at
 * the assertion — an entry that saved without a path is a broken fixture, not a
 * passing test.
 */
function scriptStrippingWrittenYaml(Statamic\Entries\Entry $entry): string
{
    $path = $entry->path();

    if (! is_string($path) || $path === '') {
        throw new RuntimeException('The saved entry reported no file path.');
    }

    return File::get($path);
}

beforeEach(function (): void {
    $posts = CollectionFacade::make(SCRIPT_STRIPPING_COLLECTION);
    $posts->title('Script Stripping Posts');
    $posts->save();
});

it('carries no script out of WordPress post content and into the written entry', function (): void {
    $parser = new XmlParser;
    $parser->parse(base_path('tests/Fixtures/wordpress-import/script-injection.xml'));

    $items = $parser->getItems('post');
    expect($items)->toHaveCount(1);

    $postContent = XpathHelper::getStringValue($items[0], 'content:encoded');

    // Guard the fixture itself: if the WXR ever stops carrying the payloads, every
    // assertion below would pass vacuously.
    expect($postContent)
        ->toContain('<script>alert(1)</script>')
        ->toContain('<iframe')
        ->toContain('javascript:alert(1)')
        ->toContain('onclick');

    $bardContent = TextProcessor::processForBard(
        $postContent,
        'default',
        new AssetImporter,
        new NullOutput,
        true
    );

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(SCRIPT_STRIPPING_COLLECTION);
    $entry->slug('sickle-cell-clinic-hours');
    $entry->data(['title' => 'Sickle Cell Clinic Hours', 'content' => $bardContent]);
    $entry->saveQuietly();

    $written = scriptStrippingWrittenYaml($entry);

    expect($written)
        ->not->toContain('<script')
        ->not->toContain('<iframe')
        ->not->toContain('javascript:')
        ->not->toContain('onclick')
        ->not->toContain('alert(1)')
        ->not->toContain('evil.test');

    // The editorial copy must survive, or the assertions above would also hold for
    // an import that produced nothing at all — the false green this test exists to
    // rule out. The safe link survives with it, so an over-aggressive schema that
    // dropped every anchor would fail here rather than read as extra safety.
    expect($written)
        ->toContain('The Sickle Cell Clinic sees patients weekday mornings.')
        ->toContain('Walk-ins are accepted before 10 a.m.')
        ->toContain('https://uams.edu/parking');
});
