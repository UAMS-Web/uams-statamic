<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\PendingAwaitablePage;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;

/*
 | Does a conditional wrapper destroy the signature field's state, and if so is the
 | SUBMITTED VALUE lost or only the UI mode (UAMS-Web/uams-statamic-archive#2063)?
 |
 | UAMS-Web/uams-statamic-archive#2063 was raised from an observation during UAMS-Web/uams-statamic-archive#2060's audit: the signature field's
 | `mode` read back as `draw` a round-trip after a correct `draw -> type` transition.
 | The control itself is fine — PublicFormControlsTest proves the transition — so the
 | suspicion was that `<template x-if>` tears the subtree down and rebuilds the
 | component at its factory default.
 |
 | That mechanism was explicitly NOT established when the ticket was filed, and the
 | ticket says which question decides severity: the hidden input carries the submitted
 | value and `init()` re-seeds from it, so if the value survives this is cosmetic and
 | if it does not it is data loss on the field a waiver form exists to collect.
 |
 | These two tests answer it by acting rather than by reading the source. They are
 | deliberately separated because they can disagree: a form-scope write that leaves
 | the condition TRUE is the realistic journey, while driving the condition false and
 | back is the deliberate teardown. `x-if` only tears down on a falsy re-evaluation,
 | so the two are different events and only one of them is a rebuild.
 |
 | @command vendor/bin/pest tests/Browser/SignatureConditionalRebuildTest.php
 */

const SIGNATURE_REBUILD_HANDLE = 'signature_conditional_rebuild';

$sigFormsDir = '';
$sigViewsDir = '';

/** The fixture's public path, fixed so afterEach can delete it. */
function signatureRebuildFixturePath(): string
{
    return public_path('__signature_conditional_rebuild_fixture.html');
}

/** Write the fixture page and return its public path. */
function writeSignatureRebuildFixture(string $viewsDir): string
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.SIGNATURE_REBUILD_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

    $fixture = signatureRebuildFixturePath();

    File::put($fixture, implode('', [
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
        '<title>Signature conditional rebuild fixture</title>',
        '<script defer src="/vendor/statamic-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
        '</head><body><h1>Signature conditional rebuild fixture</h1>',
        (string) View::make('signature_rebuild::form')->render(),
        '</body></html>',
    ]));

    return $fixture;
}

beforeEach(function () use (&$sigFormsDir, &$sigViewsDir): void {
    $sigFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($sigFormsDir);
    Config::set('statamic.forms.forms', $sigFormsDir);

    app(BlueprintRepository::class)->setFallback(
        'forms.'.SIGNATURE_REBUILD_HANDLE,
        fn () => Blueprint::makeFromFields([
            // Drives the signature field's condition. Default satisfies it, so the
            // field is present on load.
            'trigger' => ['type' => 'text', 'display' => 'Trigger', 'default' => 'Yes'],
            // Unconditional, so typing here writes to the form scope WITHOUT changing
            // whether the signature field should show.
            'note' => ['type' => 'text', 'display' => 'Note'],
            'my_sig' => [
                'type' => 'signature',
                'display' => 'My Signature',
                'if' => ['trigger' => 'equals Yes'],
            ],
        ]),
    );

    $form = makeForm(SIGNATURE_REBUILD_HANDLE);
    $form->title('Signature Conditional Rebuild');
    $form->save();

    $sigViewsDir = storage_path('framework/testing/sig-views-'.uniqid('', true));
    File::ensureDirectoryExists($sigViewsDir);
    View::addNamespace('signature_rebuild', $sigViewsDir);
});

