<?php

declare(strict_types=1);

use App\Scopes\EntriesByProvenance;
use Illuminate\Support\Collection;
use Statamic\Query\ItemQueryBuilder;
use Tests\TestCase;

/**
 * Pin provenance aliases and their resulting record selection.
 *
 * Linux orb, PHP 8.4/PCOV: 26/26 covered statements and 27 killed mutants.
 * Three equivalent mutants are narrowly excluded: strict membership compares
 * a string suffix with nonnumeric values; duplicates and array keys do not affect
 * the records selected by whereIn(). Every killable mutant is dead.
 *
 * @command  php artisan test --compact tests/Feature/Scopes/EntriesByProvenanceScopeTest.php
 */
uses(TestCase::class);

covers(EntriesByProvenance::class);

it('registers exactly the two public provenance aliases', function (): void {
    expect(EntriesByProvenance::aliases())->toBe([
        'entries_by_provenance_first_party',
        'entries_by_provenance_third_party',
    ]);
});

it('selects recognized provenances without widening an existing query', function (
    array $values,
    array $expected
): void {
    // Distinguish both provenances from missing, unrecognized, and excluded rows.
    $query = new ItemQueryBuilder;
    $query->withItems(collect([
        ['id' => 'first-main', 'provenance' => 'first_party', 'site' => 'main'],
        ['id' => 'third-main', 'provenance' => 'third_party', 'site' => 'main'],
        ['id' => 'unknown-main', 'provenance' => 'unknown', 'site' => 'main'],
        ['id' => 'missing-main', 'site' => 'main'],
        ['id' => 'first-other', 'provenance' => 'first_party', 'site' => 'other'],
    ]));
    $query->where('site', 'main');

    // Exercise handle parsing through the public scope and its selected records.
    (new EntriesByProvenance)->apply($query, mustBeStringKeyed($values, 'scope values'));

    expect(mustBe(Collection::class, $query->get(), 'selected records')->pluck('id')->all())->toBe($expected);
})->with([
    'first party string' => [['queryScopes' => 'entries_by_provenance_first_party'], ['first-main']],
    'third party array' => [['queryScopes' => ['entries_by_provenance_third_party']], ['third-main']],
    'both provenances' => [['queryScopes' => ['entries_by_provenance_third_party', 'entries_by_provenance_first_party']], ['first-main', 'third-main']],
    'duplicate handles' => [['queryScopes' => ['entries_by_provenance_first_party', 'entries_by_provenance_first_party']], ['first-main']],
    'duplicates before another provenance' => [['queryScopes' => ['entries_by_provenance_first_party', 'entries_by_provenance_first_party', 'entries_by_provenance_third_party']], ['first-main', 'third-main']],
    'uppercase suffix' => [['queryScopes' => ['entries_by_provenance_THIRD_PARTY']], ['third-main']],
    'mixed handles' => [['queryScopes' => [null, 42, [], 'unrelated', 'entries_by_provenance_unknown', 'entries_by_provenance_first_party']], ['first-main']],
    'missing handles' => [[], ['first-main', 'third-main', 'unknown-main', 'missing-main']],
    'null handles' => [['queryScopes' => null], ['first-main', 'third-main', 'unknown-main', 'missing-main']],
    'empty handles' => [['queryScopes' => []], ['first-main', 'third-main', 'unknown-main', 'missing-main']],
    'invalid scalar' => [['queryScopes' => false], ['first-main', 'third-main', 'unknown-main', 'missing-main']],
    'unknown suffix' => [['queryScopes' => ['entries_by_provenance_unknown']], ['first-main', 'third-main', 'unknown-main', 'missing-main']],
    'wrong prefix' => [['queryScopes' => ['other_first_party']], ['first-main', 'third-main', 'unknown-main', 'missing-main']],
    'uppercase prefix' => [['queryScopes' => ['ENTRIES_BY_PROVENANCE_first_party']], ['first-main', 'third-main', 'unknown-main', 'missing-main']],
]);
