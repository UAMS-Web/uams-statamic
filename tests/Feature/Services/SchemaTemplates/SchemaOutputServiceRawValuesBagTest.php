<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins how {@see SchemaOutputService} reads the raw variable bag for a schema
 * template's Antlers context from a context entity that is not a full Statamic
 * entry: `extractRawMergedValuesBagFromEntity()` prefers `values()`, falls back
 * to `data()`, and answers null when neither exposes `all()`; and
 * `overlayAugmentedFieldTypeValuesForSchemaAntlersContext()` leaves the context
 * unchanged for an augmentable entity with no `blueprint()`. The entities are
 * anonymous classes on the `Term` contract, which declares no methods, so each
 * accessor's presence and return shape is set exactly.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceRawValuesBagTest.php
 */

/**
 * Call `extractRawMergedValuesBagFromEntity()`.
 */
function sosBagExtract(Term $entity): mixed
{
    return (new ReflectionMethod(SchemaOutputService::class, 'extractRawMergedValuesBagFromEntity'))
        ->invoke(app(SchemaOutputService::class), $entity);
}

it('reads the values collection when the entity has one', function (): void {
    $entity = new class implements Term
    {
        public function values(): mixed
        {
            return collect(['name' => 'From values']);
        }

        public function data(): mixed
        {
            return collect(['name' => 'From data']);
        }
    };

    expect(sosBagExtract($entity))->toBe(['name' => 'From values']);
});

it('falls back to data when values is not a collection', function (mixed $values): void {
    $entity = new class($values) implements Term
    {
        public function __construct(private readonly mixed $values) {}

        public function values(): mixed
        {
            return $this->values;
        }

        public function data(): mixed
        {
            return collect(['name' => 'From data']);
        }
    };

    expect(sosBagExtract($entity))->toBe(['name' => 'From data']);
})->with([
    'null' => [null],
    'array' => [['name' => 'From values']],
    'object without all()' => [new stdClass],
]);

it('reads data when the entity has no values accessor', function (): void {
    $entity = new class implements Term
    {
        public function data(): mixed
        {
            return collect(['name' => 'From data']);
        }
    };

    expect(sosBagExtract($entity))->toBe(['name' => 'From data']);
});

it('returns null when neither accessor exposes a collection', function (mixed $data): void {
    $entity = new class($data) implements Term
    {
        public function __construct(private readonly mixed $data) {}

        public function values(): mixed
        {
            return null;
        }

        public function data(): mixed
        {
            return $this->data;
        }
    };

    expect(sosBagExtract($entity))->toBeNull();
})->with([
    'null' => [null],
    'array' => [['name' => 'From data']],
    'object without all()' => [new stdClass],
]);

it('returns null for an entity with neither accessor', function (): void {
    expect(sosBagExtract(new class implements Term {}))->toBeNull();
});

it('leaves the context unchanged for an augmentable entity with no blueprint', function (): void {
    // A mock of the contract declares no `blueprint()`, and fails if anything augments it.
    /** @var Augmentable&MockInterface $entity */
    $entity = Mockery::mock(Augmentable::class);
    $entity->shouldNotReceive('augmented', 'augmentedValue', 'toAugmentedArray');

    $method = new ReflectionMethod(SchemaOutputService::class, 'overlayAugmentedFieldTypeValuesForSchemaAntlersContext');

    expect($method->invoke(app(SchemaOutputService::class), $entity, ['name' => 'Raw']))->toBe(['name' => 'Raw']);
});

/**
 * Overlay augmented values onto a context for an entry whose blueprint holds the
 * given fields, each augmenting through the given values by handle.
 *
 * @param  list<array{0: mixed, 1: string}>  $fields  Handle and fieldtype of each field.
 * @param  array<array-key, mixed>  $augmented  Augmented value by handle.
 * @param  array<array-key, mixed>  $context
 */
function sosBagOverlay(array $fields, array $augmented, array $context): mixed
{
    $doubles = [];
    foreach ($fields as [$handle, $type]) {
        /** @var Field&MockInterface $field */
        $field = Mockery::mock(Field::class);
        $field->shouldReceive('handle')->andReturn($handle);
        $field->shouldReceive('type')->andReturn($type);
        $doubles[] = $field;
    }
    /** @var Blueprint&MockInterface $blueprint */
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('fields->all')->andReturn(collect($doubles));
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('augmentedValue')->andReturnUsing(fn (string $handle): mixed => $augmented[$handle] ?? 'not a Value');

    $method = new ReflectionMethod(SchemaOutputService::class, 'overlayAugmentedFieldTypeValuesForSchemaAntlersContext');

    return $method->invoke(app(SchemaOutputService::class), $entry, $context);
}

it('overlays each augmented field value, skipping schema object fields and keeping a value that does not augment', function (): void {
    $out = sosBagOverlay(
        [['subject', 'schema_object'], ['title', 'text'], ['raw', 'text']],
        ['subject' => new Value('augmented subject'), 'title' => new Value('Augmented')],
        ['subject' => 'raw subject', 'title' => 'Raw', 'raw' => 'Kept']
    );

    expect($out)->toBe(['subject' => 'raw subject', 'title' => 'Augmented', 'raw' => 'Kept']);
});

it('overlays a field under its scalar handle as a string, and under an empty string otherwise', function (mixed $handle, string $key): void {
    expect(sosBagOverlay([[$handle, 'text']], [$key => new Value('Augmented')], []))->toBe([$key => 'Augmented']);
})->with([
    'a float' => [1.5, '1.5'],
    'null' => [null, ''],
]);
