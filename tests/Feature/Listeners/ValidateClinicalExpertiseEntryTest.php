<?php

declare(strict_types=1);

use App\Listeners\SyncEntryPublishedStatus;
use App\Listeners\ValidateClinicalExpertiseEntry;
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
| ValidateClinicalExpertiseEntry (UAMS-Web/uams-statamic-archive#1056, D4)
|--------------------------------------------------------------------------
|
| Three concerns are covered:
|   1. The parent-blueprint rules in handle() — the AoE hierarchy (unbounded
|      depth; no absolute-depth rules).
|   2. The controlled certifying-org anchor rule (UAMS-Web/uams-statamic-archive#1066) — a published
|      clinical_expertise entry must set schema_medical_specialty or
|      clinical_specializations; drafts, content pages, and WordPress imports
|      are exempt.
|   3. getParent() resolution (cloned from the knowledge_bases precedent) —
|      new-vs-existing entries, request `_parent`, structure-tree lookup, and
|      graceful degradation when the structure/tree/parent is missing.
|
| @covers \App\Listeners\ValidateClinicalExpertiseEntry
|
| @command  php artisan test --compact tests/Feature/Listeners/ValidateClinicalExpertiseEntryTest.php
*/

/** Invoke the listener's protected getParent() method via reflection. */
function validateExpertiseEntryGetParent(ValidateClinicalExpertiseEntry $listener, Entry $entry): mixed
{
    $method = (new ReflectionClass($listener))->getMethod('getParent');

    return $method->invoke($listener, $entry);
}

/**
 * A clinical_expertise (or other-collection) entry mock with the given blueprint.
 *
 * Defaults to a *draft* with no field data, so the parent-blueprint tests below
 * exercise only the hierarchy rule (the UAMS-Web/uams-statamic-archive#1066 anchor rule applies to *published*
 * clinical_expertise entries). Pass `published: true` and `data:` to drive the
 * anchor rule.
 *
 * @param  array<string, mixed>  $data  Field values returned by get() (e.g. the controlled-anchor fields)
 */
function expertiseEntry(string $blueprint, string $collection = 'clinical_expertise', bool $published = false, array $data = []): Entry
{
    $entry = Mockery::mock(Entry::class);

    $col = Mockery::mock(Collection::class);
    $col->shouldReceive('handle')->andReturn($collection);
    $entry->shouldReceive('collection')->andReturn($col);

    $bp = Mockery::mock(Blueprint::class);
    $bp->shouldReceive('handle')->andReturn($blueprint);
    $entry->shouldReceive('blueprint')->andReturn($bp);

    $entry->shouldReceive('id')->andReturn('entry-'.$blueprint);
    $entry->shouldReceive('published')->andReturn($published);
    $entry->shouldReceive('get')->andReturnUsing(fn (string $key): mixed => $data[$key] ?? null);
    $entry->shouldReceive('value')->andReturnUsing(fn (string $key): mixed => $data[$key] ?? null);

    return $entry;
}

/** A listener whose getParent() is stubbed to return the given parent (or null). */
function expertiseListenerWithParent(?Entry $parent): ValidateClinicalExpertiseEntry
{
    $listener = Mockery::mock(ValidateClinicalExpertiseEntry::class, [Request::create('/')])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $listener->shouldReceive('getParent')->andReturn($parent);

    return $listener;
}

// ---------------------------------------------------------------------------
// Parent-blueprint rules
// ---------------------------------------------------------------------------

it('skips validation for entries in other collections', function () {
    $entry = expertiseEntry('clinical_expertise', 'pages');
    $listener = new ValidateClinicalExpertiseEntry(Request::create('/'));

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('allows an Area of Expertise at the subsite root (no parent)', function () {
    $entry = expertiseEntry('clinical_expertise');
    $listener = expertiseListenerWithParent(null);

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('allows an Area of Expertise nested under another Area of Expertise', function () {
    $entry = expertiseEntry('clinical_expertise');
    $listener = expertiseListenerWithParent(expertiseEntry('clinical_expertise'));

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('rejects an Area of Expertise nested under a content page', function () {
    $entry = expertiseEntry('clinical_expertise');
    $listener = expertiseListenerWithParent(expertiseEntry('landing_page'));

    expect(fn () => $listener->handle(new EntrySaving($entry)))
        ->toThrow(Exception::class, 'child of another Area of Expertise');
});

it('rejects a content page at the subsite root (no parent)', function () {
    $entry = expertiseEntry('basic_page');
    $listener = expertiseListenerWithParent(null);

    expect(fn () => $listener->handle(new EntrySaving($entry)))
        ->toThrow(Exception::class, 'cannot be a top-level entry');
});

it('allows a content page nested under an Area of Expertise', function () {
    $entry = expertiseEntry('basic_page');
    $listener = expertiseListenerWithParent(expertiseEntry('clinical_expertise'));

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('allows a content page nested under another content page', function () {
    $entry = expertiseEntry('landing_page');
    $listener = expertiseListenerWithParent(expertiseEntry('basic_page'));

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('rejects a content page under a parent that is neither expertise nor a content page', function () {
    $entry = expertiseEntry('basic_page');
    $listener = expertiseListenerWithParent(expertiseEntry('article', 'knowledge_bases'));

    expect(fn () => $listener->handle(new EntrySaving($entry)))
        ->toThrow(Exception::class, 'must be the child of an Area of Expertise');
});

// ---------------------------------------------------------------------------
// Controlled certifying-org anchor (UAMS-Web/uams-statamic-archive#1066)
// ---------------------------------------------------------------------------

it('rejects a published Area of Expertise with no controlled anchor', function () {
    $entry = expertiseEntry('clinical_expertise', published: true);
    $listener = expertiseListenerWithParent(null);

    expect(fn () => $listener->handle(new EntrySaving($entry)))
        ->toThrow(Exception::class, 'anchored to at least one controlled term');
});

it('accepts a published Area of Expertise anchored by a Schema.org Medical Specialty', function () {
    // schema_medical_specialty (schema_type, multiple-single mode) stores a list of
    // member names; the None opt-out is the non-empty ['__none__'], likewise set.
    $entry = expertiseEntry('clinical_expertise', published: true, data: [
        'schema_medical_specialty' => ['Cardiovascular'],
    ]);
    $listener = expertiseListenerWithParent(null);

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('accepts a published Area of Expertise anchored only by a Health Care Provider Taxonomy classification', function () {
    // The escape hatch when no clean Schema.org MedicalSpecialty member applies.
    $entry = expertiseEntry('clinical_expertise', published: true, data: [
        'clinical_specializations' => ['207RC0000X'],
    ]);
    $listener = expertiseListenerWithParent(null);

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('treats an empty anchor array as no anchor', function () {
    // Both anchor fields store [] when cleared; an empty list is not a set anchor.
    $entry = expertiseEntry('clinical_expertise', published: true, data: [
        'schema_medical_specialty' => [],
        'clinical_specializations' => [],
    ]);
    $listener = expertiseListenerWithParent(null);

    expect(fn () => $listener->handle(new EntrySaving($entry)))
        ->toThrow(Exception::class, 'anchored to at least one controlled term');
});

it('exempts a draft Area of Expertise from the anchor rule', function () {
    $entry = expertiseEntry('clinical_expertise', published: false);
    $listener = expertiseListenerWithParent(null);

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('does not apply the anchor rule to content pages', function () {
    // A published content page with a valid parent and no anchor is fine.
    $entry = expertiseEntry('basic_page', published: true);
    $listener = expertiseListenerWithParent(expertiseEntry('clinical_expertise'));

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

/**
 * An anchorless Area of Expertise whose editor has just set a status, with a
 * `published` flag `SyncEntryPublishedStatus` has not reconciled yet.
 *
 * `published()` reads and writes one value, so the sync listener can change it
 * and a listener running after it sees the change.
 */
function expertiseEntryWithChangedStatus(string $status, bool $stalePublished): Entry
{
    $state = ['published' => $stalePublished, 'status_and_visibility' => $status];

    $entry = Mockery::mock(Entry::class);

    $col = Mockery::mock(Collection::class);
    $col->shouldReceive('handle')->andReturn('clinical_expertise');
    $entry->shouldReceive('collection')->andReturn($col);

    $bp = Mockery::mock(Blueprint::class);
    $bp->shouldReceive('handle')->andReturn('clinical_expertise');
    $entry->shouldReceive('blueprint')->andReturn($bp);

    $entry->shouldReceive('id')->andReturn('entry-changed-status');
    $entry->shouldReceive('published')->andReturnUsing(function (?bool $published = null) use (&$state, $entry): mixed {
        if ($published === null) {
            return $state['published'];
        }

        $state['published'] = $published;

        return $entry;
    });
    $entry->shouldReceive('get')->andReturnUsing(fn (string $key): mixed => $state[$key] ?? null);
    $entry->shouldReceive('value')->andReturnUsing(fn (string $key): mixed => $state[$key] ?? null);
    $entry->shouldReceive('getOriginal')->andReturnNull();
    $entry->shouldReceive('isDirty')->andReturnUsing(fn (string $key): bool => $key === 'status_and_visibility');
    $entry->shouldReceive('set')->andReturnUsing(function (string $key, mixed $value) use (&$state, $entry): Entry {
        $state[$key] = $value;

        return $entry;
    });

    return $entry;
}

it('decides the anchor rule from the status whichever of it and the status sync runs first (#3240)', function (string $status, bool $stalePublished, bool $refused, bool $syncFirst): void {
    $entry = expertiseEntryWithChangedStatus($status, $stalePublished);
    $event = new EntrySaving($entry);

    // Run the two EntrySaving listeners in this case's order
    $listeners = [
        fn () => expertiseListenerWithParent(null)->handle($event),
        fn () => (new SyncEntryPublishedStatus)->handle($event),
    ];
    $run = function () use ($listeners, $syncFirst): void {
        foreach ($syncFirst ? array_reverse($listeners) : $listeners as $listener) {
            $listener();
        }
    };

    if ($refused) {
        expect($run)->toThrow(Exception::class, 'anchored to at least one controlled term');
    } else {
        expect(throwableFrom($run))->toBeNull();
    }
})->with([
    'published status over an unpublished flag' => ['published', false, true],
    'draft status over a published flag' => ['draft', true, false],
])->with([
    'validator first' => [false],
    'status sync first' => [true],
]);

it('bypasses the anchor rule during a WordPress console import', function () {
    // A published, anchorless AoE that would otherwise be rejected must save
    // during a console import, so legacy specialty pages are not blocked.
    $entry = expertiseEntry('clinical_expertise', published: true);

    $listener = Mockery::mock(ValidateClinicalExpertiseEntry::class, [Request::create('/')])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $listener->shouldReceive('getParent')->andReturn(null);
    $listener->shouldReceive('isRunningWordPressImport')->andReturn(true);

    $listener->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('detects a WordPress console import from argv (the real bypass predicate)', function () {
    // Exercises the real isRunningWordPressImport() — the bypass test above stubs it,
    // so the argv scan itself would otherwise be untested.
    $listener = new ValidateClinicalExpertiseEntry(Request::create('/'));
    $method = (new ReflectionClass($listener))->getMethod('isRunningWordPressImport');

    $original = $_SERVER['argv'] ?? null;

    try {
        $_SERVER['argv'] = ['artisan', 'import:wordpress', '--fresh'];
        expect($method->invoke($listener))->toBeTrue();

        $_SERVER['argv'] = ['artisan', 'test'];
        expect($method->invoke($listener))->toBeFalse();
    } finally {
        if ($original === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $original;
        }
    }
});

// ---------------------------------------------------------------------------
// getParent() resolution (cloned from the knowledge_bases precedent)
// ---------------------------------------------------------------------------

it('returns null for a top-level entry', function () {
    $listener = new ValidateClinicalExpertiseEntry(Request::create('/'));

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');
    $mockEntry->shouldReceive('locale')->andReturn('default');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    $mockStructure = Mockery::mock(Structure::class);
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::clinical_expertise')
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

    expect(validateExpertiseEntryGetParent($listener, $mockEntry))->toBeNull();
});

it('returns null when the parent page is the structure root', function () {
    $listener = new ValidateClinicalExpertiseEntry(Request::create('/'));

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');
    $mockEntry->shouldReceive('locale')->andReturn('default');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    $mockStructure = Mockery::mock(Structure::class);
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::clinical_expertise')
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

    expect(validateExpertiseEntryGetParent($listener, $mockEntry))->toBeNull();
});

it('returns the parent entry when a non-root parent exists', function () {
    $listener = new ValidateClinicalExpertiseEntry(Request::create('/'));

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');
    $mockEntry->shouldReceive('locale')->andReturn('default');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    $mockStructure = Mockery::mock(Structure::class);
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::clinical_expertise')
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

    expect(validateExpertiseEntryGetParent($listener, $mockEntry))->toBe($mockParentEntry);
});

it('returns null for a new entry without a parent', function () {
    $listener = new ValidateClinicalExpertiseEntry(Request::create('/'));

    // A new entry (not in the repository, no parent in data or request).
    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('new-entry-id');
    $mockEntry->shouldReceive('get')->with('parent')->andReturn(null);

    EntryFacade::shouldReceive('find')
        ->with('new-entry-id')
        ->andReturn(null);

    expect(validateExpertiseEntryGetParent($listener, $mockEntry))->toBeNull();
});

it('resolves the parent from the request _parent field for a new entry', function () {
    // For structure collections, the Control Panel passes the parent as
    // '_parent' when creating a child entry. The entry has no 'parent' field in
    // its data, so the listener must resolve the parent from the request.
    $request = Request::create('/', 'POST', ['_parent' => 'parent-entry-uuid']);
    $listener = new ValidateClinicalExpertiseEntry($request);

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

    expect(validateExpertiseEntryGetParent($listener, $mockEntry))->toBe($mockParentEntry);
});

it('handles a missing structure gracefully', function () {
    $listener = new ValidateClinicalExpertiseEntry(Request::create('/'));

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    // Structure not found → return null after logging a warning.
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::clinical_expertise')
        ->andReturn(null);

    Log::shouldReceive('warning')
        ->once()
        ->with('ValidateClinicalExpertiseEntry::getParent: No structure found for collection::clinical_expertise');

    expect(validateExpertiseEntryGetParent($listener, $mockEntry))->toBeNull();
});

it('never reads a web request as the WordPress import, whatever argv says', function () {
    $listener = new ValidateClinicalExpertiseEntry(Request::create('/'));
    $method = (new ReflectionClass($listener))->getMethod('isRunningWordPressImport');

    $original = $_SERVER['argv'] ?? null;
    $running = new ReflectionProperty(app(), 'isRunningInConsole');
    $running->setValue(app(), false);

    try {
        $_SERVER['argv'] = ['artisan', 'import:wordpress'];
        expect($method->invoke($listener))->toBeFalse();
    } finally {
        $running->setValue(app(), null);

        if ($original === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $original;
        }
    }
});

// ---------------------------------------------------------------------------
// What each rejection logs, and the collection gate
// ---------------------------------------------------------------------------

it('logs each rejection with the entry and, where there is one, the parent blueprint', function (string $blueprint, ?string $parentBlueprint, bool $published, string $logged, array $context): void {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    $entry = expertiseEntry($blueprint, published: $published);
    $listener = expertiseListenerWithParent($parentBlueprint === null ? null : expertiseEntry($parentBlueprint));

    expect(fn () => $listener->handle(new EntrySaving($entry)))->toThrow(Exception::class);

    $log->shouldHaveReceived('error')->once()->with('ValidateClinicalExpertiseEntry::handle: Throwing exception: '.$logged, $context);
})->with([
    'an Area of Expertise under a content page' => ['clinical_expertise', 'basic_page', false, 'Invalid parent for Area of Expertise entry', ['entry_id' => 'entry-clinical_expertise', 'parent_blueprint' => 'basic_page']],
    'a published Area of Expertise with no anchor' => ['clinical_expertise', null, true, 'Area of Expertise has no controlled anchor', ['entry_id' => 'entry-clinical_expertise']],
    'a content page at the root' => ['landing_page', null, false, 'Content page must have a parent', ['entry_id' => 'entry-landing_page']],
    'a content page under another kind of entry' => ['basic_page', 'faq', false, 'Invalid parent for content page', ['entry_id' => 'entry-basic_page', 'parent_blueprint' => 'faq']],
]);

it('leaves an entry in another collection alone, even one the rules would reject', function (): void {
    // A root content page is rejected in clinical_expertise; in `pages` it is none of this
    // listener's business.
    $entry = expertiseEntry('basic_page', 'pages');

    expertiseListenerWithParent(null)->handle(new EntrySaving($entry));
})->throwsNoExceptions();

it('leaves an entry with no collection alone', function (): void {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn(null);
    $entry->shouldReceive('blueprint')->never();

    (new ValidateClinicalExpertiseEntry(Request::create('/')))->handle(new EntrySaving($entry));
});

it('does not read a process with no argv as the WordPress import', function (): void {
    $listener = new ValidateClinicalExpertiseEntry(Request::create('/'));
    $method = (new ReflectionClass($listener))->getMethod('isRunningWordPressImport');

    $original = $_SERVER['argv'] ?? null;

    try {
        unset($_SERVER['argv']);
        expect($method->invoke($listener))->toBeFalse();

        $_SERVER['argv'] = 'artisan import:wordpress';
        expect($method->invoke($listener))->toBeFalse();
    } finally {
        if ($original !== null) {
            $_SERVER['argv'] = $original;
        }
    }
});
