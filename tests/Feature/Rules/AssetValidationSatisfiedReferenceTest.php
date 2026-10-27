<?php

declare(strict_types=1);

use App\Models\Asset as AssetModel;
use App\Rules\AssetValidationSatisfied;
use App\Statamic\Eloquent\Assets\Asset;
use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

uses(TestCase::class);

covers(AssetValidationSatisfied::class);

/**
 * How `AssetValidationSatisfied` resolves the value it is given: a
 * container-qualified ID through `Asset::find()`, any other string through
 * `Asset::findByPath()` and then each container in turn, the first candidate of
 * an array payload (`0`, then `id`, then `value`), and which values count as no
 * selection at all. Asset lookups are mocked through the Statamic facades, so no
 * asset store is read.
 *
 * The blueprint-satisfaction outcome itself is covered by
 * tests/Feature/Rules/AssetValidationSatisfiedRuleTest.php.
 *
 * @command  php artisan test --compact tests/Feature/Rules/AssetValidationSatisfiedReferenceTest.php
 */

/**
 * Build an images asset whose metadata does or does not satisfy its blueprint.
 */
function assetValidationReferenceAsset(bool $satisfied): Asset
{
    $data = $satisfied
        ? [
            'exempt_from_alt' => false,
            'alt' => 'A descriptive alt text.',
            'country_of_origin_query' => true,
            'digital_source_type' => [
                'preset' => 'humanEdits_image_photo_edited',
            ],
        ]
        : [];

    return mustBe(Asset::class, Asset::fromModel(new AssetModel([
        'container' => 'images',
        'folder' => 'default',
        'basename' => 'example.jpg',
        'filename' => 'example',
        'extension' => 'jpg',
        'path' => 'default/example.jpg',
        'meta' => ['data' => $data],
    ])), 'hydrated asset');
}

/**
 * Run the rule and return every message it failed with.
 *
 * @return list<mixed>
 */
