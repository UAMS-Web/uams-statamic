<?php

declare(strict_types=1);

use App\Tags\SchemaAdministrativeAreas;
use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_administrative_areas` Antlers tag.
 *
 * Term-object resolution is covered end-to-end against the real `regions`
 * taxonomy in SchemaJsonLdLocationTest.
 *
 * Mutation: 100% on App\Tags\SchemaAdministrativeAreas (83 mutants, --covered-only), with
 * five equivalents annotated in the class. One line is not covered: the return for an
 * unusable AdministrativeArea type, since normalizedType('AdministrativeArea') always
 * returns a URI.
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaAdministrativeAreasTest.php
 */
function renderAreas(mixed $areas): mixed
{
    return json_decode(trim((string) Antlers::parse(
        '{{ schema_administrative_areas :areas="areas" }}',
        ['areas' => $areas],
        true
    )), true);
}

it('exposes schema_administrative_areas as the tag handle', function (): void {
    expect(SchemaAdministrativeAreas::handle())->toBe('schema_administrative_areas');
});

it('emits an AdministrativeArea node from a plain string name', function (): void {
    expect(renderAreas(['Central Arkansas']))->toBe([
        ['@type' => 'https://schema.org/AdministrativeArea', 'name' => 'Central Arkansas'],
    ]);
});

it('emits a node from a single (non-list) string', function (): void {
    expect(renderAreas('Central Arkansas'))->toBe([
        ['@type' => 'https://schema.org/AdministrativeArea', 'name' => 'Central Arkansas'],
    ]);
});

it('reads the title from an augmented term row', function (): void {
    $areas = [['id' => 'central-arkansas', 'title' => 'Central Arkansas', 'slug' => 'central-arkansas']];

    expect(renderAreas($areas))->toBe([
        ['@type' => 'https://schema.org/AdministrativeArea', 'name' => 'Central Arkansas'],
    ]);
});

it('reads the label from a LabeledValue', function (): void {
    expect(renderAreas([new LabeledValue('central-arkansas', 'Central Arkansas')]))->toBe([
        ['@type' => 'https://schema.org/AdministrativeArea', 'name' => 'Central Arkansas'],
    ]);
});

it('strips tags from the name for defense in depth', function (): void {
    expect(renderAreas(['<b>Central</b> Arkansas']))->toBe([
        ['@type' => 'https://schema.org/AdministrativeArea', 'name' => 'Central Arkansas'],
    ]);
});

it('de-duplicates repeated area names', function (): void {
    expect(renderAreas(['Central Arkansas', 'Central Arkansas']))->toHaveCount(1);
});

it('emits nothing for an empty list', function (): void {
    expect(trim((string) Antlers::parse(
        '{{ schema_administrative_areas :areas="areas" }}',
        ['areas' => []],
        true
    )))->toBe('');
});

it('emits nothing for a null value', function (): void {
    expect(trim((string) Antlers::parse(
        '{{ schema_administrative_areas :areas="areas" }}',
        ['areas' => null],
        true
    )))->toBe('');
});

it('skips blank names', function (): void {
    expect(renderAreas(['Central Arkansas', '   ']))->toBe([
        ['@type' => 'https://schema.org/AdministrativeArea', 'name' => 'Central Arkansas'],
    ]);
});

/**
 * The tag's raw output for params built without Parameters::make().
 *
 * make(), the route setParameters() takes, already unwraps a top-level Value, so the
 * tag's own unwrap of `areas` is reached only by params built directly.
 *
 * @param  array<string, mixed>  $parameters
 */
function areasFromParams(array $parameters): string
{
    $tag = new SchemaAdministrativeAreas;
    $tag->setContext([]);
    $tag->params = new Parameters($parameters);

    return $tag->index();
}

/**
 * A term-like object exposing `title()`.
 */
function areaTerm(mixed $title): object
{
    return new class($title)
    {
        public function __construct(private mixed $title) {}

        public function title(): mixed
        {
            return $this->title;
        }
    };
}

/**
 * One AdministrativeArea node per name, as the tag emits them.
 *
 * @param  list<string>  $names
 * @return list<array{'@type': string, name: string}>
 */
function areaNodes(array $names): array
{
    return array_map(
        fn (string $name): array => ['@type' => 'https://schema.org/AdministrativeArea', 'name' => $name],
        $names
    );
}

it('unwraps an areas list passed inside a Value', function (): void {
    expect(areasFromParams(['areas' => new Value(['Central Arkansas'])]))
        ->toBe('[{"@type":"https://schema.org/AdministrativeArea","name":"Central Arkansas"}]');
});

it('reads the items of a collection, as the schema engine passes a terms field', function (): void {
    expect(renderAreas(collect(['a' => 'Central Arkansas', 'b' => 'Northwest Arkansas'])))
        ->toBe(areaNodes(['Central Arkansas', 'Northwest Arkansas']));
});

it('wraps a single term object into a one-element list and reads its title', function (): void {
    expect(areasFromParams(['areas' => areaTerm('  Central Arkansas  ')]))
        ->toBe('[{"@type":"https://schema.org/AdministrativeArea","name":"Central Arkansas"}]');
});

it('reads names from Value-wrapped elements and term objects in a list', function (): void {
    expect(renderAreas([new Value('Central Arkansas'), areaTerm('Northwest Arkansas')]))
        ->toBe(areaNodes(['Central Arkansas', 'Northwest Arkansas']));
});

it('falls back from title to label to value in an augmented row', function (): void {
    expect(renderAreas([
        ['label' => 'Central Arkansas', 'value' => 'ignored'],
        ['value' => 'Northwest Arkansas'],
        ['title' => 42],
        ['other' => 'no name'],
    ]))->toBe(areaNodes(['Central Arkansas', 'Northwest Arkansas']));
});

it('falls back to the stored value of a LabeledValue with no label', function (): void {
    expect(renderAreas([new LabeledValue('Central Arkansas', null)]))->toBe(areaNodes(['Central Arkansas']));
});

it('skips elements whose name is not text, and elements of any other type', function (): void {
    expect(renderAreas([areaTerm(null), areaTerm(5), 42, true, 'Central Arkansas']))->toBe(areaNodes(['Central Arkansas']));
});

it('emits nothing when every element is skipped', function (): void {
    expect(areasFromParams(['areas' => [42, areaTerm(null), '   ']]))->toBe('');
});

it('emits nothing for a blank string or a scalar that is not a name', function (mixed $areas): void {
    expect(areasFromParams(['areas' => $areas]))->toBe('');
})->with([
    'blank string' => ['   '],
    'integer' => [5],
    'object without a title' => [new stdClass],
]);

it('keeps non-ASCII characters and slashes unescaped', function (): void {
    expect(areasFromParams(['areas' => ['Café/Area']]))
        ->toBe('[{"@type":"https://schema.org/AdministrativeArea","name":"Café/Area"}]');
});

it('emits nothing when the nodes cannot be encoded as JSON', function (): void {
    expect(areasFromParams(['areas' => ["Bad\xB1Name"]]))->toBe('');
});

it('keeps reading after a duplicate name', function (): void {
    expect(renderAreas(['Central Arkansas', 'Central Arkansas', 'Northwest Arkansas']))
        ->toBe(areaNodes(['Central Arkansas', 'Northwest Arkansas']));
});

// No mutant targets this: the mutate plugin never flips in_array()'s strict flag. It pins
// exact-match de-duplication as behavior.
it('de-duplicates by exact name, so names that only compare equal loosely both stay', function (): void {
    expect(renderAreas(['1', '01']))->toBe(areaNodes(['1', '01']));
});

it('reads a single augmented row as one area, not one per field (#2988)', function (array $row, string $name): void {
    expect(areasFromParams(['areas' => $row]))
        ->toBe('[{"@type":"https://schema.org/AdministrativeArea","name":"'.$name.'"}]');
})->with([
    'title' => [['id' => 'central-arkansas', 'title' => 'Central Arkansas', 'slug' => 'central-arkansas'], 'Central Arkansas'],
    'label' => [['label' => 'Central Arkansas', 'slug' => 'central-arkansas'], 'Central Arkansas'],
    'value' => [['value' => 'Central Arkansas', 'slug' => 'central-arkansas'], 'Central Arkansas'],
]);

it('emits nothing for a single row whose name is not text, rather than reading its other fields (#2988)', function (): void {
    expect(areasFromParams(['areas' => ['title' => 42, 'slug' => 'central-arkansas']]))->toBe('');
});

it('still reads an array without a name key as a list of names (#2988)', function (): void {
    expect(areasFromParams(['areas' => ['first' => 'Central Arkansas', 'second' => 'Northwest Arkansas']]))
        ->toBe('[{"@type":"https://schema.org/AdministrativeArea","name":"Central Arkansas"},{"@type":"https://schema.org/AdministrativeArea","name":"Northwest Arkansas"}]');
});

it('reads a single LabeledValue as one area named by its label (#2988)', function (): void {
    expect(areasFromParams(['areas' => new LabeledValue('central-arkansas', 'Central Arkansas')]))
        ->toBe('[{"@type":"https://schema.org/AdministrativeArea","name":"Central Arkansas"}]');
});
