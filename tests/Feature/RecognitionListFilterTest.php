<?php

declare(strict_types=1);

use App\Scopes\RecognitionListMembership;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Entry;
use Statamic\Facades\Fieldset;
use Statamic\Facades\Stache;
use Statamic\Query\Builder;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\Store;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The Find-a-Doc provider-block recognition-list filter (UAMS-Web/uams-statamic-archive#1176): the block control
 * and the `recognition_list_membership` scope that narrows the provider listing to
 * a chosen recognition list (or category), riding on the UAMS-Web/uams-statamic-archive#1546 top-level
 * `recognition_lists` relationship.
 *
 * The scope's list→descendant expansion queries the recognition_lists structure, so
 * those cases run against the migrated content and skip when it is absent.
 *
 * @command  php artisan test --compact tests/Feature/RecognitionListFilterTest.php
 */

/**
 * Apply the recognition-list scope and return the builder apply() hands back, so
 * the caller can assert the scope's documented pass-through — apply() returns the
 * builder so it composes in the block scope pipe list.
 *
 * @param  array<string, mixed>  $values
 */
function applyRecognitionListScope(RecognitionListMembership $scope, EntryQueryBuilder $query, array $values): Builder
{
    return $scope->apply($query, $values);
}

/** The migrated recognition_lists collection, or skip. */
function recFilterSkipWithoutContent(): void
{
    /** @var Store $collectionsStore */
    $collectionsStore = Stache::store('collections');
    /** @var string $collectionsDirectory */
    $collectionsDirectory = $collectionsStore->directory();

    if (! File::exists($collectionsDirectory.'recognition_lists.yaml')) {
        ContentSubmodule::reportMissing('migrated recognition_lists collection');
    }
}

/**
 * Apply the scope to a people query and return the matched slugs.
 *
 * @return Collection<int, string>
 */
function recFilterMatchedSlugs(string $recognitionParam): Collection
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'people');
    applyRecognitionListScope(new RecognitionListMembership, $query, ['recognition_lists' => $recognitionParam]);

    /** @var Collection<int, Statamic\Entries\Entry> $people */
    $people = $query->get();

    return $people->map(function (Statamic\Entries\Entry $entry): string {
        /** @var string $slug */
        $slug = $entry->slug();

        return $slug;
    });
}

it('exposes an optional recognition-list entries control on the provider block', function () {
    // Read the fieldset field directly (the block imports it into the page builder set).
    $fieldset = mustBe(Statamic\Fields\Fieldset::class, Fieldset::find('uamsweb_block_fad_providers'), 'fieldset');

    /** @var array<int, array<string, mixed>> $fields */
    $fields = $fieldset->contents()['fields'];

    /** @var array{field: array<string, mixed>} $config */
    $config = collect($fields)->firstWhere('handle', 'recognition_lists');
    expect($config)->not->toBeNull();

    expect($config['field']['type'])->toBe('entries');
    expect($config['field']['collections'])->toBe(['recognition_lists']);
    // Optional by default — no `required` validation on the filter control.
    expect($config['field']['validate'] ?? [])->not->toContain('required');
});

it('wires the recognition_list_membership scope into the provider block query', function () {
    $partial = File::get(resource_path('views/page_builder/_uamsweb_block_fad_providers.antlers.html'));

    // The block composes the recognition filter with the expertise pre-filter as a
    // pipe-listed second scope, passing the block's selected ids.
    expect($partial)->toContain('ontology_overlap_expertise|recognition_list_membership');
    expect($partial)->toContain("recognition_lists=\"{{ recognition_lists | pluck:'id' | implode:',' }}\"");
});

it('narrows to a category member when filtering by that category', function () {
    recFilterSkipWithoutContent();

    /** @var EntryQueryBuilder $categoryQuery */
    $categoryQuery = Entry::query();
    $categoryQuery->where('collection', 'recognition_lists');
    $categoryQuery->where('slug', 'best-doctors-in-america-2020-orthopaedic-surgery');
    /** @var Statamic\Entries\Entry|null $category */
    $category = $categoryQuery->first();
    /** @var string|null $categoryId */
    $categoryId = $category?->id();

    // A null id would degrade to the scope's blank→pass-through, so the positive
    // assertion below would trivially pass on a dropped fixture — guard it loud.
    expect($categoryId)->not->toBeNull();
    expect(recFilterMatchedSlugs((string) $categoryId))->toContain('c-lowry-barnes');
});

