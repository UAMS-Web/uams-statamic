<?php

declare(strict_types=1);

use App\Enums\SchemaEnumerationMember;
use App\Fieldtypes\SchemaType;
use App\Rules\ChainedSchemaTypeComplete;
use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for SchemaType fieldtype (chained vs single normalization).
 *
 * Mutation: 100% on App\Fieldtypes\SchemaType (321 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/SchemaTypeFieldtypeTest.php
 */
it('ignores the single-mode default config for chained default value', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing'],
        'default' => 'Organization',
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $out = $fieldtype->defaultValue();

    expect($out)->toBeArray();
    expect(data_get($out, 'selections'))->toBe([]);
    expect(collect(is_array($out) ? $out : []))->not->toHaveKey('value');
});

it('prefills the sole configured starting type when chained and required', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Audience'],
        'validate' => ['required'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $out = $fieldtype->defaultValue();

    expect(data_get($out, 'selections'))->toBe(['Audience']);
    expect(data_get($out, 'value'))->toBe('Audience');
});

it('keeps an empty chain when multiple starting types are configured', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing', 'Person'],
        'validate' => ['required'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $out = $fieldtype->defaultValue();

    expect(data_get($out, 'selections'))->toBe([]);
    expect(collect(is_array($out) ? $out : []))->not->toHaveKey('value');
});

it('keeps a valid chain and derives value from the last selection', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'selections' => ['Thing', 'Organization', 'EducationalOrganization', 'CollegeOrUniversity'],
        'value' => 'Wrong',
    ]);

    expect(data_get($out, 'selections'))->toBe(
        ['Thing', 'Organization', 'EducationalOrganization', 'CollegeOrUniversity']
    );
    expect(data_get($out, 'value'))->toBe('CollegeOrUniversity');
});

it('truncates the chain when a step is not an immediate subtype of the parent', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'selections' => ['Thing', 'Organization', 'Article'],
    ]);

    expect(data_get($out, 'selections'))->toBe(['Thing', 'Organization']);
    expect(data_get($out, 'value'))->toBe('Organization');
});

it('persists chain_complete when provided in chained process', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'selections' => ['Thing', 'Organization', 'EducationalOrganization'],
        'chain_complete' => true,
    ]);

    expect(data_get($out, 'chain_complete'))->toBeTrue();
    expect(data_get($out, 'value'))->toBe('EducationalOrganization');
});

it('returns null from chained process when nothing is selected', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    expect($fieldtype->process(null))->toBeNull();
    expect($fieldtype->process([]))->toBeNull();
    expect($fieldtype->process(['selections' => []]))->toBeNull();
    expect($fieldtype->process(['selections' => [], 'value' => null]))->toBeNull();
});

it('augments chained null to the default shape with no value key', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $out = $fieldtype->augment(null);

    expect($out)->toBeArray();
    expect(data_get($out, 'selections'))->toBe([]);
    expect(collect(is_array($out) ? $out : []))->not->toHaveKey('value');
});

it('filters single multiple mode to allowed subtree types only', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'single',
        'starting_types' => ['Organization'],
        'multiple' => true,
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $out = $fieldtype->process(['EducationalOrganization', 'Thing', 'Airline']);

    expect(collect(is_array($out) ? $out : []))->toContain('EducationalOrganization');
    expect(collect(is_array($out) ? $out : []))->toContain('Airline');
    expect(collect(is_array($out) ? $out : []))->not->toContain('Thing');
});

it('returns one allowed type or null in single scalar mode', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'single',
        'starting_types' => ['Organization'],
        'multiple' => false,
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    expect($fieldtype->process('Airline'))->toBe('Airline');
    expect($fieldtype->process('Thing'))->toBeNull();
});

it('returns chained extra rules keys when the field is required', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing'],
        'required' => true,
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $rules = $fieldtype->extraRules();

    expect($rules)->toHaveKey('path.selections');
    expect($rules)->toHaveKey('path.value');
    expect($rules)->not->toHaveKey('path');

    $fieldRules = $fieldtype->fieldRules();
    expect($fieldRules)->toBeArray();
    /** @var array<int, mixed> $fieldRules */
    expect($fieldRules[\count($fieldRules) - 1])->toBeInstanceOf(ChainedSchemaTypeComplete::class);
});

