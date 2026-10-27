<?php

declare(strict_types=1);

use App\Tags\SchemaObject;
use Statamic\Facades\Antlers;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_object` Antlers tag.
 *
 * Mutation: 100% on App\Tags\SchemaObject (52 mutants, --covered-only; every line covered).
 * Nine equivalents in the layered type guards are annotated in the class. A 53rd mutant, on
 * the `$aliases` default, is not run under --covered-only, since a property default is not
 * an executable line.
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaObjectTest.php
 */
it('emits person with bound properties', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object type="Person" :properties="person" }}',
        [
            'person' => ['name' => 'John Smith'],
        ],
        true
    ));

    expect($out)->toBe('{"@type":"https://schema.org/Person","name":"John Smith"}');
});

it('emits full type uri', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object type="https://schema.org/Person" :properties="person" }}',
        [
            'person' => ['name' => 'John Smith'],
        ],
        true
    ));

    expect($out)->toBe('{"@type":"https://schema.org/Person","name":"John Smith"}');
});

it('emits organization', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object type="Organization" :properties="org" }}',
        [
            'org' => ['name' => 'Acme Inc.'],
        ],
        true
    ));

    expect($out)->toBe('{"@type":"https://schema.org/Organization","name":"Acme Inc."}');
});

it('exposes schema_object as the handle', function () {
    expect(SchemaObject::handle())->toBe('schema_object');
});

it('includes schema_object_tag in the aliases', function () {
    expect(SchemaObject::aliases())->toBe(['schema_object_tag']);
});

/**
 * Context may include the `schema_object` entries field; that variable shadows the
 * primary tag handle so Antlers treats `type` as a modifier. The alias avoids that.
 */
it('emits via the schema_object_tag alias when a schema_object field is in context', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object_tag type="Person" :properties="person" }}',
        [
            'schema_object' => '00000000-0000-0000-0000-000000000001',
            'person' => ['name' => 'Jane Doe'],
        ],
        true
    ));

    expect($out)->toBe('{"@type":"https://schema.org/Person","name":"Jane Doe"}');
});

it('throws when a schema_object context variable shadows the primary handle', function () {
    expect(fn () => Antlers::parse(
        '{{ schema_object type="Person" :properties="person" }}',
        [
            'schema_object' => '00000000-0000-0000-0000-000000000001',
            'person' => ['name' => 'Jane Doe'],
        ],
        true
    ))->toThrow('Modifier [type] not found');
});

it('outputs an empty string when properties are empty', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object type="Person" :properties="props" }}',
        [
            'props' => [],
        ],
        true
    ));

    expect($out)->toBe('');
});

it('emits a bound properties array', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object type="QuantitativeValue" :properties="dims" }}',
        [
            'dims' => [
                'value' => 1080,
                'unitCode' => 'E37',
                'unitText' => 'pixel',
            ],
        ],
        true
    ));

    expect($out)->toBe('{"@type":"https://schema.org/QuantitativeValue","value":1080,"unitCode":"E37","unitText":"pixel"}');
});

it('decodes json string properties from context', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object type="QuantitativeValue" :properties="props_json" }}',
        [
            'props_json' => '{"value":720,"unitText":"px"}',
        ],
        true
    ));

    expect($out)->toBe('{"@type":"https://schema.org/QuantitativeValue","value":720,"unitText":"px"}');
});

it('outputs empty when enabled is the literal false', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object type="Person" :properties="person" enabled="false" }}',
        [
            'person' => ['name' => 'John Smith'],
        ],
        true
    ));

    expect($out)->toBe('');
});

it('outputs json when the enabled binding is truthy', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object type="QuantitativeValue" :properties="dims" :enabled="height" }}',
        [
            'dims' => [
                'value' => 1080,
                'unitCode' => 'E37',
                'unitText' => 'pixel',
            ],
            'height' => 1080,
        ],
        true
    ));

    expect($out)->toBe('{"@type":"https://schema.org/QuantitativeValue","value":1080,"unitCode":"E37","unitText":"pixel"}');
});

it('outputs empty when the enabled binding is falsy', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object type="QuantitativeValue" :properties="dims" :enabled="height" }}',
        [
            'dims' => [
                'value' => 1080,
                'unitCode' => 'E37',
                'unitText' => 'pixel',
            ],
            'height' => null,
        ],
        true
    ));

    expect($out)->toBe('');
});

/**
 * Bound `type` from context (string) emits the same JSON as a literal type.
 */
it('emits when type is bound from context', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object :type="schema_type" :properties="person" }}',
        [
            'schema_type' => 'Person',
            'person' => ['name' => 'Jane Doe'],
        ],
        true
    ));

    expect($out)->toBe('{"@type":"https://schema.org/Person","name":"Jane Doe"}');
});

/**
 * Whitespace-only `type` yields empty output.
 */
it('outputs empty when type is whitespace only', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_object type="   " :properties="person" }}',
        [
            'person' => ['name' => 'John Smith'],
        ],
        true
    ));

    expect($out)->toBe('');
});

/**
 * The tag's output for the given parameters.
 *
 * @param  array<mixed>  $parameters
 */
function schemaObjectTag(array $parameters): string
{
    $tag = new SchemaObject;
    $tag->setContext([]);
    $tag->setParameters($parameters);

    return $tag->index();
}

/**
 * The tag's output for params built without Parameters::make().
 *
 * make(), the route setParameters() takes, already unwraps a top-level Value, so the
 * tag's own unwraps of `type` and `enabled` are reached only by params built directly.
 *
 * @param  array<string, mixed>  $parameters
 */
function schemaObjectTagFromParams(array $parameters): string
{
    $tag = new SchemaObject;
    $tag->setContext([]);
    $tag->params = new Parameters($parameters);

    return $tag->index();
}

it('emits nothing for an absent, empty, false or non-string type', function (mixed $type) {
    expect(schemaObjectTag(['type' => $type, 'properties' => ['name' => 'X']]))->toBe('');
})->with([
    'null' => [null],
    'empty' => [''],
    'false' => [false],
    'whitespace' => ['   '],
    'integer' => [5],
    'array' => [['Person']],
]);

it('unwraps a type and an enabled flag passed inside a Value', function () {
    expect(schemaObjectTagFromParams([
        'type' => new Value('Person'),
        'properties' => ['name' => 'X'],
        'enabled' => new Value('yes'),
    ]))->toBe('{"@type":"https://schema.org/Person","name":"X"}')
        ->and(schemaObjectTagFromParams([
            'type' => 'Person',
            'properties' => ['name' => 'X'],
            'enabled' => new Value(false),
        ]))->toBe('');
});

it('emits nothing when enabled is off', function (mixed $enabled) {
    expect(schemaObjectTag(['type' => 'Person', 'properties' => ['name' => 'X'], 'enabled' => $enabled]))->toBe('');
})->with([
    'false' => [false],
    'null' => [null],
    'empty string' => [''],
    'whitespace' => ['   '],
    'integer zero' => [0],
    'float zero' => [0.0],
    'string zero' => ['0'],
]);

it('emits when enabled is on', function (mixed $enabled) {
    expect(schemaObjectTag(['type' => 'Person', 'properties' => ['name' => 'X'], 'enabled' => $enabled]))
        ->toBe('{"@type":"https://schema.org/Person","name":"X"}');
})->with([
    'true' => [true],
    'positive integer' => [120],
    'float' => [0.5],
    'text' => ['yes'],
    'string with a zero' => ['10'],
]);

it('emits nothing when the builder produces no fragment', function () {
    expect(schemaObjectTag(['type' => 'Person', 'properties' => 'not json']))->toBe('');
});
