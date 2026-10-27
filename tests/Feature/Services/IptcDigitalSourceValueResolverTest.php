<?php

declare(strict_types=1);

use App\Services\IptcDigitalSourceValueResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for IptcDigitalSourceValueResolver (IPTC digital source type key and
 * schema.org DigitalSource URL).
 *
 * Mutation: 100% on App\Services\IptcDigitalSourceValueResolver (144 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Services/IptcDigitalSourceValueResolverTest.php
 */
it('resolves the preset and composite branches', function () {
    // Preset select uses the substring before the first underscore as the type key.
    expect(IptcDigitalSourceValueResolver::resolve(['preset' => 'humanEdits_portrait_standard']))->toBe('humanEdits');

    // Composite entries return composite_type as the resolved key.
    expect(IptcDigitalSourceValueResolver::resolve([
        'is_composite' => true,
        'composite_type' => 'compositeSynthetic',
        'origin_type' => 'capture',
    ]))->toBe('compositeSynthetic');

    // Missing origin_type after the non-composite branch yields null.
    expect(IptcDigitalSourceValueResolver::resolve(['is_composite' => false]))->toBeNull();
});

it('resolves the capture origin grid', function () {
    // Capture uses enhancement when gen-AI edits are off; computational and
    // screen-capture flags are ignored.
    expect(IptcDigitalSourceValueResolver::resolve([
        'is_composite' => false,
        'origin_type' => 'capture',
        'has_gen_ai_edits' => false,
        'enhancement_type' => 'none',
        'is_computational' => null,
        'is_screen_capture' => null,
    ]))->toBe('digitalCapture');

    expect(IptcDigitalSourceValueResolver::resolve([
        'is_composite' => false,
        'origin_type' => 'capture',
        'has_gen_ai_edits' => false,
        'enhancement_type' => 'humanEdits',
        'is_computational' => true,
        'is_screen_capture' => false,
    ]))->toBe('humanEdits');

    // Capture with generative AI edits maps to compositeWithTrainedAlgorithmicMedia.
    expect(IptcDigitalSourceValueResolver::resolve([
        'is_composite' => false,
        'origin_type' => 'capture',
        'has_gen_ai_edits' => true,
        'enhancement_type' => 'none',
    ]))->toBe('compositeWithTrainedAlgorithmicMedia');
});

it('resolves the digitized and algorithmic origin grids', function () {
    // Digitized origin with gen-AI edits short-circuits to the composite key
    // (the medium is irrelevant once gen-AI editing is flagged).
    expect(IptcDigitalSourceValueResolver::resolve([
        'is_composite' => false,
        'origin_type' => 'digitized',
        'has_gen_ai_edits' => true,
        'digitized_medium' => 'print',
    ]))->toBe('compositeWithTrainedAlgorithmicMedia');

    // Digitized origin without gen-AI and without enhancement resolves to the
    // medium's own IPTC term. The ONLY digitized media are negativeFilm /
    // positiveFilm / print (digital_source_type_questions.yaml) — exercise each
    // real value (a regression for the map that was mis-keyed by ai_type values).
    foreach (['negativeFilm', 'positiveFilm', 'print'] as $medium) {
        expect(IptcDigitalSourceValueResolver::resolve([
            'is_composite' => false,
            'origin_type' => 'digitized',
            'has_gen_ai_edits' => false,
            'digitized_medium' => $medium,
            'enhancement_type' => 'none',
        ]))->toBe($medium);
    }

    // Enhancement on a digitized medium overrides the medium key.
    expect(IptcDigitalSourceValueResolver::resolve([
        'is_composite' => false,
        'origin_type' => 'digitized',
        'has_gen_ai_edits' => false,
        'digitized_medium' => 'print',
        'enhancement_type' => 'humanEdits',
    ]))->toBe('humanEdits');

    expect(IptcDigitalSourceValueResolver::resolve([
        'is_composite' => false,
        'origin_type' => 'digitized',
        'has_gen_ai_edits' => false,
        'digitized_medium' => 'negativeFilm',
        'enhancement_type' => 'algorithmicallyEnhanced',
    ]))->toBe('algorithmicallyEnhanced');

    // A value that is NOT a digitized medium (e.g. an ai_type key) has no mapping.
    expect(IptcDigitalSourceValueResolver::resolve([
        'is_composite' => false,
        'origin_type' => 'digitized',
        'has_gen_ai_edits' => false,
        'digitized_medium' => 'algorithmicMedia',
        'enhancement_type' => 'none',
    ]))->toBeNull();

    // Algorithmic origin without gen-AI maps enhancement none to trainedAlgorithmicMedia.
    expect(IptcDigitalSourceValueResolver::resolve([
        'is_composite' => false,
        'origin_type' => 'algorithmic',
        'has_gen_ai_edits' => false,
        'enhancement_type' => 'none',
    ]))->toBe('trainedAlgorithmicMedia');
});

it('resolves the creation origin using the data-driven flag', function () {
    // Creation origin with none enhancement uses is_data_driven to pick the key.
    expect(IptcDigitalSourceValueResolver::resolve([
        'is_composite' => false,
        'origin_type' => 'creation',
        'has_gen_ai_edits' => false,
        'enhancement_type' => 'none',
        'is_data_driven' => true,
    ]))->toBe('dataDrivenMedia');

    expect(IptcDigitalSourceValueResolver::resolve([
        'is_composite' => false,
        'origin_type' => 'creation',
        'has_gen_ai_edits' => false,
        'enhancement_type' => 'none',
        'is_data_driven' => false,
    ]))->toBe('digitalCreation');
});

it('builds the schema.org DigitalSource URL or an empty string', function (?string $key, string $expected) {
    expect(IptcDigitalSourceValueResolver::schemaOrgDigitalSourceTypeUrl($key))->toBe($expected);
})->with([
    'valid member' => [
        'compositeWithTrainedAlgorithmicMedia',
        'https://schema.org/CompositeWithTrainedAlgorithmicMediaDigitalSource',
    ],
    'null key' => [null, ''],
    'empty key' => ['', ''],
    'digitalCreation falls back to DigitalArt' => [
        'digitalCreation',
        'https://schema.org/DigitalArtDigitalSource',
    ],
    'humanEdits falls back to MinorHumanEdits' => [
        'humanEdits',
        'https://schema.org/MinorHumanEditsDigitalSource',
    ],
    // The digitized-medium keys map to their own schema.org members (the
    // resolved keys the digitized grid now emits).
    'print maps to PrintDigitalSource' => [
        'print',
        'https://schema.org/PrintDigitalSource',
    ],
    'negativeFilm maps to NegativeFilmDigitalSource' => [
        'negativeFilm',
        'https://schema.org/NegativeFilmDigitalSource',
    ],
    'positiveFilm maps to PositiveFilmDigitalSource' => [
        'positiveFilm',
        'https://schema.org/PositiveFilmDigitalSource',
    ],
]);

it('exposes group-handle helpers and resolves from group payloads', function () {
    // The primary group handle is present in the supported list.
    expect(IptcDigitalSourceValueResolver::digitalSourceTypeGroupHandles())
        ->toContain('digital_source_type');

    // The primary group handle uses the short computed key names.
    expect(IptcDigitalSourceValueResolver::computedKeysForGroupHandle('digital_source_type'))->toBe([
        'value' => 'digital_source_type_value',
        'schema' => 'digital_source_type_value_schema',
    ]);

    // Non-primary group handles namespace the computed key names.
    expect(IptcDigitalSourceValueResolver::computedKeysForGroupHandle('digital_source_type_audio'))->toBe([
        'value' => 'digital_source_type_audio_digital_source_type_value',
        'schema' => 'digital_source_type_audio_digital_source_type_value_schema',
    ]);

    // Payload-based helpers resolve the value key and schema URL.
    expect(IptcDigitalSourceValueResolver::resolveFromGroupPayload(['preset' => 'digitized_none']))->toBe('digitized');
    expect(IptcDigitalSourceValueResolver::schemaUrlFromGroupPayload(['preset' => 'digitized_none']))->toBe('');

    // Invalid payloads normalize to an empty unresolved value.
    expect(IptcDigitalSourceValueResolver::resolveFromGroupPayload('not-an-array'))->toBe('');
});

it('unwraps Statamic Value wrappers in group payload normalization', function () {
    $raw = ['preset' => 'digitized_none'];
    $wrapped = new Value($raw);

    expect(IptcDigitalSourceValueResolver::normalizeGroupPayload($wrapped))->toBe($raw);
    expect(IptcDigitalSourceValueResolver::resolveFromGroupPayload($wrapped))->toBe('digitized');
});

it('reads entry group fields via get() and matches payload resolution', function () {
    $payload = ['preset' => 'digitized_none'];

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('get')
        ->twice()
        ->with('digital_source_type')
        ->andReturnUsing(fn (): array => $payload);

    expect(IptcDigitalSourceValueResolver::resolveFromEntryGroupField($entry, 'digital_source_type'))->toBe('digitized');
    expect(IptcDigitalSourceValueResolver::schemaUrlFromEntryGroupField($entry, 'digital_source_type'))->toBe('');
});