it('ignores a stale multiple flag for chained required rules', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'multiple' => true,
        'starting_types' => ['Thing'],
        'required' => true,
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $rules = $fieldtype->extraRules();

    expect($rules)->toHaveKey('path.selections');
    expect($rules)->toHaveKey('path.value');
    expect($rules)->not->toHaveKey('path');
    expect($rules)->not->toHaveKey('path.*');

    $fieldRules = $fieldtype->fieldRules();
    expect($fieldRules)->toBeArray();
    /** @var array<int, mixed> $fieldRules */
    expect($fieldRules[\count($fieldRules) - 1])->toBeInstanceOf(ChainedSchemaTypeComplete::class);
});

it('returns root handle rules for single multiple mode when required', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'single',
        'starting_types' => ['Organization'],
        'multiple' => true,
        'required' => true,
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $rules = $fieldtype->extraRules();

    expect($rules)->toHaveKey('path');
    expect($rules)->toHaveKey('path.*');
});

it('accepts a terminal enumeration member after its parent type', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['MedicalSpecialty'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'selections' => ['MedicalSpecialty', 'Anesthesia'],
    ]);

    expect(data_get($out, 'selections'))->toBe(['MedicalSpecialty', 'Anesthesia']);
    expect(data_get($out, 'value'))->toBe('Anesthesia');
});

it('exposes merged subtype and enumeration rows with disambiguating labels', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['SizeSystemEnumeration'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $preload = $fieldtype->preload();
    $rows = data_get($preload, 'chained_next_options_by_parent.SizeSystemEnumeration', []);

    $byValue = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $value = data_get($row, 'value');
        $byValue[is_string($value) ? $value : ''] = data_get($row, 'label');
    }

    expect($byValue['WearableSizeSystemEnumeration'])->toBe('WearableSizeSystemEnumeration (subtype)');
    expect($byValue['SizeSystemImperial'])->toBe('SizeSystemImperial (enumeration member)');
    expect($byValue['SizeSystemMetric'])->toBe('SizeSystemMetric (enumeration member)');

    expect(data_get($preload, 'type_descriptions'))->toBeArray();
    expect(collect(is_array(data_get($preload, 'type_descriptions')) ? data_get($preload, 'type_descriptions') : []))
        ->toHaveKey('SizeSystemEnumeration');
    $description = data_get($preload, 'type_descriptions.SizeSystemEnumeration');
    expect(trim(is_string($description) ? $description : ''))->not->toBe('');
});

it('lists enumeration member names for medical specialty including anesthesia', function () {
    $names = SchemaEnumerationMember::memberNamesForEnumerationType('MedicalSpecialty');

    expect($names)->toContain('Anesthesia');
});

it('orders sometimes before required in field rules', function () {
    $field = new Field('schema_type', [
        'type' => 'schema_type',
        'mode' => 'single',
        'validate' => ['required', 'sometimes', 'min:99'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $rules = $fieldtype->fieldRules();

    expect($rules)->toBe(['sometimes', 'required', 'min:99']);
});

it('orders sometimes for pipe-separated string validate', function () {
    $field = new Field('schema_type', [
        'type' => 'schema_type',
        'mode' => 'single',
        'validate' => 'required|sometimes',
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $rules = $fieldtype->fieldRules();

    expect($rules)->toBe(['sometimes', 'required']);
});

it('prepends sometimes to dotted keys when validate includes sometimes', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing'],
        'validate' => ['required', 'sometimes'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $rules = $fieldtype->extraRules();

    // extraRules() keys contain literal dots; pass data_get() an array of segments
    // so the dotted key is taken literally rather than traversed as nested.
    expect(data_get($rules, ['path.selections', 0]))->toBe('sometimes');
    expect(collect(is_array(data_get($rules, ['path.selections'])) ? data_get($rules, ['path.selections']) : []))
        ->toContain('required');
    expect(data_get($rules, ['path.value', 0]))->toBe('sometimes');
    expect(collect(is_array(data_get($rules, ['path.value'])) ? data_get($rules, ['path.value']) : []))
        ->toContain('required');
    expect($rules)->not->toHaveKey('path');

    $fieldRules = $fieldtype->fieldRules();
    expect($fieldRules)->toBeArray();
    /** @var array<int, mixed> $fieldRules */
    expect($fieldRules[0])->toBe('sometimes');
    expect(collect($fieldRules))->toContain('required');
    expect($fieldRules[\count($fieldRules) - 1])->toBeInstanceOf(ChainedSchemaTypeComplete::class);
});

it('keeps the Statamic field required for a chained validate-only required field', function () {
    $field = new Field('schema_type', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Audience'],
        'validate' => ['required'],
    ]);
    $field->setValue([]);

    expect($field->isRequired())->toBeTrue();
    expect(data_get($field->toPublishArray(), 'required'))->toBeTrue();
});

it('resolves chained drill-down false when thing only without the stop sentinel', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    expect($fieldtype->chainedDrilldownResolvedForRequired([
        'selections' => ['Thing'],
        'value' => 'Thing',
    ]))->toBeFalse();
});

it('resolves chained drill-down true when stop sentinel is set at a non-leaf', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Thing'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    expect($fieldtype->chainedDrilldownResolvedForRequired([
        'selections' => ['Thing'],
        'value' => 'Thing',
        'chain_complete' => true,
    ]))->toBeTrue();
});

