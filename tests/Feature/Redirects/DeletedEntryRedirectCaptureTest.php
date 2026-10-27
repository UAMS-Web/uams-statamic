<?php

declare(strict_types=1);

use App\Support\Redirects\DeletedEntryRedirectCapture;
use App\Support\Redirects\RedirectRuleMatcher;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Covers the redirect captured when an entry with a public URL is deleted (UAMS-Web/uams-statamic-archive#301).
 *
 * The load-bearing property is that the captured row is **disabled**: it exists so an
 * editor can complete it, and `RedirectRuleMatcher` must not serve it in the meantime. A
 * capture that served immediately would send visitors to an empty destination, which is
 * worse than the 404 it replaces.
 *
 * @command  php artisan test --compact tests/Feature/Redirects/DeletedEntryRedirectCaptureTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $set = GlobalSet::make('redirects');

    if (! $set instanceof Statamic\Globals\GlobalSet) {
        throw new RuntimeException('GlobalSet::make() did not return a global set.');
    }
    $set->title('Redirects');
    $set->save();

    $vars = $set->makeLocalization(defaultSiteHandle());

    if (! $vars instanceof Variables) {
        throw new RuntimeException('makeLocalization() did not return variables.');
    }

    $vars->data(['redirects' => []]);
    $vars->save();

    $c = Collection::make('capture_pages');
    $c->routes('/{slug}');
    $c->title('Capture Pages');
    $c->save();
});

/**
 * Read the stored redirect rows back off the default site's global.
 *
 * @return array<int, array<string, mixed>> The stored rows.
 */
function storedRedirectRows(): array
{
    /** @var Statamic\Globals\GlobalSet $global */
    $global = GlobalSet::findByHandle('redirects');

    /** @var Statamic\Sites\Site $site */
    $site = Site::default();
    $handle = $site->handle();

    /** @var Variables|null $vars */
    $vars = $global->in(is_string($handle) ? $handle : 'default');

    $rows = $vars?->get('redirects', []);

    if (! is_array($rows)) {
        return [];
    }

    /** @var array<int, array<string, mixed>> $typed */
    $typed = [];

    foreach ($rows as $row) {
        if (is_array($row)) {
            /** @var array<string, mixed> $row */
            $typed[] = $row;
        }
    }

    return $typed;
}

/**
 * Build and save a published entry, narrowed to the concrete class.
 *
 * `Entry::make()` is `mixed` to the analyser, so every call site would otherwise repeat the
 * same narrowing. Doing it once here keeps the tests readable and the analysis honest.
 *
 * @param  string  $collection  Collection handle.
 * @param  string  $slug  Entry slug.
 * @return ConcreteEntry The saved entry.
 */
function makeSavedEntry(string $collection, string $slug): ConcreteEntry
{
    $entry = Entry::make();

    if (! $entry instanceof ConcreteEntry) {
        throw new RuntimeException('Entry::make() did not return an entry.');
    }

    $entry->collection($collection);
    $entry->slug($slug);
    $entry->published(true);
    $entry->save();

    return $entry;
}

/**
 * The default site handle, narrowed.
 *
 * @return string The handle, or `default` when it cannot be read.
 */
function defaultSiteHandle(): string
{
    $site = Site::default();

    if (! $site instanceof Statamic\Sites\Site) {
        return 'default';
    }

    $handle = $site->handle();

    return is_string($handle) ? $handle : 'default';
}

it('captures a disabled redirect for a deleted entry that had a URL', function (): void {
    $entry = makeSavedEntry('capture_pages', 'going-away');

    $uri = $entry->uri();
    $uri = is_string($uri) ? $uri : '';

    expect(app(DeletedEntryRedirectCapture::class)->captureFor($entry))->toBe(1);

    $rows = storedRedirectRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['source'])->toBe($uri)
        ->and($rows[0]['enabled'])->toBeFalse()
        ->and($rows[0]['target_url'])->toBe('');
});

