<?php

declare(strict_types=1);

use App\Scopes\AssetValidationSatisfied;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Assets\QueryBuilder;
use Statamic\Facades\Blueprint;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for AssetValidationSatisfied query scope aliases and apply logic.
 *
 * Mutation: 100% on App\Scopes\AssetValidationSatisfied (54 mutants), measured on macOS on 2026-10-08, serial, --covered-only, with every test file that names the class.
 *
 * @command  php artisan test --compact tests/Feature/Scopes/AssetValidationSatisfiedTest.php
 */
it('exposes base, true and false alias handles', function () {
    // Resolve aliases from the scope class
    $aliases = AssetValidationSatisfied::aliases();

    // Assert base alias is present
    expect($aliases)->toContain('asset_validation_satisfied');

    // Assert true alias is present
    expect($aliases)->toContain('asset_validation_satisfied_true');

    // Assert false alias is present
    expect($aliases)->toContain('asset_validation_satisfied_false');
});

it('filters true when the base alias is present', function () {
    // Create asset mocks with stable IDs
    $assetOne = Mockery::mock(StatamicAsset::class);
    $assetOne->shouldReceive('id')->andReturn('asset-1');
    $assetOne->shouldReceive('path')->andReturn('default/asset-1.jpg');
    $assetTwo = Mockery::mock(StatamicAsset::class);
    $assetTwo->shouldReceive('id')->andReturn('asset-2');
    $assetTwo->shouldReceive('path')->andReturn('default/asset-2.jpg');

    // Initialize a partial scope mock with dynamic validation responses
    /** @var AssetValidationSatisfied&MockInterface $scope */
    $scope = Mockery::mock(AssetValidationSatisfied::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $scope->shouldReceive('isValidationSatisfied')->with($assetOne)->andReturn(true);
    $scope->shouldReceive('isValidationSatisfied')->with($assetTwo)->andReturn(false);

    // Create a query mock expecting ID-based filtering
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('get')->once()->andReturn(collect([$assetOne, $assetTwo]));
    $query->shouldReceive('where')
        ->once()
        ->with(
            Mockery::on(
                fn ($argument): bool => $argument instanceof Closure
            )
        )
        ->andReturnSelf();

    // Apply scope using base alias
    $scope->apply($query, [
        'queryScopes' => [
            'asset_validation_satisfied',
        ],
    ]);
});

it('filters true when the true alias is present', function () {
    // Create asset mocks with stable IDs
    $assetOne = Mockery::mock(StatamicAsset::class);
    $assetOne->shouldReceive('id')->andReturn('asset-1');
    $assetOne->shouldReceive('path')->andReturn('default/asset-1.jpg');
    $assetTwo = Mockery::mock(StatamicAsset::class);
    $assetTwo->shouldReceive('id')->andReturn('asset-2');
    $assetTwo->shouldReceive('path')->andReturn('default/asset-2.jpg');

    // Initialize a partial scope mock with dynamic validation responses
    /** @var AssetValidationSatisfied&MockInterface $scope */
    $scope = Mockery::mock(AssetValidationSatisfied::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $scope->shouldReceive('isValidationSatisfied')->with($assetOne)->andReturn(true);
    $scope->shouldReceive('isValidationSatisfied')->with($assetTwo)->andReturn(false);

    // Create a query mock expecting ID-based filtering
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('get')->once()->andReturn(collect([$assetOne, $assetTwo]));
    $query->shouldReceive('where')
        ->once()
        ->with(
            Mockery::on(
                fn ($argument): bool => $argument instanceof Closure
            )
        )
        ->andReturnSelf();

    // Apply scope using encoded true alias
    $scope->apply($query, [
        'queryScopes' => [
            'asset_validation_satisfied_true',
        ],
    ]);
});

it('filters false when the false alias is present', function () {
    // Create asset mocks with stable IDs
    $assetOne = Mockery::mock(StatamicAsset::class);
    $assetOne->shouldReceive('id')->andReturn('asset-1');
    $assetOne->shouldReceive('path')->andReturn('default/asset-1.jpg');
    $assetTwo = Mockery::mock(StatamicAsset::class);
    $assetTwo->shouldReceive('id')->andReturn('asset-2');
    $assetTwo->shouldReceive('path')->andReturn('default/asset-2.jpg');

    // Initialize a partial scope mock with dynamic validation responses
    /** @var AssetValidationSatisfied&MockInterface $scope */
    $scope = Mockery::mock(AssetValidationSatisfied::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $scope->shouldReceive('isValidationSatisfied')->with($assetOne)->andReturn(true);
    $scope->shouldReceive('isValidationSatisfied')->with($assetTwo)->andReturn(false);

    // Create a query mock expecting ID-based filtering
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('get')->once()->andReturn(collect([$assetOne, $assetTwo]));
    $query->shouldReceive('where')
        ->once()
        ->with(
            Mockery::on(
                fn ($argument): bool => $argument instanceof Closure
            )
        )
        ->andReturnSelf();

    // Apply scope using encoded false alias
    $scope->apply($query, [
        'queryScopes' => [
            'asset_validation_satisfied_false',
        ],
    ]);
});

it('does not filter when no alias is present', function () {
    // Initialize the scope under test
    $scope = new AssetValidationSatisfied;

    // Create a query mock with no expected query mutations
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('get')->never();
    $query->shouldReceive('where')->never();

    // Apply scope with unrelated query scope values
    $scope->apply($query, [
        'queryScopes' => [
            'assets_by_file_type_image_jpeg',
        ],
    ]);
});

// ---------------------------------------------------------------------------
// Which paths the scope keeps
//
// The tests above check that a nested constraint is added; these run that constraint
// against a recording query, so the paths it keeps are asserted rather than assumed.
// ---------------------------------------------------------------------------

/**
 * A mock asset at the given path.
 */
function validationScopeAsset(string $path): StatamicAsset&MockInterface
{
    $asset = Mockery::mock(StatamicAsset::class);
    $asset->shouldReceive('path')->andReturn($path);

    return $asset;
}

/**
 * Apply the scope to the given query items, with each asset path mapped to whether it
 * passes validation, and return the constraints it adds as [method, column, operator, value].
 *
 * @param  list<mixed>  $items  What the query's get() returns.
 * @param  array<string, bool>  $validByPath  Validation result per asset path.
 * @param  array<string, mixed>  $values  Scope values.
 * @return list<array{0: string, 1: mixed, 2: mixed, 3: mixed}>
 */
function validationScopeConstraints(array $items, array $validByPath, array $values): array
{
    /** @var AssetValidationSatisfied&MockInterface $scope */
    $scope = Mockery::mock(AssetValidationSatisfied::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $scope->shouldReceive('isValidationSatisfied')
        ->andReturnUsing(fn (StatamicAsset $asset): bool => $validByPath[mustBeString($asset->path(), 'asset path')]);

    $recorded = [];

    $nested = Mockery::mock();
    $nested->shouldReceive('where')->andReturnUsing(function (mixed ...$args) use (&$recorded, $nested) {
        $recorded[] = ['where', ...$args];

        return $nested;
    });
    $nested->shouldReceive('orWhere')->andReturnUsing(function (mixed ...$args) use (&$recorded, $nested) {
        $recorded[] = ['orWhere', ...$args];

        return $nested;
    });

    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('get')->once()->andReturn(collect($items));
    $query->shouldReceive('where')->andReturnUsing(function (mixed ...$args) use (&$recorded, $query, $nested) {
        if ($args[0] instanceof Closure) {
            $args[0]($nested);
        } else {
            $recorded[] = ['outer where', ...$args];
        }

        return $query;
    });

    $scope->apply($query, $values);

    /** @var list<array{0: string, 1: mixed, 2: mixed, 3: mixed}> $recorded */
    return $recorded;
}

it('keeps the assets that pass validation for the true and base aliases', function (string $alias): void {
    $constraints = validationScopeConstraints(
        [validationScopeAsset('a/pass.jpg'), validationScopeAsset('a/fail.jpg')],
        ['a/pass.jpg' => true, 'a/fail.jpg' => false],
        ['queryScopes' => [$alias]],
    );

    expect($constraints)->toBe([['where', 'path', '=', 'a/pass.jpg']]);
})->with(['asset_validation_satisfied', 'asset_validation_satisfied_true']);

it('keeps the assets that fail validation for the false alias', function (): void {
    $constraints = validationScopeConstraints(
        [validationScopeAsset('a/pass.jpg'), validationScopeAsset('a/fail.jpg')],
        ['a/pass.jpg' => true, 'a/fail.jpg' => false],
        ['queryScopes' => ['asset_validation_satisfied_false']],
    );

    expect($constraints)->toBe([['where', 'path', '=', 'a/fail.jpg']]);
});

it('joins every kept path after the first with orWhere', function (): void {
    $constraints = validationScopeConstraints(
        [validationScopeAsset('a/one.jpg'), validationScopeAsset('a/two.jpg'), validationScopeAsset('a/three.jpg')],
        ['a/one.jpg' => true, 'a/two.jpg' => true, 'a/three.jpg' => true],
        ['queryScopes' => ['asset_validation_satisfied_true']],
    );

    expect($constraints)->toBe([
        ['where', 'path', '=', 'a/one.jpg'],
        ['orWhere', 'path', '=', 'a/two.jpg'],
        ['orWhere', 'path', '=', 'a/three.jpg'],
    ]);
});

it('matches nothing when no asset is in the requested state', function (): void {
    $constraints = validationScopeConstraints(
        [validationScopeAsset('a/pass.jpg')],
        ['a/pass.jpg' => true],
        ['queryScopes' => ['asset_validation_satisfied_false']],
    );

    expect($constraints)->toBe([['outer where', 'path', '=', '__no_asset_validation_matches__']]);
});

it('skips query results that are not assets', function (): void {
    $constraints = validationScopeConstraints(
        ['not an asset', new stdClass, validationScopeAsset('a/pass.jpg')],
        ['a/pass.jpg' => true],
        ['queryScopes' => ['asset_validation_satisfied_true']],
    );

    expect($constraints)->toBe([['where', 'path', '=', 'a/pass.jpg']]);
});

it('reads past scope handles that are not text to find its alias', function (): void {
    $constraints = validationScopeConstraints(
        [validationScopeAsset('a/fail.jpg')],
        ['a/fail.jpg' => false],
        ['queryScopes' => [42, ['nested'], 'assets_by_file_type_image_jpeg', 'asset_validation_satisfied_false']],
    );

    expect($constraints)->toBe([['where', 'path', '=', 'a/fail.jpg']]);
});

it('reads a single scope handle given as a string', function (): void {
    $constraints = validationScopeConstraints(
        [validationScopeAsset('a/fail.jpg')],
        ['a/fail.jpg' => false],
        ['queryScopes' => 'asset_validation_satisfied_false'],
    );

    expect($constraints)->toBe([['where', 'path', '=', 'a/fail.jpg']]);
});

it('asks the shared resolver whether an asset passes its blueprint validation', function (array $data, bool $expected): void {
    $blueprint = Blueprint::makeFromFields(['alt' => ['type' => 'text', 'validate' => 'required']]);

    $asset = Mockery::mock(StatamicAsset::class);
    $asset->shouldReceive('blueprint')->andReturn($blueprint);
    $asset->shouldReceive('data')->andReturn(collect($data));

    $isValid = new ReflectionMethod(AssetValidationSatisfied::class, 'isValidationSatisfied');

    expect($isValid->invoke(new AssetValidationSatisfied, $asset))->toBe($expected);
})->with([
    'required field filled' => [['alt' => 'A campus map'], true],
    'required field missing' => [[], false],
]);
