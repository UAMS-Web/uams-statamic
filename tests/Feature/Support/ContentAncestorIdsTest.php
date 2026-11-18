<?php

declare(strict_types=1);

use App\Support\ContentAncestorIds;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\TermRepository;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Tests\Support\Doubles\TermAncestorTestStub;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `ContentAncestorIds` entry and term ancestor walks.
 *
 * @covers \App\Support\ContentAncestorIds
 *
 * Mutation: 116 of 116 killed on App\Support\ContentAncestorIds without
 * --covered-only (Windows 11, #1071).
 *
 * @command  php artisan test --compact tests/Feature/Support/ContentAncestorIdsTest.php
 */
it('returns ordered ancestors for a linear entry chain', function () {
    $pageP1 = Mockery::mock();
    $pageP1->shouldReceive('id')->andReturn('p1');

    $pageP2 = Mockery::mock();
    $pageP2->shouldReceive('id')->andReturn('p2');

    $child = Mockery::mock(Entry::class);
    $child->shouldReceive('id')->andReturn('child');
    $child->shouldReceive('parent')->andReturn($pageP1);

    $entryP1 = Mockery::mock(Entry::class);
    $entryP1->shouldReceive('id')->andReturn('p1');
    $entryP1->shouldReceive('parent')->andReturn($pageP2);

    $entryP2 = Mockery::mock(Entry::class);
    $entryP2->shouldReceive('id')->andReturn('p2');
    $entryP2->shouldReceive('parent')->andReturn(null);

    EntryFacade::shouldReceive('findOrFail')->once()->with('child')->andReturn($child);
    EntryFacade::shouldReceive('find')->with('p1')->once()->andReturn($entryP1);
    EntryFacade::shouldReceive('find')->with('p2')->once()->andReturn($entryP2);

    expect(ContentAncestorIds::forEntry('child'))->toBe(['p1', 'p2']);
});

it('stops the entry walk on a self-parent and logs a warning', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ($context['reason'] ?? null) === 'self_parent'
            && ($context['entry_id'] ?? null) === 'same');

    $pageSelf = Mockery::mock();
    $pageSelf->shouldReceive('id')->andReturn('same');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('same');
    $entry->shouldReceive('parent')->andReturn($pageSelf);

    EntryFacade::shouldReceive('findOrFail')->once()->with('same')->andReturn($entry);

    expect(ContentAncestorIds::forEntry('same'))->toBe([]);
});

it('stops the entry walk on a cycle and logs a warning', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ($context['reason'] ?? null) === 'cycle'
            && ($context['parent_id'] ?? null) === 'b');

    $pageB = Mockery::mock();
    $pageB->shouldReceive('id')->andReturn('b');

    $pageA = Mockery::mock();
    $pageA->shouldReceive('id')->andReturn('a');

    $entryA = Mockery::mock(Entry::class);
    $entryA->shouldReceive('id')->andReturn('a');
    $entryA->shouldReceive('parent')->andReturn($pageB);

    $entryB = Mockery::mock(Entry::class);
    $entryB->shouldReceive('id')->andReturn('b');
    $entryB->shouldReceive('parent')->andReturn($pageA);

    EntryFacade::shouldReceive('findOrFail')->once()->with('a')->andReturn($entryA);
    EntryFacade::shouldReceive('find')->with('b')->once()->andReturn($entryB);
    EntryFacade::shouldReceive('find')->with('a')->once()->andReturn($entryA);

    expect(ContentAncestorIds::forEntry('a'))->toBe(['b', 'a']);
});

it('logs the hop limit when the cap truncates the entry chain', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ($context['reason'] ?? null) === 'hop_limit'
            && ($context['max_hops'] ?? null) === 1);

    $pageP1 = Mockery::mock();
    $pageP1->shouldReceive('id')->andReturn('p1');

    $pageP2 = Mockery::mock();
    $pageP2->shouldReceive('id')->andReturn('p2');

    $child = Mockery::mock(Entry::class);
    $child->shouldReceive('id')->andReturn('child');
    $child->shouldReceive('parent')->andReturn($pageP1);

    $entryP1 = Mockery::mock(Entry::class);
    $entryP1->shouldReceive('id')->andReturn('p1');
    $entryP1->shouldReceive('parent')->andReturn($pageP2);

    EntryFacade::shouldReceive('findOrFail')->once()->with('child')->andReturn($child);
    EntryFacade::shouldReceive('find')->with('p1')->once()->andReturn($entryP1);

    expect(ContentAncestorIds::forEntry('child', 1))->toBe(['p1']);
});

