<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Support\RequestAttributeMemo;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Taxonomies\Taxonomy as StatamicTaxonomy;

/**
 * Read/write Schema Templates blueprint defaults (enabled, inherit_template, parameter).
 * Stored in collection/taxonomy YAML under inject.schema_templates.blueprint_defaults.
 */
class SchemaBlueprintDefaultsRepository
{
    protected const string CASCADE_ROOT = 'schema_templates';

    protected const string BLUEPRINT_KEY = 'blueprint_defaults';

    /**
     * Request attribute name for memoized `get` results.
     */
    private const string MEMO_BAG = 'schema_blueprint_defaults_repository';

    /**
     * Get blueprint defaults for a section and blueprint.
     *
     * @return array{enabled?: string, inherit_template?: bool, parameter?: string}
     */
    public function get(string $sectionHandle, string $blueprintHandle, string $type = 'collection'): array
    {
        $memoKey = sprintf('%s:%s:%s', $type, $sectionHandle, $blueprintHandle);

        /** @var array{enabled?: string, inherit_template?: bool, parameter?: string} $defaults */
        $defaults = RequestAttributeMemo::remember(
            self::MEMO_BAG,
            $memoKey,
            function () use ($sectionHandle, $blueprintHandle, $type): array {
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

                $all = $root[self::BLUEPRINT_KEY] ?? [];
                $all = \is_array($all) ? $all : [];

                $defaults = $all[$blueprintHandle] ?? [];

                return \is_array($defaults) ? $defaults : [];
            }
        );

        return $defaults;
    }

    /**
     * Save blueprint defaults.
     *
     * @param  array<string, mixed>  $values
     */
    public function put(string $sectionHandle, string $blueprintHandle, string $type, array $values): void
    {
        $section = $type === 'taxonomy'
            ? Taxonomy::find($sectionHandle)
            : Collection::find($sectionHandle);

        if (! $section instanceof StatamicCollection && ! $section instanceof StatamicTaxonomy) {
            return;
        }

        /** @var \Illuminate\Support\Collection<string, mixed> $cascadeData */
        $cascadeData = $section->cascade();
        $cascade = $cascadeData->all();
        $root = $cascade[self::CASCADE_ROOT] ?? [];
        $root = \is_array($root) ? $root : [];

        $defaults = $root[self::BLUEPRINT_KEY] ?? [];
        $defaults = \is_array($defaults) ? $defaults : [];

        $values = array_filter($values, fn ($v): bool => $v !== null && $v !== '');

        if ($values === []) {
            unset($defaults[$blueprintHandle]);
        } else {
            $defaults[$blueprintHandle] = $values;
        }

        if ($defaults === []) {
            unset($root[self::BLUEPRINT_KEY]);
        } else {
            $root[self::BLUEPRINT_KEY] = $defaults;
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
