<?php

declare(strict_types=1);

use App\Services\Identifiers\IdentifierAuditSurface;
use Illuminate\Support\Facades\Artisan;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Behavior of `identifiers:audit-duplicates` (UAMS-Web/uams-statamic-archive#2395), the half of the identifier
 * uniqueness work that covers imported content.
 *
 * UAMS-Web/uams-statamic-archive#2152's rule is blueprint validation and runs in the Control Panel only, so it
 * never sees an entry the importer wrote. This command is what does.
 *
 * The case worth naming is the LAST one: a clean run must print what it examined.
 * "No duplicates found" and "found nothing because it looked nowhere" are
 * otherwise byte-identical, and the second is what a broken scan produces.
 *
 * @command  php artisan test --compact tests/Feature/Console/AuditDuplicateIdentifiersTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    foreach (['people', 'clinical_locations'] as $handle) {
        $collection = Collection::make($handle);
        $collection->title(ucfirst($handle));
        $collection->save();
    }
});

/**
 * Create and save an entry carrying the given data.
 *
 * @param  array<string, mixed>  $data
 */
function auditEntry(string $collection, string $slug, array $data): ConcreteEntry
{
    $entry = Entry::make();

    if (! $entry instanceof ConcreteEntry) {
        throw new RuntimeException('Entry::make() did not return an entry.');
    }

    $entry->collection($collection);
    $entry->slug($slug);
    $entry->data($data);
    $entry->save();

    return $entry;
}

/**
 * Wrap Workday values in the group they are stored under.
 *
 * @param  array<string, string>  $values
 * @return array<string, array<string, string>>
 */
function auditWorkday(array $values): array
{
    return ['uams_workday_id' => $values];
}

/**
 * `epic_dep_ids`-shaped replicator rows.
 *
 * @param  array<int, string>  $values
 * @return array<int, array<string, mixed>>
 */
function auditRows(array $values, string $key): array
{
    return array_map(
        static fn (string $v): array => ['type' => 'new_set', 'enabled' => true, $key => $v],
        $values,
    );
}

/**
 * Run the audit and return everything it rendered.
 *
 * `$this->artisan(...)->expectsOutputToContain(...)` does NOT see `table()`
 * output, so an assertion written that way passes or fails for reasons
 * unrelated to the finding it is about. Calling the command and reading the
 * real buffer asserts against what an operator actually sees.
 */
function runIdentifierAudit(): string
{
    Artisan::call('identifiers:audit-duplicates');

    return Artisan::output();
}

it('derives its audit surface from the wired blueprint rules', function (): void {
    $groups = app(IdentifierAuditSurface::class)->all();

    $labels = array_column($groups, 'label');

    // The five groups UAMS-Web/uams-statamic-archive#2152 wires, and nothing else
    expect($labels)->toHaveCount(5);
    expect($labels)->toContain('people npi');
    expect($labels)->toContain('people epic_ser_id');
    expect($labels)->toContain('clinical_locations list:dep');
    expect($labels)->toContain('clinical_locations list:item');

    // The four Workday fields are ONE pooled group, not four
    $workday = array_values(array_filter(
        $groups,
        static fn (array $g): bool => str_contains($g['label'], 'workday_universal_id')
    ));

    expect($workday)->toHaveCount(1);
    expect($workday[0]['paths'])->toHaveCount(4);
});

it('does not audit the scheduling widget dep field or clinical_providers', function (): void {
    // Both hold by construction rather than by a skip-list: neither is wired.
    // Asserted anyway, because "by construction" is exactly the kind of claim
    // that stops being true without anyone noticing.
    $groups = app(IdentifierAuditSurface::class)->all();

    foreach ($groups as $group) {
        expect($group['collection'])->not->toBe('clinical_providers');
    }

    // The widget shares a fieldset with the declared list, so the only thing
    // keeping it out is that the rule is wired per blueprint field
    $handles = array_map(
        static fn (array $g): string => $g['rowKey'] ?? '',
        $groups
    );

    expect(array_count_values(array_filter($handles)))->toBe(['dep' => 1, 'item' => 1]);
});

it('reports a cross-entry duplicate, naming the value and both entries', function (): void {
    auditEntry('people', 'first', ['title' => 'Dana Reed', 'npi' => '1234567893']);
    auditEntry('people', 'second', ['title' => 'Alex Stone', 'npi' => '1234567893']);

    $output = runIdentifierAudit();

    expect($output)
        ->toContain('across entries')
        ->toContain('Dana Reed')
        ->toContain('Alex Stone');
});

it('reports a pooled Workday collision across two different fields', function (): void {
    // The case a per-field check passes: one value in two different id spaces
    auditEntry('people', 'first', ['title' => 'Dana Reed'] + auditWorkday(['workday_employee_id' => 'W-4471']));
    auditEntry('people', 'second', ['title' => 'Alex Stone'] + auditWorkday(['workday_universal_id' => 'W-4471']));

    $output = runIdentifierAudit();

    expect($output)
        ->toContain('W-4471')
        ->toContain('across entries');
});

it('reports a within-entry duplicate distinctly from a cross-entry one', function (): void {
    auditEntry('clinical_locations', 'clinic', [
        'title' => 'Clinic',
        'epic_dep_ids' => auditRows(['10502', '10502'], 'dep'),
    ]);

    $output = runIdentifierAudit();

    expect($output)
        ->toContain('within entry')
        ->toContain('10502');
});

it('matches on a case-and-whitespace-only difference', function (): void {
    auditEntry('people', 'first', ['title' => 'Dana Reed'] + auditWorkday(['workday_employee_id' => 'w-4471']));
    auditEntry('people', 'second', ['title' => 'Alex Stone'] + auditWorkday(['workday_universal_id' => '  W-4471  ']));

    $output = runIdentifierAudit();

    expect($output)
        ->toContain('across entries');
});

it('never reports blank identifiers as colliding, however many are blank', function (): void {
    foreach (range(1, 6) as $n) {
        auditEntry('people', "blank-{$n}", ['title' => "Blank {$n}", 'npi' => '', 'epic_ser_id' => null]);
    }

    $output = runIdentifierAudit();

    expect($output)
        ->toContain('No duplicate identifiers found.');
});

it('prints what it examined on a clean run, so a clean report is not an empty one', function (): void {
    // The criterion that matters most. Without the denominator, a scan that
    // read nothing and a scan that found nothing print the same thing.
    auditEntry('people', 'first', ['title' => 'Dana Reed', 'npi' => '1234567893']);
    auditEntry('people', 'second', ['title' => 'Alex Stone', 'npi' => '1987654launch']);

    $output = runIdentifierAudit();

    expect($output)
        ->toContain('Audited 5 identifier group(s)')
        ->toContain('2 entries')
        ->toContain('identifier value(s)')
        ->toContain('No duplicate identifiers found.');
});

it('reports zero entries as zero rather than as a clean scan', function (): void {
    // No entries at all: the denominator must say so rather than printing a
    // reassuring "no duplicates" over an empty read
    $output = runIdentifierAudit();

    expect($output)
        ->toContain('over 0 entries');
});
