<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;

/*
 | Behavioral coverage for every interactive control on a public form (UAMS-Web/uams-statamic-archive#2060).
 |
 | The gap this closes: the suite asserted that control markup was PRESENT and
 | never that clicking it DID anything. `ReplicatorFormRoundTripTest` asserted
 | `addSet('contact')` — a string that existed only inside a `@click` the CSP
 | build never bound — so it passed *because* the control was broken (UAMS-Web/uams-statamic-archive#2056).
 |
 | Under `@alpinejs/csp` an attribute expression is parsed by a restricted parser
 | and a rejected one can fail with no visual difference in the served document,
 | so a document-level assertion cannot see it. These tests act, then assert on
 | what the action changed.
 |
 | Every control under resources/views/vendor/statamic/forms/ is covered here:
 | list (add/remove), checkboxes (sync), person_name (assignment), signature
 | (mode switching). The replicator's own controls are covered by
 | ReplicatorSubfieldConditionsTest.
 |
 | @command vendor/bin/pest tests/Browser/PublicFormControlsTest.php
 */

const PUBLIC_FORM_CONTROLS_HANDLE = 'public_form_controls';

$pfcFormsDir = '';
$pfcViewsDir = '';

/** The fixture's public path, fixed so afterEach can delete it. */
function publicFormControlsFixturePath(): string
{
    return public_path('__public_form_controls_fixture.html');
}

/** Write the fixture page and return its public path. */
function writePublicFormControlsFixture(string $viewsDir): string
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.PUBLIC_FORM_CONTROLS_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

    $fixture = publicFormControlsFixturePath();

    File::put($fixture, implode('', [
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
        '<title>Public form controls fixture</title>',
        '<script defer src="/vendor/statamic-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
        '</head><body><h1>Public form controls fixture</h1>',
        (string) View::make('public_form_controls::form')->render(),
        '</body></html>',
    ]));

    return $fixture;
}

beforeEach(function () use (&$pfcFormsDir, &$pfcViewsDir): void {
    $pfcFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($pfcFormsDir);
    Config::set('statamic.forms.forms', $pfcFormsDir);

    app(BlueprintRepository::class)->setFallback(
        'forms.'.PUBLIC_FORM_CONTROLS_HANDLE,
        fn () => Blueprint::makeFromFields([
            'my_list' => ['type' => 'list', 'display' => 'My List', 'min_items' => 1, 'max_items' => 5],
            // `checkboxes` only emits its x-data/@change wrapper when the field is required.
            'my_checks' => ['type' => 'checkboxes', 'display' => 'My Checks', 'options' => ['a' => 'Aye', 'b' => 'Bee'], 'validate' => ['required']],
            'my_name' => ['type' => 'person_name', 'display' => 'My Name'],
            'my_sig' => ['type' => 'signature', 'display' => 'My Signature'],
        ]),
    );

    $form = makeForm(PUBLIC_FORM_CONTROLS_HANDLE);
    $form->title('Public Form Controls');
    $form->save();

    $pfcViewsDir = storage_path('framework/testing/pfc-views-'.uniqid('', true));
    File::ensureDirectoryExists($pfcViewsDir);
    View::addNamespace('public_form_controls', $pfcViewsDir);
});

