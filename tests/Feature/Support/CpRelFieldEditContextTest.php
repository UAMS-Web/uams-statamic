<?php

declare(strict_types=1);

use App\Support\CpRelFieldEditContext;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Taxonomies\TermRepository;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `CpRelFieldEditContext` CP URL parsing and validation.
 *
 * @covers \App\Support\CpRelFieldEditContext
 *
 * Mutation: 100% on App\Support\CpRelFieldEditContext (58 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Support/CpRelFieldEditContextTest.php
 */
it('returns empty when there is no cp segment', function () {
    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/dashboard'))
        ->toBe(['type' => null, 'id' => null]);
});

it('returns empty when the path is too short', function () {
    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/collections/pages/entries'))
        ->toBe(['type' => null, 'id' => null]);
});

it('returns empty on the create screen', function () {
    // The create segment is not an entry id, so it must never reach the repository
    EntryFacade::shouldReceive('find')->never();

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/collections/pages/entries/create/default'))
        ->toBe(['type' => null, 'id' => null]);
});

it('returns the entry when the entry exists', function () {
    $entryId = '00000000-1111-2222-3333-444444444444';

    EntryFacade::shouldReceive('find')->once()->with($entryId)->andReturn(Mockery::mock(Entry::class));

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/collections/pages/entries/'.$entryId))
        ->toBe(['type' => 'Entry', 'id' => $entryId]);
});

it('clears the context when the entry is missing', function () {
    $entryId = '00000000-1111-2222-3333-444444444444';

    EntryFacade::shouldReceive('find')->once()->with($entryId)->andReturn(null);

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/collections/pages/entries/'.$entryId))
        ->toBe(['type' => null, 'id' => null]);
});

it('returns the term when the term exists', function () {
    $termRepo = Mockery::mock(TermRepository::class);
    $termRepo->shouldReceive('find')->once()->with('tags::my-term')->andReturn(Mockery::mock());
    app()->instance(TermRepository::class, $termRepo);

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/taxonomies/tags/terms/my-term/default'))
        ->toBe(['type' => 'Term', 'id' => 'tags::my-term']);
});

it('clears the context when the term is missing', function () {
    $termRepo = Mockery::mock(TermRepository::class);
    $termRepo->shouldReceive('find')->once()->with('tags::missing')->andReturn(null);
    app()->instance(TermRepository::class, $termRepo);

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/taxonomies/tags/terms/missing/default'))
        ->toBe(['type' => null, 'id' => null]);
});

it('reads the cp segments after a path prefix', function () {
    $entryId = '00000000-1111-2222-3333-444444444444';

    EntryFacade::shouldReceive('find')->once()->with($entryId)->andReturn(Mockery::mock(Entry::class));

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/admin/cp/collections/pages/entries/'.$entryId))
        ->toBe(['type' => 'Entry', 'id' => $entryId]);
});

it('clears the context when an entry url carries an empty id', function () {
    // An empty id must never reach the repository
    EntryFacade::shouldReceive('find')->never();

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/collections/pages/entries/'))
        ->toBe(['type' => null, 'id' => null]);
});

it('clears the type too when a term url carries an empty slug', function () {
    // An empty slug must never reach the repository
    $termRepo = Mockery::mock(TermRepository::class);
    $termRepo->shouldNotReceive('find');
    app()->instance(TermRepository::class, $termRepo);

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/taxonomies/tags/terms/'))
        ->toBe(['type' => null, 'id' => null]);
});

it('clears the type too when a term url carries an empty taxonomy handle', function () {
    // An empty handle must never reach the repository
    $termRepo = Mockery::mock(TermRepository::class);
    $termRepo->shouldNotReceive('find');
    app()->instance(TermRepository::class, $termRepo);

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/taxonomies//terms/my-term'))
        ->toBe(['type' => null, 'id' => null]);
});

it('strips a query string from an entry referrer so the id resolves cleanly', function () {
    // A referrer carrying `?…` must NOT bleed into the id segment. If it did,
    // `find()` would be called with `abc-123?blueprint=default` (mismatching the
    // `with($entryId)` expectation), the context would clear, and the
    // cycle-prevention pickers would fail open.
    $entryId = '00000000-1111-2222-3333-444444444444';

    EntryFacade::shouldReceive('find')->once()->with($entryId)->andReturn(Mockery::mock(Entry::class));

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/collections/pages/entries/'.$entryId.'?blueprint=default&foo=bar'))
        ->toBe(['type' => 'Entry', 'id' => $entryId]);
});

it('strips a fragment from an entry referrer so the id resolves cleanly', function () {
    $entryId = '00000000-1111-2222-3333-444444444444';

    EntryFacade::shouldReceive('find')->once()->with($entryId)->andReturn(Mockery::mock(Entry::class));

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/collections/pages/entries/'.$entryId.'#tab-1'))
        ->toBe(['type' => 'Entry', 'id' => $entryId]);
});

it('resolves a term slug even when the referrer carries a query string', function () {
    // A term edit URL without a trailing blueprint segment puts the slug last,
    // so a query string pollutes the slug itself.
    $termRepo = Mockery::mock(TermRepository::class);
    $termRepo->shouldReceive('find')->once()->with('tags::my-term')->andReturn(Mockery::mock());
    app()->instance(TermRepository::class, $termRepo);

    expect(CpRelFieldEditContext::fromAbsoluteUrl('https://example.test/cp/taxonomies/tags/terms/my-term?foo=bar'))
        ->toBe(['type' => 'Term', 'id' => 'tags::my-term']);
});
