<?php

declare(strict_types=1);

use App\Support\Statamic\Assets\Logos\LogosBlueprintOptionLabels;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blink;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for option labels for the `logos` asset blueprint.
 *
 * Tests the label maps against the live blueprint to ensure they match.
 *
 * Mutation: 100% on App\Support\Statamic\Assets\Logos\LogosBlueprintOptionLabels (17 mutants, --covered-only; every line covered).
 *
 * @command  php artisan test --compact tests/Feature/Support/Statamic/Assets/Logos/LogosBlueprintOptionLabelsTest.php
 */
it('matches the live logos blueprint radio labels', function (): void {
    // Forget the cached label maps
    Blink::forget('app.support.logos-blueprint-option-label-maps');

    // Get the label maps
    $maps = LogosBlueprintOptionLabels::maps();

    // Check if the orientation label map matches the blueprint
    expect($maps['orientation']['horizontal'] ?? null)->toBe('Horizontal');

    // Check if the color scheme label map matches the blueprint
    expect($maps['color_scheme']['dark'] ?? null)->toBe('Dark');

    // Check if the color scheme label map matches the blueprint
    expect($maps['color_scheme']['white'] ?? null)->toBe('All White');
});

/**
 * Bind a `logos` container whose blueprint is `$blueprint`, after clearing the cache.
 */
function logosContainerWithBlueprint(mixed $blueprint): void
{
    Blink::forget('app.support.logos-blueprint-option-label-maps');

    $container = Mockery::mock(AssetContainerContract::class);
    $container->shouldReceive('blueprint')->andReturn($blueprint);
    AssetContainer::shouldReceive('find')->with('logos')->andReturn($container);
}

/**
 * A field double whose fieldtype is `$fieldtype`.
 */
function logosField(mixed $fieldtype): Field
{
    $field = Mockery::mock(Field::class);
    $field->shouldReceive('fieldtype')->andReturn($fieldtype);

    return $field;
}

it('returns empty maps for both fields when the logos container is missing', function (): void {
    Blink::forget('app.support.logos-blueprint-option-label-maps');
    AssetContainer::shouldReceive('find')->once()->with('logos')->andReturnNull();

    expect(LogosBlueprintOptionLabels::maps())->toBe(['orientation' => [], 'color_scheme' => []]);
});

it('maps each field from its fieldtype options, and a missing field to an empty map', function (): void {
    $fieldtype = Mockery::mock(Fieldtype::class);
    $fieldtype->shouldReceive('extraRenderableFieldData')->andReturn(['options' => ['wide' => 'Wide']]);

    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('hasField')->with('orientation')->andReturnTrue();
    $blueprint->shouldReceive('field')->with('orientation')->andReturn(logosField($fieldtype));
    $blueprint->shouldReceive('hasField')->with('color_scheme')->andReturnFalse();
    $blueprint->shouldReceive('field')->with('color_scheme')->never();
    logosContainerWithBlueprint($blueprint);

    expect(LogosBlueprintOptionLabels::maps())->toBe(['orientation' => ['wide' => 'Wide'], 'color_scheme' => []]);
});

it('maps a field to an empty map when its fieldtype does not resolve or has no options', function (): void {
    $noOptions = Mockery::mock(Fieldtype::class);
    $noOptions->shouldReceive('extraRenderableFieldData')->andReturn([]);

    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('hasField')->andReturnTrue();
    $blueprint->shouldReceive('field')->with('orientation')->andReturn(logosField(null));
    $blueprint->shouldReceive('field')->with('color_scheme')->andReturn(logosField($noOptions));
    logosContainerWithBlueprint($blueprint);

    expect(LogosBlueprintOptionLabels::maps())->toBe(['orientation' => [], 'color_scheme' => []]);
});

it('builds the maps once per request', function (): void {
    Blink::forget('app.support.logos-blueprint-option-label-maps');
    AssetContainer::shouldReceive('find')->once()->with('logos')->andReturnNull();

    LogosBlueprintOptionLabels::maps();

    expect(LogosBlueprintOptionLabels::maps())->toBe(['orientation' => [], 'color_scheme' => []]);
});
