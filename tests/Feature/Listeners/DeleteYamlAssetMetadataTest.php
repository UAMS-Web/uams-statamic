<?php

declare(strict_types=1);

use App\Listeners\DeleteYamlAssetMetadata;
use App\Models\Asset as AssetMetadataModel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Events\AssetDeleting;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Listeners\DeleteYamlAssetMetadata
 *
 * Each test points `asset_metadata.yaml_directory` at its own directory under
 * `storage/framework/testing`, and uses its own container handle, so the `assets_meta` rows and
 * YAML files one test writes cannot match another's lookup. `Asset::$skipYamlSync` stays armed
 * (Tests\TestCase), so saving a row writes no YAML; each test writes the file it needs.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/DeleteYamlAssetMetadataTest.php
 */
beforeEach(function (): void {
    $this->yamlDirectory = storage_path('framework/testing/delete-yaml-asset-metadata-'.uniqid());
    File::ensureDirectoryExists($this->yamlDirectory);
    Config::set('asset_metadata.yaml_directory', $this->yamlDirectory);

    $this->container = 'delete_yaml_'.uniqid();
});

afterEach(function (): void {
    File::deleteDirectory($this->yamlDirectory);
});

/**
 * Save an `assets_meta` row. `skipYamlSync` is armed, so no YAML is written.
 */
function deleteYamlMetadataRow(string $container, string $folder, string $basename, string $path): AssetMetadataModel
{
    $row = new AssetMetadataModel;
    $row->setAttribute('container', $container);
    $row->setAttribute('folder', $folder);
    $row->setAttribute('basename', $basename);
    $row->setAttribute('filename', pathinfo($basename, PATHINFO_FILENAME));
    $row->setAttribute('extension', pathinfo($basename, PATHINFO_EXTENSION));
    $row->setAttribute('path', $path);
    $row->setAttribute('meta', ['data' => []]);
    $row->save();

    return $row;
}

/**
 * The `{id}.yml` path the listener builds for a row.
 */
function deleteYamlPathFor(AssetMetadataModel $row): string
{
    $key = $row->getKey();

    if (! \is_int($key)) {
        throw new RuntimeException('the row was saved without an integer primary key');
    }

    return Config::string('asset_metadata.yaml_directory').'/'.$key.'.yml';
}

/**
 * Write a row's YAML file with the given contents.
 */
function deleteYamlWrite(AssetMetadataModel $row, string $contents): string
{
    $yamlPath = deleteYamlPathFor($row);
    File::put($yamlPath, $contents);

    return $yamlPath;
}

/**
 * A mock Statamic asset in the given container.
 *
 * @param  mixed  $originalPath  What `getOriginal('path')` returns.
 * @param  mixed  $path  What `path()` returns.
 */
function deleteYamlAsset(string $container, mixed $originalPath, mixed $path): AssetContract
{
    $assetContainer = Mockery::mock(AssetContainerContract::class);
    $assetContainer->shouldReceive('handle')->andReturn($container);

    $asset = Mockery::mock(AssetContract::class);
    $asset->shouldReceive('container')->andReturn($assetContainer);
    $asset->shouldReceive('getOriginal')->with('path')->andReturn($originalPath);
    $asset->shouldReceive('path')->andReturn($path);

    return $asset;
}

/**
 * Fire the listener for a deleting asset.
 */
function deleteYamlFire(AssetContract $asset): void
{
    (new DeleteYamlAssetMetadata)->handle(new AssetDeleting($asset));
}

it('exits quietly when there is no asset metadata row', function (): void {
    expect(throwableFrom(fn () => deleteYamlFire(deleteYamlAsset($this->container, null, 'orphan/no-model-match.jpg'))))->toBeNull();
});

it('deletes the YAML of the row found by its original path', function (): void {
    $row = deleteYamlMetadataRow($this->container, 'images', 'photo.jpg', 'images/photo.jpg');
    $yamlPath = deleteYamlWrite($row, Yaml::dump(['container' => $this->container, 'path' => 'images/photo.jpg']));

    // The original path wins over the current one, which a move has already changed.
    deleteYamlFire(deleteYamlAsset($this->container, 'images/photo.jpg', 'moved/photo.jpg'));

    expect(File::exists($yamlPath))->toBeFalse();
});

it('falls back to the current path when there is no original path', function (): void {
    $row = deleteYamlMetadataRow($this->container, 'images', 'photo.jpg', 'images/photo.jpg');
    $yamlPath = deleteYamlWrite($row, Yaml::dump(['container' => $this->container, 'path' => 'images/photo.jpg']));

    deleteYamlFire(deleteYamlAsset($this->container, null, 'images/photo.jpg'));

    expect(File::exists($yamlPath))->toBeFalse();
});

