<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Events\EntryDeleted;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\GlobalSet as ConcreteGlobalSet;
use Statamic\Globals\Variables;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Pins the multi-site behavior of the deleted-entry redirect capture (UAMS-Web/uams-statamic-archive#2365).
 *
 * The question this answers is whether a localized entry's sibling URLs go dark
 * uncaptured. They do not, and the reason is that Statamic deletes localizations
 * individually: `Entry::delete()` refuses outright while descendants exist, so the only
 * route to deleting a localized entry is `deleteDescendants()` first, and that calls
 * `delete()` on each descendant in turn. Every localization therefore raises its own
 * `EntryDeleted` carrying its own `locale()`, and the single-site capture already writes
 * each row into the right place.
 *
 * That makes this a characterization test rather than a guard on new behavior: it locks in
 * a property the capture gets for free from Statamic's delete semantics, so a future change
 * to either side has to fail here rather than silently start dropping sibling URLs.
 *
 * @command  php artisan test --compact tests/Feature/Redirects/MultisiteDeletedEntryRedirectCaptureTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    Site::setSites([
        'default' => ['name' => 'Default', 'url' => '/', 'locale' => 'en_US'],
        'second' => ['name' => 'Second', 'url' => '/second/', 'locale' => 'en_US'],
    ]);

    $set = GlobalSet::make('redirects');

    if (! $set instanceof ConcreteGlobalSet) {
        throw new RuntimeException('GlobalSet::make() did not return a global set.');
    }

    $set->title('Redirects');

    // The real `redirects` global lists every site in `resources/sites.yaml`, which is what
    // makes `GlobalSet::in()` resolve for any of them — it returns null only for a site the
    // set does not list, and otherwise creates the localization on demand.
    $set->sites(['default', 'second']);
    $set->save();

    foreach (['default', 'second'] as $handle) {
        $vars = $set->makeLocalization($handle);

        if (! $vars instanceof Variables) {
            throw new RuntimeException("makeLocalization({$handle}) did not return variables.");
        }

        $vars->data(['redirects' => []]);
        $vars->save();
    }

    // `Collection::make()` is already concretely typed, so it needs no narrowing here.
    $collection = Collection::make('ms_pages');
    $collection->sites(['default', 'second']);
    $collection->routes(['default' => '/{slug}', 'second' => '/{slug}']);
    $collection->title('MS Pages');
    $collection->save();
});

/**
 * The captured redirect sources stored in one site's localization of the global.
 *
 * @param  string  $site  Site handle whose localization to read.
 * @return array<int, string> Source URIs, in stored order.
 */
function capturedSourcesIn(string $site): array
{
    $global = GlobalSet::findByHandle('redirects');
    $variables = $global?->in($site);
    $rows = $variables instanceof Variables ? $variables->get('redirects', []) : [];

    if (! is_array($rows)) {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn (mixed $row): ?string => is_array($row) && is_string($row['source'] ?? null) ? $row['source'] : null,
        $rows
    )));
}

/**
 * A localized entry plus its origin, both addressable.
 *
 * `Entry::make()` is `mixed` to the analyser, so each one is narrowed here rather than at
 * every call site — the same reason `DeletedEntryRedirectCaptureTest` has its own helper.
 *
 * @return array{0: ConcreteEntry, 1: ConcreteEntry} The origin, then its localization.
 */
function makeLocalizedEntry(): array
{
    $origin = Entry::make();
    $localization = Entry::make();

    if (! $origin instanceof ConcreteEntry || ! $localization instanceof ConcreteEntry) {
        throw new RuntimeException('Entry::make() did not return an entry.');
    }

    $origin->collection('ms_pages');
    $origin->locale('default');
    $origin->slug('cardiology');
    $origin->data(['title' => 'Cardiology']);
    $origin->save();

    $localization->collection('ms_pages');
    $localization->locale('second');
    $localization->slug('cardiology');
    $localization->origin($origin->id());
    $localization->data(['title' => 'Cardiology (Second)']);
    $localization->save();

    return [$origin, $localization];
}

it('raises a separate EntryDeleted for each localization, carrying that localization site', function (): void {
    [$origin] = makeLocalizedEntry();

    $locales = [];
    Event::listen(EntryDeleted::class, function (EntryDeleted $event) use (&$locales): void {
        $entry = $event->entry;
        $locale = $entry instanceof ConcreteEntry ? $entry->locale() : null;
        $locales[] = is_string($locale) ? $locale : null;
    });

    // The two calls DeleteMultisiteEntry makes for its "delete localizations" behavior.
    $origin->deleteDescendants();
    $origin->delete();

    // Descendants are deleted first, so the localization's event precedes the origin's.
    expect($locales)->toBe(['second', 'default']);
});

it('captures one disabled redirect into each affected site', function (): void {
    [$origin] = makeLocalizedEntry();

    $origin->deleteDescendants();
    $origin->delete();

    expect(capturedSourcesIn('default'))->toBe(['/cardiology']);
    expect(capturedSourcesIn('second'))->toBe(['/cardiology']);
});

it('captures a route-relative URI, without the site URL prefix', function (): void {
    [$origin, $localization] = makeLocalizedEntry();

    // `second` is served from `/second/`, but `uri()` is route-relative on both sites, so
    // both rows record `/cardiology`. A reader expecting `/second/cardiology` here is
    // reading the site URL into a value that never carries it.
    $localizationUri = $localization->uri();
    $originUri = $origin->uri();

    expect(is_string($localizationUri) ? $localizationUri : null)->toBe('/cardiology');
    expect(is_string($originUri) ? $originUri : null)->toBe('/cardiology');
});

it('leaves sibling URLs untouched when localizations are detached rather than deleted', function (): void {
    [$origin] = makeLocalizedEntry();

    // DeleteMultisiteEntry's other behavior: detach, then delete only the origin. The
    // localization survives as an independent entry, so its URL never goes dark and there
    // is nothing for the capture to record in that site.
    $origin->detachLocalizations();
    $origin->delete();

    expect(capturedSourcesIn('default'))->toBe(['/cardiology']);
    expect(capturedSourcesIn('second'))->toBe([]);
});
