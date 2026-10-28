<?php

declare(strict_types=1);

use App\Console\Commands\ConvertLegacyVideoSets;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The one-shot `content:convert-legacy-video-sets` command (UAMS-Web/uams-statamic-archive#834): it
 * rewrites each stored Bard `video` set whose URL names a known platform into that
 * platform's set, reports each entry it changes and the totals, warns about blocks
 * it cannot route, and saves nothing on a dry run.
 *
 * @command  php artisan test --compact tests/Feature/Console/ConvertLegacyVideoSetsCommandTest.php
 */
uses(StacheIsolatedTestCase::class);

covers(ConvertLegacyVideoSets::class);

beforeEach(function (): void {
    $pages = CollectionFacade::make('pages');
    $pages->title('Pages');
    $pages->save();
});

/**
 * Bard content holding one legacy `video` set per given URL.
 *
 * @param  list<string>  $urls
 * @return list<array<string, mixed>>
 */
function convertLegacyVideoContent(array $urls): array
{
    return array_map(
        static fn (string $url): array => ['type' => 'set', 'attrs' => ['id' => md5($url), 'values' => ['type' => 'video', 'video_url' => $url]]],
        $urls
    );
}

/**
 * Persist a `pages` entry with the given Bard content and return its id.
 *
 * @param  list<array<string, mixed>>  $content
 */
function convertLegacyVideoPage(string $slug, array $content): string
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('pages');
    $entry->slug($slug);
    $entry->data(['title' => ucfirst($slug), 'content' => $content]);
    $entry->saveQuietly();

    return mustBeString($entry->id(), 'the entry id');
}

/**
 * The `values` of every set in an entry's stored content, read fresh.
 *
 * @return list<array<string, mixed>>
 */
function convertLegacyVideoStoredValues(string $id): array
{
    $entry = Entry::find($id);
    expect($entry)->toBeInstanceOf(EntryInstance::class);
    /** @var EntryInstance $entry */

    /** @var list<array{attrs: array{values: array<string, mixed>}}> $content */
    $content = $entry->get('content');

    return array_map(static fn (array $set): array => $set['attrs']['values'], $content);
}

it('converts each routable video set, and reports the entry and the totals', function (): void {
    $id = convertLegacyVideoPage('two-videos', convertLegacyVideoContent([
        'https://www.youtube.com/watch?v=abc',
        'https://vimeo.com/123',
    ]));

    pendingArtisan('content:convert-legacy-video-sets')
        ->expectsOutput('Converted '.$id.' (2 blocks)')
        ->expectsOutput('Converted 2 block(s) across 1 entry.')
        ->doesntExpectOutputToContain('Skipped')
        ->assertExitCode(0);

    expect(convertLegacyVideoStoredValues($id))->toBe([
        ['type' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=abc', 'embed_mode' => 'simple'],
        ['type' => 'vimeo', 'url' => 'https://vimeo.com/123', 'embed_mode' => 'simple'],
    ]);
});

it('says block, not blocks, for an entry with one conversion, and counts entries in the plural', function (): void {
    $first = convertLegacyVideoPage('one-video', convertLegacyVideoContent(['https://app.box.com/s/abc']));
    $second = convertLegacyVideoPage('another-video', convertLegacyVideoContent(['https://youtu.be/xyz']));

    pendingArtisan('content:convert-legacy-video-sets')
        ->expectsOutputToContain('(1 block)')
        ->doesntExpectOutputToContain('(1 blocks)')
        ->expectsOutput('Converted 2 block(s) across 2 entries.')
        ->assertExitCode(0);

    expect(convertLegacyVideoStoredValues($first)[0]['type'])->toBe('box')
        ->and(convertLegacyVideoStoredValues($second)[0]['type'])->toBe('youtube');
});

it('reports a dry run without saving anything', function (): void {
    $id = convertLegacyVideoPage('dry-run', convertLegacyVideoContent(['https://vimeo.com/9']));

    pendingArtisan('content:convert-legacy-video-sets', ['--dry-run' => true])
        ->expectsOutput('[dry-run] '.$id.' (1 block)')
        ->expectsOutput('Would convert 1 block(s) across 1 entry.')
        ->doesntExpectOutputToContain('Converted')
        ->assertExitCode(0);

    expect(convertLegacyVideoStoredValues($id))->toBe([['type' => 'video', 'video_url' => 'https://vimeo.com/9']]);
});

it('warns about video sets with an unrecognized host and leaves them, even in an entry with nothing converted', function (): void {
    $id = convertLegacyVideoPage('unknown-host', convertLegacyVideoContent([
        'https://example.com/clip.mp4',
        'https://dropbox.com/clip',
    ]));

    pendingArtisan('content:convert-legacy-video-sets')
        ->doesntExpectOutputToContain($id)
        ->expectsOutput('Converted 0 block(s) across 0 entries.')
        ->expectsOutputToContain('Skipped 2 video block(s) with an unrecognized host')
        ->assertExitCode(0);

    expect(convertLegacyVideoStoredValues($id))->toBe([
        ['type' => 'video', 'video_url' => 'https://example.com/clip.mp4'],
        ['type' => 'video', 'video_url' => 'https://dropbox.com/clip'],
    ]);
});

it('counts a skipped block in an entry it also converts', function (): void {
    $id = convertLegacyVideoPage('mixed', convertLegacyVideoContent([
        'https://vimeo.com/1',
        'https://example.com/clip.mp4',
    ]));

    pendingArtisan('content:convert-legacy-video-sets')
        ->expectsOutput('Converted '.$id.' (1 block)')
        ->expectsOutput('Converted 1 block(s) across 1 entry.')
        ->expectsOutputToContain('Skipped 1 video block(s) with an unrecognized host')
        ->assertExitCode(0);
});

it('keeps walking past an entry with nothing to convert', function (): void {
    // Several entries with nothing to convert, saved before the one that needs it,
    // so the walk has to carry on past them to reach it
    foreach (['clean-a', 'clean-b', 'clean-c'] as $slug) {
        convertLegacyVideoPage($slug, [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'No video']]]]);
    }
    $id = convertLegacyVideoPage('needs-conversion', convertLegacyVideoContent(['https://vimeo.com/5']));

    pendingArtisan('content:convert-legacy-video-sets')
        ->expectsOutput('Converted '.$id.' (1 block)')
        ->expectsOutput('Converted 1 block(s) across 1 entry.')
        ->assertExitCode(0);

    expect(convertLegacyVideoStoredValues($id)[0]['type'])->toBe('vimeo');
});
