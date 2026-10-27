<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Override;
use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Statamic\Eloquent\Assets\AssetModel;
use Statamic\Facades\AssetContainer;
use Statamic\Filesystem\FlysystemAdapter;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Asset metadata model: SQLite + YAML dual-write (no Orbit).
 *
 * Extends Statamic's AssetModel for the Eloquent driver. Uses the asset_metadata
 * connection (dedicated SQLite) and writes the same data to `{id}.yml` under
 * `config('asset_metadata.yaml_directory')` — `content/models/assets/` in
 * production — for version control.
 *
 * On save, meta is normalized so `last_modified` (Unix) is always set for
 * Statamic CP compatibility (see `ensureMetaLastModified`).
 */
#[Connection('asset_metadata')]
#[Table(name: 'assets_meta')]
class Asset extends AssetModel
{
    /**
     * When true, saved/deleted events do not write/delete YAML (e.g., during sync).
     */
    public static bool $skipYamlSync = false;

    /**
     * Ensure Statamic asset meta includes a numeric `last_modified` (Unix
     * seconds).
     *
     * File-backed assets normally get this from the container disk; Eloquent
     * rows restored from YAML or without a binary file often omit it, which
     * breaks CP asset listing when core Statamic passes null to
     * `Carbon::createFromTimestamp()`.
     *
     * When `last_modified` is missing or non-numeric, sets it from (in order)
     * storage mtime, `updated_at`, `created_at`, or the current time.
     *
     * @param  array<string, mixed>  $meta  Asset meta array (may include `data` and other Statamic keys)
     * @param  string  $containerHandle  Statamic asset container handle for disk lookup
     * @param  string  $assetPath  Path of the asset within the container
     * @param  Carbon|null  $updatedAt  Row `updated_at` fallback when disk mtime is unavailable
     * @param  Carbon|null  $createdAt  Row `created_at` fallback when disk and `updated_at` are unavailable
     * @return array<string, mixed> Meta with `last_modified` set to a Unix timestamp
     */
    public static function ensureMetaLastModified(
        array $meta,
        string $containerHandle,
        string $assetPath,
        ?Carbon $updatedAt = null,
        ?Carbon $createdAt = null
    ): array {
        // Check if meta already has a numeric last_modified
        if (
            isset($meta['last_modified'])
            && is_numeric($meta['last_modified'])
        ) {
            // Return meta as-is
            return $meta;
        }

        // Initialize timestamp from disk or row timestamps
        $timestamp = null;

        // Check if container handle and asset path are not empty
        if (
            $containerHandle !== ''
            && $assetPath !== ''
        ) {
            // Attempt to read mtime from the asset container disk when possible
            try {
                // Resolve the Statamic asset container
                $container = AssetContainer::find($containerHandle);

                // Check if container is the concrete Statamic implementation (has disk())
                if ($container instanceof StatamicAssetContainer) {
                    // Get the container filesystem disk
                    /** @var FlysystemAdapter $disk */
                    $disk = $container->disk();

                    // Check if the object exists on disk
                    if ($disk->exists($assetPath)) {
                        // Read last modified time from storage
                        $timestamp = $disk->lastModified($assetPath);
                    }
                }
            } catch (Throwable) {
                // Ignore missing container/disk errors; use row timestamps below
            }
        }

        // Check if updated_at can supply a fallback timestamp
        if (
            $timestamp === null
            && $updatedAt instanceof Carbon
        ) {
            // Set timestamp from updated_at
            $timestamp = $updatedAt->getTimestamp();
        }

        // Check if created_at can supply a fallback timestamp
        if (
            $timestamp === null
            && $createdAt instanceof Carbon
        ) {
            // Set timestamp from created_at
            $timestamp = $createdAt->getTimestamp();
        }

        // Use current time when no other source is available
        if ($timestamp === null) {
            // Set timestamp from current time
            $timestamp = time();
        }

        // Persist last_modified alongside blueprint `data` and other meta keys
        $meta['last_modified'] = $timestamp;

        // Return meta with `last_modified` set to a Unix timestamp
        return $meta;
    }