it('resolves chained drill-down true for an enumeration terminal selection', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['MedicalSpecialty'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    expect($fieldtype->chainedDrilldownResolvedForRequired([
        'selections' => ['MedicalSpecialty', 'Anesthesia'],
        'value' => 'Anesthesia',
    ]))->toBeTrue();
});

it('removes excluded types and their descendants from the single-mode picker (#965)', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'single',
        'multiple' => true,
        'starting_types' => ['MedicalOrganization'],
        'excluded_types' => ['Physician', 'VeterinaryCare'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $values = data_get($fieldtype->preload(), 'single_select_values');

    expect($values)->toContain('Hospital', 'MedicalClinic', 'Pharmacy', 'Dentist', 'DiagnosticLab');
    expect($values)->not->toContain('Physician');
    expect($values)->not->toContain('VeterinaryCare');
});

it('normalizes excluded values out of single-mode storage (#965)', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'single',
        'multiple' => true,
        'starting_types' => ['MedicalOrganization'],
        'excluded_types' => ['Physician', 'VeterinaryCare'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    expect($fieldtype->process(['Hospital', 'Physician']))->toBe(['Hospital']);
});

it('removes excluded types from the chained drill-down and truncates a chain through them (#965)', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['MedicalOrganization'],
        'excluded_types' => ['Physician'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    /** @var list<array<string, mixed>> $childOptions */
    $childOptions = data_get($fieldtype->preload(), 'chained_next_options_by_parent.MedicalOrganization', []);
    $childValues = array_column($childOptions, 'value');
    expect($childValues)->toContain('Hospital');
    expect($childValues)->not->toContain('Physician');

    $out = $fieldtype->process(['selections' => ['MedicalOrganization', 'Physician']]);
    expect(data_get($out, 'selections'))->toBe(['MedicalOrganization']);
});

it('removes an excluded enumeration member from the picker (#965)', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'single',
        'multiple' => true,
        'starting_types' => ['MedicalImagingTechnique'],
        'excluded_types' => ['CT'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    $values = data_get($fieldtype->preload(), 'single_select_values');
    expect($values)->toContain('MRI');
    expect($values)->not->toContain('CT');
});

it('removes an excluded enumeration member from the chained drill-down (#965)', function () {
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['MedicalImagingTechnique'],
        'excluded_types' => ['CT'],
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    /** @var list<array<string, mixed>> $rowOptions */
    $rowOptions = data_get($fieldtype->preload(), 'chained_next_options_by_parent.MedicalImagingTechnique', []);
    $rowValues = array_column($rowOptions, 'value');
    expect($rowValues)->toContain('MRI');
    expect($rowValues)->not->toContain('CT');
});

/**
 * UAMS-Web/uams-statamic-archive#966 — explicit "None applies" selectable option.
 *
 * The sentinel is recorded in storage (distinct from empty, so it satisfies
 * `required`) and shown in the CP, but omitted from augmented/JSON-LD output.
 *
 * @param  array<string, mixed>  $overrides
 */
function noneField(array $overrides = []): SchemaType
{
    $field = new Field('path', array_merge([
        'type' => 'schema_type',
        'mode' => 'single',
        'multiple' => true,
        'starting_types' => ['Organization'],
        'none_option' => true,
    ], $overrides));
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    return $fieldtype;
}

it('keeps the None sentinel in single-multiple storage when enabled (#966)', function () {
    expect(noneField()->process([SchemaType::NONE_VALUE]))->toBe([SchemaType::NONE_VALUE]);
});

