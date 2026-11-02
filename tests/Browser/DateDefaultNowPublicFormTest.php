<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;

/*
 | A public-form date field with `default: now` shows today's date on load (UAMS-Web/uams-statamic-archive#2885).
 |
 | The feature test (tests/Feature/Forms/DateDefaultNowPublicFormTest.php) proves
 | the form's `x-data` carries the date and the input is bound with a plain
 | `x-model`. That the input then DISPLAYS it is Alpine's doing under the CSP
 | build, which only a browser shows, so this reads the input's live value.
 |
 | @command vendor/bin/pest tests/Browser/DateDefaultNowPublicFormTest.php
 */
const DATE_DEFAULT_NOW_BROWSER_HANDLE = 'date_default_now_browser';

$ddnFormsDir = '';
$ddnViewsDir = '';

/** The fixture's public path, fixed so afterEach can delete it. */
function dateDefaultNowBrowserFixturePath(): string
{
    return public_path('__date_default_now_fixture.html');
}

/** Write the fixture page, rendered in this process so the frozen clock applies. */
function writeDateDefaultNowBrowserFixture(string $viewsDir): void
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.DATE_DEFAULT_NOW_BROWSER_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

    File::put(dateDefaultNowBrowserFixturePath(), implode('', [
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
        '<title>Date default now fixture</title>',
        '<script defer src="/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
        '</head><body><h1>Date default now fixture</h1>',
        (string) View::make('date_default_now_browser::form')->render(),
        '</body></html>',
    ]));
}

beforeEach(function () use (&$ddnFormsDir, &$ddnViewsDir): void {
    $ddnFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($ddnFormsDir);
    Config::set('statamic.forms.forms', $ddnFormsDir);
    Config::set('statamic.system.display_timezone', 'America/Chicago');

    // 03:30 UTC on 2026-03-10 is still 2026-03-09 in America/Chicago.
    Carbon::setTestNow(Carbon::parse('2026-03-10 03:30:00', 'UTC'));

    app(BlueprintRepository::class)->setFallback(
        'forms.'.DATE_DEFAULT_NOW_BROWSER_HANDLE,
        fn () => Blueprint::makeFromFields([
            'visit_date' => ['type' => 'date', 'display' => 'Visit Date', 'mode' => 'single', 'default' => 'now'],
        ]),
    );

    $form = makeForm(DATE_DEFAULT_NOW_BROWSER_HANDLE);
    $form->title('Date Default Now');
    $form->save();

    $ddnViewsDir = storage_path('framework/testing/ddn-views-'.uniqid('', true));
    File::ensureDirectoryExists($ddnViewsDir);
    View::addNamespace('date_default_now_browser', $ddnViewsDir);
});

afterEach(function () use (&$ddnFormsDir, &$ddnViewsDir): void {
    Carbon::setTestNow();

    if (File::exists($fixture = dateDefaultNowBrowserFixturePath())) {
        File::delete($fixture);
    }

    foreach ([$ddnFormsDir, $ddnViewsDir, storage_path('forms/'.DATE_DEFAULT_NOW_BROWSER_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('shows today in a default-now date input on load', function () use (&$ddnViewsDir): void {
    writeDateDefaultNowBrowserFixture($ddnViewsDir);

    $page = visit('/__date_default_now_fixture.html');

    // Before UAMS-Web/uams-statamic-archive#2885 the input was bound to the string "now", which a native date
    // input rejects, so it showed an empty value.
    $value = mustBeString($page->script(
        '(() => document.querySelector(\'input[name="visit_date"]\').value)()'
    ), 'the visit_date input value');

    expect($value)->toBe('2026-03-09');

    assertNoAppJavaScriptErrors($page);
});