    /**
     * Parse a timestamp attribute via `getAttribute` (avoids `__get` for static
     * analysis).
     *
     * @param  Asset  $model  The asset row being saved
     * @param  string  $key  `created_at` or `updated_at`
     * @return Carbon|null Parsed instant or null when absent or empty
     */
    private static function carbonFromModelAttribute(
        Asset $model,
        string $key
    ): ?Carbon {
        // Read raw value without magic property access
        $value = $model->getAttribute($key);

        // Check if the attribute is already a Carbon instance
        if ($value instanceof Carbon) {
            // Return Carbon instance as-is
            return $value;
        }

        // Check if the attribute is missing or empty string
        if (
            $value === null
            || $value === ''
        ) {
            // Return null when attribute is missing or empty string
            return null;
        }

        // Parse DateTimeInterface values to Carbon
        if ($value instanceof \DateTimeInterface) {
            // Return parsed DateTimeInterface instance
            return Carbon::parse($value);
        }

        // Parse scalar (string/int) values to Carbon
        if (is_scalar($value)) {
            // Coerce bool to int to match Carbon::parse's accepted scalar types
            $scalar = is_bool($value) ? (int) $value : $value;

            // Return parsed scalar value
            return Carbon::parse($scalar);
        }

        // Return null for unparseable non-scalar values
        return null;
    }

    /**
     * Constructor: use asset_metadata connection and assets_meta table only.
     */
    public function __construct(
        array $attributes = []
    ) {
        // Delegate to parent to set table prefix and default connection from config
        parent::__construct($attributes);

        // Override connection to dedicated asset metadata SQLite
        $this->setConnection('asset_metadata');

        // Override table name (no global prefix for this connection)
        $this->setTable('assets_meta');
    }

    /**
     * Boot the model and register saved/deleted for YAML dual-write.
     */
    #[Override]
    protected static function boot(): void
    {
        // Register parent boot logic (e.g., trait boot methods)
        parent::boot();

        // Normalize meta.last_modified before every persist (CP, imports, API)
        static::saving(function (Asset $model): void {
            // Read raw meta (array or object from casts / Statamic)
            $raw = $model->getAttribute('meta');

            // ---
            // Normalize meta to a plain array for merging
            // ---

            // Check if raw meta is an instance of \stdClass
            if ($raw instanceof \stdClass) {
                // Decode raw meta to an array
                /** @var array<string, mixed> $meta */
                $meta = json_decode(
                    json_encode($raw, JSON_THROW_ON_ERROR),
                    true
                );
            } elseif (\is_array($raw)) { // Check if raw meta is an array
                // Set meta to raw meta
                /** @var array<string, mixed> $meta */
                $meta = $raw;
            } else {
                // Set meta to an empty array
                $meta = [];
            }

            // Check if last_modified is already a numeric Unix timestamp
            $hasLastModified = isset($meta['last_modified'])
                && is_numeric($meta['last_modified']);

            // ---
            // Skip when meta already carries last_modified
            // ---

            // Check if meta already carries last_modified
            if ($hasLastModified) {
                // Return early
                return;
            }

            // Resolve updated_at as Carbon when present
            $updatedAt = self::carbonFromModelAttribute(
                $model,
                'updated_at'
            );

            // Resolve created_at as Carbon when present
            $createdAt = self::carbonFromModelAttribute(
                $model,
                'created_at'
            );

            // Read container handle, coercing only scalar values to string
            $containerRaw = $model->getAttribute('container');
            $containerHandle = is_scalar($containerRaw)
                ? (string) $containerRaw
                : '';

            // Read asset path, coercing only scalar values to string
            $pathRaw = $model->getAttribute('path');
            $assetPath = is_scalar($pathRaw)
                ? (string) $pathRaw
                : '';

            // Merge last_modified using disk or row timestamps
            $model->setAttribute(
                'meta',
                static::ensureMetaLastModified(
                    $meta,
                    $containerHandle,
                    $assetPath,
                    $updatedAt,
                    $createdAt
                )
            );
        });

        // Write YAML file when model is saved (unless syncing from YAML)
        static::saved(function (Asset $model): void {
            // ---
            // Skip YAML write when syncing from YAML to avoid redundant writes
            // ---

            // Check if YAML write is not skipped
            if (! static::$skipYamlSync) {
                // Write current model data to content/models/assets/{id}.yml
                $model->writeMetadataToYaml();
            }
        });

        // Delete YAML file when model is deleted
        static::deleted(function (Asset $model): void {
            // ---
            // Skip YAML delete when syncing from YAML to avoid redundant deletes
            // ---

            // Check if YAML delete is not skipped
            if (! static::$skipYamlSync) {
                // Delete content/models/assets/{id}.yml from disk
                $model->deleteMetadataYaml();
            }
        });
    }

