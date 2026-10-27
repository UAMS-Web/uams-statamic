<?php

declare(strict_types=1);

use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| UAMS-Web/uams-statamic-archive#1065 D2 — "this page is about a C/T" aboutness fields on content pages
|--------------------------------------------------------------------------
|
| Conditions & Treatments are data, not pages: a deliberate content page (in the
| `pages` collection) declares it is *about* a clinical condition or service via
| entries-typed aboutness fields — the page is the destination, the C/T is the
| subject. Following the `knows_about_entries` precedent, these are `entries`
| fields targeting `clinical_conditions` + `clinical_services`, and C/T entries
| are not added to the `subject_matter` collection, which stays the
| general-concept vocabulary. They feed the `MedicalWebPage.about`/`mainEntity` JSON-LD emission,
| which per the D2 boundary decision is owned by UAMS-Web/uams-statamic-archive#1067 (this ticket ships the
| fields UAMS-Web/uams-statamic-archive#1067 consumes; UAMS-Web/uams-statamic-archive#1067 emits). Each field is entries-typed and is bound
| to no taxonomy at all.
|
| @command php artisan test --compact tests/Feature/Blueprints/ClinicalAboutnessFieldsTest.php
*/

/**
 * The entries-typed clinical aboutness fields, and whether each is single-select.
 *
 * @var array<string, bool>
 */
const CLINICAL_ABOUTNESS_FIELDS = [
    'about_clinical_entries' => false,
    'mentions_clinical_entries' => false,
    'main_entity_of_page_clinical_entry' => true,
];

/** The two C/T collections every aboutness field targets. */
const CLINICAL_ABOUTNESS_COLLECTIONS = ['clinical_conditions', 'clinical_services'];

it('defines each aboutness field as an entries field targeting both C/T collections', function (string $handle, bool $single) {
    /** @var array{fields: array<int, array<string, mixed>>} $fieldset */
    $fieldset = Yaml::parseFile(base_path('resources/fieldsets/uamsweb_clinical_aboutness.yaml'));

    /** @var array{field: array{type: string, collections: list<string>, create: bool, max_items?: int}} $field */
    $field = collect($fieldset['fields'])->firstWhere('handle', $handle);

    expect($field)->not->toBeNull()
        ->and($field['field']['type'])->toBe('entries')
        ->and($field['field']['collections'])->toBe(CLINICAL_ABOUTNESS_COLLECTIONS)
        ->and($field['field']['create'])->toBeFalse();

    // The main entity is the single primary subject; about/mentions are multi.
    if ($single) {
        expect($field['field']['max_items'] ?? null)->toBe(1);
    } else {
        expect($field['field']['max_items'] ?? null)->toBeNull();
    }
})->with([
    'about' => ['about_clinical_entries', false],
    'mentions' => ['mentions_clinical_entries', false],
    'main_entity' => ['main_entity_of_page_clinical_entry', true],
]);

it('lands all three aboutness fields on the content-page blueprint via the imported fieldset', function (string $handle) {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/pages')->get($handle);

    foreach (array_keys(CLINICAL_ABOUTNESS_FIELDS) as $field) {
        expect($blueprint->hasField($field))->toBeTrue();

        /** @var Field $blueprintField */
        $blueprintField = $blueprint->field($field);
        expect($blueprintField->type())->toBe('entries')
            ->and($blueprintField->get('collections'))->toBe(CLINICAL_ABOUTNESS_COLLECTIONS);
    }
})->with([
    'basic_page' => ['basic_page'],
    'landing_page' => ['landing_page'],
]);

it('caps the main-entity field at one selection and leaves about/mentions multi', function (string $handle) {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/pages')->get($handle);

    /** @var Field $mainEntityField */
    $mainEntityField = $blueprint->field('main_entity_of_page_clinical_entry');
    expect($mainEntityField->get('max_items'))->toBe(1);

    /** @var Field $aboutField */
    $aboutField = $blueprint->field('about_clinical_entries');
    expect($aboutField->get('max_items'))->toBeNull();

    /** @var Field $mentionsField */
    $mentionsField = $blueprint->field('mentions_clinical_entries');
    expect($mentionsField->get('max_items'))->toBeNull();
})->with([
    'basic_page' => ['basic_page'],
    'landing_page' => ['landing_page'],
]);

it('types every C/T aboutness field as entries, bound to no taxonomy', function (string $handle) {
    // AC #6: no parallel clinical vocabulary is created. Each aboutness field is
    // entries-typed and carries no `taxonomies` key, so none can be bound to any
    // taxonomy.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/pages')->get($handle);

    foreach (array_keys(CLINICAL_ABOUTNESS_FIELDS) as $field) {
        /** @var Field $blueprintField */
        $blueprintField = $blueprint->field($field);
        $config = $blueprintField->config();
        expect($config['type'] ?? $blueprintField->type())->toBe('entries');
        expect($config)->not->toHaveKey('taxonomies');
    }
})->with([
    'basic_page' => ['basic_page'],
    'landing_page' => ['landing_page'],
]);
