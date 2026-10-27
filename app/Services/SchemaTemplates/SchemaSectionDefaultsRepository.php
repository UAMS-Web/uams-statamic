<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Support\RequestAttributeMemo;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Taxonomies\Taxonomy as StatamicTaxonomy;

/**
 * Read/write Schema Templates section defaults (enabled, inherit_template, template).
 * Stored in collection/taxonomy YAML under inject.schema_templates.section_defaults.
 */
class SchemaSectionDefaultsRepository
{
    protected const string CASCADE_ROOT = 'schema_templates';

    protected const string SECTION_KEY = 'section_defaults';

    /**
     * Request attribute name for memoized `get` results.
     */
    private const string MEMO_BAG = 'schema_section_defaults_repository';

    /**
     * Get section defaults for a collection or taxonomy.
     *
     * @return array{enabled?: string, inherit_template?: bool, template?: string}
     */
    public function get(string $sectionHandle, string $type = 'collection'): array
    {
        $memoKey = sprintf('%s:%s', $type, $sectionHandle);

        /** @var array{enabled?: string, inherit_template?: bool, template?: string} $defaults */
        $defaults = RequestAttributeMemo::remember(
            self::MEMO_BAG,
            $memoKey,
            function () use ($sectionHandle, $type): array {
                $section = $type === 'taxonomy'
                    ? Taxonomy::find($sectionHandle)
                    : Collection::find($sectionHandle);

                if (! $section instanceof StatamicCollection && ! $section instanceof StatamicTaxonomy) {
                    return [];
                }

                /** @var \Illuminate\Support\Collection<string, mixed> $cascade */
                $cascade = $section->cascade();
                $root = $cascade->get(self::CASCADE_ROOT, []);
                $root = \is_array($root) ? $root : [];

                $defaults = $root[self::SECTION_KEY] ?? [];

                return \is_array($defaults) ? $defaults : [];
            }
        );

        return $defaults;
    }

    /**
     * Save section defaults.
     *
     * @param  array<string, mixed>  $values
     */
    public function put(string $sectionHandle, string $type, array $values): void
    {
        $section = $type === 'taxonomy'
            ? Taxonomy::find($sectionHandle)
            : Collection::find($sectionHandle);

        if (! $section instanceof StatamicCollection && ! $section instanceof StatamicTaxonomy) {
            return;
        }

        /** @var \Illuminate\Support\Collection<string, mixed> $cascadeBag */
        $cascadeBag = $section->cascade();
        $cascade = $cascadeBag->all();
        $root = $cascade[self::CASCADE_ROOT] ?? [];
        $root = \is_array($root) ? $root : [];

        $values = array_filter($values, fn ($v): bool => $v !== null && $v !== '');

        if ($values === []) {
            unset($root[self::SECTION_KEY]);
        } else {
            $root[self::SECTION_KEY] = $values;
        }

        if ($root === []) {
            unset($cascade[self::CASCADE_ROOT]);
        } else {
            $cascade[self::CASCADE_ROOT] = $root;
        }

        /** @var StatamicCollection|StatamicTaxonomy $section */
        $section = $section->cascade($cascade);
        $section->save();

        RequestAttributeMemo::forget(self::MEMO_BAG);
    }
}