function assetValidationReferenceMessages(mixed $value): array
{
    $messages = [];

    (new AssetValidationSatisfied)->validate('image', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

it('treats a value with no selected asset as valid without looking anything up', function (mixed $value): void {
    AssetFacade::shouldReceive('find')->never();
    AssetFacade::shouldReceive('findByPath')->never();
    AssetContainer::shouldReceive('all')->never();

    expect(assetValidationReferenceMessages($value))->toBe([]);
})->with([
    'null' => [null],
    'empty string' => [''],
    'whitespace string' => ['   '],
    'empty array' => [[]],
    'array with a null first item' => [[null]],
    'array with a blank first item' => [[' ']],
    'array with a blank id' => [['id' => '']],
    'array with a null value key' => [['value' => null]],
    'array with no known key' => [['path' => 'images::default/example.jpg']],
]);

it('resolves a container-qualified ID through Asset::find', function (bool $satisfied, array $messages): void {
    AssetFacade::shouldReceive('find')->once()->with('images::default/example.jpg')
        ->andReturn(assetValidationReferenceAsset($satisfied));
    AssetFacade::shouldReceive('findByPath')->never();
    AssetContainer::shouldReceive('all')->never();

    expect(assetValidationReferenceMessages('images::default/example.jpg'))
        ->toBe(array_map(fn (mixed $key): mixed => __(mustBeString($key, 'message key')), $messages));
})->with([
    'satisfied' => [true, []],
    'unsatisfied' => [false, ['statamic::validation.asset_validation_satisfied.unsatisfied']],
]);

it('fails a container-qualified ID that resolves to nothing without trying a path', function (): void {
    AssetFacade::shouldReceive('find')->once()->with('images::missing.jpg')->andReturn(null);
    AssetFacade::shouldReceive('findByPath')->never();
    AssetContainer::shouldReceive('all')->never();

    expect(assetValidationReferenceMessages('images::missing.jpg'))
        ->toBe([__('statamic::validation.asset_validation_satisfied.default')]);
});

it('takes the first candidate from an array payload', function (array $value, string $expectedId): void {
    AssetFacade::shouldReceive('find')->once()->with($expectedId)
        ->andReturn(assetValidationReferenceAsset(true));

    expect(assetValidationReferenceMessages($value))->toBe([]);
})->with([
    'list item' => [['images::a.jpg'], 'images::a.jpg'],
    'id key' => [['id' => 'images::b.jpg'], 'images::b.jpg'],
    'value key' => [['value' => 'images::c.jpg'], 'images::c.jpg'],
    'list item before id and value' => [[0 => 'images::a.jpg', 'id' => 'images::b.jpg', 'value' => 'images::c.jpg'], 'images::a.jpg'],
    'id before value' => [['value' => 'images::c.jpg', 'id' => 'images::b.jpg'], 'images::b.jpg'],
]);

it('accepts an asset instance held inside an array payload', function (): void {
    AssetFacade::shouldReceive('find')->never();

    expect(assetValidationReferenceMessages(['id' => assetValidationReferenceAsset(true)]))->toBe([])
        ->and(assetValidationReferenceMessages(['value' => assetValidationReferenceAsset(false)]))
        ->toBe([__('statamic::validation.asset_validation_satisfied.unsatisfied')]);
});

it('resolves a plain path through Asset::findByPath before any container', function (): void {
    AssetFacade::shouldReceive('find')->never();
    AssetFacade::shouldReceive('findByPath')->once()->with('/assets/images/default/example.jpg')
        ->andReturn(assetValidationReferenceAsset(true));
    AssetContainer::shouldReceive('all')->never();

    expect(assetValidationReferenceMessages('/assets/images/default/example.jpg'))->toBe([]);
});

it('falls back to each container in turn for a relative path', function (bool $satisfied, array $messages): void {
    $miss = Mockery::mock(StatamicAssetContainer::class);
    $miss->shouldReceive('asset')->once()->with('default/example.jpg')->andReturn(null);

    $hit = Mockery::mock(StatamicAssetContainer::class);
    $hit->shouldReceive('asset')->once()->with('default/example.jpg')
        ->andReturn(assetValidationReferenceAsset($satisfied));

    $unreached = Mockery::mock(StatamicAssetContainer::class);
    $unreached->shouldReceive('asset')->never();

    AssetFacade::shouldReceive('findByPath')->once()->with('default/example.jpg')->andReturn(null);
    AssetContainer::shouldReceive('all')->once()->andReturn(collect([$miss, $hit, $unreached]));

    expect(assetValidationReferenceMessages('default/example.jpg'))
        ->toBe(array_map(fn (mixed $key): mixed => __(mustBeString($key, 'message key')), $messages));
})->with([
    'satisfied' => [true, []],
    'unsatisfied' => [false, ['statamic::validation.asset_validation_satisfied.unsatisfied']],
]);

it('fails a path no container can resolve', function (array $containers): void {
    AssetFacade::shouldReceive('findByPath')->once()->with('default/missing.jpg')->andReturn(null);
    AssetContainer::shouldReceive('all')->once()->andReturn(collect(array_map(function (mixed $index): StatamicAssetContainer {
        $container = Mockery::mock(StatamicAssetContainer::class);
        $container->shouldReceive('asset')->once()->with('default/missing.jpg')->andReturn(null);

        return $container;
    }, $containers)));

    expect(assetValidationReferenceMessages('default/missing.jpg'))
        ->toBe([__('statamic::validation.asset_validation_satisfied.default')]);
})->with([
    'no containers' => [[]],
    'one container' => [[1]],
    'two containers' => [[1, 2]],
]);

it('fails a value that is neither an asset nor a string reference', function (mixed $value): void {
    AssetFacade::shouldReceive('find')->never();
    AssetFacade::shouldReceive('findByPath')->never();

    expect(assetValidationReferenceMessages($value))
        ->toBe([__('statamic::validation.asset_validation_satisfied.default')]);
})->with([
    'integer' => [123],
    'boolean true' => [true],
    'object' => [new stdClass],
    'array with an integer first item' => [[123]],
    'array with a nested array id' => [['id' => ['images::a.jpg']]],
]);
