<?php

declare(strict_types=1);

use App\Scopes\OntologyOverlapSpecialInterests;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

covers(OntologyOverlapSpecialInterests::class);

/*
|--------------------------------------------------------------------------
| OntologyOverlapSpecialInterests (UAMS-Web/uams-statamic-archive#1065 D1 — special-interest FaD filter)
|--------------------------------------------------------------------------
|
| The query scope behind the "special interest in [condition/service]" filter on
| the Find-a-Doc providers/locations blocks. It filters a people /
| clinical_locations listing to entries whose own authored special interest
| overlaps the C/T ids the block selected. Special interest is authored in the two
| typed UAMS-Web/uams-statamic-archive#1064 fields — condition ids in `special_interest_clinical_conditions`,
| service ids in `special_interest_clinical_services` — so the scope reads both
| block parameters and OR-matches a profile whose EITHER field overlaps its
| selection, grouped so the disjunction cannot leak into an outer conjunction. It
| is the sibling of the shipped `ontology_overlap_expertise` filter. These mock the
| query builder to isolate the accept/skip, id-parsing, and OR-grouping decisions.
|
| Mutation: tracked under App\Scopes\OntologyOverlapSpecialInterests
| (--covered-only).
|
| @command php artisan test --compact tests/Unit/Scopes/OntologyOverlapSpecialInterestsScopeTest.php
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

/**
 * Capture the nested-group closure `apply()` passes to `where(...)`, invoke it
 * against a fresh inner-builder mock carrying the given `orWhereJsonOverlaps`
 * expectations, and assert the outer `where` was called exactly once. Returns the
 * inner mock so a caller can add its own expectations before invocation.
 *
 * @param  Builder&MockInterface  $query  the outer builder mock
 * @param  callable(Builder&MockInterface): void  $expectInner  sets the inner group's expectations
 */
function expectOverlapGroup(MockInterface $query, callable $expectInner): void
{
    // `whereNested()` passes the nested-where closure a Statamic builder, so the
    // inner group is a mock of that class.
    /** @var Builder&MockInterface $inner */
    $inner = Mockery::mock(Builder::class);
    $expectInner($inner);

    $query->shouldReceive('where')
        ->once()
        ->with(Mockery::type('Closure'))
        ->andReturnUsing(function (Closure $group) use ($query, $inner) {
            $group($inner);

            return $query;
        });
}

it('returns the query unchanged when neither special-interest parameter is present', function () {
    $scope = new OntologyOverlapSpecialInterests;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('where')->never();
    $query->shouldReceive('orWhereJsonOverlaps')->never();

    $result = $scope->apply($query, []);

    expect($result)->toBe($query);
});

it('returns the query unchanged when both typed parameters are empty strings', function () {
    // An unfiltered block passes empty strings; explode() yields [''] which must be
    // treated as "no selection" so the whole listing is returned, not an empty set.
    $scope = new OntologyOverlapSpecialInterests;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('where')->never();

    $result = $scope->apply($query, [
        'special_interest_clinical_conditions' => '',
        'special_interest_clinical_services' => '',
    ]);

    expect($result)->toBe($query);
});

it('returns the query unchanged when both typed parameters are empty arrays', function () {
    $scope = new OntologyOverlapSpecialInterests;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('where')->never();

    $result = $scope->apply($query, [
        'special_interest_clinical_conditions' => [],
        'special_interest_clinical_services' => [],
    ]);

    expect($result)->toBe($query);
});

it('overlaps only the conditions field when only conditions are selected', function () {
    // A services-only match must NOT be possible here — the services leg is absent.
    $scope = new OntologyOverlapSpecialInterests;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    expectOverlapGroup($query, function (MockInterface $inner): void {
        $inner->shouldReceive('orWhereJsonOverlaps')
            ->once()
            ->with('special_interest_clinical_conditions', ['cond-a']);
        $inner->shouldReceive('orWhereJsonOverlaps')
            ->with('special_interest_clinical_services', Mockery::any())
            ->never();
    });

    $result = $scope->apply($query, ['special_interest_clinical_conditions' => 'cond-a']);

    expect($result)->toBe($query);
});

