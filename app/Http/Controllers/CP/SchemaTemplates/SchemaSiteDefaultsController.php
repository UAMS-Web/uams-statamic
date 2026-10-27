<?php

declare(strict_types=1);

namespace App\Http\Controllers\CP\SchemaTemplates;

use App\Services\SchemaTemplates\SchemaSiteDefaultsRepository;
use App\Services\SchemaTemplates\SchemaTemplatesFields;
use Illuminate\Http\Request;
use Statamic\CP\PublishForm;
use Statamic\Facades\Site;
use Statamic\Fields\Blueprint as FieldsBlueprint;
use Statamic\Http\Controllers\CP\CpController;

/**
 * CP controller for viewing and updating site-level schema-template defaults in the
 * schema-templates cascade.
 */
class SchemaSiteDefaultsController extends CpController
{
    public function edit(): mixed
    {
        $this->authorize('edit schema site defaults');

        /** @var \Statamic\Sites\Site $selectedSite */
        $selectedSite = Site::selected();
        $handle = $selectedSite->handle();
        $siteHandle = is_scalar($handle) ? (string) $handle : '';
        $repository = app(SchemaSiteDefaultsRepository::class);
        $values = $repository->get($siteHandle);

        $route = cp_route('schema-templates.site-defaults.update');
        $updateUrl = is_scalar($route) ? (string) $route : '';

        return PublishForm::make($this->siteBlueprint())
            ->icon('earth')
            ->title(__('Schema Templates').' — '.__('Site Defaults'))
            ->values($values)
            ->submittingTo($updateUrl);
    }

    /**
     * @return array{saved: bool}
     */
    public function update(Request $request): array
    {
        $this->authorize('edit schema site defaults');

        /** @var array<string, mixed> $values */
        $values = PublishForm::make($this->siteBlueprint())->submit($request->all());
        $filtered = array_filter($values, fn ($v): bool => $v !== null && $v !== '');

        /** @var \Statamic\Sites\Site $selectedSite */
        $selectedSite = Site::selected();
        $handle = $selectedSite->handle();
        $siteHandle = is_scalar($handle) ? (string) $handle : '';
        app(SchemaSiteDefaultsRepository::class)->put($siteHandle, $filtered);

        return ['saved' => true];
    }

    protected function siteBlueprint(): FieldsBlueprint
    {
        return SchemaTemplatesFields::toBlueprint(
            SchemaTemplatesFields::siteDefaults()
        );
    }
}
