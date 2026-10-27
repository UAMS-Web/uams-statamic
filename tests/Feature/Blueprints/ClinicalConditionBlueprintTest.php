<?php

declare(strict_types=1);

use App\Scopes\EntriesBySchemaType;
use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Scope;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Locks the UAMS-Web/uams-statamic-archive#804/UAMS-Web/uams-statamic-archive#1052 normalization of the clinical_condition blueprint: the
 * flat MedicalCondition subtype cascade collapses into one chained `schema_type`
 * field, dependent fields/relationships gate off its resolved path, the
 * condition-targeting relationships scope on the new resolved-type scopes, and
 * the legacy `clinical_services` catch-all carries the migrate-then-clear
 * deprecation.
 *
 * @command  php artisan test --compact tests/Feature/Blueprints/ClinicalConditionBlueprintTest.php
 */
beforeEach(function () {
    expect(Blueprint::find('collections.clinical_conditions.clinical_condition'))->not->toBeNull();
});

/**
 * Resolve the `clinical_condition` blueprint's fields, keyed by handle.
 *
 * @return Collection<string, Field>
 */
function clinicalConditionFields(): Collection
{
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find('collections.clinical_conditions.clinical_condition');
    /** @var Collection<string, Field> $fields */
    $fields = $blueprint->fields()->all();

    return $fields;
}

/**
 * Resolve a field's config array by handle (empty when the field is absent).
 *
 * @return array<string, mixed>
 */
function clinicalConditionConfig(string $handle): array
{
    /** @var array<string, mixed> $config */
    $config = clinicalConditionFields()->get($handle)?->config() ?? [];

    return $config;
}

/**
 * Resolve a field's `if` conditions by handle.
 */
function clinicalConditionIf(string $handle): mixed
{
    return clinicalConditionConfig($handle)['if'] ?? [];
}

/**
 * Resolve a field's `query_scopes` by handle.
 */
function clinicalConditionScopes(string $handle): mixed
{
    return clinicalConditionConfig($handle)['query_scopes'] ?? [];
}

it('consolidates the subtype cascade into one chained schema_type field', function () {
    /** @var Field $field */
    $field = clinicalConditionFields()->get('schema_type');
    expect($field)->not->toBeNull();
    expect($field->type())->toBe('schema_type');
    expect(clinicalConditionConfig('schema_type')['mode'])->toBe('chained');
    expect(clinicalConditionConfig('schema_type')['starting_types'])->toBe(['MedicalCondition']);
});

it('scopes infectious agent class to InfectiousAgentClass members', function () {
    /** @var Field $field */
    $field = clinicalConditionFields()->get('schema_infectious_agent_class');
    expect($field->type())->toBe('schema_type');
    expect(clinicalConditionConfig('schema_infectious_agent_class')['mode'])->toBe('single');
    expect(clinicalConditionConfig('schema_infectious_agent_class')['starting_types'])->toBe(['InfectiousAgentClass']);
});

it('gates dependent fields off the resolved schema_type path', function () {
    expect(clinicalConditionIf('schema_infectious_agent'))->toBe(['schema_type.selections' => 'contains InfectiousDisease']);
    expect(clinicalConditionIf('schema_infectious_agent_class'))->toBe(['schema_type.selections' => 'contains InfectiousDisease']);
    expect(clinicalConditionIf('schema_additional_type'))->toBe(['schema_type.value' => 'not empty']);
    expect(clinicalConditionIf('schema_identifying_exam'))->toBe(['schema_type.selections' => 'contains MedicalSign']);
    expect(clinicalConditionIf('schema_identifying_test'))->toBe(['schema_type.selections' => 'contains MedicalSign']);
    expect(clinicalConditionIf('schema_is_sign_or_symptom_of'))->toBe(['schema_type.selections' => 'contains MedicalSignOrSymptom']);
});

it('scopes the schema relationships on resolved-type scopes', function () {
    expect(clinicalConditionScopes('schema_has_sign_or_symptom'))->toBe(['exclude_self', 'entries_by_schema_type_medical_sign_or_symptom']);
    expect(clinicalConditionScopes('schema_primary_prevention'))->toBe(['entries_by_schema_type_medical_therapy']);
    expect(clinicalConditionScopes('schema_secondary_prevention'))->toBe(['entries_by_schema_type_medical_therapy']);
    expect(clinicalConditionScopes('schema_possible_treatment'))->toBe(['entries_by_schema_type_medical_therapy']);
    expect(clinicalConditionScopes('schema_identifying_exam'))->toBe(['entries_by_schema_type_physical_exam']);
    expect(clinicalConditionScopes('schema_identifying_test'))->toBe(['entries_by_schema_type_medical_test']);
    expect(clinicalConditionScopes('schema_typical_test'))->toBe(['entries_by_schema_type_medical_test']);

    // No relationship still references a deleted flat condition-subtype scope.
    $allScopes = clinicalConditionFields()->map(fn ($f) => $f->config()['query_scopes'] ?? [])->flatten();
    expect($allScopes)->not->toContain('schema_type_medical_sign_or_symptom');
    expect($allScopes)->not->toContain('schema_type_physical_exam');
});

it('registers the new resolved-type scopes the relationships use', function () {
    expect(Scope::find('entries_by_schema_type_medical_sign_or_symptom'))
        ->toBeInstanceOf(EntriesBySchemaType::class);
    expect(Scope::find('entries_by_schema_type_physical_exam'))
        ->toBeInstanceOf(EntriesBySchemaType::class);
});

it('deprecates the legacy clinical_services relationship (migrate-then-clear)', function () {
    $config = clinicalConditionConfig('clinical_services');

    expect($config['validate'] ?? [])->toContain('prohibited');
    expect($config['always_save'] ?? null)->toBeTrue();
    expect($config['if'] ?? [])->toBe(['clinical_services' => 'not empty']);

    /** @var Field $clinicalServicesField */
    $clinicalServicesField = clinicalConditionFields()->get('clinical_services');
    /** @var array<string, mixed> $fieldRules */
    $fieldRules = $clinicalServicesField->rules();
    $rules = $fieldRules['clinical_services'];
    expect($rules)->toContain('prohibited');
    expect(validator(['clinical_services' => ['some-entry-id']], ['clinical_services' => $rules])->fails())
        ->toBeTrue();
    expect(validator(['clinical_services' => []], ['clinical_services' => $rules])->passes())
        ->toBeTrue();
});
