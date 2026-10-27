<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;
use Tests\TestCase;

/**
 * Render-level coverage for field conditions on a subfield inside a replicator set (UAMS-Web/uams-statamic-archive#2056).
 *
 * Before this, a subfield's `if:` was accepted by the blueprint and silently ignored: the partial
 * emitted `{{ field }}` with no `<template x-if>` wrapper, so a conditional subfield was always
 * visible. The same probe also found `x-model` bound to the bracketed handle
 * (`participants[0][is_minor]`), which Alpine reads as identifier lookups rather than string keys,
 * so subfield values never reached Alpine state at all.
 *
 * These cases pin the emitted markup — the wrapper, the per-set scope and its seed, the bare
 * `x-model`, and the untouched bracketed `name`. The live show/hide behavior is proven in
 * tests/Browser/ReplicatorSubfieldConditionsTest.php, which this cannot see.
 *
 * @command  php artisan test --compact tests/Feature/Forms/ReplicatorSubfieldConditionsTest.php
 */
uses(TestCase::class);

/**
 * Handle of the throwaway form created for these cases.
 */
const PEST_REPLICATOR_CONDITIONS_HANDLE = 'replicator_conditions_probe';

/**
 * Isolated forms directory so the throwaway form never touches resources/forms.
 *
 * @var string $pestReplicatorConditionsFormsDir
 */
$pestReplicatorConditionsFormsDir = '';

/**
 * Isolated views directory holding the throwaway form template.
 *
 * @var string $pestReplicatorConditionsViewsDir
 */
$pestReplicatorConditionsViewsDir = '';

/**
 * Register a form whose set carries a toggle and a subfield conditional on it.
 */
function pestMakeReplicatorConditionsForm(): void
{
    app(BlueprintRepository::class)->setFallback(
        'forms.'.PEST_REPLICATOR_CONDITIONS_HANDLE,
        fn () => Blueprint::makeFromFields([
            'participants' => [
                'type' => 'replicator',
                'display' => 'Participants',
                'sets' => [
                    'main' => [
                        'sets' => [
                            'participant' => [
                                'display' => 'Participant',
                                'fields' => [
                                    ['handle' => 'is_minor', 'field' => ['type' => 'toggle', 'display' => 'Is Minor']],
                                    ['handle' => 'parent_name', 'field' => ['type' => 'text', 'display' => 'Parent Name', 'if' => ['is_minor' => 'equals true']]],
                                    ['handle' => 'always', 'field' => ['type' => 'text', 'display' => 'Always Shown']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])
    );

    $form = makeForm(PEST_REPLICATOR_CONDITIONS_HANDLE);
    $form->title('Replicator Conditions Probe');
    $form->save();
}

/**
 * Render the form through the real `form:create` pipeline under the CSP driver.
 *
 * @return string The rendered HTML, entity-decoded so attribute values can be matched literally.
 */
function pestRenderReplicatorConditionsForm(string $viewsDir): string
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.PEST_REPLICATOR_CONDITIONS_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}'
    );

    return html_entity_decode((string) View::make('replicator_conditions_probe::form')->render());
}

beforeEach(function () use (&$pestReplicatorConditionsFormsDir, &$pestReplicatorConditionsViewsDir): void {
    $pestReplicatorConditionsFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($pestReplicatorConditionsFormsDir);
    Config::set('statamic.forms.forms', $pestReplicatorConditionsFormsDir);

    $pestReplicatorConditionsViewsDir = storage_path('framework/testing/views-'.uniqid('', true));
    File::ensureDirectoryExists($pestReplicatorConditionsViewsDir);
    View::addNamespace('replicator_conditions_probe', $pestReplicatorConditionsViewsDir);

    pestMakeReplicatorConditionsForm();
});

afterEach(function () use (&$pestReplicatorConditionsFormsDir, &$pestReplicatorConditionsViewsDir): void {
    foreach ([$pestReplicatorConditionsFormsDir, $pestReplicatorConditionsViewsDir, storage_path('forms/'.PEST_REPLICATOR_CONDITIONS_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('wraps every subfield in a conditions template', function () use (&$pestReplicatorConditionsViewsDir): void {
    $html = pestRenderReplicatorConditionsForm($pestReplicatorConditionsViewsDir);

    // The defect this fixes: the wrapper did not exist at all, so `if:` was inert.
    expect(substr_count($html, '<template x-if='))->toBeGreaterThan(0);
    expect($html)->toContain('$showField({"if":{"is_minor":"equals true"}}');
});

it('gives each set its own Alpine scope seeded with the row values', function () use (&$pestReplicatorConditionsViewsDir): void {
    $html = pestRenderReplicatorConditionsForm($pestReplicatorConditionsViewsDir);

    // Without a per-set scope the condition's source handle resolves against the form scope, where
    // it does not exist — every conditional subfield would be permanently hidden.
    // The seed is a factory ARGUMENT, not an attribute: Alpine resolves an x-model identifier
    // by walking up the scope stack, so a property declared only in init() would be found on an
    // ancestor and shared by every set.
    expect($html)->toContain('x-data="replicatorSet({');

    // The seed is keyed by BARE handle, not the bracketed submission handle.
    expect($html)->toContain('"is_minor"');
    expect($html)->not->toContain('"participants[REPLICATORSETINDEX][is_minor]":');
});

it('binds a subfield x-model to its bare handle while name keeps the bracketed shape', function () use (&$pestReplicatorConditionsViewsDir): void {
    $html = pestRenderReplicatorConditionsForm($pestReplicatorConditionsViewsDir);

    // Alpine reads x-model as an expression, so the bracketed handle was identifier lookups.
    expect($html)->toContain('x-model="is_minor"');
    expect($html)->not->toContain('x-model="participants[REPLICATORSETINDEX][is_minor]"');

    // Submission shape is untouched — this is the half that must not change.
    expect($html)->toContain('name="participants[REPLICATORSETINDEX][is_minor]"');
});

it('leaves a top-level field binding and condition unchanged', function () use (&$pestReplicatorConditionsViewsDir): void {
    // Bounds the change: only bracketed (subfield) handles are rewritten.
    app(BlueprintRepository::class)->setFallback(
        'forms.'.PEST_REPLICATOR_CONDITIONS_HANDLE,
        fn () => Blueprint::makeFromFields([
            'trigger' => ['type' => 'toggle', 'display' => 'Trigger'],
            'note' => ['type' => 'text', 'display' => 'Note', 'if' => ['trigger' => 'equals true']],
        ])
    );

    $html = pestRenderReplicatorConditionsForm($pestReplicatorConditionsViewsDir);

    expect($html)->toContain('x-model="trigger"');

    // statamic/cms 6.31.0 binds `x-model.fill`, which reseeds a checkbox group or multi-select from
    // its markup when a condition rebuilds it; the driver keeps the plain binding
    // (ConditionalRebuildStateSweepTest)
    expect($html)->not->toContain('x-model.fill');
    expect($html)->toContain('name="trigger"');
    expect($html)->toContain('$showField({"if":{"trigger":"equals true"}}, $data, \'note\')');
});