it('does not serve the captured redirect, because it is disabled', function (): void {
    $entry = makeSavedEntry('capture_pages', 'going-away');

    $uri = $entry->uri();
    $uri = is_string($uri) ? $uri : '';

    app(DeletedEntryRedirectCapture::class)->captureFor($entry);

    /** @var Statamic\Globals\GlobalSet $global */
    $global = GlobalSet::findByHandle('redirects');
    $rules = app(RedirectRuleMatcher::class)->rulesForSite($global, defaultSiteHandle(), defaultSiteHandle());

    // The row IS in the rule list -- `rulesForSite()` does not filter on `enabled`; `match()`
    // does. Asserting on the rule list would pass whether or not the row is disabled, so the
    // assertion that carries the design is the match result.
    $matched = app(RedirectRuleMatcher::class)->match($rules, $uri, '');

    expect(storedRedirectRows())->toHaveCount(1)
        ->and(collect($rules)->firstWhere('source', $uri))->not->toBeNull()
        ->and($matched)->toBe([]);
});

it('captures nothing for an entry that never had a URL', function (): void {
    $c = Collection::make('routeless')->title('Routeless');
    if (! $c instanceof Statamic\Entries\Collection) {
        throw new RuntimeException('Collection::make() did not return a collection.');
    }
    $c->save();

    $entry = makeSavedEntry('routeless', 'no-url');

    expect(app(DeletedEntryRedirectCapture::class)->captureFor($entry))->toBe(0)
        ->and(storedRedirectRows())->toHaveCount(0);
});

it('does not capture a second row for a URI that already has a rule', function (): void {
    $entry = makeSavedEntry('capture_pages', 'going-away');

    $capture = app(DeletedEntryRedirectCapture::class);

    expect($capture->captureFor($entry))->toBe(1)
        ->and($capture->captureFor($entry))->toBe(0)
        ->and(storedRedirectRows())->toHaveCount(1);
});

it('captures through the EntryDeleted listener, not only through the service', function (): void {
    $entry = makeSavedEntry('capture_pages', 'deleted-via-event');

    $uri = $entry->uri();
    $uri = is_string($uri) ? $uri : '';

    // Delete through Statamic so the real event fires and the registered listener runs --
    // calling the service directly would leave the wiring itself untested.
    $entry->delete();

    $rows = storedRedirectRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['source'])->toBe($uri)
        ->and($rows[0]['enabled'])->toBeFalse();
});

it('captures a page-tree entry\'s full URL, parent segments included', function (): void {
    // A page tree, which is where redirects matter most.
    $pages = Collection::make('capture_tree');
    $pages->routes('{parent_uri}/{slug}');
    $pages->title('Capture Tree');
    $pages->structureContents(['root' => true]);
    $pages->save();

    $home = makeSavedEntry('capture_tree', 'home');
    $parent = makeSavedEntry('capture_tree', 'services');
    $child = makeSavedEntry('capture_tree', 'cardiology');

    $structure = $pages->structure();

    if (! $structure instanceof CollectionStructure) {
        throw new RuntimeException('The collection has no structure.');
    }

    $tree = $structure->in(defaultSiteHandle());

    if (! $tree instanceof Tree) {
        throw new RuntimeException('The structure has no tree for the default site.');
    }

    $tree->append($home);
    $tree->append($parent);
    $tree->save();

    $tree = $structure->in(defaultSiteHandle());

    if (! $tree instanceof Tree) {
        throw new RuntimeException('The structure has no tree for the default site.');
    }

    $tree->appendTo($parent->id(), $child);
    $tree->save();

    $fresh = Entry::find($child->id());

    if (! $fresh instanceof ConcreteEntry) {
        throw new RuntimeException('The child entry could not be re-read.');
    }

    // Control: the live URL carries its parent segment.
    expect($fresh->uri())->toBe('/services/cardiology');

    $fresh->delete();

    $rows = storedRedirectRows();

    // This pins behavior the capture DEPENDS ON rather than behavior it implements.
    // `EntryDeleted` fires once the entry has left its structure, so a URI re-derived at
    // that point would be the parentless '/cardiology' -- a URL that never existed --
    // while the URL that just began returning 404 went unrecorded. It is correct today
    // because Statamic resolves and caches the entry's URI during the delete itself, an
    // implementation detail of `Entry::delete()` rather than a documented guarantee.
    // Should that change, this test is what turns a silently wrong redirect into a red
    // suite. Measured on Statamic 6 (macOS): a truly cold process, with `uri()` never read
    // before the delete, still captures the full path.
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['source'])->toBe('/services/cardiology')
        ->and($rows[0]['enabled'])->toBeFalse();
});
