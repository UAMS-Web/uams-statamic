<?php

declare(strict_types=1);

use App\Services\IptcDigitalSourceValueResolver;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine structure regression.
beforeEach(fn () => Blink::flush());

/*
|--------------------------------------------------------------------------
| Clinical Resource — IPTC digital_source_type adoption (UAMS-Web/uams-statamic-archive#799)
|--------------------------------------------------------------------------
|
| Each blueprint dropped the flat `iptc_digital_source_enumeration` multi-
| select in favor of the guided `digital_source_type` group, picking the
| media-appropriate preset variant (text for article/video, image for
| infographic, the new document variant for document). All blueprints expose
| the field under the canonical `digital_source_type` handle so the shared
| `digital_source_type_value_schema` computed feeds the schema graph. These
| tests pin the variant wiring and prove every document preset key resolves
| to a real schema.org DigitalSource URL (a typo'd key would silently emit
| nothing).
*/

/**
 * The raw `field:` reference string for the `digital_source_type` field on a
 * clinical blueprint's Main tab, before fieldset expansion.
 */
function clinicalDigitalSourceFieldReference(string $blueprint): ?string
{
    /** @var Statamic\Fields\Blueprint $blueprintObj */
    $blueprintObj = Blueprint::in('collections/clinical_resources')->get($blueprint);

    /** @var array{tabs: array<string, array{sections: array<int, array{fields?: array<int, array{handle?: string, field?: string}>}>}>} $contents */
    $contents = $blueprintObj->contents();
    $sections = $contents['tabs']['main']['sections'];

    foreach ($sections as $section) {
        foreach ($section['fields'] ?? [] as $field) {
            if (($field['handle'] ?? null) === 'digital_source_type') {
                return $field['field'] ?? null;
            }
        }
    }

    return null;
}

it('references the media-appropriate digital_source_type variant per blueprint', function (string $blueprint, string $reference) {
    expect(clinicalDigitalSourceFieldReference($blueprint))->toBe($reference);
})->with([
    ['article', 'digital_source_type.digital_source_type_text'],
    ['video', 'digital_source_type.digital_source_type_text'],
    ['infographic', 'digital_source_type.digital_source_type_image'],
    ['document', 'digital_source_type.digital_source_type_document'],
]);

it('resolves digital_source_type to a group field under the canonical handle', function (string $blueprint) {
    /** @var Statamic\Fields\Blueprint $blueprintObj */
    $blueprintObj = Blueprint::in('collections/clinical_resources')->get($blueprint);
    /** @var Field $field */
    $field = $blueprintObj->field('digital_source_type');

    expect($field->type())->toBe('group');
})->with(['article', 'infographic', 'document', 'video']);

it('pins the exact document preset option keys against the fieldset', function () {
    // The resolver keys on the IPTC-term *prefix* before the first underscore, so
    // it never validates a preset's suffix — only this exact key list catches a
    // typo'd suffix or an added/removed/renamed option drifting from the spec.
    /** @var Statamic\Fields\Fieldset $fieldset */
    $fieldset = Fieldset::find('digital_source_type');

    /** @var array<int, array<string, mixed>> $fields */
    $fields = $fieldset->contents()['fields'];

    /** @var array{field: array{options: array<int, array<string, mixed>>}} $documentPreset */
    $documentPreset = collect($fields)->firstWhere('handle', 'digital_source_type_preset_document');

    $options = $documentPreset['field']['options'];

    expect(array_column($options, 'key'))->toBe([
        'humanEdits_document_standard',
        'digitalCreation_document_raw',
        'algorithmicallyEnhanced_document_polished',
        'compositeWithTrainedAlgorithmicMedia_document_ai_assisted',
        'print_document_scanned',
        'dataDrivenMedia_document_report_auto',
        'humanEdits_document_report_annotated',
        'trainedAlgorithmicMedia_document_ai_raw',
        'humanEdits_document_ai_verified',
    ]);
});

it('resolves each document preset key to its specific schema.org DigitalSource URL', function (string $presetKey, string $expectedUrl) {
    // A preset whose prefix is not a valid IPTC term resolves to an empty URL and
    // silently emits no digitalSourceType — pin the exact resolved member per key
    // (not merely "some https://schema.org/ URL", which any valid prefix passes).
    expect(IptcDigitalSourceValueResolver::schemaUrlFromGroupPayload(['preset' => $presetKey]))
        ->toBe($expectedUrl);
})->with([
    ['humanEdits_document_standard', 'https://schema.org/MinorHumanEditsDigitalSource'],
    ['digitalCreation_document_raw', 'https://schema.org/DigitalArtDigitalSource'],
    ['algorithmicallyEnhanced_document_polished', 'https://schema.org/AlgorithmicallyEnhancedDigitalSource'],
    ['compositeWithTrainedAlgorithmicMedia_document_ai_assisted', 'https://schema.org/CompositeWithTrainedAlgorithmicMediaDigitalSource'],
    ['print_document_scanned', 'https://schema.org/PrintDigitalSource'],
    ['dataDrivenMedia_document_report_auto', 'https://schema.org/DataDrivenMediaDigitalSource'],
    ['humanEdits_document_report_annotated', 'https://schema.org/MinorHumanEditsDigitalSource'],
    ['trainedAlgorithmicMedia_document_ai_raw', 'https://schema.org/TrainedAlgorithmicMediaDigitalSource'],
    ['humanEdits_document_ai_verified', 'https://schema.org/MinorHumanEditsDigitalSource'],
]);
