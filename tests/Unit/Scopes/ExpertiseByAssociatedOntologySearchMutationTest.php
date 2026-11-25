<?php

declare(strict_types=1);

use App\Scopes\ExpertiseByAssociatedOntologySearch;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ExpertiseByAssociatedOntologySearch::class);

/**
 * Narrow mutate coverage for App\Scopes\ExpertiseByAssociatedOntologySearch.
 *
 * EntryFacade::query() and the scoped builder are mocked so mutant children
 * never touch the Stache. The behavior against the real query layer is covered by
 * tests/Feature/Scopes/ExpertiseByAssociatedOntologySearchScopeTest.php.
 *
 * Mutation: no survivor, without --covered-only; the class has no equivalent mutant.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/ExpertiseByAssociatedOntologySearchMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    padMutateChildTimeout();
});

/**
 * Stub the two ontology lookups, in the order the scope runs them.
 *
 * @param  list<Entry>  $conditions
 * @param  list<Entry>  $services
 */
function expertiseOntologyStubLookups(array $conditions, array $services): void
{
    $lookups = [];
    foreach (['clinical_conditions' => $conditions, 'clinical_services' => $services] as $collection => $entries) {
        $lookup = Mockery::mock();
        $lookup->shouldReceive('where')->once()->with('collection', $collection)->andReturnSelf();
        $lookup->shouldReceive('get')->once()->andReturn(collect($entries));
        $lookups[] = $lookup;
    }

    EntryFacade::shouldReceive('query')->twice()->andReturn(...$lookups);
}

/**
 * An ontology entry double.
 *
 * @param  array<string, mixed>  $data
 */
function expertiseOntologyEntry(mixed $id, array $data, bool $published = true): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('published')->andReturn($published);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('get')->andReturnUsing(fn (string $key): mixed => $data[$key] ?? null);

    return $entry;
}

/**
 * Apply the scope and return the field overlaps its single nested group adds.
 *
 * @return array<string, mixed> field handle => the ids it must overlap
 */
function expertiseOntologyOverlaps(mixed $term): array
{
    $overlaps = [];

    /** @var Builder&MockInterface $group */
    $group = Mockery::mock(Builder::class);
    $group->shouldReceive('orWhereJsonOverlaps')->andReturnUsing(
        function (string $field, mixed $ids) use (&$overlaps, $group): Builder {
            $overlaps[$field] = $ids;

            return $group;
        }
    );

    /** @var Builder&MockInterface $query */
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')->once()->andReturnUsing(
        function (Closure $closure) use ($group, $query): Builder {
            $closure($group);

            return $query;
        }
    );

    expect((new ExpertiseByAssociatedOntologySearch)->apply($query, ['q' => $term]))->toBe($query);

    return $overlaps;
}

it('returns the query untouched, without a lookup, when no term is passed', function (): void {
    EntryFacade::shouldReceive('query')->never();

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')->never();

    expect((new ExpertiseByAssociatedOntologySearch)->apply($query, []))->toBe($query);
});

it('returns the query untouched, without a lookup, when the term is not an active search', function (mixed $term): void {
    EntryFacade::shouldReceive('query')->never();

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')->never();

    expect((new ExpertiseByAssociatedOntologySearch)->apply($query, ['q' => $term]))->toBe($query);
})->with([
    'empty term' => [''],
    'blank term' => ["  \t "],
    'null term' => [null],
    'non-string term' => [42],
    'list term' => [['knee']],
]);

it('overlaps each field with the ids its own collection matched', function (): void {
    expertiseOntologyStubLookups(
        [
            expertiseOntologyEntry('condition-a', ['title' => 'Knee Pain']),
            expertiseOntologyEntry('condition-b', ['title' => 'Asthma']),
            expertiseOntologyEntry('condition-c', ['title' => 'Torn knee ligament']),
        ],
        [expertiseOntologyEntry('service-a', ['title' => 'Knee Replacement'])],
    );

    expect(expertiseOntologyOverlaps('knee'))->toBe([
        'clinical_conditions' => ['condition-a', 'condition-c'],
        'clinical_services' => ['service-a'],
    ]);
});

it('leaves out a field whose collection matched nothing', function (): void {
    expertiseOntologyStubLookups(
        [expertiseOntologyEntry('condition-a', ['title' => 'Asthma'])],
        [expertiseOntologyEntry('service-a', ['title' => 'Knee Replacement'])],
    );

    expect(expertiseOntologyOverlaps('knee'))->toBe(['clinical_services' => ['service-a']]);
});

it('overlaps an id no entry carries when nothing matched', function (): void {
    expertiseOntologyStubLookups([], []);

    expect(expertiseOntologyOverlaps('knee'))->toBe(['clinical_conditions' => ['__no_matching_ontology_term__']]);
});

it('trims the term and folds its case and the title\'s, beyond ASCII', function (): void {
    expertiseOntologyStubLookups([expertiseOntologyEntry('condition-a', ['title' => 'CAFÉ-AU-LAIT SPOTS'])], []);

    expect(expertiseOntologyOverlaps('  Café-au-lait '))->toBe(['clinical_conditions' => ['condition-a']]);
});

it('folds the case of an alternate name, beyond ASCII', function (): void {
    expertiseOntologyStubLookups(
        [expertiseOntologyEntry('condition-a', [
            'title' => 'Pigmented macules',
            'alternate_name' => [['type' => 'new_set', 'enabled' => true, 'text' => 'CAFÉ-AU-LAIT SPOTS']],
        ])],
        [],
    );

    expect(expertiseOntologyOverlaps('café-au-lait'))->toBe(['clinical_conditions' => ['condition-a']]);
});

it('matches an alternate name when the title is not a string', function (): void {
    expertiseOntologyStubLookups(
        [
            expertiseOntologyEntry('condition-a', [
                'title' => 42,
                'alternate_name' => [['type' => 'new_set', 'enabled' => true, 'text' => 'Knee pain']],
            ]),
            expertiseOntologyEntry('condition-b', ['title' => 42]),
        ],
        [],
    );

    expect(expertiseOntologyOverlaps('knee'))->toBe(['clinical_conditions' => ['condition-a']]);
});

it('skips an entry that is unpublished, opted out of search, unmatched, or without a string id', function (): void {
    expertiseOntologyStubLookups(
        [
            expertiseOntologyEntry('draft', ['title' => 'Knee draft'], published: false),
            expertiseOntologyEntry('opted-out', ['title' => 'Knee hidden', 'searchable' => false]),
            expertiseOntologyEntry('unmatched', ['title' => 'Asthma']),
            expertiseOntologyEntry(42, ['title' => 'Knee integer id']),
            expertiseOntologyEntry(null, ['title' => 'Knee no id']),
            expertiseOntologyEntry('opted-in', ['title' => 'Knee shown', 'searchable' => true]),
            expertiseOntologyEntry('null-toggle', ['title' => 'Knee default', 'searchable' => null]),
        ],
        [],
    );

    expect(expertiseOntologyOverlaps('knee'))->toBe(['clinical_conditions' => ['opted-in', 'null-toggle']]);
});
