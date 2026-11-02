<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;

/*
 | Browser smoke for the public-form Replicator (UAMS-Web/uams-statamic-archive#675). The form is pre-rendered through the real
 | form:create pipeline and written to a **static** public HTML file (served directly, so it needs
 | neither the `content/` submodule nor Statamic front-end routing), which loads the compiled
 | `site.js`. A headless browser then loads it, proving the field renders end-to-end in a real
 | browser with its Alpine engine binding, cloneable per-type set templates, and correctly-nested
 | subfield names — without a fatal page error.
 |
 | Scope, honestly: this asserts on the served document (`content()` returns the HTTP response, not the
 | live post-Alpine DOM), so it proves the field *renders* in a real browser context — not that the
 | engine executed. The engine's JS logic (add / remove / min_sets clamp / reindex-on-submit) is
 | covered directly by the jsdom unit spec `resources/js/replicator-field.test.js`, and its server
 | contract (submit round-trip, enabled coercion, min_sets / at-least-one-enabled) by
 | tests/Feature/Forms/ReplicatorFormRoundTripTest. A click-driven live-DOM browser assertion is
 | blocked by the pest-plugin-browser Webpage API surface here (no locator/evaluate access).
 |
 | @command vendor/bin/pest tests/Browser/ReplicatorPublicFormTest.php
 */

const REPLICATOR_BROWSER_HANDLE = 'referrals_browser';

$replicatorBrowserFormsDir = '';
$replicatorBrowserViewsDir = '';
$replicatorBrowserFixture = '';

beforeEach(function () use (&$replicatorBrowserFormsDir, &$replicatorBrowserViewsDir, &$replicatorBrowserFixture): void {
    $replicatorBrowserFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($replicatorBrowserFormsDir);
    Config::set('statamic.forms.forms', $replicatorBrowserFormsDir);

    app(BlueprintRepository::class)->setFallback('forms.'.REPLICATOR_BROWSER_HANDLE, fn () => Blueprint::makeFromFields([
        'referrals' => [
            'type' => 'replicator',
            'display' => 'Referrals',
            'min_sets' => 1,
            'sets' => [
                'main' => [
                    'sets' => [
                        'contact' => ['display' => 'Contact', 'fields' => [
                            ['handle' => 'name', 'field' => ['type' => 'text', 'display' => 'Name']],
                        ]],
                        'note' => ['display' => 'Note', 'fields' => [
                            ['handle' => 'body', 'field' => ['type' => 'textarea', 'display' => 'Body']],
                        ]],
                    ],
                ],
            ],
        ],
    ]));

    $form = makeForm(REPLICATOR_BROWSER_HANDLE);
    $form->title('Referrals');
    $form->save();

    // Pre-render the form through the real pipeline, then write it (plus the compiled site.js, by
    // its manifest path) to a static public file — served directly, bypassing Statamic routing.
    $replicatorBrowserViewsDir = storage_path('framework/testing/replicator-views-'.uniqid('', true));
    File::ensureDirectoryExists($replicatorBrowserViewsDir);
    View::addNamespace('replicator_browser', $replicatorBrowserViewsDir);
    File::put(
        $replicatorBrowserViewsDir.'/form.antlers.html',
        '{{ form:create handle="'.REPLICATOR_BROWSER_HANDLE.'" }}{{ partial:vendor/statamic/forms/fields }}{{ /form:create }}'
    );

    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');
    $formHtml = (string) View::make('replicator_browser::form')->render();

    $replicatorBrowserFixture = public_path('__replicator_fixture.html');
    File::put(
        $replicatorBrowserFixture,
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Replicator fixture</title>'
        .'<script defer src="/vendor/statamic/frontend/js/helpers.js"></script><script defer src="/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script></head>'
        .'<body><h1>Replicator fixture</h1>'.$formHtml.'</body></html>'
    );
});

afterEach(function () use (&$replicatorBrowserFormsDir, &$replicatorBrowserViewsDir, &$replicatorBrowserFixture): void {
    if ($replicatorBrowserFixture !== '' && File::exists($replicatorBrowserFixture)) {
        File::delete($replicatorBrowserFixture);
    }

    foreach ([$replicatorBrowserFormsDir, $replicatorBrowserViewsDir, storage_path('forms/'.REPLICATOR_BROWSER_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('renders the replicator form end-to-end in a headless browser', function (): void {
    $page = visit('/__replicator_fixture.html');

    // The page loads (the heading is visible), confirming the fixture serves and parses.
    $page->assertSee('Replicator fixture');

    // The rendered document carries the replicator field: its Alpine engine binding, a cloneable
    // template per set type, the "Add Set" controls, and correctly-nested placeholder subfield
    // names ready for the engine to reindex.
    $content = $page->content();
    expect($content)->toContain('x-data="replicatorField()"');
    expect($content)->toContain('data-replicator-template="contact"');
    expect($content)->toContain('data-replicator-template="note"');
    expect($content)->toContain('data-replicator-add-type="contact"');
    expect($content)->toContain('name="referrals[REPLICATORSETINDEX][name]"');
    expect($content)->toContain('name="referrals[REPLICATORSETINDEX][type]"');
});
