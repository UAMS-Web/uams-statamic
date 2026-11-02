<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;

/*
 | Browser proof for field conditions INSIDE a replicator set (UAMS-Web/uams-statamic-archive#2056).
 |
 | CspPublicFormConditionsTest proves conditions work on a TOP-LEVEL public-form
 | field. This file proves the nested case, which was silently broken: a subfield
 | carrying `if:` rendered with no `<template x-if>` wrapper at all, so it was
 | always visible, and `x-model` bound to the bracketed handle — identifier
 | lookups, not string keys — so subfield values never reached Alpine state.
 |
 | These assertions run against the LIVE post-Alpine DOM, which is the only place
 | the fix is observable: the served document contains every subfield inside its
 | template regardless of whether the condition evaluates.
 |
 | The waiver form this unblocks reveals parent-name fields per participant row,
 | so the per-row independence case below is the one that matters most.
 |
 | @command vendor/bin/pest tests/Browser/ReplicatorSubfieldConditionsTest.php
 */

const REPLICATOR_CONDITIONS_HANDLE = 'replicator_conditions_browser';

$rcFormsDir = '';
$rcViewsDir = '';

/**
 * The fixture's public path, fixed so afterEach can delete it without a by-ref handle.
 */
function replicatorConditionsFixturePath(): string
{
    return public_path('__replicator_conditions_fixture.html');
}

/**
 * Write the fixture page and return its public path.
 */
function writeReplicatorConditionsFixture(string $viewsDir): string
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.REPLICATOR_CONDITIONS_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

    $fixture = replicatorConditionsFixturePath();

    File::put($fixture, implode('', [
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
        '<title>Replicator conditions fixture</title>',
        '<script defer src="/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
        '</head><body><h1>Replicator conditions fixture</h1>',
        (string) View::make('replicator_conditions_browser::form')->render(),
        '</body></html>',
    ]));

    return $fixture;
}

