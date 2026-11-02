<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;

/*
 | A Select or Radio field's Dynamic Default Value applies on a public form (UAMS-Web/uams-statamic-archive#2886),
 | and so does a Checkboxes field's or a multiple Select's (UAMS-Web/uams-statamic-archive#2924).
 |
 | The feature test (tests/Feature/Views/FormsFieldsChoiceDynamicDefaultTest.php)
 | proves the templates emit the `data-calculation-*` attributes. That the choice
 | is then SELECTED is calculation-engine.js writing the formula's result into the
 | field's Alpine key under the CSP build, which only a browser shows, so this reads
 | the live controls after load.
 |
 | @command vendor/bin/pest tests/Browser/ChoiceDynamicDefaultPublicFormTest.php
 */
const CHOICE_DYNAMIC_DEFAULT_BROWSER_HANDLE = 'choice_dynamic_default_browser';

$cddFormsDir = '';
$cddViewsDir = '';

/** The fixture's public path, fixed so afterEach can delete it. */
function choiceDynamicDefaultBrowserFixturePath(): string
{
    return public_path('__choice_dynamic_default_fixture.html');
}

/** Write the fixture page around the real form tag and field partials. */
function writeChoiceDynamicDefaultBrowserFixture(string $viewsDir): void
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.CHOICE_DYNAMIC_DEFAULT_BROWSER_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

    File::put(choiceDynamicDefaultBrowserFixturePath(), implode('', [
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
        '<title>Choice dynamic default fixture</title>',
        '<script defer src="/vendor/statamic/frontend/js/helpers.js"></script><script defer src="/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
        '</head><body><h1>Choice dynamic default fixture</h1>',
        (string) View::make('choice_dynamic_default_browser::form')->render(),
        '</body></html>',
    ]));
}

beforeEach(function () use (&$cddFormsDir, &$cddViewsDir): void {
    $cddFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($cddFormsDir);
    Config::set('statamic.forms.forms', $cddFormsDir);

    $options = ['a' => 'Aye', 'b' => 'Bee', 'c' => 'Cee'];
    $dynamic = ['calculation_query' => true, 'calculation_formula' => '[get_param:topic]'];

    app(BlueprintRepository::class)->setFallback(
        'forms.'.CHOICE_DYNAMIC_DEFAULT_BROWSER_HANDLE,
        fn () => Blueprint::makeFromFields([
            'topic_select' => ['type' => 'select', 'display' => 'Topic Select', 'options' => $options, ...$dynamic],
            'topic_radio' => ['type' => 'radio', 'display' => 'Topic Radio', 'options' => $options, ...$dynamic],
            // Required, so the group's `required` handling is measured alongside the default
            'topic_checkboxes' => ['type' => 'checkboxes', 'display' => 'Topic Checkboxes', 'options' => $options, 'validate' => ['required'], ...$dynamic],
            'topic_multi' => ['type' => 'select', 'display' => 'Topic Multi', 'options' => $options, 'multiple' => true, ...$dynamic],
        ]),
    );

    $form = makeForm(CHOICE_DYNAMIC_DEFAULT_BROWSER_HANDLE);
    $form->title('Choice Dynamic Default');
    $form->save();

    $cddViewsDir = storage_path('framework/testing/cdd-views-'.uniqid('', true));
    File::ensureDirectoryExists($cddViewsDir);
    View::addNamespace('choice_dynamic_default_browser', $cddViewsDir);
});

