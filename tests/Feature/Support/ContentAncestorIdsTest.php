<?php

declare(strict_types=1);

use App\Support\ContentAncestorIds;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
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
 * Mutation: 100% on App\Support\ContentAncestorIds (110 mutants, --covered-only).
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
