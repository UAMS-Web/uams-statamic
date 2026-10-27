<?php

declare(strict_types=1);

use Statamic\Facades\Blink;
use Statamic\Facades\Fieldset;
use Tests\TestCase;

uses(TestCase::class);

// Fieldset::find() Blink-caches resolved definitions; flush so a stale cache
// can never mask a genuine structure regression.
beforeEach(fn () => Blink::flush());

/*
|--------------------------------------------------------------------------
| Orphaned uamsweb_schema fields removed (UAMS-Web/uams-statamic-archive#1377)
|--------------------------------------------------------------------------
|
| The medical_test / medical_therapy `entries` fields (and their paired
| _subtype radios) were imported by no blueprint and declared no target
| collection — dead scaffolding surfaced by the UAMS-Web/uams-statamic-archive#1360 relationship audit.
| They are removed; the live medical_* schema *types* are driven by the
| SchemaType enum, not these fields, so nothing else should change.
*/

it('still resolves the uamsweb_schema fieldset after the removal', function () {
    expect(Fieldset::find('uamsweb_schema'))->not->toBeNull();
});

it('no longer exposes the orphaned medical_test / medical_therapy fields', function (string $handle) {
    /** @var Statamic\Fields\Fieldset $fieldset */
    $fieldset = Fieldset::find('uamsweb_schema');
    $handles = $fieldset->fields()->all()->keys()->all();

    expect($handles)->not->toContain($handle);
})->with([
    'medical_test',
    'medical_test_subtype',
    'medical_therapy',
    'medical_therapy_subtype',
]);

it('keeps the neighboring live schema fields intact', function (string $handle) {
    /** @var Statamic\Fields\Fieldset $fieldset */
    $fieldset = Fieldset::find('uamsweb_schema');
    $handles = $fieldset->fields()->all()->keys()->all();

    expect($handles)->toContain($handle);
})->with([
    'medical_specialty_single',
    'medicine_system_multiple',
]);
