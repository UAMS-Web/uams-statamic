<?php

declare(strict_types=1);

use App\Services\People\MergeReconciliation;
use App\Services\People\MergeResult;
use App\Support\References\ReferenceRepointSummary;

covers(MergeReconciliation::class, MergeResult::class);

/**
 * The two value objects a people merge hands the reconciliation page
 * (UAMS-Web/uams-statamic#309): the field-by-field comparison before a merge, with
 * its count of conflicting fields, and the merge's outcome. Each `toArray()` is the
 * JSON shape the page reads, so its keys and values are pinned whole.
 *
 * @command  php artisan test --compact tests/Unit/Services/People/MergeValueObjectsTest.php
 */

/**
 * One reconcilable field row.
 *
 * @return array{handle: string, display: string, type: string, conflict: bool, values: list<array{entry_id: string, value: mixed, display: string, has_value: bool}>, default_source: string}
 */
function mergeFieldRow(string $handle, bool $conflict): array
{
    return [
        'handle' => $handle,
        'display' => ucfirst($handle),
        'type' => 'text',
        'conflict' => $conflict,
        'values' => [['entry_id' => 'a', 'value' => $handle, 'display' => $handle, 'has_value' => true]],
        'default_source' => 'a',
    ];
}

it('counts only the fields whose candidates disagree', function (array $conflicts, int $expected): void {
    /** @var list<bool> $conflicts */
    $fields = [];
    foreach ($conflicts as $i => $conflict) {
        $fields[] = mergeFieldRow('field_'.$i, $conflict);
    }

    expect(new MergeReconciliation('a', [], $fields, [])->conflictCount())->toBe($expected);
})->with([
    'no fields' => [[], 0],
    'none in conflict' => [[false, false], 0],
    'one of three' => [[false, true, false], 1],
    'all in conflict' => [[true, true], 2],
]);

it('lays out the reconciliation the page reads, with the conflict count', function (): void {
    $candidates = [
        ['id' => 'a', 'title' => 'Ann', 'slug' => 'ann', 'site' => 'default', 'url' => '/ann', 'edit_url' => '/cp/ann', 'is_survivor' => true],
        ['id' => 'b', 'title' => 'Ann B', 'slug' => null, 'site' => 'health', 'url' => null, 'edit_url' => null, 'is_survivor' => false],
    ];
    $fields = [mergeFieldRow('title', true), mergeFieldRow('email', false)];
    $sites = ['default' => [['entry_id' => 'a', 'slug' => 'ann', 'url' => '/ann', 'is_survivor' => true]]];

    expect(new MergeReconciliation('a', $candidates, $fields, $sites)->toArray())->toBe([
        'survivor_id' => 'a',
        'candidates' => $candidates,
        'fields' => $fields,
        'sites' => $sites,
        'conflict_count' => 1,
    ]);
});

it('lays out the merge outcome the page reads, with the reference summary expanded', function (bool $dryRun): void {
    $redirects = [['site' => 'default', 'source' => '/ann-b', 'target' => '/ann', 'response' => '301']];

    $result = new MergeResult(
        survivorId: 'a',
        dryRun: $dryRun,
        appliedFields: ['email'],
        references: new ReferenceRepointSummary(entries: 2, globals: 1, terms: 3, occurrences: 9),
        redirectsCreated: 1,
        redirects: $redirects,
        retiredIds: ['b'],
    );

    expect($result->toArray())->toBe([
        'survivor_id' => 'a',
        'dry_run' => $dryRun,
        'applied_fields' => ['email'],
        'references' => ['entries' => 2, 'globals' => 1, 'terms' => 3, 'occurrences' => 9, 'items_changed' => 6],
        'redirects_created' => 1,
        'redirects' => $redirects,
        'retired_ids' => ['b'],
    ]);
})->with(['preview' => [true], 'committed' => [false]]);
