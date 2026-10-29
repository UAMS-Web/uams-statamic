<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaTemplatesFields;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(TestCase::class);

covers(SchemaTemplatesFields::class);

/**
 * Pins the Schema Templates CP blueprint contents whole.
 *
 * The fixture holds each builder's output as `main` produced it at
 * `c6ffa9440`, in the testing environment (locale `en`, entries collection
 * `schema_objects`). A strict comparison of the whole array kills every
 * changed label, option, default, flag and field order. The remaining cases
 * pin the configured collection, its fallback, and `toBlueprint()`.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaTemplatesFieldsTest.php
 */

/**
 * Read the pinned blueprint contents, keyed by builder call.
 *
 * @return array<string, array<string, mixed>> Pinned contents per call.
 */
function pinnedSchemaTemplatesFields(): array
{
    $path = __DIR__.'/../../../Fixtures/SchemaTemplates/schema-templates-fields.json';
    $json = file_get_contents($path);

    if ($json === false) {
        throw new RuntimeException('Cannot read the pinned fixture: '.$path);
    }

    /** @var array<string, array<string, mixed>> $decoded */
    $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
}

/**
 * Collect the `collections` setting of every entries field in some contents.
 *
 * @param  array<string, mixed>  $contents  Blueprint contents from a builder.
 * @return list<mixed> One `collections` value per entries field, in order.
 */
function schemaTemplatesEntriesCollections(array $contents): array
{
    $found = [];

    /** @var list<array{field: array<string, mixed>}> $fields */
    $fields = data_get($contents, 'tabs.main.sections.0.fields');

    foreach ($fields as $field) {
        if ($field['field']['type'] === 'entries') {
            $found[] = $field['field']['collections'];
        }
    }

    return $found;
}

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

it('builds the pinned site defaults contents', function (): void {
    expect(SchemaTemplatesFields::siteDefaults())->toBe(pinnedSchemaTemplatesFields()['siteDefaults']);
});

it('builds the pinned section defaults contents', function (bool $siteDisabled): void {
    $key = 'sectionDefaults('.($siteDisabled ? 'true' : 'false').')';

    expect(SchemaTemplatesFields::sectionDefaults($siteDisabled))->toBe(pinnedSchemaTemplatesFields()[$key]);
})->with([
    'site enabled' => false,
    'site disabled' => true,
]);

it('builds the pinned blueprint defaults contents', function (bool $sectionDisabled): void {
    $key = 'blueprintDefaults('.($sectionDisabled ? 'true' : 'false').')';

    expect(SchemaTemplatesFields::blueprintDefaults($sectionDisabled))->toBe(pinnedSchemaTemplatesFields()[$key]);
})->with([
    'section enabled' => false,
    'section disabled' => true,
]);

it('points every entries field at the configured collection', function (): void {
    Config::set('schema_object.entries_collection', 'other_objects');

    expect(schemaTemplatesEntriesCollections(SchemaTemplatesFields::siteDefaults()))
        ->toBe(array_fill(0, 4, ['other_objects']))
        ->and(schemaTemplatesEntriesCollections(SchemaTemplatesFields::sectionDefaults(false)))
        ->toBe(array_fill(0, 2, ['other_objects']))
        ->and(schemaTemplatesEntriesCollections(SchemaTemplatesFields::blueprintDefaults(false)))
        ->toBe(array_fill(0, 2, ['other_objects']));
});

it('falls back to the schema_objects collection when none is configured', function (): void {
    /** @var array<string, mixed> $schemaObject */
    $schemaObject = config('schema_object');
    unset($schemaObject['entries_collection']);
    Config::set('schema_object', $schemaObject);

    expect(config()->has('schema_object.entries_collection'))->toBeFalse()
        ->and(schemaTemplatesEntriesCollections(SchemaTemplatesFields::siteDefaults()))
        ->toBe(array_fill(0, 4, ['schema_objects']));
});

it('wraps contents in a blueprint that keeps them', function (): void {
    $contents = SchemaTemplatesFields::siteDefaults();
    $blueprint = SchemaTemplatesFields::toBlueprint($contents);

    expect($blueprint->contents())->toBe($contents)
        ->and($blueprint->fields()->all()->keys()->all())
        ->toBe(['enabled', 'subject_entry', 'website_entry', 'webpage_entry', 'main_content_entry']);
});
