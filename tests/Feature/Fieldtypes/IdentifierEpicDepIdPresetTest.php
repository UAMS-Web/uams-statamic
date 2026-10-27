<?php

declare(strict_types=1);

use App\Fieldtypes\Identifier;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

it('wires the fad epic_dep_ids dep subfield to the epic_dep_id identifier preset (#960)', function () {
    // The production deliverable: the fad fieldset's `dep` subfield now uses the
    // identifier fieldtype + epic_dep_id preset (not the interim type:text + regex),
    // and still validates required-when-present.
    /** @var array{fields: array<int, array{handle: string, field: array{sets: array{new_set_group: array{sets: array{new_set: array{fields: array<int, array{field: array<string, mixed>}>}}}}}}>} $fad */
    $fad = Yaml::parseFile(resource_path('fieldsets/uamsweb_common_fad.yaml'));
    $epicDepIds = $fad['fields'][0];
    $dep = $epicDepIds['field']['sets']['new_set_group']['sets']['new_set']['fields'][0]['field'];

    expect($epicDepIds['handle'])->toBe('epic_dep_ids');
    expect($dep['type'])->toBe('identifier');
    expect($dep['preset'])->toBe('epic_dep_id');
    // `required` stays; the interim inline `regex:/^\d+$/` is gone (the preset's
    // epic_dep_id rule replaces it), so validate is exactly the required rule.
    expect($dep['validate'])->toBe(['required']);
});

/**
 * End-to-end validation through the `epic_dep_id` identifier preset (UAMS-Web/uams-statamic-archive#960):
 * the preset resolves to the registered `epic_dep_id` rule alias
 * (App\Rules\EpicDepId, UAMS-Web/uams-statamic-archive#959), so a value validated with the preset's rules
 * accepts integer-only DEP IDs and rejects everything else.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/IdentifierEpicDepIdPresetTest.php
 */
it('validates a value through the epic_dep_id preset rules', function (mixed $value, bool $shouldPass) {
    // Resolve the rules the fieldtype applies when the epic_dep_id preset is selected.
    $rules = (new Identifier)->getValidationRules('dep', ['preset' => 'epic_dep_id']);

    $passes = Validator::make(['dep' => $value], ['dep' => $rules])->passes();

    expect($passes)->toBe($shouldPass);
})->with([
    'digits pass' => ['10089', true],
    'leading zeros preserved' => ['00789', true],
    'letters fail' => ['10a89', false],
    'spaces fail' => ['100 89', false],
    'punctuation fails' => ['100-89', false],
    'trailing newline fails (the #959/#1017 anchor)' => ["10089\n", false],
    'empty passes the preset rule alone (required is separate)' => ['', true],
]);