it('casts a scalar current path to text', function (): void {
    $row = deleteYamlMetadataRow($this->container, '/', '2024', '2024');
    $yamlPath = deleteYamlWrite($row, Yaml::dump(['container' => $this->container, 'path' => '2024']));

    deleteYamlFire(deleteYamlAsset($this->container, null, 2024));

    expect(File::exists($yamlPath))->toBeFalse();
});

it('finds a root asset by folder and basename when the path column is stale', function (): void {
    $row = deleteYamlMetadataRow($this->container, '/', 'logo.png', 'stale/logo.png');
    $yamlPath = deleteYamlWrite($row, Yaml::dump(['container' => $this->container, 'path' => 'logo.png']));

    // dirname('logo.png') is '.', which the listener reads as the root folder '/'.
    deleteYamlFire(deleteYamlAsset($this->container, 'logo.png', 'logo.png'));

    expect(File::exists($yamlPath))->toBeFalse();
});

it('finds a nested asset by folder and basename when the path column is stale', function (): void {
    $row = deleteYamlMetadataRow($this->container, 'a/b', 'chart.pdf', 'old/chart.pdf');
    $yamlPath = deleteYamlWrite($row, Yaml::dump(['container' => $this->container, 'path' => 'a/b/chart.pdf']));

    deleteYamlFire(deleteYamlAsset($this->container, 'a/b/chart.pdf', 'a/b/chart.pdf'));

    expect(File::exists($yamlPath))->toBeFalse();
});

it('reads an empty path as the root folder', function (): void {
    // A path that is neither a string original nor a scalar current value resolves to '',
    // whose dirname is '' as well; both mean the root folder.
    $row = deleteYamlMetadataRow($this->container, '/', '', 'stale');
    $yamlPath = deleteYamlWrite($row, Yaml::dump(['container' => $this->container, 'path' => '']));

    deleteYamlFire(deleteYamlAsset($this->container, null, ['not', 'a', 'path']));

    expect(File::exists($yamlPath))->toBeFalse();
});

it('does not look in another container', function (): void {
    $row = deleteYamlMetadataRow($this->container, 'images', 'photo.jpg', 'images/photo.jpg');
    $yamlPath = deleteYamlWrite($row, Yaml::dump(['container' => $this->container, 'path' => 'images/photo.jpg']));

    deleteYamlFire(deleteYamlAsset($this->container.'_other', 'images/photo.jpg', 'images/photo.jpg'));

    expect(File::exists($yamlPath))->toBeTrue();
});

it('does nothing when the row has no YAML file', function (): void {
    $row = deleteYamlMetadataRow($this->container, 'images', 'photo.jpg', 'images/photo.jpg');

    deleteYamlFire(deleteYamlAsset($this->container, 'images/photo.jpg', 'images/photo.jpg'));

    expect(File::exists(deleteYamlPathFor($row)))->toBeFalse()
        ->and(File::files($this->yamlDirectory))->toBe([]);
});

it('keeps a YAML file that describes a different asset', function (array $described): void {
    // A dataset cannot read $this, so 'CONTAINER' stands for this test's own handle.
    $described = array_map(fn (mixed $value): mixed => $value === 'CONTAINER' ? $this->container : $value, $described);

    $row = deleteYamlMetadataRow($this->container, 'images', 'photo.jpg', 'images/photo.jpg');
    $yamlPath = deleteYamlWrite($row, Yaml::dump($described));

    deleteYamlFire(deleteYamlAsset($this->container, 'images/photo.jpg', 'images/photo.jpg'));

    expect(File::exists($yamlPath))->toBeTrue();
})->with([
    'another container' => [['container' => 'elsewhere', 'path' => 'images/photo.jpg']],
    'another path' => [['container' => 'CONTAINER', 'path' => 'images/other.jpg']],
    'no container' => [['path' => 'images/photo.jpg']],
    'no path' => [['container' => 'CONTAINER']],
]);

it('keeps a YAML file it cannot read as a mapping', function (string $contents): void {
    $row = deleteYamlMetadataRow($this->container, 'images', 'photo.jpg', 'images/photo.jpg');
    $yamlPath = deleteYamlWrite($row, $contents);

    deleteYamlFire(deleteYamlAsset($this->container, 'images/photo.jpg', 'images/photo.jpg'));

    expect(File::exists($yamlPath))->toBeTrue();
})->with([
    'invalid YAML' => ["container: [unclosed\n"],
    'a scalar' => ["just a string\n"],
]);
