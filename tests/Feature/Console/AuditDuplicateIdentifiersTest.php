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

/**
 * Bind an audit surface that reports exactly the given groups.
 *
 * @param  list<array{collection: string, paths: list<string>, rowKey: string|null, label: string}>  $groups
 */
function auditSurfaceOf(array $groups): void
{
    $surface = Mockery::mock(IdentifierAuditSurface::class);
    $surface->shouldReceive('all')->andReturn($groups);

    app()->instance(IdentifierAuditSurface::class, $surface);
}

/**
 * Run the audit and return its exit code and what it rendered.
 *
 * @return array{0: int, 1: string}
 */
function runIdentifierAuditWithStatus(): array
{
    $status = Artisan::call('identifiers:audit-duplicates');

    return [$status, Artisan::output()];
}

it('fails, and audits nothing, when no uniqueness rule is wired', function (): void {
    auditSurfaceOf([]);

    [$status, $output] = runIdentifierAuditWithStatus();

    expect($status)->toBe(1)
        ->and($output)->toContain('No uniqueness rules are wired in the blueprints, so nothing was audited.')
        ->and($output)->not->toContain('Audited');
});

it('prints the exact denominator and names each group, with SUCCESS on a clean run', function (): void {
    auditSurfaceOf([
        ['collection' => 'people', 'paths' => ['npi'], 'rowKey' => null, 'label' => 'people npi'],
        ['collection' => 'people', 'paths' => ['epic_ser_id'], 'rowKey' => null, 'label' => 'people epic_ser_id'],
        ['collection' => 'clinical_locations', 'paths' => [], 'rowKey' => 'dep', 'label' => 'clinical_locations list:dep'],
    ]);
    auditEntry('people', 'first', ['title' => 'Dana Reed', 'npi' => '1234567893', 'epic_ser_id' => 'S-1']);
    auditEntry('people', 'second', ['title' => 'Alex Stone', 'npi' => '1987654321']);
    auditEntry('clinical_locations', 'clinic', ['title' => 'Clinic', 'epic_dep_ids' => auditRows(['10502', '10503'], 'dep')]);

    [$status, $output] = runIdentifierAuditWithStatus();

    // 3 groups; people (2) and clinical_locations (1) are 3 distinct entries in 2
    // collections; people is read twice, so 5 reads; 2 + 1 + 2 values
    expect($status)->toBe(0)
        ->and($output)->toContain('Audited 3 identifier group(s) over 3 entries in 2 collection(s): 5 entry read(s), 5 identifier value(s).')
        ->and($output)->toContain("  - people npi\n  - people epic_ser_id\n  - clinical_locations list:dep\n")
        ->and($output)->toContain('No duplicate identifiers found.')
        // A clean run stops at the clean line: no table and no finding count
        ->and($output)->not->toContain('Kind')
        ->and($output)->not->toContain('duplicate identifier finding(s)');
});

it('reports each finding as a table row and counts them, still with SUCCESS', function (): void {
    auditSurfaceOf([
        ['collection' => 'people', 'paths' => ['npi'], 'rowKey' => null, 'label' => 'people npi'],
    ]);
    auditEntry('people', 'first', ['title' => 'Dana Reed', 'npi' => '1234567893']);
    auditEntry('people', 'second', ['title' => 'Alex Stone', 'npi' => '1234567893']);
    auditEntry('people', 'third', ['title' => 'Sam Lee', 'npi' => '1111111111']);

    [$status, $output] = runIdentifierAuditWithStatus();

    expect($status)->toBe(0)
        ->and($output)->toMatch('/\|\s*Kind\s*\|\s*Group\s*\|\s*Value\s*\|\s*Where\s*\|/')
        ->and($output)->toMatch('/\|\s*across entries\s*\|\s*people npi\s*\|\s*1234567893\s*\|\s*(Dana Reed \(npi\); Alex Stone \(npi\)|Alex Stone \(npi\); Dana Reed \(npi\))\s*\|/')
        ->and($output)->not->toContain('1111111111 ')
        ->and($output)->toContain('1 duplicate identifier finding(s).')
        ->and($output)->not->toContain('No duplicate identifiers found.')
        // The table is set off from the group list by one blank line
        ->and($output)->toContain("  - people npi\n\n+");
});

it('calls a value one entry holds in two pooled fields a within-entry duplicate', function (): void {
    auditSurfaceOf([
        ['collection' => 'people', 'paths' => ['workday.a', 'workday.b'], 'rowKey' => null, 'label' => 'people workday'],
    ]);
    auditEntry('people', 'first', ['title' => 'Dana Reed', 'workday' => ['a' => 'W-1', 'b' => 'w-1']]);

    [, $output] = runIdentifierAuditWithStatus();

    expect($output)->toMatch('/\|\s*within entry\s*\|\s*people workday\s*\|\s*W-1\s*\|\s*Dana Reed \(workday\.a\); Dana Reed \(workday\.b\)\s*\|/')
        ->and($output)->toContain('1 duplicate identifier finding(s).');
});