it('returns ordered ancestors for a linear term chain', function () {
    $parentT2 = Mockery::mock();
    $parentT2->shouldReceive('id')->andReturn('tax::t2');

    $parentT1 = Mockery::mock();
    $parentT1->shouldReceive('id')->andReturn('tax::t1');

    $termLeaf = new TermAncestorTestStub('tax::leaf', $parentT1);
    $termT1 = new TermAncestorTestStub('tax::t1', $parentT2);
    $termT2 = new TermAncestorTestStub('tax::t2', null);

    $termRepo = Mockery::mock(TermRepository::class);
    $termRepo->shouldReceive('findOrFail')->once()->with('tax::leaf')->andReturn($termLeaf);
    $termRepo->shouldReceive('find')->with('tax::t1')->once()->andReturn($termT1);
    $termRepo->shouldReceive('find')->with('tax::t2')->once()->andReturn($termT2);
    app()->instance(TermRepository::class, $termRepo);

    expect(ContentAncestorIds::forTerm('tax::leaf'))->toBe(['tax::t1', 'tax::t2']);
});

it('stops the term walk on a self-parent and logs a warning', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ($context['reason'] ?? null) === 'self_parent');

    $parentSelf = Mockery::mock();
    $parentSelf->shouldReceive('id')->andReturn('tax::x');

    $term = new TermAncestorTestStub('tax::x', $parentSelf);

    $termRepo = Mockery::mock(TermRepository::class);
    $termRepo->shouldReceive('findOrFail')->once()->with('tax::x')->andReturn($term);
    app()->instance(TermRepository::class, $termRepo);

    expect(ContentAncestorIds::forTerm('tax::x'))->toBe([]);
});

it('stops the term walk on a cycle and logs a warning', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ($context['reason'] ?? null) === 'cycle');

    $parentB = Mockery::mock();
    $parentB->shouldReceive('id')->andReturn('tax::b');

    $parentA = Mockery::mock();
    $parentA->shouldReceive('id')->andReturn('tax::a');

    $termA = new TermAncestorTestStub('tax::a', $parentB);
    $termB = new TermAncestorTestStub('tax::b', $parentA);

    $termRepo = Mockery::mock(TermRepository::class);
    $termRepo->shouldReceive('findOrFail')->once()->with('tax::a')->andReturn($termA);
    $termRepo->shouldReceive('find')->with('tax::b')->once()->andReturn($termB);
    $termRepo->shouldReceive('find')->with('tax::a')->once()->andReturn($termA);
    app()->instance(TermRepository::class, $termRepo);

    expect(ContentAncestorIds::forTerm('tax::a'))->toBe(['tax::b', 'tax::a']);
});

it('logs the hop limit when the cap truncates the term chain', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ($context['reason'] ?? null) === 'hop_limit');

    $parentP1 = Mockery::mock();
    $parentP1->shouldReceive('id')->andReturn('tax::p1');

    $parentP2 = Mockery::mock();
    $parentP2->shouldReceive('id')->andReturn('tax::p2');

    $termLeaf = new TermAncestorTestStub('tax::leaf', $parentP1);
    $termP1 = new TermAncestorTestStub('tax::p1', $parentP2);

    $termRepo = Mockery::mock(TermRepository::class);
    $termRepo->shouldReceive('findOrFail')->once()->with('tax::leaf')->andReturn($termLeaf);
    $termRepo->shouldReceive('find')->with('tax::p1')->once()->andReturn($termP1);
    app()->instance(TermRepository::class, $termRepo);

    expect(ContentAncestorIds::forTerm('tax::leaf', 1))->toBe(['tax::p1']);
});

/**
 * A structure entry double whose `id()` and parent page id are fixed.
 */
function ancestorEntry(mixed $id, mixed $parentPageId): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    if ($parentPageId === null) {
        $entry->shouldReceive('parent')->andReturn(null);

        return $entry;
    }

    $page = Mockery::mock();
    $page->shouldReceive('id')->andReturn($parentPageId);
    $entry->shouldReceive('parent')->andReturn($page);

    return $entry;
}

/**
 * Expect exactly one warning with this message and exactly this context.
 *
 * @param  array<string, mixed>  $context
 */
function expectAncestorWarning(string $message, array $context): void
{
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(static fn (string $actualMessage, array $actualContext): bool => $actualMessage === $message
            && $actualContext === $context);
}

/**
 * A term parent double whose `id()` answers the given value.
 */
function ancestorTermParent(mixed $id): object
{
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn($id);

    return $parent;
}

/**
 * Bind a term repository double whose `findOrFail()` answers the given term.
 */
function bindAncestorTermRepository(string $reference, object $term): MockInterface
{
    $termRepo = Mockery::mock(TermRepository::class);
    $termRepo->shouldReceive('findOrFail')->once()->with($reference)->andReturn($term);
    app()->instance(TermRepository::class, $termRepo);

    return $termRepo;
}

