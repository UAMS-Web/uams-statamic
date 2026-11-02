<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;

/*
 | Browser proof for the @alpinejs/csp migration (UAMS-Web/uams-statamic-archive#1859).
 |
 | Unlike ReplicatorPublicFormTest — which asserts on `content()`, the served
 | HTTP document — these assertions run against the LIVE post-Alpine DOM via
 | Playwright's text locators. That distinction is the whole point: the failure
 | this ticket exists to prevent is a form whose markup is perfectly correct but
 | whose `<template x-if="…">` wrappers never activate, so every field stays
 | inert inside its <template> and never reaches the DOM. A document-level
 | assertion cannot see that; `assertSee` can.
 |
 | The fixture is a static public HTML file loading Statamic's `helpers.js`, the
 | alpine-csp-forms add-on's published script and then the compiled `site.js`, as
 | `layout.antlers.html` does (#189), so it needs neither the `content/`
 | submodule nor Statamic front-end routing.
 |
 | @command vendor/bin/pest tests/Browser/CspPublicFormConditionsTest.php
 */

const CSP_CONDITIONS_HANDLE = 'csp_conditions_browser';

$cspFormsDir = '';
$cspViewsDir = '';

/**
 * The fixture's public path. Fixed rather than per-test so `afterEach` can
 * delete it without threading a by-reference handle out of each test closure.
 */
function cspConditionsFixturePath(): string
{
    return public_path('__csp_conditions_fixture.html');
}

/**
 * Write the fixture page for a given JS driver and return its public path.
 */
function writeCspConditionsFixture(string $js, string $viewsDir): string
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.CSP_CONDITIONS_HANDLE.'" js="'.$js.'" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

    $fixture = cspConditionsFixturePath();

    File::put($fixture, implode('', [
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
        '<title>CSP conditions fixture</title>',
        '<script defer src="/vendor/statamic/frontend/js/helpers.js"></script><script defer src="/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
        '</head><body><h1>CSP conditions fixture</h1>',
        (string) View::make('csp_conditions_browser::form')->render(),
        '</body></html>',
    ]));

    return $fixture;
}

beforeEach(function () use (&$cspFormsDir, &$cspViewsDir): void {
    $cspFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($cspFormsDir);
    Config::set('statamic.forms.forms', $cspFormsDir);

    app(BlueprintRepository::class)->setFallback(
        'forms.'.CSP_CONDITIONS_HANDLE,
        fn () => Blueprint::makeFromFields([
            'trigger' => [
                'type' => 'text',
                'display' => 'Trigger',
                'default' => 'Yes',
            ],
            // Its condition is satisfied by the default, so it must render.
            'passing_note' => [
                'type' => 'textarea',
                'display' => 'Passing Condition Field',
                'if' => ['trigger' => 'equals Yes'],
            ],
            // Its condition is not satisfied, so it must stay out of the DOM.
            'failing_note' => [
                'type' => 'textarea',
                'display' => 'Failing Condition Field',
                'if' => ['trigger' => 'equals Absolutely Not'],
            ],
        ]),
    );

    $form = makeForm(CSP_CONDITIONS_HANDLE);
    $form->title('CSP Conditions');
    $form->save();

    $cspViewsDir = storage_path('framework/testing/csp-views-'.uniqid('', true));
    File::ensureDirectoryExists($cspViewsDir);
    View::addNamespace('csp_conditions_browser', $cspViewsDir);
});

afterEach(function () use (&$cspFormsDir, &$cspViewsDir): void {
    if (File::exists($fixture = cspConditionsFixturePath())) {
        File::delete($fixture);
    }

    foreach ([$cspFormsDir, $cspViewsDir, storage_path('forms/'.CSP_CONDITIONS_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('renders a conditional public form under the csp build', function () use (&$cspViewsDir): void {
    writeCspConditionsFixture('alpine_csp', $cspViewsDir);

    $page = visit('/__csp_conditions_fixture.html');

    // The fixture served and parsed.
    $page->assertSee('CSP conditions fixture');

    // ---
    // The load-bearing assertion. This label only exists in the live DOM if
    // Alpine started, the `$showField` magic resolved from the scope stack, and
    // the call returned true — i.e. the entire chain this ticket rebuilt. Under
    // the stock driver the expression throws and nothing below the <template>
    // ever renders, which is what the negative control below pins down.
    // ---
    $page->assertSee('Passing Condition Field');

    // The unconditional field renders too, so a total Alpine failure and a
    // conditions failure stay distinguishable.
    $page->assertSee('Trigger');

    // And the condition genuinely discriminates rather than showing everything.
    $page->assertDontSee('Failing Condition Field');

    // No expression errors reached the console. Uses the project helper rather
    // than the plugin's raw assertion so known-benign browser noise is filtered
    // (see tests/Browser/README.md).
    assertNoAppJavaScriptErrors($page);
});

it('is what makes the difference — the stock driver renders no fields at all', function () use (&$cspViewsDir): void {
    // ---
    // The negative control, and the clearest statement of why AlpineCsp exists.
    // Identical fixture, identical bundle, only the driver changes. If this ever
    // starts seeing the field, either Statamic fixed the expression upstream or
    // the CSP build stopped enforcing its scope rule — both worth knowing.
    // ---
    writeCspConditionsFixture('alpine', $cspViewsDir);

    $page = visit('/__csp_conditions_fixture.html');

    // The page itself is fine — this is not a load failure.
    $page->assertSee('CSP conditions fixture');

    // But every conditional field is gone, because each `x-if` threw.
    $page->assertDontSee('Passing Condition Field');
    $page->assertDontSee('Failing Condition Field');

    // ---
    // And the reason is on the console, in the CSP build's own words. Read from
    // `window.__pestBrowser.jsErrors` — the buffer the plugin's own
    // assertNoJavaScriptErrors() reads — because Alpine's error handler
    // re-throws from a setTimeout, which lands there rather than in a return
    // value the test could observe directly.
    // ---
    $messages = mustBeString($page->script(
        '(() => JSON.stringify(((window.__pestBrowser && window.__pestBrowser.jsErrors) || [])'
        .'.map((e) => String((e && e.message) || e))))()'
    ), 'the browser JS error buffer');

    expect($messages)->toContain('Undefined variable: Statamic');
});