afterEach(function () use (&$pfcFormsDir, &$pfcViewsDir): void {
    if (File::exists($fixture = publicFormControlsFixturePath())) {
        File::delete($fixture);
    }

    foreach ([$pfcFormsDir, $pfcViewsDir, storage_path('forms/'.PUBLIC_FORM_CONTROLS_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('renders list rows, and its add and remove controls change the row count', function () use (&$pfcViewsDir): void {
    writePublicFormControlsFixture($pfcViewsDir);

    $page = visit('/__public_form_controls_fixture.html');
    $rows = fn (): int => (int) mustBeString($page->script(
        '(() => String(document.querySelectorAll(\'input[name^="my_list["]\').length))()'
    ), 'the list row count');

    // ---
    // Before UAMS-Web/uams-statamic-archive#2060 this was ZERO. The partial declared its state as an inline x-data
    // object literal with method shorthand, which the CSP parser rejects outright, so
    // `items` never existed and the x-for rendered nothing — the field showed no inputs
    // at all and submitted nothing.
    // ---
    expect($rows())->toBe(1);

    $page->script('(() => { const b = Array.from(document.querySelectorAll("button")).find(x => x.textContent.trim() === "Add"); if (b) { b.click(); b.click(); } return 1; })()');
    expect($rows())->toBe(3);

    // `remove(idx)` is a call WITH an argument, resolved from the x-for scope.
    $page->script('(() => { const r = Array.from(document.querySelectorAll("button")).filter(x => x.textContent.trim() === "Remove"); if (r.length) { r[0].click(); } return 1; })()');
    expect($rows())->toBe(2);

    assertNoAppJavaScriptErrors($page);
});

it('reconciles the required attribute across a checkbox group when one is checked', function () use (&$pfcViewsDir): void {
    writePublicFormControlsFixture($pfcViewsDir);

    $page = visit('/__public_form_controls_fixture.html');
    $requiredCount = fn (): int => (int) mustBeString($page->script(
        '(() => { const g = document.querySelector(\'[x-data="checkboxRequiredGroup"]\');'
        .' return String(g ? Array.from(g.querySelectorAll("input[type=checkbox]")).filter(b => b.required).length : -1); })()'
    ), 'the required checkbox count');

    // With nothing checked, every box carries `required` so the browser blocks submission.
    expect($requiredCount())->toBe(2);

    $page->script('(() => { const g = document.querySelector(\'[x-data="checkboxRequiredGroup"]\');'
        .' const b = g.querySelector("input[type=checkbox]"); b.checked = true;'
        .' b.dispatchEvent(new Event("change", {bubbles:true})); return 1; })()');

    // `@change="sync"` — a bare method reference — reconciles the group.
    expect($requiredCount())->toBe(0);

    assertNoAppJavaScriptErrors($page);
});

it('applies a person_name toggle to the form scope', function () use (&$pfcViewsDir): void {
    writePublicFormControlsFixture($pfcViewsDir);

    $page = visit('/__public_form_controls_fixture.html');
    $noMiddle = fn (): string => mustBeString($page->script(
        '(() => { try { return String(window.Alpine.$data(document.querySelector("form")).my_name.no_middle_name); }'
        .' catch (e) { return "ERR"; } })()'
    ), 'the no_middle_name flag');

    expect($noMiddle())->toBe('false');

    // `@change="<handle>.no_middle_name = $event.target.checked"` — an assignment
    // expression with member access, the most complex shape emitted onto a public form.
    $page->script('(() => { const c = document.querySelector(\'input[type=checkbox][id*="no-middle"], input[type=checkbox][id*="no_middle"]\');'
        .' if (c) { c.checked = true; c.dispatchEvent(new Event("change", {bubbles:true})); } return 1; })()');

    expect($noMiddle())->toBe('true');

    assertNoAppJavaScriptErrors($page);
});

it('switches the signature field between draw and type modes', function () use (&$pfcViewsDir): void {
    writePublicFormControlsFixture($pfcViewsDir);

    $page = visit('/__public_form_controls_fixture.html');

    // ---
    // Assert the component's own `mode`, read in the SAME round-trip as the click, rather
    // than the computed display of its `x-show` branches.
    //
    // The displays do follow `mode` — verified directly during UAMS-Web/uams-statamic-archive#2060's audit. But the
    // signature field's `init()` is `async` (it lazily `import()`s signature_pad) and the
    // field renders inside the conditions partial's `<template x-if>`, so a later scope
    // change can tear the subtree down and recreate the component at its factory default.
    // Reading the display a round-trip later is therefore racy in a way the control is not.
    // That churn is recorded separately; it is not this control's behavior.
    // ---
    $clickAndReadMode = function (string $pattern) use ($page): string {
        return mustBeString($page->script(
            '(() => { const s = document.querySelector(\'[x-data^="signatureField"]\');'
            .' const before = window.Alpine.$data(s).mode;'
            .' const b = Array.from(document.querySelectorAll("button")).find(x => /'.$pattern.'/.test(x.textContent));'
            .' if (b) { b.click(); }'
            .' return before + "->" + window.Alpine.$data(s).mode; })()'
        ), 'the signature mode transition');
    };

    // `@click="useTypeMode()"` — a call with no arguments.
    expect($clickAndReadMode('Type your name'))->toBe('draw->type');

    // `@click="useDrawMode()"` — and back, so the control is not one-way.
    expect($clickAndReadMode('Draw signature'))->toBe('type->draw');

    assertNoAppJavaScriptErrors($page);
});
