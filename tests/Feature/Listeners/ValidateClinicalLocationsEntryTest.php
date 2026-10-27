<?php

declare(strict_types=1);

use App\Listeners\ValidateClinicalLocationsEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Structure as StructureFacade;
use Statamic\Fields\Blueprint;
use Statamic\Structures\Page;
use Statamic\Structures\Structure;
use Statamic\Structures\Tree;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/*
|--------------------------------------------------------------------------
| ValidateClinicalLocationsEntry (UAMS-Web/uams-statamic-archive#1068, D7)
|--------------------------------------------------------------------------
|
| Two concerns are covered:
|   1. The parent-blueprint rules in handle() — a `location` is top-level or the
|      child of another `location`; a content page must live under a location or
|      another content page. (The D7 location depth-2 cap is enforced by the tree
|      listener, which has the full depth context.)
|   2. getParent() resolution (cloned from the knowledge_bases precedent).
|
| @covers \App\Listeners\ValidateClinicalLocationsEntry
|
| @command  php artisan test --compact tests/Feature/Listeners/ValidateClinicalLocationsEntryTest.php
*/

/** Invoke the listener's protected getParent() method via reflection. */
function validateLocationsEntryGetParent(ValidateClinicalLocationsEntry $listener, Entry $entry): mixed
{
    $method = (new ReflectionClass($listener))->getMethod('getParent');

    return $method->invoke($listener, $entry);
}

/** A clinical_locations (or other-collection) entry mock with the given blueprint. */
function locationEntry(string $blueprint, string $collection = 'clinical_locations'): Entry
{
    $entry = Mockery::mock(Entry::class);

    $col = Mockery::mock(Collection::class);
    $col->shouldReceive('handle')->andReturn($collection);
    $entry->shouldReceive('collection')->andReturn($col);

    $bp = Mockery::mock(Blueprint::class);
    $bp->shouldReceive('handle')->andReturn($blueprint);
    $entry->shouldReceive('blueprint')->andReturn($bp);

    $entry->shouldReceive('id')->andReturn('entry-'.$blueprint);

    return $entry;
}

/**
 * A listener whose getParent() is stubbed to return the given parent (or null).
 *
 * handle() resolves the entry's immediate parent with the first getParent() call;
 * the `location` depth cap then calls getParent($parent) to look up the
 * grandparent. This fixture returns $parent first and null thereafter, so the
 * parent reads as a *root* location — keeping these entries at the valid depth ≤ 2.
 * (The depth-3 case supplies its own explicit ancestor chain.)
 */
function locationsListenerWithParent(?Entry $parent): ValidateClinicalLocationsEntry
{
    $listener = Mockery::mock(ValidateClinicalLocationsEntry::class, [Request::create('/')])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $listener->shouldReceive('getParent')->andReturn($parent, null);

    return $listener;
}

/** A listener whose getParent() resolves each entry in an explicit ancestor chain. */
function locationsListenerWithChain(Entry $entry, ?Entry ...$ancestors): ValidateClinicalLocationsEntry
{
    $listener = Mockery::mock(ValidateClinicalLocationsEntry::class, [Request::create('/')])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    // getParent($entry) → $ancestors[0]; getParent($ancestors[0]) → $ancestors[1]; …
    $chain = [$entry, ...$ancestors];
    for ($i = 0, $n = \count($chain) - 1; $i < $n; $i++) {
        if (! $chain[$i] instanceof Entry) {
            continue;
        }
        $listener->shouldReceive('getParent')->with($chain[$i])->andReturn($chain[$i + 1]);
    }

    return $listener;
}

// ---------------------------------------------------------------------------
// Parent-blueprint rules
// ---------------------------------------------------------------------------

