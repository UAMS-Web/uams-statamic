<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| UAMS-Web/uams-statamic-archive#1065 D1 — "special interest in [C/T]" filter on the FaD blocks
|--------------------------------------------------------------------------
|
| The Find-a-Doc providers/locations page-builder blocks gain a special-interest
| filter in their Filtering subsection, alongside the existing `expertise` filter.
| Special interest is authored in the two typed UAMS-Web/uams-statamic-archive#1064 fields, so the block reuses
| BOTH shared definitions — `uamsweb_common_fad.special_interest_clinical_conditions`
| and `..._services` — as two filter pickers rather than inventing a parallel
| relationship. Their selected C/T ids feed the OntologyOverlapSpecialInterests
| query scope (whose behavior is covered in the scope unit test). Per the D1
| decision, the live render wiring is deferred to the block-completion tickets
| UAMS-Web/uams-statamic-archive#61/UAMS-Web/uams-statamic-archive#62/UAMS-Web/uams-statamic-archive#63/UAMS-Web/uams-statamic-archive#64/UAMS-Web/uams-statamic-archive#919 that own the placeholder render partials — this ticket ships
| the filter fields + scope those tickets consume.
|
| @command php artisan test --compact tests/Feature/Blueprints/FadSpecialInterestFilterTest.php
*/

/**
 * The two typed special-interest filter fields the blocks reuse, each mapped to
 * the shared `uamsweb_common_fad` definition it imports.
 *
 * @var array<string, string>
 */
const SPECIAL_INTEREST_FILTER_REFS = [
    'special_interest_clinical_conditions' => 'uamsweb_common_fad.special_interest_clinical_conditions',
    'special_interest_clinical_services' => 'uamsweb_common_fad.special_interest_clinical_services',
];

/**
 * Parse a block fieldset into its ordered handles, the imported-field ref per
 * handle, the per-handle overridden config, and the index of the two subsection
 * markers that bound the Filtering group.
 *
 * @return array{handles: list<string>, refs: array<string, string>, configs: array<string, array<string, mixed>>, filteringIndex: int, displayIndex: int}
 */
function fadFilterFieldset(string $fieldset): array
{
    /** @var array{fields: list<array{handle: string, field?: mixed, config?: array<string, mixed>}>} $parsed */
    $parsed = Yaml::parseFile(base_path("resources/fieldsets/{$fieldset}.yaml"));
    $fields = $parsed['fields'];

    $handles = array_column($fields, 'handle');
    $refs = [];
    $configs = [];
    foreach ($fields as $f) {
        $field = $f['field'] ?? null;
        if (is_string($field)) {
            $refs[$f['handle']] = $field;
        }

        $config = $f['config'] ?? null;
        if (is_array($config)) {
            $configs[$f['handle']] = $config;
        }
    }

    /** @var int<0, max> $filteringIndex */
    $filteringIndex = array_search('section_filtering', $handles, true);
    /** @var int<0, max> $displayIndex */
    $displayIndex = array_search('section_display_settings', $handles, true);

    return [
        'handles' => $handles,
        'refs' => $refs,
        'configs' => $configs,
        'filteringIndex' => $filteringIndex,
        'displayIndex' => $displayIndex,
    ];
}

it('adds both typed special-interest filters, each reusing its shared #1064 definition', function (string $fieldset) {
    $meta = fadFilterFieldset($fieldset);

    foreach (SPECIAL_INTEREST_FILTER_REFS as $handle => $ref) {
        // The filter field exists and imports the shared UAMS-Web/uams-statamic-archive#1064 typed definition
        // (not a parallel relationship, and not a combined both-collections picker).
        expect($meta['refs'])->toHaveKey($handle)
            ->and($meta['refs'][$handle])->toBe($ref);
    }
})->with([
    'providers' => ['uamsweb_block_fad_providers'],
    'locations' => ['uamsweb_block_fad_locations'],
]);

it('places both special-interest filters inside the Filtering subsection', function (string $fieldset) {
    $meta = fadFilterFieldset($fieldset);

    // Each sits after the filtering marker and before display settings — a listing
    // filter, not a display/query-config field.
    foreach (array_keys(SPECIAL_INTEREST_FILTER_REFS) as $handle) {
        $index = array_search($handle, $meta['handles'], true);
        expect($index)->toBeGreaterThan($meta['filteringIndex'])
            ->and($index)->toBeLessThan($meta['displayIndex']);
    }
})->with([
    'providers' => ['uamsweb_block_fad_providers'],
    'locations' => ['uamsweb_block_fad_locations'],
]);

