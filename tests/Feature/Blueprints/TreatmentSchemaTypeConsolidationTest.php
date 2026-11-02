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
 * Locks in the UAMS-Web/uams-statamic-archive#805/UAMS-Web/uams-statamic-archive#1052 consolidation of treatment's schema subtype cascade:
 * the five discriminator radios collapse into one chained `schema_type` field,
 * the dependent fields/relationships gate off that field's resolved path, and
 * the two self-referential relationships scope on the new resolved-type scopes.
 *
 * @command  php artisan test --compact tests/Feature/Blueprints/TreatmentSchemaTypeConsolidationTest.php
 */
beforeEach(function () {
    $bp = Blueprint::find('collections.clinical_services.clinical_service');
    expect($bp)->not->toBeNull();
    /** @var Statamic\Fields\Blueprint $bp */
    $this->bp = $bp;
    $this->fields = $bp->fields()->all();
});

/** The five flat discriminator radios that the chained field replaces. */
const REMOVED_DISCRIMINATORS = [
    'schema_medicalentity_subtype',
    'schema_medicalprocedure_subtype',
    'schema_therapeuticprocedure_subtype',
    'schema_medical_therapy_subtype',
    'schema_medical_test_subtype',
];

it('replaces the five subtype radios with one chained schema_type field', function () {
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;
    $handles = $fields->keys()->all();

    foreach (REMOVED_DISCRIMINATORS as $gone) {
        expect($handles)->not->toContain($gone);
    }

    /** @var Field $schemaType */
    $schemaType = $fields->get('schema_type');
    expect($schemaType)->not->toBeNull();
    expect($schemaType->type())->toBe('schema_type');

    $config = $schemaType->config();
    expect($config['mode'] ?? null)->toBe('chained');
    expect($config['starting_types'] ?? [])->toBe(['MedicalProcedure', 'MedicalTest']);
    expect($config['validate'] ?? [])->toContain('required');
});

it('gates dependent fields off the resolved schema_type path, never the removed radios', function () {
    // Every field's conditions must reference schema_type (or a sibling like
    // schema_drug_query), never a removed discriminator handle.
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;
    foreach ($fields->all() as $field) {
        foreach (['if', 'if_any', 'unless', 'unless_any'] as $key) {
            /** @var array<array-key, mixed> $conditions */
            $conditions = $field->config()[$key] ?? [];

            foreach (array_keys($conditions) as $target) {
                // The condition target's root handle (strip any `.value`/`.selections`).
                $root = explode('.', (string) $target)[0];
                expect(REMOVED_DISCRIMINATORS)->not->toContain($root);
            }
        }
    }
});

it('rewrites the representative cascade gates onto schema_type', function () {
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;
    $if = fn (string $handle) => $fields->get($handle)?->config()['if'] ?? [];

    // Intermediate-level gates test the selections path (array membership).
    expect($if('schema_duplicate_therapy'))->toBe(['schema_type.selections' => 'contains MedicalTherapy']);
    expect($if('schema_imaging_technique'))->toBe(['schema_type.selections' => 'contains ImagingTest']);
    expect($if('schema_drug_query'))->toBe(['schema_type.selections' => 'contains TherapeuticProcedure']);

    // schema_drug keeps its companion boolean gate alongside the path gate.
    expect($if('schema_drug'))->toBe([
        'schema_type.selections' => 'contains TherapeuticProcedure',
        'schema_drug_query' => 'equals true',
    ]);

    // The "any type chosen" gate keys off the resolved leaf value.
    expect($if('additional_type'))->toBe(['schema_type.value' => 'not empty']);

    // The one negation gate (MedicalProcedure minus SurgicalProcedure) enumerates
    // the non-surgical procedure subtree on the leaf value — SurgicalProcedure absent.
    /** @var array<string, string> $procType */
    $procType = $if('schema_procedure_type');
    expect($procType)->toHaveKey('schema_type.value');
    $allowed = explode(',', $procType['schema_type.value']);
    expect($allowed[0])->toBe('contains_any DiagnosticProcedure');
    expect($procType['schema_type.value'])->toContain('MedicalProcedure');
    expect($procType['schema_type.value'])->not->toContain('SurgicalProcedure');
});

it('scopes the self-referential relationships on the resolved-type scopes', function () {
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;
    $scopes = fn (string $handle) => $fields->get($handle)?->config()['query_scopes'] ?? [];

    expect($scopes('schema_duplicate_therapy'))->toBe(['exclude_self', 'entries_by_schema_type_medical_therapy']);
    expect($scopes('schema_subtest'))->toBe(['exclude_self', 'entries_by_schema_type_medical_test']);

    // The condition-targeting sign relationships resolve off the consolidated
    // condition `schema_type` field (condition.yaml consolidated in UAMS-Web/uams-statamic-archive#1052).
    expect($scopes('schema_sign_detected_physical_exam'))->toBe(['entries_by_schema_type_medical_sign']);
    expect($scopes('schema_sign_detected_medical_test'))->toBe(['entries_by_schema_type_medical_sign']);
});

it('scopes the four condition-targeting relationships on the resolved MedicalCondition type (#1052)', function () {
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;
    $scopes = fn (string $handle) => $fields->get($handle)?->config()['query_scopes'] ?? [];

    expect($scopes('schema_primary_prevention'))->toContain('entries_by_schema_type_medical_condition');
    expect($scopes('schema_secondary_prevention'))->toContain('entries_by_schema_type_medical_condition');
    expect($scopes('schema_treated_medical_condition'))->toContain('entries_by_schema_type_medical_condition');
    expect($scopes('schema_used_to_diagnose'))->toContain('entries_by_schema_type_medical_condition');
});

it('registers every resolved-type handle to the single alias-driven scope', function () {
    foreach ([
        'entries_by_schema_type_medical_therapy',
        'entries_by_schema_type_medical_test',
        'entries_by_schema_type_medical_sign',
        'entries_by_schema_type_medical_condition',
    ] as $handle) {
        expect(Scope::find($handle))->toBeInstanceOf(EntriesBySchemaType::class);
    }
});
