<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Support\RequestAttributeMemo;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Site;
use Symfony\Component\Yaml\Yaml;

/**
 * Read/write Schema Templates site defaults (enabled, template).
 * Stored in resources/addons/schema-templates.yaml.
 */
class SchemaSiteDefaultsRepository
{
    protected const string ADDON_PATH = 'resources/addons/schema-templates.yaml';

    /**
     * Request attribute name for memoized YAML parse in `read`.
     */
    private const string MEMO_BAG = 'schema_site_defaults_repository_read';

    /**
     * Get site defaults for a site (or default site).
     *
     * @return array{enabled: bool, template: string}|array<string, mixed>
     */
    public function get(string $siteHandle = 'default'): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->read();
        $siteKey = Site::multiEnabled() ? $siteHandle : 'default';
        $siteDefaults = $data['site_defaults'] ?? [];
        $values = \is_array($siteDefaults) ? ($siteDefaults[$siteKey] ?? []) : [];

        /** @var array<string, mixed> $narrowed */
        $narrowed = \is_array($values) ? $values : [];

        return $narrowed;
    }

    /**
     * Save site defaults for a site.
     *
     * @param  array<string, mixed>  $values
     */
    public function put(string $siteHandle, array $values): void
    {
        $path = base_path(self::ADDON_PATH);
        /** @var array<string, mixed> $data */
        $data = $this->read();
        $siteKey = Site::multiEnabled() ? $siteHandle : 'default';
        if (! isset($data['site_defaults'])) {
            $data['site_defaults'] = [];
        }

        /** @var array<string, mixed> $siteDefaults */
        $siteDefaults = $data['site_defaults'];
        $siteDefaults[$siteKey] = $values;
        $data['site_defaults'] = $siteDefaults;
        $dir = \dirname($path);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($path, Yaml::dump($data, 4, 2));

        RequestAttributeMemo::forget(self::MEMO_BAG);
    }

    /**
     * @return array<string, mixed>
     */
    protected function read(): array
    {
        $remembered = RequestAttributeMemo::remember(
            self::MEMO_BAG,
            'yaml',
            function (): array {
                $path = base_path(self::ADDON_PATH);

                // A missing file and an empty one read the same: get() and put() both
                // treat an absent `site_defaults` as an empty map.
                if (! File::exists($path)) {
                    // @pest-mutate-ignore: RemoveArrayItem
                    return ['site_defaults' => []];
                }

                $parsed = Yaml::parseFile($path);

                // As above, the fallback's one key changes nothing a caller reads.
                // @pest-mutate-ignore: RemoveArrayItem
                return \is_array($parsed) ? $parsed : ['site_defaults' => []];
            }
        );

        /** @var array<string, mixed> $narrowed */
        // As above, the fallback's one key changes nothing a caller reads.
        // @pest-mutate-ignore: RemoveArrayItem
        $narrowed = \is_array($remembered) ? $remembered : ['site_defaults' => []];

        return $narrowed;
    }
}