it('labels an entry with no usable title by its id', function (): void {
    auditSurfaceOf([
        ['collection' => 'people', 'paths' => ['npi'], 'rowKey' => null, 'label' => 'people npi'],
    ]);
    $first = auditEntry('people', 'first', ['title' => '   ', 'npi' => '1234567893']);
    auditEntry('people', 'second', ['title' => 'Alex Stone', 'npi' => '1234567893']);

    $id = $first->id();
    expect($id)->toBeString();

    [, $output] = runIdentifierAuditWithStatus();

    expect($output)->toContain((is_string($id) ? $id : '').' (npi)');
});

it('reads a list group from the field its row key names', function (string $rowKey, string $handle): void {
    auditSurfaceOf([
        ['collection' => 'clinical_locations', 'paths' => [], 'rowKey' => $rowKey, 'label' => "clinical_locations list:{$rowKey}"],
    ]);
    auditEntry('clinical_locations', 'clinic', ['title' => 'Clinic', $handle => auditRows(['10502', '10502'], $rowKey)]);

    [, $output] = runIdentifierAuditWithStatus();

    expect($output)->toContain('1 identifier value(s).')
        ->and($output)->toMatch("/\\|\\s*within entry\\s*\\|\\s*clinical_locations list:{$rowKey}\\s*\\|\\s*10502\\s*\\|\\s*Clinic\\s*\\|/")
        ->and($output)->toContain('1 duplicate identifier finding(s).');
})->with([
    'epic_dep_ids rows' => ['dep', 'epic_dep_ids'],
    'npi rows' => ['item', 'npi'],
    'any other row key, by its own name' => ['code', 'code'],
]);

it('reports a value repeated on several rows of one entry once, and skips blank and non-row values', function (): void {
    auditSurfaceOf([
        ['collection' => 'clinical_locations', 'paths' => [], 'rowKey' => 'dep', 'label' => 'clinical_locations list:dep'],
    ]);
    auditEntry('clinical_locations', 'clinic', ['title' => 'Clinic', 'epic_dep_ids' => [
        ['dep' => '10502'],
        ['dep' => ' 10502 '],
        ['dep' => '10502'],
        ['dep' => ''],
        ['dep' => '   '],
        ['other' => '7'],
        '20001',
        '20001',
    ]]);
    auditEntry('clinical_locations', 'empty', ['title' => 'Empty', 'epic_dep_ids' => 'not rows']);

    [, $output] = runIdentifierAuditWithStatus();

    expect(substr_count($output, 'within entry'))->toBe(2)
        ->and($output)->toMatch('/\|\s*within entry\s*\|\s*clinical_locations list:dep\s*\|\s*10502\s*\|\s*Clinic\s*\|/')
        ->and($output)->toMatch('/\|\s*within entry\s*\|\s*clinical_locations list:dep\s*\|\s*20001\s*\|\s*Clinic\s*\|/')
        ->and($output)->toContain('2 duplicate identifier finding(s).');
});

it('keeps looking past a value seen once for a later duplicate', function (): void {
    auditSurfaceOf([
        ['collection' => 'people', 'paths' => ['ids.a', 'ids.b', 'ids.c'], 'rowKey' => null, 'label' => 'people ids'],
    ]);
    // One entry, so the order values are seen in is the path order: the unique
    // value comes first, then the repeated one
    auditEntry('people', 'first', ['title' => 'Dana Reed', 'ids' => ['a' => 'UNIQUE-1', 'b' => 'D-2', 'c' => 'd-2']]);

    [, $output] = runIdentifierAuditWithStatus();

    expect($output)->toMatch('/\|\s*within entry\s*\|\s*people ids\s*\|\s*D-2\s*\|/')
        ->and($output)->toContain('1 duplicate identifier finding(s).');
});

it('reads nothing, without failing, for a list group with no row key', function (): void {
    auditSurfaceOf([
        ['collection' => 'people', 'paths' => [], 'rowKey' => null, 'label' => 'people list'],
    ]);
    auditEntry('people', 'first', ['title' => 'Dana Reed', 'npi' => '1234567893']);

    [$status, $output] = runIdentifierAuditWithStatus();

    expect($status)->toBe(0)
        ->and($output)->toContain('1 entry read(s), 0 identifier value(s).')
        ->and($output)->toContain('No duplicate identifiers found.');
});

it('does not treat a single-value field holding a list as repeating within itself', function (): void {
    auditSurfaceOf([
        ['collection' => 'people', 'paths' => ['tags'], 'rowKey' => null, 'label' => 'people tags'],
    ]);
    auditEntry('people', 'first', ['title' => 'Dana Reed', 'tags' => ['x', 'x']]);

    [, $output] = runIdentifierAuditWithStatus();

    expect($output)->toContain('0 identifier value(s).')
        ->and($output)->toContain('No duplicate identifiers found.');
});