it('skips validation for entries in other collections', function () {
    $entry = locationEntry('location', 'pages');
    $listener = new ValidateClinicalLocationsEntry(Request::create('/'));

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('allows a location at the subsite root (no parent)', function () {
    $entry = locationEntry('location');
    $listener = locationsListenerWithParent(null);

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('allows a location nested under another location', function () {
    $entry = locationEntry('location');
    $listener = locationsListenerWithParent(locationEntry('location'));

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('allows a location one level deep (root parent → depth 2)', function () {
    // New location → parent location (root, no location grandparent) = depth 2, valid.
    $entry = locationEntry('location');
    $parent = locationEntry('location');
    $listener = locationsListenerWithChain($entry, $parent, null);

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('rejects creating a location that would nest three levels deep (pre-persist depth cap)', function () {
    // New location → depth-2 parent location → depth-1 root: the child lands at
    // depth 3, which the tree listener rejects post-persist. The entry listener
    // must reject it *before* the entry is written, so no orphan is left behind.
    $entry = locationEntry('location');
    $parent = locationEntry('location');       // depth-2 parent
    $grandparent = locationEntry('location');  // depth-1 root
    $listener = locationsListenerWithChain($entry, $parent, $grandparent, null);

    expect(fn () => $listener->handle(new EntrySaving($entry)))
        ->toThrow(Exception::class, 'at most one level deep');
});

it('bounds the ancestor walk on a cyclic location tree (guard terminates and rejects)', function () {
    // A malformed/cyclic tree where every location resolves to another location.
    // The MAX_ANCESTOR_WALK cycle guard must bound the walk so validation
    // terminates (never hangs) and still rejects the over-deep nesting.
    $entry = locationEntry('location');
    $everyLookupIsALocation = locationEntry('location');

    $listener = Mockery::mock(ValidateClinicalLocationsEntry::class, [Request::create('/')])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $listener->shouldReceive('getParent')->andReturn($everyLookupIsALocation);

    expect(fn () => $listener->handle(new EntrySaving($entry)))
        ->toThrow(Exception::class, 'at most one level deep');
});

it('rejects a location nested under a content page', function () {
    $entry = locationEntry('location');
    $listener = locationsListenerWithParent(locationEntry('landing_page'));

    expect(fn () => $listener->handle(new EntrySaving($entry)))
        ->toThrow(Exception::class, 'child of another location');
});

it('rejects a content page at the subsite root (no parent)', function () {
    $entry = locationEntry('basic_page');
    $listener = locationsListenerWithParent(null);

    expect(fn () => $listener->handle(new EntrySaving($entry)))
        ->toThrow(Exception::class, 'cannot be a top-level entry');
});

it('allows a content page nested under a location', function () {
    $entry = locationEntry('basic_page');
    $listener = locationsListenerWithParent(locationEntry('location'));

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('allows a content page nested under another content page', function () {
    $entry = locationEntry('landing_page');
    $listener = locationsListenerWithParent(locationEntry('basic_page'));

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('rejects a content page under a parent that is neither location nor a content page', function () {
    $entry = locationEntry('basic_page');
    $listener = locationsListenerWithParent(locationEntry('article', 'knowledge_bases'));

    expect(fn () => $listener->handle(new EntrySaving($entry)))
        ->toThrow(Exception::class, 'must be the child of a location');
});

// ---------------------------------------------------------------------------
// getParent() resolution (cloned from the knowledge_bases precedent)
// ---------------------------------------------------------------------------

it('returns null for a top-level entry', function () {
    $listener = new ValidateClinicalLocationsEntry(Request::create('/'));

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');
    $mockEntry->shouldReceive('locale')->andReturn('default');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    $mockStructure = Mockery::mock(Structure::class);
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::clinical_locations')
        ->andReturn($mockStructure);

    $mockTree = Mockery::mock(Tree::class);
    $mockStructure->shouldReceive('in')
        ->with('default')
        ->andReturn($mockTree);

    $mockPage = Mockery::mock(Page::class);
    $mockTree->shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockPage);

    // The key behavior: parent() safely returns null for a top-level entry.
    $mockPage->shouldReceive('parent')->andReturn(null);

    expect(validateLocationsEntryGetParent($listener, $mockEntry))->toBeNull();
});

it('returns null when the parent page is the structure root', function () {
    $listener = new ValidateClinicalLocationsEntry(Request::create('/'));

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');
    $mockEntry->shouldReceive('locale')->andReturn('default');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    $mockStructure = Mockery::mock(Structure::class);
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::clinical_locations')
        ->andReturn($mockStructure);

    $mockTree = Mockery::mock(Tree::class);
    $mockStructure->shouldReceive('in')
        ->with('default')
        ->andReturn($mockTree);

    $mockPage = Mockery::mock(Page::class);
    $mockTree->shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockPage);

    // parent() returns a root page → treated as no parent.
    $mockParentPage = Mockery::mock(Page::class);
    $mockParentPage->shouldReceive('isRoot')->andReturn(true);
    $mockPage->shouldReceive('parent')->andReturn($mockParentPage);

    expect(validateLocationsEntryGetParent($listener, $mockEntry))->toBeNull();
});

it('returns the parent entry when a non-root parent exists', function () {
    $listener = new ValidateClinicalLocationsEntry(Request::create('/'));

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');
    $mockEntry->shouldReceive('locale')->andReturn('default');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    $mockStructure = Mockery::mock(Structure::class);
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::clinical_locations')
        ->andReturn($mockStructure);

    $mockTree = Mockery::mock(Tree::class);
    $mockStructure->shouldReceive('in')
        ->with('default')
        ->andReturn($mockTree);

    $mockPage = Mockery::mock(Page::class);
    $mockTree->shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockPage);

    // A non-root parent page resolving to a parent entry.
    $mockParentPage = Mockery::mock(Page::class);
    $mockParentPage->shouldReceive('isRoot')->andReturn(false);
    $mockParentPage->shouldReceive('id')->andReturn('parent-entry-id');
    $mockPage->shouldReceive('parent')->andReturn($mockParentPage);

    $mockParentEntry = Mockery::mock(Entry::class);
    EntryFacade::shouldReceive('find')
        ->with('parent-entry-id')
        ->andReturn($mockParentEntry);

    expect(validateLocationsEntryGetParent($listener, $mockEntry))->toBe($mockParentEntry);
});

it('returns null for a new entry without a parent', function () {
    $listener = new ValidateClinicalLocationsEntry(Request::create('/'));

    // A new entry (not in the repository, no parent in data or request).
    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('new-entry-id');
    $mockEntry->shouldReceive('get')->with('parent')->andReturn(null);

    EntryFacade::shouldReceive('find')
        ->with('new-entry-id')
        ->andReturn(null);

    expect(validateLocationsEntryGetParent($listener, $mockEntry))->toBeNull();
});

it('resolves the parent from the request _parent field for a new entry', function () {
    // For structure collections, the Control Panel passes the parent as '_parent'
    // when creating a child entry. The entry has no 'parent' field in its data, so
    // the listener must resolve the parent from the request.
    $request = Request::create('/', 'POST', ['_parent' => 'parent-entry-uuid']);
    $listener = new ValidateClinicalLocationsEntry($request);

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn(null);
    $mockEntry->shouldReceive('get')->with('parent')->andReturn(null);

    EntryFacade::shouldReceive('find')
        ->with(null)
        ->andReturn(null);

    $mockParentEntry = Mockery::mock(Entry::class);
    EntryFacade::shouldReceive('find')
        ->with('parent-entry-uuid')
        ->andReturn($mockParentEntry);

    expect(validateLocationsEntryGetParent($listener, $mockEntry))->toBe($mockParentEntry);
});

it('handles a missing structure gracefully', function () {
    $listener = new ValidateClinicalLocationsEntry(Request::create('/'));

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    // Structure not found → return null after logging a warning.
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::clinical_locations')
        ->andReturn(null);

    Log::shouldReceive('warning')
        ->once()
        ->with('ValidateClinicalLocationsEntry::getParent: No structure found for collection::clinical_locations');

    expect(validateLocationsEntryGetParent($listener, $mockEntry))->toBeNull();
});

it('stops counting depth at the first ancestor that is not a location', function () {
    // New location → parent location → a content page → another location above it. Only
    // the unbroken run of locations counts, so the child lands at depth 2 and is allowed;
    // counting past the page would put it at depth 4.
    $entry = locationEntry('location');
    $parent = locationEntry('location');
    $page = locationEntry('content_page');
    $aboveThePage = locationEntry('location');
    $listener = locationsListenerWithChain($entry, $parent, $page, $aboveThePage, null);

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('logs each rejection with the entry and, where there is one, the parent blueprint', function (string $blueprint, string $case, string $logged, array $context): void {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    $entry = locationEntry($blueprint);
    $listener = match ($case) {
        'under a content page' => locationsListenerWithParent(locationEntry('basic_page')),
        'too deep' => locationsListenerWithChain($entry, locationEntry('location'), locationEntry('location'), null),
        'at the root' => locationsListenerWithParent(null),
        'under another kind' => locationsListenerWithParent(locationEntry('faq')),
        default => throw new LogicException("Unknown case [{$case}]."),
    };

    expect(fn () => $listener->handle(new EntrySaving($entry)))->toThrow(Exception::class);

    $log->shouldHaveReceived('error')->once()->with('ValidateClinicalLocationsEntry::handle: Throwing exception: '.$logged, $context);
})->with([
    'a location under a content page' => ['location', 'under a content page', 'Invalid parent for location entry', ['entry_id' => 'entry-location', 'parent_blueprint' => 'basic_page']],
    'a location nested too deep' => ['location', 'too deep', 'location nested beyond the max depth', ['entry_id' => 'entry-location']],
    'a content page at the root' => ['landing_page', 'at the root', 'Content page must have a parent', ['entry_id' => 'entry-landing_page']],
    'a content page under another kind of entry' => ['basic_page', 'under another kind', 'Invalid parent for content page', ['entry_id' => 'entry-basic_page', 'parent_blueprint' => 'faq']],
]);
