<?php

declare(strict_types=1);

use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1060 — the per-entry `searchable` editor toggle on the clinical ontology + AoE
 * overview blueprints. It gates whether the entry participates in interactive site
 * search (UAMS-Web/uams-statamic-archive#1438) and whether a condition/service surfaces an Area of Expertise in
 * the faceted search (UAMS-Web/uams-statamic-archive#1489), without affecting routing or the sitemap.
 *
 * The toggle is a NEW boolean field (`uamsweb_entry_search.searchable`, default on),
 * deliberately distinct from the `searchable_query` query-scope selector that the
 * UAMS-Web/uams-statamic-archive#804/UAMS-Web/uams-statamic-archive#1052 normalization dropped and `ClinicalConditionBlueprintTest` keeps
 * dropped. Delivering the gate's editorial purpose (deferred with UAMS-Web/uams-statamic-archive#1060 until the
 * interactive search surface existed, now shipped as UAMS-Web/uams-statamic-archive#1504) does not reopen that
 * removal.
 *
 * @command  php artisan test --compact tests/Feature/Blueprints/ClinicalSearchableFieldTest.php
 */
it('exposes an editor searchable toggle defaulting to on', function (string $blueprintHandle) {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find($blueprintHandle);
    expect($blueprint)->not->toBeNull();

    /** @var Field $field */
    $field = $blueprint->fields()->all()->get('searchable');
    expect($field)->not->toBeNull();
    expect($field->type())->toBe('toggle');
    expect($field->config()['default'] ?? null)->toBeTrue();
})->with([
    'clinical_expertise' => ['collections.clinical_expertise.clinical_expertise'],
    'clinical_condition' => ['collections.clinical_conditions.clinical_condition'],
    'clinical_service' => ['collections.clinical_services.clinical_service'],
]);

it('adds a fresh searchable toggle without reviving the dropped searchable_query field', function () {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find('collections.clinical_conditions.clinical_condition');
    $fields = $blueprint->fields()->all();

    // The new editor gate is present…
    expect($fields->has('searchable'))->toBeTrue();
    // …but the query-scope selector the UAMS-Web/uams-statamic-archive#804/UAMS-Web/uams-statamic-archive#1052 normalization dropped stays dropped.
    expect($fields->has('searchable_query'))->toBeFalse();
});
