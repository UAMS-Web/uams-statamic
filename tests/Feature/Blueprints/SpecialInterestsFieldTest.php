<?php

declare(strict_types=1);

use App\Listeners\ValidateSpecialInterestsScope;
use App\Services\ComputedFields\Ontology\ImpliedOntologyComputedValues;
use App\Services\ComputedFields\Ontology\SpecialInterestInverseComputedValues;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| UAMS-Web/uams-statamic-archive#1064 — the special-interest fields on Provider + Location
|--------------------------------------------------------------------------
|
| Special Interest is authored as TWO typed `entries` fields —
| `special_interest_clinical_conditions` (→ clinical_conditions) and
| `special_interest_clinical_services` (→ clinical_services) — defined in the
| uamsweb_common_fad fieldset and referenced independently on the person
| (Provider) and clinical_locations (Location) blueprints. They are the retained
| explicit exception path over the transitive C/T inference (UAMS-Web/uams-statamic-archive#1063). The two
| references are authored separately (a value on one profile does not populate the
| other) and configured independently (the Provider fields are gated on the
| clinical-role queries; the Location fields are ungated).
|
| This asserts the field shapes + both references. The specialty-scoped
| validation, the computed inverse, and the schema mappings are covered by their
| own tests.
|
| @command php artisan test --compact tests/Feature/Blueprints/SpecialInterestsFieldTest.php
*/

/** The two typed special-interest fields, keyed by their one target C/T collection. */
const SPECIAL_INTEREST_FIELDS = [
    'clinical_conditions' => 'special_interest_clinical_conditions',
    'clinical_services' => 'special_interest_clinical_services',
];

it('defines each special-interest field as a shared entries field targeting its one C/T collection', function (string $collection, string $handle) {
    /** @var array{fields: array<int, array<string, mixed>>} $fieldset */
    $fieldset = Yaml::parseFile(base_path('resources/fieldsets/uamsweb_common_fad.yaml'));
    /** @var array{field: array<string, mixed>} $field */
    $field = collect($fieldset['fields'])->firstWhere('handle', $handle);

    expect($field)->not->toBeNull()
        ->and($field['field']['type'])->toBe('entries')
        ->and($field['field']['collections'])->toBe([$collection])
        ->and($field['field']['select_across_sites'])->toBeTrue()
        ->and($field['field']['create'])->toBeFalse();
})->with([
    'conditions' => ['clinical_conditions', 'special_interest_clinical_conditions'],
    'services' => ['clinical_services', 'special_interest_clinical_services'],
]);

it('references both special-interest fields as authored entries fields on Provider and Location', function (string $namespace, string $handle) {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in($namespace)->get($handle);

    foreach (SPECIAL_INTEREST_FIELDS as $collection => $field) {
        /** @var Field $blueprintField */
        $blueprintField = $blueprint->field($field);
        expect($blueprintField->type())->toBe('entries')
            ->and($blueprintField->get('collections'))->toBe([$collection]);
    }
})->with([
    'provider' => ['collections/people', 'person'],
    'location' => ['collections/clinical_locations', 'location'],
]);

it('configures the references independently: Provider fields gated on clinical-role queries, Location fields ungated', function (string $field) {
    /** @var Statamic\Fields\Blueprint $providerBlueprint */
    $providerBlueprint = Blueprint::in('collections/people')->get('person');
    /** @var Field $providerField */
    $providerField = $providerBlueprint->field($field);
    /** @var Statamic\Fields\Blueprint $locationBlueprint */
    $locationBlueprint = Blueprint::in('collections/clinical_locations')->get('location');
    /** @var Field $locationField */
    $locationField = $locationBlueprint->field($field);

    // The Provider fields only show for a clinical, direct-patient-care person (mirrors their sibling `expertise`).
    expect($providerField->get('if'))->toBe([
        'clinical_role_query' => 'equals true',
        'direct_patient_care_query' => 'equals true',
    ]);

    // The Location fields are always shown — no clinical-role gate.
    expect($locationField->get('if'))->toBeNull();
})->with([
    'conditions' => ['special_interest_clinical_conditions'],
    'services' => ['special_interest_clinical_services'],
]);

// ---------------------------------------------------------------------------
// Handle contract (UAMS-Web/uams-statamic-archive#1064 review — mirrors UAMS-Web/uams-statamic-archive#1063's constant↔blueprint guard)
// ---------------------------------------------------------------------------
//
// Three classes name the special-interest fields by constant: the scope guard
// (ValidateSpecialInterestsScope::OVERRIDE_FIELDS), the implied-set override union
// (ImpliedOntologyComputedValues::OVERRIDE_FIELD_BY_COLLECTION), and the computed
// inverse (SpecialInterestInverseComputedValues::OVERRIDE_FIELD_BY_COLLECTION). If any
// constant drifts from the actual stored handle, the scope guard, the union, and the
// inverse query all silently no-op — the exact false-green class the UAMS-Web/uams-statamic-archive#1063 review
// caught. These tie the constants (and the blueprint references) to the fieldset
// fields' real handles.

/**
 * The typed special-interest fieldset handles, keyed by their one target C/T collection.
 * Distinguished from the sibling `conditions` / `treatments` fields by the
 * `special_interest_` handle prefix.
 *
 * @return array<string, string>
 */
function specialInterestFieldsetHandles(): array
{
    /** @var array{fields: array<int, array{handle: string, field: array<string, mixed>}>} $fieldset */
    $fieldset = Yaml::parseFile(base_path('resources/fieldsets/uamsweb_common_fad.yaml'));

    $map = [];
    foreach ($fieldset['fields'] as $f) {
        $collections = $f['field']['collections'] ?? null;
        if (($f['field']['type'] ?? null) === 'entries'
            && is_array($collections)
            && count($collections) === 1
            && str_starts_with((string) $f['handle'], 'special_interest_')
        ) {
            /** @var string $collectionHandle */
            $collectionHandle = $collections[0];
            $map[$collectionHandle] = $f['handle'];
        }
    }

    ksort($map);

    return $map;
}

/**
 * A constant (public or private) on a class, read reflectively.
 *
 * @param  class-string  $class
 */
function specialInterestConstant(string $class, string $name): mixed
{
    return (new ReflectionClass($class))->getConstant($name);
}

it('names the special-interest fields with one handle per collection across the fieldset, the reader constants, and both blueprint references', function () {
    $handles = specialInterestFieldsetHandles();
    expect($handles)->toBe([
        'clinical_conditions' => 'special_interest_clinical_conditions',
        'clinical_services' => 'special_interest_clinical_services',
    ]);

    // The scope guard reads exactly these two field handles (order-independent).
    /** @var array<int, string> $scopeFields */
    $scopeFields = specialInterestConstant(ValidateSpecialInterestsScope::class, 'OVERRIDE_FIELDS');
    expect(collect($scopeFields)->sort()->values()->all())->toBe(collect($handles)->sort()->values()->all());

    // The implied-set union and the computed inverse both map each C/T collection to its field.
    /** @var array<string, string> $impliedMap */
    $impliedMap = specialInterestConstant(ImpliedOntologyComputedValues::class, 'OVERRIDE_FIELD_BY_COLLECTION');
    /** @var array<string, string> $inverseMap */
    $inverseMap = specialInterestConstant(SpecialInterestInverseComputedValues::class, 'OVERRIDE_FIELD_BY_COLLECTION');
    ksort($impliedMap);
    ksort($inverseMap);
    expect($impliedMap)->toBe($handles)
        ->and($inverseMap)->toBe($handles);

    // Both blueprints import each fieldset field under the same handle.
    foreach (['collections/people/person', 'collections/clinical_locations/location'] as $blueprintPath) {
        /** @var array{tabs: array<int, array{sections?: array<int, array{fields?: array<int, array<string, mixed>>}>}>} $parsed */
        $parsed = Yaml::parseFile(base_path("resources/blueprints/{$blueprintPath}.yaml"));
        $tabs = $parsed['tabs'];

        foreach ($handles as $handle) {
            $refHandles = collect($tabs)->flatMap(
                fn (array $tab): array => collect($tab['sections'] ?? [])->flatMap(
                    fn (array $section): array => collect($section['fields'] ?? [])
                        ->filter(fn (array $f): bool => ($f['field'] ?? null) === "uamsweb_common_fad.{$handle}")
                        ->pluck('handle')->all()
                )->all()
            )->all();

            expect($refHandles)->toBe([$handle]);
        }
    }
});
