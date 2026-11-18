<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Taxonomies\Term;
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
