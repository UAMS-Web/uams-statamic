<?php

declare(strict_types=1);

use App\Listeners\ValidateKnowledgeBaseEntry;
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

/**
 * Invoke the listener's protected getParent() method via reflection.
 */
function validateKbEntryGetParent(ValidateKnowledgeBaseEntry $listener, Entry $entry): mixed
{
    $method = (new ReflectionClass($listener))->getMethod('getParent');

    return $method->invoke($listener, $entry);
}

/**
 * Tests for ValidateKnowledgeBaseEntry::getParent() — safer null-parent handling
 * (the Statamic v5.54.0 "safer check on parent tag" fix, commit 7a77568).
 *
 * getParent() must return null for a top-level entry, for an entry whose parent
 * page is the structure root, and for a new entry without a parent in its data or
 * the request; resolve the parent entry when a non-root parent page exists; honor
 * the Control Panel's `_parent` request field when creating a child; and degrade
 * to null (with a warning) when the structure is missing.
 *
 * @covers \App\Listeners\ValidateKnowledgeBaseEntry
 *
 * Mutation: 100% on App\Listeners\ValidateKnowledgeBaseEntry (69 mutants), measured on macOS on 2026-10-08, serial, --covered-only, with every test file that names the class,
 * StructuredEntryParentResolutionTest included.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/ValidateKnowledgeBaseEntryTest.php
 */
it('returns null for a top-level entry', function () {
    $listener = new ValidateKnowledgeBaseEntry(Request::create('/'));

    // An existing entry in the repository.
    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');
    $mockEntry->shouldReceive('locale')->andReturn('default');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    $mockStructure = Mockery::mock(Structure::class);
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::knowledge_bases')
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

    expect(validateKbEntryGetParent($listener, $mockEntry))->toBeNull();
});

it('returns null when the parent page is the structure root', function () {
    $listener = new ValidateKnowledgeBaseEntry(Request::create('/'));

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');
    $mockEntry->shouldReceive('locale')->andReturn('default');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    $mockStructure = Mockery::mock(Structure::class);
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::knowledge_bases')
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

    expect(validateKbEntryGetParent($listener, $mockEntry))->toBeNull();
});

it('returns the parent entry when a non-root parent exists', function () {
    $listener = new ValidateKnowledgeBaseEntry(Request::create('/'));

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');
    $mockEntry->shouldReceive('locale')->andReturn('default');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    $mockStructure = Mockery::mock(Structure::class);
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::knowledge_bases')
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

    expect(validateKbEntryGetParent($listener, $mockEntry))->toBe($mockParentEntry);
});

it('returns null for a new entry without a parent', function () {
    $listener = new ValidateKnowledgeBaseEntry(Request::create('/'));

    // A new entry (not in the repository, no parent in data or request).
    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('new-entry-id');
    $mockEntry->shouldReceive('get')->with('parent')->andReturn(null);

    EntryFacade::shouldReceive('find')
        ->with('new-entry-id')
        ->andReturn(null);

    expect(validateKbEntryGetParent($listener, $mockEntry))->toBeNull();
});

it('resolves the parent from the request _parent field for a new entry', function () {
    // For structure collections, the Control Panel passes the parent as
    // '_parent' when creating a child entry. The entry has no 'parent' field in
    // its data, so the listener must resolve the parent from the request.
    $request = Request::create('/', 'POST', ['_parent' => 'parent-entry-uuid']);
    $listener = new ValidateKnowledgeBaseEntry($request);

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

    expect(validateKbEntryGetParent($listener, $mockEntry))->toBe($mockParentEntry);
});

it('handles a missing structure gracefully', function () {
    $listener = new ValidateKnowledgeBaseEntry(Request::create('/'));

    $mockEntry = Mockery::mock(Entry::class);
    $mockEntry->shouldReceive('id')->andReturn('existing-entry-id');

    EntryFacade::shouldReceive('find')
        ->with('existing-entry-id')
        ->andReturn($mockEntry);

    // Structure not found → return null after logging a warning.
    StructureFacade::shouldReceive('findByHandle')
        ->with('collection::knowledge_bases')
        ->andReturn(null);

    Log::shouldReceive('warning')
        ->once()
        ->with('ValidateKnowledgeBaseEntry::getParent: No structure found for collection::knowledge_bases');

    expect(validateKbEntryGetParent($listener, $mockEntry))->toBeNull();
});

// ---------------------------------------------------------------------------
// The hierarchy rules, with getParent() stubbed
// ---------------------------------------------------------------------------

/**
 * A knowledge_bases (or other-collection) entry mock with the given blueprint.
 */