    /**
     * Absolute path to one row's metadata YAML file.
     *
     * Resolved from config rather than `base_path('content/…')` so the test env
     * can point the dual-write at the disposable `content/` mirror (UAMS-Web/uams-statamic-archive#1916).
     * This write hangs off Eloquent's `saved` event and never touches the
     * Stache, so re-rooting the Stache stores does not reach it — and under test
     * the `:memory:` autoincrement restarts at `1` in every process, which is
     * how a fixture asset comes to overwrite a real image's committed alt text
     * and photographer credit (UAMS-Web/uams-statamic-archive#1919).
     *
     * @param  string  $idString  The row's primary key, as a filename stem
     * @return string Absolute path to `{directory}/{id}.yml`
     */
    private static function metadataYamlPath(string $idString): string
    {
        return sprintf('%s/%s.yml', Config::string('asset_metadata.yaml_directory'), $idString);
    }

    /**
     * Write current model data to the configured `{id}.yml` metadata file.
     */
    protected function writeMetadataToYaml(): void
    {
        // Get primary key for filename (e.g., 1.yml)
        $id = $this->getKey();

        // ---
        // Do not write if model has no key (e.g., not yet saved)
        // ---

        // Check if model has no key
        if ($id === null) {
            // Exit early if no key
            return;
        }

        // Coerce scalar key to string for the filename
        $idString = is_scalar($id) ? (string) $id : '';

        // Build path to the YAML file in the configured metadata directory
        $path = self::metadataYamlPath($idString);

        // Resolve parent directory for the YAML file
        $dir = dirname($path);

        // ---
        // Ensure directory exists before writing
        // ---

        // Check if directory does not exist
        if (! is_dir($dir)) {
            // Create directory
            mkdir(
                $dir,
                0755,
                true
            );
        }

        // Read meta attribute (may be array from cast or string from YAML)
        $meta = $this->getAttribute('meta');

        // Normalize meta to JSON string for YAML file (matches existing format)
        $metaString = \is_string($meta)
            ? $meta
            : json_encode($meta);

        // Get created_at attribute (Carbon when set, null otherwise)
        $createdAtRaw = $this->getAttribute('created_at');
        $createdAt = $createdAtRaw instanceof \DateTimeInterface
            ? $createdAtRaw
            : null;

        // Get updated_at attribute (Carbon when set, null otherwise)
        $updatedAtRaw = $this->getAttribute('updated_at');
        $updatedAt = $updatedAtRaw instanceof \DateTimeInterface
            ? $updatedAtRaw
            : null;

        // Build array matching existing content/models/assets/*.yml structure
        $data = [
            'id' => $id,
            'container' => $this->getAttribute('container'),
            'folder' => $this->getAttribute('folder'),
            'basename' => $this->getAttribute('basename'),
            'filename' => $this->getAttribute('filename'),
            'extension' => $this->getAttribute('extension'),
            'path' => $this->getAttribute('path'),
            'meta' => $metaString,
            'created_at' => $createdAt?->format('Y-m-d\TH:i:sP'),
            'updated_at' => $updatedAt?->format('Y-m-d\TH:i:sP'),
        ];

        // Write YAML to disk using same format as existing asset metadata files
        file_put_contents(
            $path,
            Yaml::dump(
                $data,
                4,
                2,
                Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
            )
        );
    }

    /**
     * Delete the configured `{id}.yml` metadata file when the model is deleted.
     */
    protected function deleteMetadataYaml(): void
    {
        // Get primary key to resolve YAML file path
        $id = $this->getKey();

        // ---
        // Do nothing if model has no key
        // ---

        // Check if model has no key
        if ($id === null) {
            // Exit early if no key
            return;
        }

        // Coerce scalar key to string for the filename
        $idString = is_scalar($id) ? (string) $id : '';

        // Build path to the metadata YAML file
        $path = self::metadataYamlPath($idString);

        // ---
        // Remove file from disk only if it exists
        // ---

        // Check if file exists
        if (file_exists($path)) {
            // Delete file from disk
            unlink($path);
        }
    }

    /**
     * Avoid JSON double-encoding for meta/data when value is already a string.
     *
     * @param  string  $key  The attribute key
     * @param  mixed  $value  The attribute value
     * @return mixed The result of setting the attribute
     */
    #[Override]
    public function setAttribute(
        $key,
        $value
    ) {
        // ---
        // Decode JSON string for meta/data to avoid double-encoding from casts
        // ---

        // Check if key is meta or data and value is a string
        if (
            \in_array(
                $key,
                [
                    'meta',
                    'data',
                ],
                true
            )
            && \is_string($value)
        ) {
            // Decode JSON string for meta/data to avoid double-encoding from casts
            $value = json_decode($value);
        }

        // Delegate to parent to set attribute
        return parent::setAttribute(
            $key,
            $value
        );
    }
}
