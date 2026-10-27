<?php

declare(strict_types=1);

use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Locks in the UAMS-Web/uams-statamic-archive#805 migrate-then-clear deprecation of the general
 * `clinical_conditions` relationship on the clinical_service blueprint: the
 * field surfaces only while it holds values, the entry cannot be saved while it
 * is populated (forcing the editor to reclassify each condition into the schema
 * condition relationships and clear it), and it hides once empty. Mirrors the
 * `uamsweb_common_people_research.research_interests_free_text` precedent, but
 * applied via a per-blueprint `config:` override on a fieldset-imported field.
 *
 * @command  php artisan test --compact tests/Feature/Blueprints/ClinicalServiceConditionsDeprecationTest.php
 */
beforeEach(function () {
    $bp = Blueprint::find('collections.clinical_services.clinical_service');
    expect($bp)->not->toBeNull();
    /** @var Statamic\Fields\Blueprint $bp */
    $this->field = $bp->fields()->all()->get('clinical_conditions');
    expect($this->field)->not->toBeNull();
});

it('carries the migrate-then-clear deprecation config', function () {
    /** @var Field $field */
    $field = $this->field;
    $config = $field->config();

    expect($config['display'] ?? '')->toContain('Migrate, Then Clear');
    expect($config['validate'] ?? [])->toContain('prohibited');
    expect($config['always_save'] ?? null)->toBeTrue();
    // Self-referential condition: the field shows only while it holds a value.
    expect($config['if'] ?? [])->toBe(['clinical_conditions' => 'not empty']);
});

it('blocks the save while populated and allows it once empty', function () {
    /** @var Field $field */
    $field = $this->field;
    /** @var array<string, mixed> $fieldRules */
    $fieldRules = $field->rules();
    $rules = $fieldRules['clinical_conditions'];
    expect($rules)->toContain('prohibited');

    expect(validator(['clinical_conditions' => ['some-entry-id']], ['clinical_conditions' => $rules])->fails())
        ->toBeTrue();
    expect(validator(['clinical_conditions' => []], ['clinical_conditions' => $rules])->passes())
        ->toBeTrue();
});