function kbEntry(string $blueprint, ?string $collection = 'knowledge_bases'): Entry
{
    $entry = Mockery::mock(Entry::class);

    $col = null;
    if ($collection !== null) {
        $col = Mockery::mock(Collection::class);
        $col->shouldReceive('handle')->andReturn($collection);
    }
    $entry->shouldReceive('collection')->andReturn($col);

    $bp = Mockery::mock(Blueprint::class);
    $bp->shouldReceive('handle')->andReturn($blueprint);
    $entry->shouldReceive('blueprint')->andReturn($bp);

    $entry->shouldReceive('id')->andReturn('entry-'.$blueprint);

    return $entry;
}

/**
 * A listener whose getParent() is stubbed to return the given parent (or null).
 */
function kbListenerWithParent(?Entry $parent): ValidateKnowledgeBaseEntry
{
    $listener = Mockery::mock(ValidateKnowledgeBaseEntry::class, [Request::create('/')])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $listener->shouldReceive('getParent')->andReturn($parent);

    return $listener;
}

/**
 * Run the listener over an entry with the given parent.
 */
function kbValidate(Entry $entry, ?Entry $parent): void
{
    kbListenerWithParent($parent)->handle(new EntrySaving($entry));
}

it('skips an entry outside the knowledge_bases collection', function (?string $collection): void {
    $entry = Mockery::mock(Entry::class);
    $col = $collection === null ? null : tap(Mockery::mock(Collection::class), fn ($c) => $c->shouldReceive('handle')->andReturn($collection));
    $entry->shouldReceive('collection')->andReturn($col);
    $entry->shouldReceive('blueprint')->never();

    $listener = Mockery::mock(ValidateKnowledgeBaseEntry::class, [Request::create('/')])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $listener->shouldReceive('getParent')->never();

    $listener->handle(new EntrySaving($entry));
})->with(['no collection' => [null], 'another collection' => ['pages']]);

it('accepts a top-level Knowledge Base', function (): void {
    kbValidate(kbEntry('knowledge_base'), null);
})->throwsNoExceptions();

it('rejects a Knowledge Base with a parent, and logs it', function (): void {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    expect(fn () => kbValidate(kbEntry('knowledge_base'), kbEntry('topic')))
        ->toThrow(Exception::class, 'Knowledge Base entries must be top-level (no parent).');

    $log->shouldHaveReceived('error')->once()->with(
        'ValidateKnowledgeBaseEntry::handle: Throwing exception: Knowledge Base entries must be top-level',
        ['entry_id' => 'entry-knowledge_base']
    );
});

it('accepts a Topic under a Knowledge Base or another Topic', function (string $parentBlueprint): void {
    kbValidate(kbEntry('topic'), kbEntry($parentBlueprint));
})->with(['knowledge_base', 'topic'])->throwsNoExceptions();

it('rejects a Topic with no parent, and logs it', function (): void {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    expect(fn () => kbValidate(kbEntry('topic'), null))
        ->toThrow(Exception::class, 'Topic entries must have a Knowledge Base or Topic parent.');

    $log->shouldHaveReceived('error')->once()->with(
        'ValidateKnowledgeBaseEntry::handle: Throwing exception: Topic entries must have a parent',
        ['entry_id' => 'entry-topic']
    );
});

it('rejects a Topic under an Article, and logs the parent blueprint', function (): void {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    expect(fn () => kbValidate(kbEntry('topic'), kbEntry('article')))
        ->toThrow(Exception::class, 'The parent of Topic entries must be a Knowledge Base or Topic.');

    $log->shouldHaveReceived('error')->once()->with(
        'ValidateKnowledgeBaseEntry::handle: Throwing exception: Invalid parent for Topic entry',
        ['entry_id' => 'entry-topic', 'parent_blueprint' => 'article']
    );
});

it('accepts an Article under a Topic', function (): void {
    kbValidate(kbEntry('article'), kbEntry('topic'));
})->throwsNoExceptions();

it('rejects an Article with no parent, and logs it', function (): void {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    expect(fn () => kbValidate(kbEntry('article'), null))
        ->toThrow(Exception::class, 'Article entries must have a Topic parent.');

    $log->shouldHaveReceived('error')->once()->with(
        'ValidateKnowledgeBaseEntry::handle: Throwing exception: Article entries must have a Topic parent',
        ['entry_id' => 'entry-article']
    );
});

it('rejects an Article under anything but a Topic, and logs the parent blueprint', function (string $parentBlueprint): void {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    expect(fn () => kbValidate(kbEntry('article'), kbEntry($parentBlueprint)))
        ->toThrow(Exception::class, 'Article entries must have a Topic parent.');

    $log->shouldHaveReceived('error')->once()->with(
        'ValidateKnowledgeBaseEntry::handle: Throwing exception: Invalid parent for Article entry',
        ['entry_id' => 'entry-article', 'parent_blueprint' => $parentBlueprint]
    );
})->with(['knowledge_base', 'article']);

it('places no rule on another blueprint in the collection', function (): void {
    kbValidate(kbEntry('faq'), null);
    kbValidate(kbEntry('faq'), kbEntry('article'));
})->throwsNoExceptions();
