<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\YAML;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for UAMS-Web/uams-statamic#459: a reusable content entry renders from
 * whichever of its two fields it has, whichever kind of page references it. The
 * blueprints are the real ones, copied into the isolated repository, and the
 * page body is the loop from `pages/default` or `pages/page_landing`, so every
 * value comes through real augmentation and the real partials.
 *
 * @command php artisan test --compact tests/Feature/Views/ReusableContentPairingsTest.php
 */
uses(StacheIsolatedTestCase::class);

const REUSABLE_BARD_TEXT = 'ReusableBardText';

const REUSABLE_BLOCK_HEADING = 'ReusableBlockHeading';

/**
 * Copy a real blueprint into the isolated blueprint repository.
 */
function copyRealBlueprint(string $collection, string $handle): void
{
    /** @var array<string, mixed> $contents */
    $contents = YAML::parse(File::get(resource_path("blueprints/collections/{$collection}/{$handle}.yaml")));

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make($handle);
    $blueprint->setNamespace("collections.{$collection}");
    $blueprint->setContents($contents);
    $blueprint->save();
}

/**
 * Save a published entry.
 *
 * @param  array<string, mixed>  $data
 */
function saveReusableFixture(string $collection, string $blueprint, string $id, array $data, bool $published = true): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    /** @var EntryInstance $entry */
    $entry = $entry->collection($collection);
    /** @var EntryInstance $entry */
    $entry = $entry->blueprint($blueprint);
    /** @var EntryInstance $entry */
    $entry = $entry->id($id);
    /** @var EntryInstance $entry */
    $entry = $entry->slug($id);
    /** @var EntryInstance $entry */
    $entry = $entry->published($published);
    /** @var EntryInstance $entry */
    $entry = $entry->data(['title' => $id] + $data);
    $entry->save();

    return $entry;
}

/**
 * The Bard value a basic reusable entry stores.
 *
 * @return list<array<string, mixed>>
 */
function reusableBardValue(string $text): array
{
    return [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]];
}

/**
 * The page-builder value a landing reusable entry stores.
 *
 * @return list<array<string, mixed>>
 */
function reusableBlockValue(): array
{
    return [[
        'id' => 'inner-block',
        'type' => 'uamsweb_block_content',
        'enabled' => true,
        'block_heading' => REUSABLE_BLOCK_HEADING,
        'body' => reusableBardValue('ReusableBlockBody'),
    ]];
}

/**
 * Render a page of the given kind whose body holds a sibling and then the
 * reference, so a field that resolves to the page shows up as the page's own text.
 */
function renderPageReferencing(string $kind, string $entryId): string
{
    $sibling = reusableBardValue('PageSiblingText');

    if ($kind === 'bard') {
        $page = saveReusableFixture('pages', 'basic_page', 'referencing-page', ['content' => [
            ...$sibling,
            ['type' => 'set', 'attrs' => ['id' => 'reference', 'values' => [
                'type' => 'reusable_content',
                'content' => [$entryId],
            ]]],
        ]]);

        /** @var array<string, mixed> $values */
        $values = $page->toAugmentedArray(['content']);

        return (string) Antlers::parse(
            '{{ content scope="block" }}{{ partial src="sets/{type}" }}{{ /content }}',
            $values,
            true,
        );
    }

    $page = saveReusableFixture('pages', 'landing_page', 'referencing-page', ['page_builder' => [
        [
            'id' => 'page-block',
            'type' => 'uamsweb_block_content',
            'enabled' => true,
            'block_heading' => 'Page block heading',
            'body' => $sibling,
        ],
        [
            'id' => 'reference',
            'type' => 'uamsweb_block_reusable_content_landing_page',
            'enabled' => true,
            'content' => [$entryId],
        ],
    ]]);

    /** @var array<string, mixed> $values */
    $values = $page->toAugmentedArray(['page_builder']);

    return (string) Antlers::parse(
        '{{ page_builder scope="block" }}{{ partial src="page_builder/{type}" }}{{ /page_builder }}',
        $values,
        true,
    );
}

