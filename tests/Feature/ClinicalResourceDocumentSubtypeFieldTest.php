<?php

declare(strict_types=1);

use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Tests\TestCase;

uses(TestCase::class);

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine structure regression.
beforeEach(fn () => Blink::flush());

/*
|--------------------------------------------------------------------------
| Clinical Resource — Document subtype field (UAMS-Web/uams-statamic-archive#799)
|--------------------------------------------------------------------------
|
| On the `document` blueprint the hand-rolled `document_subtype` radio became
| `schema_digital_document_subtype`: a `schema_type` fieldtype rooted at the
| Schema.org DigitalDocument type. `chained` is the fieldtype's default mode,
| so per Statamic convention the blueprint does not set it explicitly.
| `NoteDigitalDocument` is excluded, leaving Presentation/Spreadsheet/Text —
| the same subtype set the old radio offered. With the per-type split the
| field is always shown on the document blueprint, so the old `type`-based
| `if` is gone.
*/

function clinicalDocumentBlueprint(): Statamic\Fields\Blueprint
{
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/clinical_resources')->get('document');

    return $blueprint;
}

it('renames document_subtype to schema_digital_document_subtype', function () {
    $blueprint = clinicalDocumentBlueprint();

    expect($blueprint->hasField('document_subtype'))->toBeFalse();
    expect($blueprint->hasField('schema_digital_document_subtype'))->toBeTrue();
});

it('configures the field as a schema_type rooted at DigitalDocument', function () {
    /** @var Field $field */
    $field = clinicalDocumentBlueprint()->field('schema_digital_document_subtype');

    expect($field->type())->toBe('schema_type');
    expect($field->get('starting_types'))->toBe(['DigitalDocument']);

    // `chained` is the fieldtype default, so it is intentionally not set in the
    // blueprint (Statamic convention: omit default config values).
    expect($field->get('mode'))->toBeNull();
});

it('excludes NoteDigitalDocument from the offered subtypes', function () {
    /** @var Field $field */
    $field = clinicalDocumentBlueprint()->field('schema_digital_document_subtype');

    expect($field->get('excluded_types'))->toBe(['NoteDigitalDocument']);

    // The exclusion must actually drop Note (and its descendants) from the
    // drilldown the editor sees, while keeping the other DigitalDocument
    // subtypes the old radio offered.
    /** @var Fieldtype $fieldtype */
    $fieldtype = $field->fieldtype();
    /** @var array<string, mixed> $preloaded */
    $preloaded = $fieldtype->preload();
    $offered = $preloaded['subtree_type_names'];

    expect($offered)->not->toContain('NoteDigitalDocument');
    expect($offered)->toContain('DigitalDocument', 'PresentationDigitalDocument', 'SpreadsheetDigitalDocument', 'TextDigitalDocument');
});

it('drops the radio-only config when switching fieldtypes', function () {
    /** @var Field $field */
    $field = clinicalDocumentBlueprint()->field('schema_digital_document_subtype');

    // `options` and `cast_booleans` were radio artifacts; they must not linger.
    expect($field->get('options'))->toBeNull();
    expect($field->get('cast_booleans'))->toBeNull();
});

it('keeps the field always-on (no type gate) and required-when-shown', function () {
    /** @var Field $field */
    $field = clinicalDocumentBlueprint()->field('schema_digital_document_subtype');

    // The per-type split removed the `type` discriminator, so nothing gates it.
    expect($field->get('if'))->toBeNull();
    expect($field->get('validate'))->toBe(['required', 'sometimes']);
    expect($field->display())->toBe('Document Subtype');
});