it('rejects the None sentinel from storage when the option is disabled (#966)', function () {
    expect(noneField(['none_option' => false])->process([SchemaType::NONE_VALUE]))->toBe([]);
});

it('collapses to the None sentinel when mixed with real selections in storage (#966)', function () {
    // Server-side backstop: the sentinel always wins so storage never mixes
    // None with real members (the Vue enforces the same exclusivity in the UI).
    expect(noneField()->process(['Airline', SchemaType::NONE_VALUE]))->toBe([SchemaType::NONE_VALUE]);
});

it('keeps the None sentinel in single-scalar storage when enabled (#966)', function () {
    expect(noneField(['multiple' => false])->process(SchemaType::NONE_VALUE))->toBe(SchemaType::NONE_VALUE);
});

it('omits the None sentinel from augmented single-multiple output (#966)', function () {
    expect(noneField()->augment([SchemaType::NONE_VALUE]))->toBe([]);
    expect(noneField()->augment(['Airline']))->toBe(['Airline']);
});

it('omits the None sentinel from augmented single-scalar output (#966)', function () {
    expect(noneField(['multiple' => false])->augment(SchemaType::NONE_VALUE))->toBeNull();
});

it('lets the None sentinel satisfy a required single-multiple field (#966)', function () {
    $rules = noneField(['required' => true])->extraRules();

    expect(Validator::make(['path' => [SchemaType::NONE_VALUE]], $rules)->passes())->toBeTrue();
    expect(Validator::make(['path' => []], $rules)->passes())->toBeFalse();
});

it('rejects the None sentinel under validation when the option is disabled (#966)', function () {
    $rules = noneField(['required' => true, 'none_option' => false])->extraRules();

    expect(Validator::make(['path' => [SchemaType::NONE_VALUE]], $rules)->passes())->toBeFalse();
});

it('exposes the None option in preload meta when enabled (#966)', function () {
    $preload = noneField(['none_option_label' => 'No relevant specialty'])->preload();

    expect(data_get($preload, 'none_option'))->toBeTrue();
    expect(data_get($preload, 'none_option_label'))->toBe('No relevant specialty');
    expect(data_get($preload, 'none_value'))->toBe(SchemaType::NONE_VALUE);
});

it('reports the None option disabled in preload meta when off (#966)', function () {
    expect(data_get(noneField(['none_option' => false])->preload(), 'none_option'))->toBeFalse();
});

it('preserves real selections when augmenting an array that mixes None with real types (#966)', function () {
    // The sentinel is omitted on output; real members survive (storage enforces
    // exclusivity, so this mixed shape only arises from hand-edited YAML).
    expect(noneField()->augment([SchemaType::NONE_VALUE, 'Airline']))->toBe(['Airline']);
    expect(noneField()->augment(['Airline', SchemaType::NONE_VALUE, 'EducationalOrganization']))
        ->toBe(['Airline', 'EducationalOrganization']);
});

it('treats none_option as off in chained mode even if a blueprint sets it (#966)', function () {
    // None applies is single-mode-only; a hand-edited chained blueprint must not
    // leak the sentinel into the chained terminal-value allow-set.
    $field = new Field('path', [
        'type' => 'schema_type',
        'mode' => 'chained',
        'starting_types' => ['Organization'],
        'none_option' => true,
        'required' => true,
    ]);
    $fieldtype = new SchemaType;
    $fieldtype->setField($field);

    expect(data_get($fieldtype->preload(), 'none_option'))->toBeFalse();

    $rules = $fieldtype->extraRules();
    $passes = Validator::make(
        ['path' => ['selections' => [SchemaType::NONE_VALUE], 'value' => SchemaType::NONE_VALUE]],
        $rules
    )->passes();
    expect($passes)->toBeFalse();
});

it('rejects the None sentinel from single-scalar storage when disabled (#966)', function () {
    expect(noneField(['multiple' => false, 'none_option' => false])->process(SchemaType::NONE_VALUE))
        ->toBeNull();
});

it('lets the None sentinel satisfy a required single-scalar field, and rejects it when disabled (#966)', function () {
    $enabled = noneField(['multiple' => false, 'required' => true])->extraRules();
    expect(Validator::make(['path' => SchemaType::NONE_VALUE], $enabled)->passes())->toBeTrue();

    $disabled = noneField(['multiple' => false, 'none_option' => false, 'required' => true])->extraRules();
    expect(Validator::make(['path' => SchemaType::NONE_VALUE], $disabled)->passes())->toBeFalse();
});
