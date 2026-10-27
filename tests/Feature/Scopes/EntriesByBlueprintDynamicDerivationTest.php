<?php

declare(strict_types=1);

use App\Scopes\EntriesByBlueprint;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Demonstrates that `EntriesByBlueprint` derives its recognized blueprint set
 * from the blueprint registry (UAMS-Web/uams-statamic-archive#1370), so adding or renaming a blueprint in an
 * opt-in collection is picked up WITHOUT editing the scope — the failure mode of
 * the old hand-maintained `BLUEPRINTS` literal (a missed rename left a scope
 * handle filtering on a handle no entry had → an empty picker).
 *
 * Runs on the isolated blueprint directory so a fixture blueprint save never
 * touches `resources/blueprints`.
 *
 * @covers \App\Scopes\EntriesByBlueprint
 *
 * @command  php artisan test --compact tests/Feature/Scopes/EntriesByBlueprintDynamicDerivationTest.php
 */
uses(StacheIsolatedTestCase::class);

// Blueprint::in() Blink-caches resolved paths; flush so each read re-resolves the
// registry rather than returning a stale cached blueprint set.
beforeEach(fn () => Blink::flush());

it('picks up a newly added blueprint in an opt-in collection with no scope edit', function () {
    // Baseline: the new handle is not yet a recognized blueprint.
    expect(EntriesByBlueprint::recognizedBlueprints())->not->toContain('warp_core');

    $blueprint = Blueprint::make('warp_core');
    $blueprint->setNamespace('collections.facilities');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]],
    ]);
    $blueprint->save();
    Blink::flush();

    // Recognized (and thus aliased) with no edit to the scope.
    expect(EntriesByBlueprint::recognizedBlueprints())->toContain('warp_core')
        ->and(EntriesByBlueprint::aliases())->toContain('entries_by_blueprint_warp_core');
});

it('excludes the shared content-page blueprints even though they exist on an opt-in collection', function () {
    // The real `clinical_expertise` collection carries `basic_page` /
    // `landing_page` content-page blueprints; they must never be recognized.
    $recognized = EntriesByBlueprint::recognizedBlueprints();
    expect($recognized)->toContain('clinical_expertise');
    expect($recognized)->not->toContain('basic_page');
    expect($recognized)->not->toContain('landing_page');
});