afterEach(function () use (&$sigFormsDir, &$sigViewsDir): void {
    if (File::exists($fixture = signatureRebuildFixturePath())) {
        File::delete($fixture);
    }

    foreach ([$sigFormsDir, $sigViewsDir, storage_path('forms/'.SIGNATURE_REBUILD_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

/**
 * Put the signature field into typed mode carrying a completed, attested name.
 *
 * Driven through the DOM — a click, an `input` event, a change — rather than by
 * assigning Alpine state, so the value under test is one a real submission would
 * actually carry.
 */
function completeTypedSignature(ArrayablePendingAwaitablePage|PendingAwaitablePage $page, string $name): void
{
    $page->script(
        '(() => {'
        .' const b = Array.from(document.querySelectorAll("button"))'
        .'   .find(x => /Type your name/.test(x.textContent));'
        .' if (b) { b.click(); }'
        .' const t = document.querySelector(\'input[id$="-typed"]\');'
        .' if (t) { t.value = '.json_encode($name).';'
        .'   t.dispatchEvent(new Event("input", { bubbles: true })); }'
        .' const a = document.querySelector(\'input[id$="-attest"]\');'
        .' if (a && !a.checked) { a.click(); }'
        .' return 1; })()'
    );
}

/**
 * Read what the form would actually submit for the signature, plus the component
 * state behind it. Returns `absent` when the field is not in the DOM at all.
 */
function readSignatureState(ArrayablePendingAwaitablePage|PendingAwaitablePage $page): string
{
    return mustBeString($page->script(
        '(() => {'
        .' const s = document.querySelector(\'[x-data^="signatureField"]\');'
        .' if (!s) { return "absent"; }'
        .' const d = window.Alpine.$data(s);'
        .' const input = document.querySelector(\'input[name="my_sig"]\');'
        .' return [d.mode, d.typedName, String(d.attested),'
        .'   input ? input.value : "no-input"].join("|"); })()'
    ), 'the signature field state');
}

it('keeps a typed signature when another field writes to the form scope', function () use (&$sigViewsDir): void {
    writeSignatureRebuildFixture($sigViewsDir);

    $page = visit('/__signature_conditional_rebuild_fixture.html');

    completeTypedSignature($page, 'Nyota Uhura');
    expect(readSignatureState($page))->toBe('type|Nyota Uhura|true|Nyota Uhura');

    // The realistic journey from UAMS-Web/uams-statamic-archive#2063: having signed, the user edits some other
    // field. `note` carries no condition, so this writes the form scope without
    // changing whether the signature should show.
    $page->script(
        '(() => { const n = document.querySelector(\'input[name="note"]\');'
        .' if (n) { n.value = "Edited after signing";'
        .'   n.dispatchEvent(new Event("input", { bubbles: true })); }'
        .' return 1; })()'
    );

    expect(readSignatureState($page))->toBe('type|Nyota Uhura|true|Nyota Uhura');

    assertNoAppJavaScriptErrors($page);
});

it('keeps a typed signature when its condition goes false and true again', function () use (&$sigViewsDir): void {
    writeSignatureRebuildFixture($sigViewsDir);

    $page = visit('/__signature_conditional_rebuild_fixture.html');

    completeTypedSignature($page, 'Nyota Uhura');
    expect(readSignatureState($page))->toBe('type|Nyota Uhura|true|Nyota Uhura');

    $setTrigger = function (string $value) use ($page): void {
        $page->script(
            '(() => { const t = document.querySelector(\'input[name="trigger"]\');'
            .' if (t) { t.value = '.json_encode($value).';'
            .'   t.dispatchEvent(new Event("input", { bubbles: true })); }'
            .' return 1; })()'
        );
    };

    // Drive the condition false. `x-if` destroys rather than hides, so the whole
    // subtree — including the hidden input carrying the submitted value — goes.
    $setTrigger('No');
    expect(readSignatureState($page))->toBe('absent');

    // Back to true. The component is rebuilt and `init()` re-seeds from the hidden
    // input, which the server rendered empty.
    $setTrigger('Yes');

    // ---
    // The UAMS-Web/uams-statamic-archive#2063 regression guard. Before the fix this read `draw||false|` — an
    // attested typed signature discarded, the field back on the canvas, and the
    // submitted value empty, with nothing to tell the user what they signed was
    // gone. The value now rides in the form's own scope, which outlives the
    // subtree the `x-if` tears down, and `init()` prefers it over the re-rendered
    // input. Restoring the value restores the typed mode with it, because a
    // non-data-URL value is what identifies the typed fallback.
    // ---
    expect(readSignatureState($page))->toBe('type|Nyota Uhura|true|Nyota Uhura');

    assertNoAppJavaScriptErrors($page);
});