it('overlaps only the services field when only services are selected', function () {
    $scope = new OntologyOverlapSpecialInterests;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    expectOverlapGroup($query, function (MockInterface $inner): void {
        $inner->shouldReceive('orWhereJsonOverlaps')
            ->once()
            ->with('special_interest_clinical_services', ['svc-b']);
        $inner->shouldReceive('orWhereJsonOverlaps')
            ->with('special_interest_clinical_conditions', Mockery::any())
            ->never();
    });

    $result = $scope->apply($query, ['special_interest_clinical_services' => 'svc-b']);

    expect($result)->toBe($query);
});

it('OR-groups both typed overlaps when conditions and services are both selected', function () {
    // The heart of the two-field design: a profile matches if EITHER its conditions
    // field overlaps the selected conditions OR its services field overlaps the
    // selected services. Both legs land inside the SAME nested where-group.
    $scope = new OntologyOverlapSpecialInterests;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    expectOverlapGroup($query, function (MockInterface $inner): void {
        $inner->shouldReceive('orWhereJsonOverlaps')
            ->once()
            ->with('special_interest_clinical_conditions', ['cond-a', 'cond-c']);
        $inner->shouldReceive('orWhereJsonOverlaps')
            ->once()
            ->with('special_interest_clinical_services', ['svc-b']);
    });

    $result = $scope->apply($query, [
        'special_interest_clinical_conditions' => 'cond-a,cond-c',
        'special_interest_clinical_services' => ['svc-b'],
    ]);

    expect($result)->toBe($query);
});

it('explodes a comma-separated string of ids and passes an array value straight through', function () {
    $scope = new OntologyOverlapSpecialInterests;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    expectOverlapGroup($query, function (MockInterface $inner): void {
        $inner->shouldReceive('orWhereJsonOverlaps')
            ->once()
            ->with('special_interest_clinical_conditions', ['cond-a', 'cond-b']);
        $inner->shouldReceive('orWhereJsonOverlaps')
            ->once()
            ->with('special_interest_clinical_services', ['svc-x', 'svc-y']);
    });

    $result = $scope->apply($query, [
        'special_interest_clinical_conditions' => 'cond-a,cond-b',
        'special_interest_clinical_services' => ['svc-x', 'svc-y'],
    ]);

    expect($result)->toBe($query);
});

it('drops blank ids from a partially-empty selection before overlapping', function () {
    // A stray empty segment (e.g. a trailing comma) must not become a blank id that
    // matches nothing; only the real ids survive into the overlap.
    $scope = new OntologyOverlapSpecialInterests;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    expectOverlapGroup($query, function (MockInterface $inner): void {
        $inner->shouldReceive('orWhereJsonOverlaps')
            ->once()
            ->with('special_interest_clinical_conditions', ['cond-a']);
        $inner->shouldReceive('orWhereJsonOverlaps')
            ->with('special_interest_clinical_services', Mockery::any())
            ->never();
    });

    $result = $scope->apply($query, [
        'special_interest_clinical_conditions' => 'cond-a,',
        'special_interest_clinical_services' => '',
    ]);

    expect($result)->toBe($query);
});

it('reindexes ids after dropping a LEADING blank so the overlap receives a JSON array, not an object', function () {
    // A leading empty segment (e.g. ",cond-a") leaves array_filter's surviving id at
    // key 1; without a reindex, json_encode emits a JSON OBJECT {"1":"cond-a"} instead
    // of the array ["cond-a"] whereJsonOverlaps needs, silently matching nothing. The
    // strict-identity matcher fails unless the ids are reindexed to a 0-based list.
    $scope = new OntologyOverlapSpecialInterests;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    expectOverlapGroup($query, function (MockInterface $inner): void {
        $inner->shouldReceive('orWhereJsonOverlaps')
            ->once()
            ->with('special_interest_clinical_conditions', Mockery::on(
                static fn (mixed $ids): bool => $ids === ['cond-a']
            ));
    });

    $result = $scope->apply($query, ['special_interest_clinical_conditions' => ',cond-a']);

    expect($result)->toBe($query);
});

it('ignores a non-string, non-array parameter value', function () {
    $scope = new OntologyOverlapSpecialInterests;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('where')->never();

    $result = $scope->apply($query, [
        'special_interest_clinical_conditions' => 123,
        'special_interest_clinical_services' => null,
    ]);

    expect($result)->toBe($query);
});