it('stops the entry walk at 500 hops by default and logs the cap', function () {
    expectAncestorWarning('Ancestor walk stopped: hop cap reached for entry chain.', [
        'entry_id' => 'e500',
        'reason' => 'hop_limit',
        'max_hops' => 500,
    ]);

    $entries = [];
    for ($index = 0; $index <= 501; $index++) {
        $entries['e'.$index] = ancestorEntry('e'.$index, 'e'.($index + 1));
    }

    EntryFacade::shouldReceive('findOrFail')->once()->with('e0')->andReturn($entries['e0']);
    EntryFacade::shouldReceive('find')->andReturnUsing(static fn (string $id): ?Entry => $entries[$id] ?? null);

    $ancestors = ContentAncestorIds::forEntry('e0');

    expect($ancestors)->toHaveCount(500)
        ->and($ancestors[0])->toBe('e1')
        ->and($ancestors[499])->toBe('e500');
});

it('stops quietly at the entry hop cap when no parent lies past it', function (mixed $parentPageId) {
    Log::shouldReceive('warning')->never();

    EntryFacade::shouldReceive('findOrFail')->once()->with('child')->andReturn(ancestorEntry('child', 'p1'));
    EntryFacade::shouldReceive('find')->once()->with('p1')->andReturn(ancestorEntry('p1', $parentPageId));

    expect(ContentAncestorIds::forEntry('child', 1))->toBe(['p1']);
})->with([
    'a root' => [null],
    'a parent page with an empty id' => [''],
]);

it('logs the entry hop cap with the current id as a string', function () {
    expectAncestorWarning('Ancestor walk stopped: hop cap reached for entry chain.', [
        'entry_id' => '7',
        'reason' => 'hop_limit',
        'max_hops' => 1,
    ]);

    EntryFacade::shouldReceive('findOrFail')->once()->with('child')->andReturn(ancestorEntry('child', 7));
    EntryFacade::shouldReceive('find')->once()->with(7)->andReturn(ancestorEntry(7, 8));

    expect(ContentAncestorIds::forEntry('child', 1))->toBe([7]);
});

it('treats a parent page with an empty id as the root', function () {
    Log::shouldReceive('warning')->never();

    EntryFacade::shouldReceive('findOrFail')->once()->with('child')->andReturn(ancestorEntry('child', ''));
    EntryFacade::shouldReceive('find')->never();

    expect(ContentAncestorIds::forEntry('child'))->toBe([]);
});

it('sees a self-parent across an int and a string id', function (mixed $entryId, mixed $parentPageId) {
    expectAncestorWarning('Ancestor walk stopped: entry parent points to self.', [
        'entry_id' => '5',
        'reason' => 'self_parent',
    ]);

    EntryFacade::shouldReceive('findOrFail')->once()->with('start')->andReturn(ancestorEntry($entryId, $parentPageId));
    EntryFacade::shouldReceive('find')->never();

    expect(ContentAncestorIds::forEntry('start'))->toBe([]);
})->with([
    'a string entry id and an int parent id' => ['5', 5],
    'an int entry id and a string parent id' => [5, '5'],
]);

it('logs an entry cycle with both ids', function () {
    expectAncestorWarning('Ancestor walk stopped: cycle in entry parent chain.', [
        'entry_id' => 'a',
        'parent_id' => 'b',
        'reason' => 'cycle',
    ]);

    $entryA = ancestorEntry('a', 'b');
    EntryFacade::shouldReceive('findOrFail')->once()->with('a')->andReturn($entryA);
    EntryFacade::shouldReceive('find')->with('b')->once()->andReturn(ancestorEntry('b', 'a'));
    EntryFacade::shouldReceive('find')->with('a')->once()->andReturn($entryA);

    expect(ContentAncestorIds::forEntry('a'))->toBe(['b', 'a']);
});

it('refuses a starting entry that is not a structure entry', function () {
    EntryFacade::shouldReceive('findOrFail')->once()->with('odd')->andReturn(Mockery::mock(EntryContract::class));

    ContentAncestorIds::forEntry('odd');
})->throws(AssertionError::class)->skip(ini_get('zend.assertions') !== '1', 'Requires zend.assertions=1.');

it('refuses an entry id that is not a scalar', function (?int $maxHops) {
    Log::shouldReceive('warning')->never();

    EntryFacade::shouldReceive('findOrFail')->once()->with('child')->andReturn(ancestorEntry(['not', 'scalar'], 'p1'));

    ContentAncestorIds::forEntry('child', $maxHops);
})->with([
    'on a parent step' => [null],
    'at the hop cap' => [0],
])->throws(AssertionError::class)->skip(ini_get('zend.assertions') !== '1', 'Requires zend.assertions=1.');

