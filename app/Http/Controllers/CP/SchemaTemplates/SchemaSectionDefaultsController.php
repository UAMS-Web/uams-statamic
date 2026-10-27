<?php

declare(strict_types=1);

namespace App\Http\Controllers\CP\SchemaTemplates;

use App\Services\SchemaTemplates\SchemaSectionDefaultsRepository;
use App\Services\SchemaTemplates\SchemaSiteDefaultsRepository;
use App\Services\SchemaTemplates\SchemaTemplatesFields;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Statamic\CP\PublishForm;
use Statamic\Entries\Collection as CollectionInstance;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Blueprint as FieldsBlueprint;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Taxonomies\Taxonomy as TaxonomyInstance;

/**
 * CP controller for viewing and editing section-level schema-template defaults in the
 * schema-templates cascade.
 */
class SchemaSectionDefaultsController extends CpController
{
    public function index(): InertiaResponse
    {
        $this->authorize('edit schema section defaults');

        /** @var \Illuminate\Support\Collection<int|string, CollectionInstance> $allCollections */
        $allCollections = Collection::all();

        $collections = $allCollections
            ->map(fn (CollectionInstance $c): array => [
                'title' => $c->title(),
                'handle' => $c->handle(),
                'icon' => $c->icon(),
                'type' => 'collection',
            ])
            ->values()
            ->all();

        /** @var \Illuminate\Support\Collection<int|string, TaxonomyInstance> $allTaxonomies */
        $allTaxonomies = Taxonomy::all();

        $taxonomies = $allTaxonomies
            ->map(fn (TaxonomyInstance $t): array => [
                'title' => $t->title(),
                'handle' => $t->handle(),
                'icon' => 'taxonomies',
                'type' => 'taxonomy',
            ])
            ->values()
            ->all();

        return Inertia::render('app::SchemaTemplatesSectionDefaultsIndex', [
            'collections' => $collections,
            'taxonomies' => $taxonomies,
        ]);
    }

    public function edit(string $sectionType, string $sectionHandle): mixed
    {
        $this->authorize('edit schema section defaults');

        $repository = app(SchemaSectionDefaultsRepository::class);
        $siteRepo = app(SchemaSiteDefaultsRepository::class);
        /** @var \Statamic\Sites\Site $selectedSite */
        $selectedSite = Site::selected();
        $selectedSiteHandle = $selectedSite->handle();
        $siteHandle = is_scalar($selectedSiteHandle) ? (string) $selectedSiteHandle : '';
        $siteDefaults = $siteRepo->get($siteHandle);
        $sectionDefaults = $repository->get($sectionHandle, $sectionType);

        $section = $sectionType === 'collection'
            ? Collection::find($sectionHandle)
            : Taxonomy::find($sectionHandle);
        $rawSectionTitle = $section && method_exists($section, 'title') ? $section->title() : $sectionHandle;
        $sectionTitle = is_scalar($rawSectionTitle) ? (string) $rawSectionTitle : $sectionHandle;

        $siteDisabled = ($siteDefaults['enabled'] ?? '') === 'false' || ($siteDefaults['enabled'] ?? '') === false;
        $defaultInheritTemplate = ! $siteDisabled;

        $values = array_merge([
            'enabled' => 'inherit',
            'inherit_webpage_provider' => $defaultInheritTemplate,
            'webpage_provider' => '',
            'inherit_main_content_provider' => $defaultInheritTemplate,
            'main_content_provider' => '',
        ], $sectionDefaults);

        $updateRoute = cp_route('schema-templates.section-defaults.update', [$sectionType, $sectionHandle]);

        return PublishForm::make($this->sectionBlueprint($siteDisabled))
            ->icon('folder')
            ->title($sectionTitle.' — '.__('Schema Section Defaults'))
            ->values($values)
            ->submittingTo(is_scalar($updateRoute) ? (string) $updateRoute : '');
    }

    /**
     * @return array{saved: bool}
     */
    public function update(string $sectionType, string $sectionHandle, Request $request): array
    {
        $this->authorize('edit schema section defaults');

        $siteRepo = app(SchemaSiteDefaultsRepository::class);
        /** @var \Statamic\Sites\Site $selectedSite */
        $selectedSite = Site::selected();
        $selectedSiteHandle = $selectedSite->handle();
        $siteDefaults = $siteRepo->get(is_scalar($selectedSiteHandle) ? (string) $selectedSiteHandle : '');
        $siteDisabled = ($siteDefaults['enabled'] ?? '') === 'false' || ($siteDefaults['enabled'] ?? '') === false;

        /** @var array<string, mixed> $values */
        $values = PublishForm::make($this->sectionBlueprint($siteDisabled))->submit($request->all());
        $filtered = array_filter($values, fn ($v): bool => $v !== null && $v !== '');

        app(SchemaSectionDefaultsRepository::class)->put($sectionHandle, $sectionType, $filtered);

        return ['saved' => true];
    }

    protected function sectionBlueprint(bool $siteDisabled): FieldsBlueprint
    {
        return SchemaTemplatesFields::toBlueprint(
            SchemaTemplatesFields::sectionDefaults($siteDisabled)
        );
    }
}
