<?php

declare(strict_types=1);

namespace App\Http\Controllers\CP\SchemaTemplates;

use App\Services\SchemaTemplates\SchemaBlueprintDefaultsRepository;
use App\Services\SchemaTemplates\SchemaTemplatesFields;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Statamic\CP\PublishForm;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Blueprint as FieldsBlueprint;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Control panel controller for Schema Templates Blueprint Defaults (index, edit, update).
 */
class SchemaBlueprintDefaultsController extends CpController
{
    /**
     * List sections and blueprints for blueprint defaults.
     */
    public function index(): InertiaResponse
    {
        $this->authorize('edit schema blueprint defaults');

        /** @var \Illuminate\Support\Collection<int, \Statamic\Entries\Collection> $allCollections */
        $allCollections = Collection::all();
        $collections = $allCollections
            ->map(function (\Statamic\Entries\Collection $collection): array {
                $handle = $collection->handle();
                /** @var \Illuminate\Support\Collection<int, FieldsBlueprint> $blueprints */
                $blueprints = Blueprint::in('collections/'.(is_scalar($handle) ? $handle : ''));

                return [
                    'title' => $collection->title(),
                    'handle' => $collection->handle(),
                    'icon' => $collection->icon(),
                    'type' => 'collection',
                    'blueprints' => $blueprints->map(fn (FieldsBlueprint $bp): array => [
                        'handle' => $bp->handle(),
                        'title' => $bp->title(),
                    ])->values()->all(),
                ];
            })
            ->filter(fn ($c): bool => \count($c['blueprints']) > 0)
            ->values()
            ->all();

        /** @var \Illuminate\Support\Collection<int, \Statamic\Taxonomies\Taxonomy> $allTaxonomies */
        $allTaxonomies = Taxonomy::all();
        $taxonomies = $allTaxonomies
            ->map(function (\Statamic\Taxonomies\Taxonomy $taxonomy): array {
                $handle = $taxonomy->handle();
                /** @var \Illuminate\Support\Collection<int, FieldsBlueprint> $blueprints */
                $blueprints = Blueprint::in('taxonomies/'.(is_scalar($handle) ? $handle : ''));

                return [
                    'title' => $taxonomy->title(),
                    'handle' => $taxonomy->handle(),
                    'icon' => 'taxonomies',
                    'type' => 'taxonomy',
                    'blueprints' => $blueprints->map(fn (FieldsBlueprint $bp): array => [
                        'handle' => $bp->handle(),
                        'title' => $bp->title(),
                    ])->values()->all(),
                ];
            })
            ->filter(fn ($t): bool => \count($t['blueprints']) > 0)
            ->values()
            ->all();

        return Inertia::render('app::SchemaTemplatesBlueprintDefaultsIndex', [
            'collections' => $collections,
            'taxonomies' => $taxonomies,
        ]);
    }

    /**
     * Show edit form for a blueprint's schema defaults.
     *
     * @param  string  $sectionType  'collection' or 'taxonomy'
     * @param  string  $sectionHandle  Handle of the collection or taxonomy
     * @param  string  $blueprintHandle  Blueprint handle
     */
    public function edit(string $sectionType, string $sectionHandle, string $blueprintHandle): mixed
    {
        $this->authorize('edit schema blueprint defaults');

        $repository = app(SchemaBlueprintDefaultsRepository::class);

        $section = $sectionType === 'collection'
            ? Collection::find($sectionHandle)
            : Taxonomy::find($sectionHandle);
        $sectionTitleValue = $section && method_exists($section, 'title') ? $section->title() : $sectionHandle;
        $sectionTitle = is_scalar($sectionTitleValue) ? $sectionTitleValue : $sectionHandle;

        $blueprint = $sectionType === 'collection'
            ? Blueprint::find(sprintf('collections.%s.%s', $sectionHandle, $blueprintHandle))
            : Blueprint::find(sprintf('taxonomies.%s.%s', $sectionHandle, $blueprintHandle));
        $blueprintTitleValue = $blueprint?->title() ?? $blueprintHandle;
        $blueprintTitle = is_scalar($blueprintTitleValue) ? $blueprintTitleValue : $blueprintHandle;

        $updateRoute = cp_route('schema-templates.blueprint-defaults.update', [
            $sectionType,
            $sectionHandle,
            $blueprintHandle,
        ]);

        return PublishForm::make($this->blueprintBlueprint())
            ->icon('blueprints')
            ->title(sprintf('%s › %s — ', $sectionTitle, $blueprintTitle).__('Schema Blueprint Defaults'))
            ->values($repository->get($sectionHandle, $blueprintHandle, $sectionType))
            ->submittingTo(is_string($updateRoute) ? $updateRoute : '');
    }

    /**
     * Save blueprint defaults.
     *
     * @param  string  $sectionType  'collection' or 'taxonomy'
     * @param  string  $sectionHandle  Handle of the collection or taxonomy
     * @param  string  $blueprintHandle  Blueprint handle
     * @return array{ saved: bool }
     */
    public function update(
        string $sectionType,
        string $sectionHandle,
        string $blueprintHandle,
        Request $request
    ): array {
        $this->authorize('edit schema blueprint defaults');

        $blueprint = $this->blueprintBlueprint();

        /** @var array<string, mixed> $values */
        $values = PublishForm::make($blueprint)->submit($request->all());

        // The repository drops the values left empty
        app(SchemaBlueprintDefaultsRepository::class)->put(
            $sectionHandle,
            $blueprintHandle,
            $sectionType,
            $values
        );

        return ['saved' => true];
    }

    protected function blueprintBlueprint(): FieldsBlueprint
    {
        return SchemaTemplatesFields::toBlueprint(
            SchemaTemplatesFields::blueprintDefaults()
        );
    }
}