it('stops the term walk at 500 hops by default', function () {
    Log::shouldReceive('warning')->once();

    $terms = [];
    for ($index = 0; $index <= 501; $index++) {
        $terms['tax::t'.$index] = new TermAncestorTestStub('tax::t'.$index, ancestorTermParent('tax::t'.($index + 1)));
    }

    $termRepo = bindAncestorTermRepository('tax::t0', $terms['tax::t0']);
    $termRepo->shouldReceive('find')->andReturnUsing(static fn (string $id): ?object => $terms[$id] ?? null);

    expect(ContentAncestorIds::forTerm('tax::t0'))->toHaveCount(500);
});

it('stops quietly at the term hop cap when no parent lies past it', function (?object $parent) {
    Log::shouldReceive('warning')->never();

    $termRepo = bindAncestorTermRepository('tax::leaf', new TermAncestorTestStub('tax::leaf', ancestorTermParent('tax::p1')));
    $termRepo->shouldReceive('find')->once()->with('tax::p1')->andReturn(new TermAncestorTestStub('tax::p1', $parent));

    expect(ContentAncestorIds::forTerm('tax::leaf', 1))->toBe(['tax::p1']);
})->with([
    'a root' => [null],
    'a parent with an empty id' => fn (): object => ancestorTermParent(''),
]);

it('logs the term hop cap with the term reference and the cap', function () {
    expectAncestorWarning('Ancestor walk stopped: hop cap reached for term chain.', [
        'term_reference' => 'tax::p1',
        'reason' => 'hop_limit',
        'max_hops' => 1,
    ]);

    $termRepo = bindAncestorTermRepository('tax::leaf', new TermAncestorTestStub('tax::leaf', ancestorTermParent('tax::p1')));
    $termRepo->shouldReceive('find')->once()->with('tax::p1')
        ->andReturn(new TermAncestorTestStub('tax::p1', ancestorTermParent('tax::p2')));

    expect(ContentAncestorIds::forTerm('tax::leaf', 1))->toBe(['tax::p1']);
});

it('treats a term parent with no usable id as the root', function (object $parent) {
    Log::shouldReceive('warning')->never();

    $termRepo = bindAncestorTermRepository('tax::leaf', new TermAncestorTestStub('tax::leaf', $parent));
    $termRepo->shouldReceive('find')->never();

    expect(ContentAncestorIds::forTerm('tax::leaf'))->toBe([]);
})->with([
    'an empty id' => fn (): object => ancestorTermParent(''),
    'no id() method' => fn (): object => new stdClass,
]);

it('logs a term self-parent with the term reference', function () {
    expectAncestorWarning('Ancestor walk stopped: term parent points to self.', [
        'term_reference' => 'tax::x',
        'reason' => 'self_parent',
    ]);

    bindAncestorTermRepository('tax::x', new TermAncestorTestStub('tax::x', ancestorTermParent('tax::x')));

    expect(ContentAncestorIds::forTerm('tax::x'))->toBe([]);
});

it('logs a term cycle with both references', function () {
    expectAncestorWarning('Ancestor walk stopped: cycle in term parent chain.', [
        'term_reference' => 'tax::a',
        'parent_id' => 'tax::b',
        'reason' => 'cycle',
    ]);

    $termA = new TermAncestorTestStub('tax::a', ancestorTermParent('tax::b'));
    $termRepo = bindAncestorTermRepository('tax::a', $termA);
    $termRepo->shouldReceive('find')->with('tax::b')->once()->andReturn(new TermAncestorTestStub('tax::b', ancestorTermParent('tax::a')));
    $termRepo->shouldReceive('find')->with('tax::a')->once()->andReturn($termA);

    expect(ContentAncestorIds::forTerm('tax::a'))->toBe(['tax::b', 'tax::a']);
});

it('reads an integer term parent id as a string reference', function () {
    $termRepo = bindAncestorTermRepository('tax::leaf', new TermAncestorTestStub('tax::leaf', ancestorTermParent(7)));
    $termRepo->shouldReceive('find')->once()->with('7')->andReturn(null);

    expect(ContentAncestorIds::forTerm('tax::leaf'))->toBe(['7']);
});

it('refuses a term parent id that is not a scalar', function () {
    bindAncestorTermRepository('tax::leaf', new TermAncestorTestStub('tax::leaf', ancestorTermParent(['not', 'scalar'])));

    ContentAncestorIds::forTerm('tax::leaf');
})->throws(AssertionError::class)->skip(ini_get('zend.assertions') !== '1', 'Requires zend.assertions=1.');
