<?php

declare(strict_types=1);

use App\Enums\SchemaType;
use App\Rules\EntrySchemaTypeIn;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

covers(EntrySchemaTypeIn::class);

beforeEach(function (): void {
    // Create the collection the fixture entries are saved into
    Collection::make('organizations')->save();
});

/**
 * Behavior of the `EntrySchemaTypeIn` rule: each selected entry must exist and
 * carry a `schema.type` that is one of the given root types or a descendant of
 * one. The first entry that is missing or carries another type fails, naming the
 * type it found and the types allowed; empty selections are left to `required`.
 *
 * @command  php artisan test --compact tests/Feature/Rules/EntrySchemaTypeInTest.php
 */

/**
 * Persist an entry whose `schema` field holds the given value.
 */
function entrySchemaTypeInEntry(string $id, mixed $schema): void
{
    /** @var EntryItem $entry */
    $entry = Entry::make();
    $entry->collection('organizations');
    $entry->locale('default');
    $entry->id($id);
    $entry->slug($id);
    $entry->data(['title' => $id, 'schema' => $schema]);
    $entry->saveQuietly();
}

/**
 * Run the rule and return every message it failed with.
 *
 * @return list<mixed>
 */
function entrySchemaTypeInMessages(EntrySchemaTypeIn $rule, mixed $value): array
{
    $messages = [];

    $rule->validate('location', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

/**
 * The `allowed` list the rule names for a `MedicalOrganization` root.
 */
function entrySchemaTypeInMedicalAllowed(): string
{
    return implode(', ', ['MedicalOrganization', ...SchemaType::descendantsOf('MedicalOrganization')]);
}

/**
 * The message the rule fails with for a type outside the allowed list.
 */
function entrySchemaTypeInNotAllowed(string $type, string $allowed): string
{
    return __('statamic::validation.entry_schema_type_in.not_allowed', ['type' => $type, 'allowed' => $allowed]);
}

it('accepts an entry of a root type or one of its descendants', function (mixed $value): void {
    entrySchemaTypeInEntry('root', ['type' => 'MedicalOrganization']);
    entrySchemaTypeInEntry('hospital', ['type' => 'Hospital']);
    entrySchemaTypeInEntry('several', ['type' => ['Place', 'Hospital']]);
    entrySchemaTypeInEntry('with-junk', ['type' => ['Hospital', 5, '']]);

    expect(entrySchemaTypeInMessages(new EntrySchemaTypeIn('MedicalOrganization'), $value))->toBe([]);
})->with([
    'the root itself' => ['root'],
    'a descendant' => ['hospital'],
    'a list of ids' => [['root', 'hospital']],
    'one allowed type among several' => ['several'],
    'non-string types beside an allowed one' => ['with-junk'],
    'a list with an empty id' => [['', 'hospital']],
]);

it('leaves an empty selection to required', function (mixed $value): void {
    expect(entrySchemaTypeInMessages(new EntrySchemaTypeIn('MedicalOrganization'), $value))->toBe([]);
})->with([
    'null' => [null],
    'empty string' => [''],
    'empty list' => [[]],
    'list of empties' => [['', null]],
]);

it('fails an id with no entry, naming the id', function (mixed $value, string $id): void {
    expect(entrySchemaTypeInMessages(new EntrySchemaTypeIn('MedicalOrganization'), $value))
        ->toBe([__('statamic::validation.entry_schema_type_in.not_found', ['id' => $id])]);
})->with([
    'a string id' => ['missing', 'missing'],
    'an integer id' => [[7], '7'],
    'an id that is not a scalar' => [[['x']], ''],
]);

it('fails an entry of another type, naming its type and the types allowed', function (mixed $schema, string $type): void {
    entrySchemaTypeInEntry('other', $schema);

    expect(entrySchemaTypeInMessages(new EntrySchemaTypeIn('MedicalOrganization'), 'other'))
        ->toBe([entrySchemaTypeInNotAllowed($type, entrySchemaTypeInMedicalAllowed())]);
})->with([
    'a single type' => [['type' => 'Person'], 'Person'],
    'a list of types' => [['type' => ['Person', 'Place']], 'Person, Place'],
    'a list holding a non-scalar' => [['type' => [['x'], 'Person']], ', Person'],
    'a list holding a number' => [['type' => ['Person', 3]], 'Person, 3'],
    'a numeric type' => [['type' => 5], '5'],
    'an empty type' => [['type' => ''], ''],
    'an empty list of types' => [['type' => []], ''],
    'a non-scalar type' => [['type' => (object) []], ''],
    'no type key' => [['name' => 'Clinic'], ''],
    'a schema that is not an array' => ['MedicalOrganization', ''],
]);

it('stops at the first entry that fails', function (): void {
    entrySchemaTypeInEntry('person', ['type' => 'Person']);
    entrySchemaTypeInEntry('hospital', ['type' => 'Hospital']);

    expect(entrySchemaTypeInMessages(new EntrySchemaTypeIn('MedicalOrganization'), ['hospital', 'missing', 'person']))
        ->toBe([__('statamic::validation.entry_schema_type_in.not_found', ['id' => 'missing'])]);
});

it('allows each root once, skipping empty roots, with its descendants after it', function (): void {
    entrySchemaTypeInEntry('person', ['type' => 'Person']);
    entrySchemaTypeInEntry('hospital', ['type' => 'Hospital']);

    $rule = new EntrySchemaTypeIn('Hospital', '', 'MedicalOrganization');

    expect(entrySchemaTypeInMessages($rule, 'hospital'))->toBe([]);
    expect(entrySchemaTypeInMessages($rule, 'person'))->toBe([entrySchemaTypeInNotAllowed(
        'Person',
        implode(', ', ['Hospital', 'MedicalOrganization', ...array_values(array_diff(SchemaType::descendantsOf('MedicalOrganization'), ['Hospital']))]),
    )]);
});

it('allows nothing when given no roots', function (): void {
    entrySchemaTypeInEntry('hospital', ['type' => 'Hospital']);

    expect(entrySchemaTypeInMessages(new EntrySchemaTypeIn, 'hospital'))
        ->toBe([entrySchemaTypeInNotAllowed('Hospital', '')]);
});