it('keeps the special-interest filters siblings of the existing expertise filter', function (string $fieldset) {
    $meta = fadFilterFieldset($fieldset);

    // The existing expertise filter is untouched and still imports its shared def.
    expect($meta['refs'])->toHaveKey('expertise')
        ->and($meta['refs']['expertise'])->toBe('uamsweb_common_fad.expertise');

    $expertiseIndex = array_search('expertise', $meta['handles'], true);
    expect($expertiseIndex)->toBeGreaterThan($meta['filteringIndex']);

    foreach (array_keys(SPECIAL_INTEREST_FILTER_REFS) as $handle) {
        $index = array_search($handle, $meta['handles'], true);
        expect($index)->toBeGreaterThan($meta['filteringIndex']);
    }
})->with([
    'providers' => ['uamsweb_block_fad_providers'],
    'locations' => ['uamsweb_block_fad_locations'],
]);

it('relabels the filter displays to read "Medical Conditions" / "Medical Services"', function (string $fieldset) {
    // AC #7: the C/T strings on the new filter fields read "Medical Conditions" /
    // "Medical Services" (display-only relabel, per the D9 reframe; handles unchanged).
    $meta = fadFilterFieldset($fieldset);

    expect($meta['configs'])->toHaveKey('special_interest_clinical_conditions')
        ->and($meta['configs']['special_interest_clinical_conditions']['display'] ?? '')
        ->toContain('Medical Conditions');

    expect($meta['configs'])->toHaveKey('special_interest_clinical_services')
        ->and($meta['configs']['special_interest_clinical_services']['display'] ?? '')
        ->toContain('Medical Services');
})->with([
    'providers' => ['uamsweb_block_fad_providers'],
    'locations' => ['uamsweb_block_fad_locations'],
]);

it('resolves each block filter ref to a real typed entries field in the shared fieldset', function (string $fieldset) {
    // Self-contained guard against a DANGLING import: the block reuses the UAMS-Web/uams-statamic-archive#1064 shared
    // defs by handle, so assert each referenced handle actually exists in
    // uamsweb_common_fad as an entries field over its one C/T collection. Without this,
    // renaming the shared handle would leave a dangling `field:` ref (the picker silently
    // vanishing from the CP) that this suite's string-only ref checks could not see.
    $meta = fadFilterFieldset($fieldset);
    /** @var array{fields: list<array<string, mixed>>} $shared */
    $shared = Yaml::parseFile(base_path('resources/fieldsets/uamsweb_common_fad.yaml'));

    $expectedCollection = [
        'special_interest_clinical_conditions' => 'clinical_conditions',
        'special_interest_clinical_services' => 'clinical_services',
    ];

    foreach ($expectedCollection as $handle => $collection) {
        // The block imports it by the fully-qualified `uamsweb_common_fad.<handle>` ref…
        expect($meta['refs'][$handle] ?? null)->toBe("uamsweb_common_fad.{$handle}");

        // …and that handle resolves to a real typed entries field in the shared fieldset.
        /** @var array{field: array{type: string, collections: array<int, string>}} $sharedField */
        $sharedField = collect($shared['fields'])->firstWhere('handle', $handle);
        expect($sharedField)->not->toBeNull()
            ->and($sharedField['field']['type'])->toBe('entries')
            ->and($sharedField['field']['collections'])->toBe([$collection]);
    }
})->with([
    'providers' => ['uamsweb_block_fad_providers'],
    'locations' => ['uamsweb_block_fad_locations'],
]);

it('does not introduce a combined both-collections special-interest picker on the block', function (string $fieldset) {
    // The UAMS-Web/uams-statamic-archive#1064 split retired the single both-collections `special_interests` field;
    // the block must reuse the two typed defs, never re-add a combined picker.
    $meta = fadFilterFieldset($fieldset);

    expect($meta['handles'])->not->toContain('special_interests');
    expect($meta['refs'])->not->toHaveKey('special_interests');
})->with([
    'providers' => ['uamsweb_block_fad_providers'],
    'locations' => ['uamsweb_block_fad_locations'],
]);