beforeEach(function () use (&$rcFormsDir, &$rcViewsDir): void {
    $rcFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($rcFormsDir);
    Config::set('statamic.forms.forms', $rcFormsDir);

    app(BlueprintRepository::class)->setFallback(
        'forms.'.REPLICATOR_CONDITIONS_HANDLE,
        fn () => Blueprint::makeFromFields([
            'participants' => [
                'type' => 'replicator',
                'display' => 'Participants',
                'min_sets' => 2,
                'sets' => [
                    'main' => [
                        'sets' => [
                            'participant' => [
                                'display' => 'Participant',
                                'fields' => [
                                    ['handle' => 'is_minor', 'field' => ['type' => 'toggle', 'display' => 'Participant Is A Minor']],
                                    // Hidden until the toggle in its OWN row is on.
                                    ['handle' => 'parent_name', 'field' => ['type' => 'text', 'display' => 'Parent Guardian Name', 'if' => ['is_minor' => 'equals true']]],
                                    // Unconditional, so a total Alpine failure stays distinguishable.
                                    ['handle' => 'always', 'field' => ['type' => 'text', 'display' => 'Always Present Field']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    );

    $form = makeForm(REPLICATOR_CONDITIONS_HANDLE);
    $form->title('Replicator Conditions');
    $form->save();

    $rcViewsDir = storage_path('framework/testing/rc-views-'.uniqid('', true));
    File::ensureDirectoryExists($rcViewsDir);
    View::addNamespace('replicator_conditions_browser', $rcViewsDir);
});

afterEach(function () use (&$rcFormsDir, &$rcViewsDir): void {
    if (File::exists($fixture = replicatorConditionsFixturePath())) {
        File::delete($fixture);
    }

    foreach ([$rcFormsDir, $rcViewsDir, storage_path('forms/'.REPLICATOR_CONDITIONS_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('hides a conditional subfield until its own row toggle is on', function () use (&$rcViewsDir): void {
    writeReplicatorConditionsFixture($rcViewsDir);

    $page = visit('/__replicator_conditions_fixture.html');

    $page->assertSee('Replicator conditions fixture');

    // The unconditional subfield proves the sets rendered and Alpine started.
    $page->assertSee('Always Present Field');
    $page->assertSee('Participant Is A Minor');

    // ---
    // The load-bearing assertion. Before the fix this label was ALWAYS in the DOM,
    // because the partial emitted the subfield with no <template x-if> wrapper.
    // ---
    $page->assertDontSee('Parent Guardian Name');

    assertNoAppJavaScriptErrors($page);
});

it('evaluates the condition per set rather than across sets', function () use (&$rcViewsDir): void {
    writeReplicatorConditionsFixture($rcViewsDir);

    $page = visit('/__replicator_conditions_fixture.html');

    // Two rows render (min_sets = 2); toggle only the FIRST row's checkbox. The rows are
    // client-added by the min-sets auto-fill, which starts its indices clear of the
    // server-rendered range (`nextIndex = length + 1000`), so the index is not 0 — match on
    // the handle suffix and take the first.
    $firstRowName = mustBeString($page->script(
        '(() => { const el = document.querySelector(\'input[type=checkbox][name$="[is_minor]"]\'); return el ? el.getAttribute("name") : ""; })()'
    ), 'the first row is_minor input name');

    expect($firstRowName)->not->toBe('');

    // The toggle posts a paired hidden `0` + checkbox `1` under one name, so target the checkbox.
    $page->check('input[type=checkbox][name="'.$firstRowName.'"]');

    // ---
    // The case the waiver actually needs: exactly one parent field appears, in the
    // row whose toggle was flipped. A form-level scope would reveal both.
    // ---
    $count = mustBeString($page->script(
        '(() => String(document.querySelectorAll(\'input[name$="[parent_name]"]\').length))()'
    ), 'the parent_name input count');

    expect($count)->toBe('1');

    // And it is the toggled row that grew the field, not the other one.
    $expectedParent = str_replace('[is_minor]', '[parent_name]', $firstRowName);

    $inToggledRow = mustBeString($page->script(
        '(() => String(!!document.querySelector(\'input[name="'.$expectedParent.'"]\')))()'
    ), 'whether the toggled row carries the parent field');

    expect($inToggledRow)->toBe('true');

    assertNoAppJavaScriptErrors($page);
});

it('evaluates conditions in a set added after page load', function () use (&$rcViewsDir): void {
    writeReplicatorConditionsFixture($rcViewsDir);

    $page = visit('/__replicator_conditions_fixture.html');

    // A new set is cloned from a <template> carrying the placeholder index token, so its
    // scope and condition have to survive that rewrite.
    $page->script('(() => { const b = document.querySelector(\'[data-replicator-add-type="participant"]\'); if (b) { b.click(); } return "clicked"; })()');

    $diag = mustBeString($page->script(
        '(() => JSON.stringify({ live: document.querySelectorAll("[data-replicator-sets] > [data-replicator-set]").length,'
        .' errs: ((window.__pestBrowser && window.__pestBrowser.jsErrors) || []).map(e => String((e && e.message) || e)).slice(0,3) }))()'
    ), 'the add-set diagnostic');

    expect($diag)->toContain('"errs":[]');

    // Count only live sets — `[data-replicator-sets]` holds them; the blank <template> ones
    // live outside it and are not in the document tree at all.
    $sets = mustBeString($page->script(
        '(() => String(document.querySelectorAll("[data-replicator-sets] > [data-replicator-set]").length))()'
    ), 'the live set count');

    expect((int) $sets)->toBe(3);

    // The cloned row starts with its condition unsatisfied, like the server-rendered ones.
    $parents = mustBeString($page->script(
        '(() => String(document.querySelectorAll(\'input[name$="[parent_name]"]\').length))()'
    ), 'the parent_name input count');

    expect($parents)->toBe('0');

    assertNoAppJavaScriptErrors($page);
});