afterEach(function () use (&$cddFormsDir, &$cddViewsDir): void {
    if (File::exists($fixture = choiceDynamicDefaultBrowserFixturePath())) {
        File::delete($fixture);
    }

    foreach ([$cddFormsDir, $cddViewsDir, storage_path('forms/'.CHOICE_DYNAMIC_DEFAULT_BROWSER_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

/**
 * Wait until the engine has bound the select, the three radios, the three checkboxes
 * and the multiple select, then until the
 * state settles on the expected value or the timeout passes, and return the last
 * state read, or a note that the controls were never bound.
 *
 * The engine binds after Alpine starts, so reading straight after load could see
 * an empty control for a reason unrelated to the dynamic default.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from visit().
 */
function choiceDynamicDefaultState(AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage $page, string $expected, float $timeoutSeconds = 10.0): string
{
    $interval = 0.1;
    $boundScript = '(() => document.querySelectorAll(\'[data-calculation-bound="1"]\').length)()';

    for ($waited = 0.0; $page->script($boundScript) !== 8; $waited += $interval) {
        if ($waited >= $timeoutSeconds) {
            return 'the engine did not bind the eight controls';
        }

        $page->wait($interval);
    }

    $state = '';

    for ($waited = 0.0; $waited < $timeoutSeconds; $waited += $interval) {
        $state = mustBeString($page->script(CHOICE_DYNAMIC_DEFAULT_STATE_SCRIPT), 'the choice-field state');

        if ($state === $expected) {
            return $state;
        }

        $page->wait($interval);
    }

    return $state;
}

/** Each control's value, checked radio, checked boxes and selected options, read from the live page. */
const CHOICE_DYNAMIC_DEFAULT_STATE_SCRIPT = <<<'JS'
    (() => JSON.stringify({
        select: document.querySelector('select[name="topic_select"]').value,
        radio: Array.from(document.querySelectorAll('input[name="topic_radio"]'))
            .filter((input) => input.checked)
            .map((input) => input.value),
        checkboxes: Array.from(document.querySelectorAll('input[type="checkbox"][name="topic_checkboxes[]"]'))
            .filter((input) => input.checked)
            .map((input) => input.value),
        multi: Array.from(document.querySelector('select[name="topic_multi[]"]').selectedOptions)
            .map((option) => option.value),
        required: Array.from(document.querySelectorAll('input[type="checkbox"][name="topic_checkboxes[]"]'))
            .filter((input) => input.required).length,
    }))()
    JS;

it('selects the URL parameter in a Select and a Radio with a dynamic default', function () use (&$cddViewsDir): void {
    writeChoiceDynamicDefaultBrowserFixture($cddViewsDir);

    $page = visit('/__choice_dynamic_default_fixture.html?topic=b');

    // Before UAMS-Web/uams-statamic-archive#2886 neither the select nor the radio template carried the attributes
    // the engine scans for, and before UAMS-Web/uams-statamic-archive#2924 the engine skipped the checkboxes' and
    // the multiple select's `[]` names, so all four stayed empty.
    // A checked box also clears `required` from the group, so the form can be submitted.
    $expected = '{"select":"b","radio":["b"],"checkboxes":["b"],"multi":["b"],"required":0}';
    expect(choiceDynamicDefaultState($page, $expected))->toBe($expected);

    assertNoAppJavaScriptErrors($page);
});

it('leaves a Select and a Radio empty when the URL parameter is absent', function () use (&$cddViewsDir): void {
    writeChoiceDynamicDefaultBrowserFixture($cddViewsDir);

    $page = visit('/__choice_dynamic_default_fixture.html');

    $expected = '{"select":"","radio":[],"checkboxes":[],"multi":[],"required":3}';
    expect(choiceDynamicDefaultState($page, $expected))->toBe($expected);

    assertNoAppJavaScriptErrors($page);
});

it('checks every box and option a comma-separated URL parameter names', function () use (&$cddViewsDir): void {
    writeChoiceDynamicDefaultBrowserFixture($cddViewsDir);

    $page = visit('/__choice_dynamic_default_fixture.html?topic=a,c');

    // The single-value controls have no option `a,c`, so they stay empty; the list
    // controls read the result as the two values it names (UAMS-Web/uams-statamic-archive#2924).
    $expected = '{"select":"","radio":[],"checkboxes":["a","c"],"multi":["a","c"],"required":0}';
    expect(choiceDynamicDefaultState($page, $expected))->toBe($expected);

    assertNoAppJavaScriptErrors($page);
});
