<?php

declare(strict_types=1);

use App\Scopes\ExcludeDescendants;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Taxonomies\TermRepository;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Statamic\Structures\Page;
use Tests\Support\Doubles\ExcludeDescendantsTermStub;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ExcludeDescendants::class);

/**
 * Mutation: tracked under App\Scopes\ExcludeDescendants (--covered-only).
 * ``CpRelFieldEditContext::resolve()`` returns a type only with a validated id
 * (UAMS-Web/uams-statamic-archive#3221), so the scope dispatches on the type alone.
 *
 * @command  php artisan test --compact tests/Feature/Scopes/ExcludeDescendantsScopeTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});
it('skips when not a statamic builder', function () {
    // A valid edit context with real descendants is set up so the Builder guard
    // is the ONLY thing preventing a whereNotIn — bypassing it would scope the
    // non-Builder query.
    bindRequestWithReferrer('http://acme.test/cp/collections/clinical_expertise/entries/abc123/edit');
    stubEntryWithDescendant('abc123', 'child');

    $scope = new ExcludeDescendants;

    $nonBuilder = Mockery::mock();
    $nonBuilder->shouldReceive('whereNotIn')->never();

    $scope->apply(
        $nonBuilder,
        []
    );
});

it('skips when values are not an array', function () {
    // Valid context + descendants again, so the is_array guard is the sole gate.
    bindRequestWithReferrer('http://acme.test/cp/collections/clinical_expertise/entries/abc123/edit');
    stubEntryWithDescendant('abc123', 'child');

    $scope = new ExcludeDescendants;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')->never();

    /** @var mixed $nonArrayValues */
    $nonArrayValues = null;

    $scope->apply(
        $query,
        $nonArrayValues
    );
});

it('skips when there is no cp context from the referrer', function () {
    bindRequestWithReferrer('http://acme.test/not-cp');

    $scope = new ExcludeDescendants;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')->never();

    $scope->apply(
        $query,
        []
    );
});

it('excludes the structure descendants of the entry being edited', function () {
    bindRequestWithReferrer('http://acme.test/cp/collections/clinical_expertise/entries/abc123/edit');

    $childPage = Mockery::mock();
    $childPage->shouldReceive('id')->andReturn('child');

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('flattenedPages')->andReturn(collect([$childPage]));

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('page')->andReturn($page);

    EntryFacade::shouldReceive('find')->with('abc123')->andReturn($entry);

    $scope = new ExcludeDescendants;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')->once()->with('id', ['child']);

    $scope->apply(
        $query,
        []
    );
});

it('does not scope when the entry being edited has no descendants', function () {
    bindRequestWithReferrer('http://acme.test/cp/collections/clinical_expertise/entries/leaf001/edit');

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('flattenedPages')->andReturn(collect([]));

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('page')->andReturn($page);

    EntryFacade::shouldReceive('find')->with('leaf001')->andReturn($entry);

    $scope = new ExcludeDescendants;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')->never();

    $scope->apply(
        $query,
        []
    );
});

it('excludes the descendant terms of the term being edited', function () {
    bindRequestWithReferrer('http://acme.test/cp/taxonomies/patient_type/terms/inpatient/edit');

    $start = new ExcludeDescendantsTermStub('patient_type::inpatient', null);
    // Raw stored parent is the bare slug (single-taxonomy storage), qualified by the handle.
    $child = new ExcludeDescendantsTermStub('patient_type::icu', 'inpatient');

    $termRepo = Mockery::mock(TermRepository::class);
    $termRepo->shouldReceive('find')->with('patient_type::inpatient')->andReturn($start);
    $termRepo->shouldReceive('whereTaxonomy')->with('patient_type')->andReturn(collect([$start, $child]));
    app()->instance(TermRepository::class, $termRepo);

    $scope = new ExcludeDescendants;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')->once()->with('id', ['patient_type::icu']);

    $scope->apply(
        $query,
        []
    );
});

/**
 * Bind a request carrying the given referrer so `url()->previous()` resolves it.
 */
function bindRequestWithReferrer(string $referrer): void
{
    app()->instance(
        'request',
        Request::create(
            'http://acme.test/',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_REFERER' => $referrer,
            ]
        )
    );
}

/**
 * Stub EntryFacade::find so the given entry resolves to a structure with a single
 * descendant page, for tests that need a valid Entry context downstream.
 */
function stubEntryWithDescendant(string $entryId, string $descendantId): void
{
    $childPage = Mockery::mock();
    $childPage->shouldReceive('id')->andReturn($descendantId);

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('flattenedPages')->andReturn(collect([$childPage]));

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('page')->andReturn($page);

    EntryFacade::shouldReceive('find')->with($entryId)->andReturn($entry);
}
