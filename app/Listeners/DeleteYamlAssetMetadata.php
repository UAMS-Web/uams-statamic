<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Asset;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Statamic\Events\AssetDeleting;
use Symfony\Component\Yaml\Yaml;

/**
 * Listener to delete asset metadata YAML files when assets are deleted.
 *
 * When an asset is deleted through Statamic, this listener ensures the
 * corresponding YAML file under `config('asset_metadata.yaml_directory')` is
 * also removed to keep the metadata in sync.
 */
class DeleteYamlAssetMetadata
{
    /**
     * Handle the asset deleting event.
     *
     * Deletes the YAML metadata file for the asset being deleted. This fires before
     * the asset is deleted, so we can still access the model.
     *
     * @param  AssetDeleting  $event  The asset deleting event
     */
    public function handle(
        AssetDeleting $event
    ): void {
        // Get the asset from the event
        /** @var \Statamic\Assets\Asset $asset */
        $asset = $event->asset;

        // Get the container handle for the asset
        $container = $asset->container()->handle();

        // Get the asset path, preferring original path if available
        $originalPath = $asset->getOriginal('path');
        $fallbackPath = $asset->path();

        // Narrow the resolved path to a string for filesystem helpers
        if (is_string($originalPath)) {
            $path = $originalPath;
        } elseif (is_scalar($fallbackPath)) {
            $path = (string) $fallbackPath;
        } else {
            $path = '';
        }

        // Find the asset model by container and path
        $model = Asset::where(
            'container',
            $container
        )
            ->where(
                'path',
                $path
            )
            ->first();

        // Check if model was not found by path
        if (
            ! $model // Model not found in database
        ) {
            // ---
            // Try to find by container and basename
            // ---

            // Extract the basename from the path
            $basename = basename($path);

            // Extract the folder from the path
            $folder = dirname($path);

            // Check if folder is root directory
            if (
                $folder === '.' // Folder is current directory
                || $folder === '' // Folder is empty string
            ) {
                // Set folder to root path
                $folder = '/';
            }

            // Find model by container, folder, and basename
            $model = Asset::where(
                'container',
                $container
            )
                ->where(
                    'folder',
                    $folder
                )
                ->where(
                    'basename',
                    $basename
                )
                ->first();
        }

        // Check if the model exists and has an ID
        if (
            ! $model // Check if model was not found
            || ! $model->getKey() // Check if model has no primary key
        ) {
            // Exit early if no model found
            return;
        }

        // Capture the primary key for narrowing
        $primaryKey = $model->getKey();

        // Narrow the primary key to a string for path building
        // A row found by the queries above always has an integer key, which concatenation
        // renders the same with or without the cast, and the '' fallback is never reached.
        // @pest-mutate-ignore: RemoveStringCast, EmptyStringToNotEmpty
        $modelKey = is_scalar($primaryKey) ? (string) $primaryKey : '';

        // Build the path to the metadata YAML file
        $yamlPath = Config::string('asset_metadata.yaml_directory')
            .'/'.$modelKey.'.yml';

        // Check if the metadata YAML file exists
        if (
            File::exists($yamlPath) // Metadata YAML file exists on filesystem
            && $this->yamlDescribesAsset(
                $yamlPath,
                $container,
                $path
            )
        ) {
            // Delete the metadata YAML file
            File::delete($yamlPath);
        }
    }

    /**
     * Confirm on-disk YAML metadata belongs to the asset being deleted.
     *
     * SQLite auto-increment ids can collide across unrelated rows when probe
     * assets never wrote YAML (see `Asset::$skipYamlSync`); never delete a
     * committed file unless its `container` and `path` match this deletion.
     *
     * @param  string  $yamlPath  Absolute path to content/models/assets/{id}.yml
     * @param  string  $container  Statamic asset container handle
     * @param  string  $path  Asset path within the container
     */
    private function yamlDescribesAsset(
        string $yamlPath,
        string $container,
        string $path
    ): bool {
        try {
            $data = Yaml::parseFile($yamlPath);
        } catch (\Throwable) {
            return false;
        }

        // Without this return the ?? reads below give null for a scalar, so the
        // comparison already answers false.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! is_array($data)) {
            return false;
        }

        return ($data['container'] ?? null) === $container
            && ($data['path'] ?? null) === $path;
    }
}