it('expands a selected list to also match members filed under its categories', function () {
    recFilterSkipWithoutContent();

    // c-lowry-barnes is filed under the "Orthopaedic Surgery" category of this list,
    // NOT the list node directly — so matching by the LIST must expand to descendants.
    /** @var EntryQueryBuilder $listQuery */
    $listQuery = Entry::query();
    $listQuery->where('collection', 'recognition_lists');
    $listQuery->where('slug', 'best-doctors-in-america-2020');
    /** @var Statamic\Entries\Entry|null $list */
    $list = $listQuery->first();
    /** @var string|null $listId */
    $listId = $list?->id();

    expect($listId)->not->toBeNull();
    expect(recFilterMatchedSlugs((string) $listId))->toContain('c-lowry-barnes');
});

it('returns the same query builder so it composes in the block scope pipe list', function () {
    recFilterSkipWithoutContent();

    /** @var EntryQueryBuilder $listQuery */
    $listQuery = Entry::query();
    $listQuery->where('collection', 'recognition_lists');
    $listQuery->where('slug', 'best-doctors-in-america-2020');
    /** @var Statamic\Entries\Entry|null $list */
    $list = $listQuery->first();
    /** @var string|null $listId */
    $listId = $list?->id();
    expect($listId)->not->toBeNull();

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'people');
    $result = applyRecognitionListScope(new RecognitionListMembership, $query, ['recognition_lists' => (string) $listId]);

    expect($result)->toBe($query);
});

it('excludes a provider not on the filtered recognition list', function () {
    recFilterSkipWithoutContent();

    // c-lowry-barnes is on soiree-top-docs-2022, not 2021.
    /** @var EntryQueryBuilder $otherListQuery */
    $otherListQuery = Entry::query();
    $otherListQuery->where('collection', 'recognition_lists');
    $otherListQuery->where('slug', 'soiree-top-docs-2021');
    /** @var Statamic\Entries\Entry|null $otherList */
    $otherList = $otherListQuery->first();
    /** @var string|null $otherListId */
    $otherListId = $otherList?->id();

    expect($otherListId)->not->toBeNull();
    expect(recFilterMatchedSlugs((string) $otherListId))->not->toContain('c-lowry-barnes');
});

it('looks descendants up in recognition_lists only and overlaps each id once', function () {
    // Every query the scope makes is a mock, so the constraints it adds are asserted directly.
    $child = Mockery::mock(Statamic\Entries\Entry::class);
    $child->shouldReceive('id')->andReturn('child-of-a');
    $repeat = Mockery::mock(Statamic\Entries\Entry::class);
    $repeat->shouldReceive('id')->andReturn('list-a');
    $unsaved = Mockery::mock(Statamic\Entries\Entry::class);
    $unsaved->shouldReceive('id')->andReturn(null);

    $descendants = Mockery::mock(EntryQueryBuilder::class);
    $descendants->shouldReceive('where')->once()->with('collection', 'recognition_lists')->andReturnSelf();
    $descendants->shouldReceive('whereIn')->once()->with('parent', ['list-a', 'list-b'])->andReturnSelf();
    $descendants->shouldReceive('get')->once()->andReturn(collect([$repeat, $child, $unsaved]));
    Entry::shouldReceive('query')->once()->andReturn($descendants);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonOverlaps')->once()->with('recognition_lists', ['list-a', 'list-b', 'child-of-a'])->andReturnSelf();

    // A blank between the ids, and a descendant repeating a selected id ahead of a new one,
    // each leave a gap in a filtered list; both lists must still reach the query as lists.
    expect((new RecognitionListMembership)->apply($query, ['recognition_lists' => 'list-a,,list-b']))->toBe($query);
});
