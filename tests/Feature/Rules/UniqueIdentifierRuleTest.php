<?php

declare(strict_types=1);

use App\Rules\UniqueIdentifier;
use App\Rules\UniqueIdentifierList;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Validator;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Behavior of the two identifier-uniqueness rules (UAMS-Web/uams-statamic-archive#2152).
 *
 * `UniqueIdentifier` covers the single-value fields on `people` and enforces the
 * pooled Workday namespace, where one value must not appear in any of the four
 * Workday fields on any entry -- including the entry being saved.
 * `UniqueIdentifierList` covers the two `clinical_locations` replicator lists and
 * enforces uniqueness both within a list and across entries.
 *
 * The cases below are the ones the ticket's acceptance criteria name, plus the
 * two that a broad rule breaks first: blank never colliding with blank, and the
 * MyChart scheduling widget's `dep` field, which is REQUIRED to repeat the
 * location's declared DEP IDs and must therefore never carry a uniqueness rule.
 *
 * @command  php artisan test --compact tests/Feature/Rules/UniqueIdentifierRuleTest.php
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
 * @param  string  $collection  Collection handle
 * @param  string  $slug  Entry slug
 * @param  array<string, mixed>  $data  Field values
 * @return ConcreteEntry The saved entry
 */
function identifierEntry(string $collection, string $slug, array $data): ConcreteEntry
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
 * An entry's id as a string, narrowed for the analyser.
 *
 * @param  ConcreteEntry  $entry  A saved entry
 * @return string The id
 */
function entryIdString(ConcreteEntry $entry): string
{
    $id = $entry->id();

    return \is_scalar($id) ? (string) $id : '';
}

/**
 * The four Workday field handles, in the order the blueprint declares them.
 *
 * @return list<string>
 */
function workdayPool(): array
{
    return [
        'uams_workday_id.workday_universal_id',
        'uams_workday_id.workday_employee_id',
        'uams_workday_id.workday_contingent_worker_id',
        'uams_workday_id.workday_student_id',
    ];
}

/**
 * Wrap Workday values in the `uams_workday_id` group they are stored under.
 *
 * `person.yaml` puts all four inside a `group` fieldtype, so both the stored
 * data and the validated attribute are nested. Building fixtures flat is what
 * made an earlier version of these tests pass against a rule that read a
 * top-level path and therefore found nothing on any real entry.
 *
 * @param  array<string, string>  $values  Bare handle => value
 * @return array<string, array<string, string>>
 */
function workdayGroup(array $values): array
{
    return ['uams_workday_id' => $values];
}

/**
 * Build `epic_dep_ids`-shaped replicator rows.
 *
 * @param  array<int, string>  $values
 * @return array<int, array<string, mixed>>
 */
function identifierRows(array $values, string $key): array
{
    return array_map(
        static fn (string $v): array => ['type' => 'new_set', 'enabled' => true, $key => $v],
        $values,
    );
}

// ---
// Single-value fields: the pooled Workday namespace and the plain identifiers
// ---

it('blocks a value already held by another entry, naming the value, the field and the entry', function (): void {
    identifierEntry('people', 'other-person', ['title' => 'Dana Reed', 'npi' => '1234567893']);

    $validator = Validator::make(
        ['npi' => '1234567893'],
        ['npi' => [new UniqueIdentifier('people', null, 'npi')]],
    );

    expect($validator->fails())->toBeTrue();

    $message = $validator->errors()->first('npi');

    expect($message)
        ->toContain('1234567893')
        ->toContain('npi')
        ->toContain('Dana Reed');
});

it('blocks a Workday value held in a DIFFERENT Workday field on another entry', function (): void {
    // The pooled namespace is the whole point: a per-field check passes this
    identifierEntry('people', 'other-person', [
        'title' => 'Dana Reed',
    ] + workdayGroup(['workday_employee_id' => 'W-4471']));

    $attribute = 'uams_workday_id.workday_universal_id';

    $validator = Validator::make(
        workdayGroup(['workday_universal_id' => 'W-4471']),
        [$attribute => [new UniqueIdentifier('people', null, ...workdayPool())]],
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first($attribute))
        ->toContain('W-4471')
        ->toContain('workday_employee_id')
        ->toContain('Dana Reed');
});

it('blocks the same value entered twice in one entry\'s own Workday fields', function (): void {
    // No other entry exists at all -- the collision is entirely within this payload
    $attribute = 'uams_workday_id.workday_universal_id';

    $validator = Validator::make(
        workdayGroup([
            'workday_universal_id' => 'W-4471',
            'workday_employee_id' => 'W-4471',
        ]),
        [$attribute => [new UniqueIdentifier('people', null, ...workdayPool())]],
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first($attribute))
        ->toContain('W-4471')
        ->toContain('workday_employee_id');
});

it('lets an entry keep four DIFFERENT Workday values without colliding with itself', function (): void {
    $group = [
        'workday_universal_id' => 'W-1',
        'workday_employee_id' => 'W-2',
        'workday_contingent_worker_id' => 'W-3',
        'workday_student_id' => 'W-4',
    ];

    $entry = identifierEntry('people', 'subject', ['title' => 'Subject'] + workdayGroup($group));

    $validator = Validator::make(
        workdayGroup($group),
        ['uams_workday_id.workday_universal_id' => [new UniqueIdentifier('people', entryIdString($entry), ...workdayPool())]],
    );

    expect($validator->passes())->toBeTrue();
});

it('does not report a collision with the entry being edited', function (): void {
    $entry = identifierEntry('people', 'subject', ['title' => 'Subject', 'npi' => '1234567893']);

    $validator = Validator::make(
        ['npi' => '1234567893'],
        ['npi' => [new UniqueIdentifier('people', entryIdString($entry), 'npi')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('treats the NULL placeholder Statamic substitutes on a create as excluding nothing', function (): void {
    // `{id}` has no replacement on a create, so the parameter arrives as the
    // literal string NULL. Read as an id it would exclude nothing anyway; read
    // as "no exclusion" it is correct, and this pins the latter.
    identifierEntry('people', 'other-person', ['title' => 'Dana Reed', 'npi' => '1234567893']);

    $validator = Validator::make(
        ['npi' => '1234567893'],
        ['npi' => [new UniqueIdentifier('people', 'NULL', 'npi')]],
    );

    expect($validator->fails())->toBeTrue();
});

it('never collides blank with blank, however many entries are blank', function (): void {
    foreach (range(1, 5) as $n) {
        identifierEntry('people', "blank-{$n}", ['title' => "Blank {$n}", 'npi' => '', 'epic_ser_id' => null]);
    }

    $validator = Validator::make(
        ['npi' => '', 'epic_ser_id' => '   '],
        [
            'npi' => [new UniqueIdentifier('people', null, 'npi')],
            'epic_ser_id' => [new UniqueIdentifier('people', null, 'epic_ser_id')],
        ],
    );

    expect($validator->passes())->toBeTrue();
});

it('compares case-insensitively with surrounding whitespace trimmed', function (): void {
    identifierEntry('people', 'other-person', ['title' => 'Dana Reed'] + workdayGroup(['workday_employee_id' => 'w-4471']));

    $validator = Validator::make(
        workdayGroup(['workday_universal_id' => '  W-4471  ']),
        ['uams_workday_id.workday_universal_id' => [new UniqueIdentifier('people', null, ...workdayPool())]],
    );

    expect($validator->fails())->toBeTrue();
});

it('scopes the scan to one collection', function (): void {
    // A location holding the same string is not a people collision
    identifierEntry('clinical_locations', 'a-location', ['title' => 'Clinic', 'npi' => '1234567893']);

    $validator = Validator::make(
        ['npi' => '1234567893'],
        ['npi' => [new UniqueIdentifier('people', null, 'npi')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects an empty collection handle and an empty pool in the constructor', function (): void {
    expect(fn () => new UniqueIdentifier('  ', null, 'npi'))->toThrow(InvalidArgumentException::class);
    expect(fn () => new UniqueIdentifier('people', null, '  '))->toThrow(InvalidArgumentException::class);
});

it('throws when a pooled check runs without a validator set', function (): void {
    $rule = new UniqueIdentifier('people', null, ...workdayPool());

    expect(fn () => $rule->validate('workday_universal_id', 'W-1', static function (): void {}))
        ->toThrow(LogicException::class);
});

it('reads a grouped identifier at its real nested path, not at the top level', function (): void {
    // The regression guard for the defect an adversarial pass found: reading
    // `workday_universal_id` at the top level returns null on EVERY real entry,
    // because person.yaml nests all four inside a `uams_workday_id` group. The
    // check then cannot fire, and nothing about a passing save says so.
    identifierEntry('people', 'other-person', [
        'title' => 'Dana Reed',
    ] + workdayGroup(['workday_universal_id' => '400026157']));

    // Control: the value is genuinely NOT at the top level on that entry
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();

    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', 'people');

    /** @var SupportCollection<int, mixed> $found */
    $found = $query->get();

    $stored = $found->first();

    expect($stored)->toBeInstanceOf(ConcreteEntry::class);

    /** @var ConcreteEntry $stored */
    expect($stored->get('workday_universal_id'))->toBeNull();
    expect($stored->get('uams_workday_id'))->toBeArray();

    $validator = Validator::make(
        workdayGroup(['workday_universal_id' => '400026157']),
        ['uams_workday_id.workday_universal_id' => [new UniqueIdentifier('people', null, ...workdayPool())]],
    );

    expect($validator->fails())->toBeTrue();
});

// ---
// Replicator list fields
// ---

it('blocks a repeated NPI within one location\'s own list', function (): void {
    $validator = Validator::make(
        ['npi' => identifierRows(['1234567893', '1234567893'], 'item')],
        ['npi' => [new UniqueIdentifierList('clinical_locations', null, 'item')]],
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('npi'))->toContain('1234567893');
});

it('blocks a repeated DEP ID within one location\'s own list', function (): void {
    $validator = Validator::make(
        ['epic_dep_ids' => identifierRows(['10502', '10502'], 'dep')],
        ['epic_dep_ids' => [new UniqueIdentifierList('clinical_locations', null, 'dep')]],
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('epic_dep_ids'))->toContain('10502');
});

it('blocks an NPI already held by another location, naming the entry', function (): void {
    identifierEntry('clinical_locations', 'other-clinic', [
        'title' => 'Other Clinic',
        'npi' => identifierRows(['1234567893'], 'item'),
    ]);

    $validator = Validator::make(
        ['npi' => identifierRows(['1234567893'], 'item')],
        ['npi' => [new UniqueIdentifierList('clinical_locations', null, 'item')]],
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('npi'))
        ->toContain('1234567893')
        ->toContain('Other Clinic');
});

it('blocks a DEP ID already declared by another location, naming the entry', function (): void {
    identifierEntry('clinical_locations', 'other-clinic', [
        'title' => 'Other Clinic',
        'epic_dep_ids' => identifierRows(['10502'], 'dep'),
    ]);

    $validator = Validator::make(
        ['epic_dep_ids' => identifierRows(['10502'], 'dep')],
        ['epic_dep_ids' => [new UniqueIdentifierList('clinical_locations', null, 'dep')]],
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('epic_dep_ids'))
        ->toContain('10502')
        ->toContain('Other Clinic');
});

it('does not report a list collision with the location being edited', function (): void {
    $entry = identifierEntry('clinical_locations', 'subject', [
        'title' => 'Subject Clinic',
        'epic_dep_ids' => identifierRows(['10502', '10503'], 'dep'),
    ]);

    $validator = Validator::make(
        ['epic_dep_ids' => identifierRows(['10502', '10503'], 'dep')],
        ['epic_dep_ids' => [new UniqueIdentifierList('clinical_locations', entryIdString($entry), 'dep')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('never collides blank list rows with each other', function (): void {
    identifierEntry('clinical_locations', 'other-clinic', [
        'title' => 'Other Clinic',
        'epic_dep_ids' => identifierRows(['', '   '], 'dep'),
    ]);

    $validator = Validator::make(
        ['epic_dep_ids' => identifierRows(['', '  '], 'dep')],
        ['epic_dep_ids' => [new UniqueIdentifierList('clinical_locations', null, 'dep')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('passes a list field holding no rows at all', function (): void {
    $validator = Validator::make(
        ['epic_dep_ids' => null],
        ['epic_dep_ids' => [new UniqueIdentifierList('clinical_locations', null, 'dep')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects an empty collection handle and an empty row key in the constructor', function (): void {
    expect(fn () => new UniqueIdentifierList('  ', null, 'dep'))->toThrow(InvalidArgumentException::class);
    expect(fn () => new UniqueIdentifierList('clinical_locations', null, '  '))->toThrow(InvalidArgumentException::class);
});
