<?php

declare(strict_types=1);

use App\Console\Commands\BackfillBardRelNoopener;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The one-shot `content:backfill-bard-rel` command (UAMS-Web/uams-statamic-archive#724): it sets
 * `rel: 'noopener noreferrer'` on stored Bard links that open in a new window with
 * no `rel`, reports each entry it changes, and saves nothing on a dry run.
 *
 * @command  php artisan test --compact tests/Feature/Console/BackfillBardRelNoopenerCommandTest.php
 */
uses(StacheIsolatedTestCase::class);

covers(BackfillBardRelNoopener::class);

beforeEach(function (): void {
    $pages = CollectionFacade::make('pages');
    $pages->title('Pages');
    $pages->save();
});

/**
 * A Bard paragraph holding one link mark per given `rel` value.
 *
 * @param  list<?string>  $relValues
 * @return list<array<string, mixed>>
 */
function backfillBardRelContent(array $relValues): array
{
    $text = [];

    foreach ($relValues as $index => $rel) {
        $text[] = [
            'type' => 'text',
            'text' => 'Link '.$index,
            'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com/'.$index, 'rel' => $rel, 'target' => '_blank']]],
        ];
    }

    return [['type' => 'paragraph', 'content' => $text]];
}

/**
 * Persist a `pages` entry with the given Bard content and return its id.
 *
 * @param  list<array<string, mixed>>  $content
 */
function backfillBardRelPage(string $slug, array $content): string
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
 * The `rel` of every link mark in an entry's stored content, read fresh.
 *
 * @return list<mixed>
 */
function backfillBardRelStoredValues(string $id): array
{
    $entry = Entry::find($id);
    expect($entry)->toBeInstanceOf(EntryInstance::class);
    /** @var EntryInstance $entry */

    /** @var list<array{content: list<array{marks: list<array{attrs: array<string, mixed>}>}>}> $content */
    $content = $entry->get('content');

    return array_map(
        static fn (array $text): mixed => $text['marks'][0]['attrs']['rel'],
        $content[0]['content']
    );
}

it('fixes each new-window link with no rel, and reports the entry and the totals', function (): void {
    $id = backfillBardRelPage('two-links', backfillBardRelContent([null, '']));

    pendingArtisan('content:backfill-bard-rel')
        ->expectsOutput('Fixed '.$id.' (2 links)')
        ->expectsOutput('Fixed 2 link(s) across 1 entry.')
        ->assertExitCode(0);

    expect(backfillBardRelStoredValues($id))->toBe(['noopener noreferrer', 'noopener noreferrer']);
});

it('says link, not links, for an entry with one fix, and counts entries in the plural', function (): void {
    $first = backfillBardRelPage('one-link', backfillBardRelContent([null]));
    $second = backfillBardRelPage('another-link', backfillBardRelContent([null]));

    pendingArtisan('content:backfill-bard-rel')
        ->expectsOutputToContain('(1 link)')
        ->doesntExpectOutputToContain('(1 links)')
        ->expectsOutput('Fixed 2 link(s) across 2 entries.')
        ->assertExitCode(0);

    expect(backfillBardRelStoredValues($first))->toBe(['noopener noreferrer'])
        ->and(backfillBardRelStoredValues($second))->toBe(['noopener noreferrer']);
});

it('reports a dry run without saving anything', function (): void {
    $id = backfillBardRelPage('dry-run', backfillBardRelContent([null, null]));

    pendingArtisan('content:backfill-bard-rel', ['--dry-run' => true])
        ->expectsOutput('[dry-run] '.$id.' (2 links)')
        ->expectsOutput('Would fix 2 link(s) across 1 entry.')
        ->doesntExpectOutputToContain('Fixed')
        ->assertExitCode(0);

    expect(backfillBardRelStoredValues($id))->toBe([null, null]);
});

it('leaves an entry whose new-window links already carry a rel alone, and reports nothing to fix', function (): void {
    $id = backfillBardRelPage('already-set', backfillBardRelContent(['noopener']));

    pendingArtisan('content:backfill-bard-rel')
        ->doesntExpectOutputToContain($id)
        ->expectsOutput('Fixed 0 link(s) across 0 entries.')
        ->assertExitCode(0);

    expect(backfillBardRelStoredValues($id))->toBe(['noopener']);
});

it('keeps walking past an entry with nothing to fix', function (): void {
    // Several entries with nothing to fix, saved before the one that needs fixing,
    // so the walk has to carry on past them to reach it
    foreach (['clean-a', 'clean-b', 'clean-c'] as $slug) {
        backfillBardRelPage($slug, backfillBardRelContent(['noopener']));
    }
    $id = backfillBardRelPage('needs-fix', backfillBardRelContent([null]));

    pendingArtisan('content:backfill-bard-rel')
        ->expectsOutput('Fixed '.$id.' (1 link)')
        ->expectsOutput('Fixed 1 link(s) across 1 entry.')
        ->assertExitCode(0);

    expect(backfillBardRelStoredValues($id))->toBe(['noopener noreferrer']);
});
