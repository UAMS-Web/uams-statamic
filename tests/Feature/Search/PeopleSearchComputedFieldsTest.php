<?php

declare(strict_types=1);

use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The person-name fields the `people` and `clinical_providers` search indexes
 * search are computed values, registered by `uams-web/aether-people` since the
 * people code moved there (#696). The index config names them as plain field
 * handles, so a registrar that stopped registering one would leave the index
 * searching an empty value with nothing failing: people search would quietly
 * fall back to the title alone.
 *
 * Each name field an index searches must be a registered computed for that
 * index's collection. The control asserts `title`, a stored field the indexes
 * also search, is not one, so the check can return both answers.
 *
 * @command  php artisan test --compact tests/Feature/Search/PeopleSearchComputedFieldsTest.php
 */

/** The searched fields that are computed person-name values, not stored fields. */
const PEOPLE_SEARCH_COMPUTED_NAME_FIELDS = [
    'alternate_name_list',
    'display_name_full',
    'display_name_full_with_credentials',
    'display_name_full_with_prefix',
    'display_name_short_with_prefix',
    'full_name',
    'full_name_with_credentials',
    'full_name_with_prefix',
    'nickname_list',
];

it('registers every computed name field the index searches', function (string $index, string $collection): void {
    /** @var list<string> $searched */
    $searched = config("statamic.search.indexes.{$index}.fields", []);
    $computed = Collection::getComputedCallbacks($collection)->keys()->all();

    // The index still searches each name field, so the check below is not vacuous
    expect($searched)->toContain(...PEOPLE_SEARCH_COMPUTED_NAME_FIELDS);

    // Each one resolves through a registered computed
    expect(array_values(array_diff(PEOPLE_SEARCH_COMPUTED_NAME_FIELDS, $computed)))->toBe([]);

    // Control: a stored field the index searches is not reported as computed
    expect($searched)->toContain('title')
        ->and($computed)->not->toContain('title');
})->with([
    'people' => ['people', 'people'],
    'clinical providers' => ['clinical_providers', 'clinical_providers'],
]);