beforeEach(function (): void {
    foreach (['pages', 'reusable_content'] as $handle) {
        /** @var Collection $collection */
        $collection = CollectionFacade::make($handle);
        $collection->title($handle);
        $collection->routes('/'.$handle.'/{slug}');
        $collection->save();
    }

    copyRealBlueprint('pages', 'basic_page');
    copyRealBlueprint('pages', 'landing_page');
    copyRealBlueprint('reusable_content', 'reusable_content_basic_page');
    copyRealBlueprint('reusable_content', 'reusable_content_landing_page');

    saveReusableFixture('reusable_content', 'reusable_content_basic_page', 'basic-entry', [
        'content' => reusableBardValue(REUSABLE_BARD_TEXT),
    ]);
    saveReusableFixture('reusable_content', 'reusable_content_landing_page', 'landing-entry', [
        'page_builder' => reusableBlockValue(),
    ]);
    saveReusableFixture('reusable_content', 'reusable_content_basic_page', 'empty-basic-entry', []);
    saveReusableFixture('reusable_content', 'reusable_content_landing_page', 'empty-landing-entry', []);
});

it('renders a basic entry on a Bard page as bare sets', function (): void {
    expect(renderPageReferencing('bard', 'basic-entry'))
        ->toContain(REUSABLE_BARD_TEXT)
        ->not->toContain('uams-module');
});

it('renders a landing entry on a landing page with no second wrapper', function (): void {
    $html = renderPageReferencing('landing', 'landing-entry');

    expect($html)->toContain(REUSABLE_BLOCK_HEADING)
        ->and(substr_count($html, 'uams-module'))->toBe(2);
});

it('renders a landing entry on a Bard page and does not throw', function (): void {
    expect(renderPageReferencing('bard', 'landing-entry'))
        ->toContain(REUSABLE_BLOCK_HEADING)
        ->toContain('ReusableBlockBody');
});

it('renders a basic entry on a landing page inside the normal block layout', function (): void {
    $html = renderPageReferencing('landing', 'basic-entry');

    expect($html)->toContain(REUSABLE_BARD_TEXT)
        ->and(substr_count($html, 'uams-module'))->toBe(2)
        ->and(preg_match(
            '/<section\s[^>]*uams-module alignfull[^>]*content-block[^>]*>\s*<div\s+class="container-fluid"\s*>\s*<div\s+class="row"\s*>\s*<div class="module-body">\s*<p>'.REUSABLE_BARD_TEXT.'/',
            $html,
        ))->toBe(1);
});

it('gives a basic entry on a landing page no id of its own or from the block before it', function (): void {
    $html = renderPageReferencing('landing', 'basic-entry');

    expect(substr_count($html, ' id="module-page-block"'))->toBe(1)
        ->and(substr_count($html, 'aria-labelledby='))->toBe(1);
});

it('keeps an empty entry nested in another entry from reading the one around it', function (): void {
    saveReusableFixture('reusable_content', 'reusable_content_basic_page', 'nesting-entry', ['content' => [
        ...reusableBardValue(REUSABLE_BARD_TEXT),
        ['type' => 'set', 'attrs' => ['id' => 'nested', 'values' => [
            'type' => 'reusable_content',
            'content' => ['empty-landing-entry'],
        ]]],
    ]]);

    expect(substr_count(renderPageReferencing('bard', 'nesting-entry'), REUSABLE_BARD_TEXT))->toBe(1);
});

it('renders nothing for a draft entry', function (string $kind): void {
    saveReusableFixture('reusable_content', 'reusable_content_basic_page', 'draft-entry', [
        'content' => reusableBardValue(REUSABLE_BARD_TEXT),
    ], false);

    expect(renderPageReferencing($kind, 'draft-entry'))->not->toContain(REUSABLE_BARD_TEXT);
})->with(['bard', 'landing']);

it('renders nothing for an entry with neither field', function (string $kind, string $entryId): void {
    $html = renderPageReferencing($kind, $entryId);

    expect(substr_count($html, 'PageSiblingText'))->toBe(1)
        ->and(substr_count($html, 'uams-module'))->toBe($kind === 'bard' ? 0 : 1)
        ->and($html)->not->toContain(REUSABLE_BLOCK_HEADING);
})->with([
    'a basic entry on a Bard page' => ['bard', 'empty-basic-entry'],
    'a landing entry on a Bard page' => ['bard', 'empty-landing-entry'],
    'a basic entry on a landing page' => ['landing', 'empty-basic-entry'],
    'a landing entry on a landing page' => ['landing', 'empty-landing-entry'],
]);
