<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form;
use Statamic\Fields\BlueprintRepository;
use Statamic\Fields\Fields;
use Tests\TestCase;

uses(TestCase::class);

/**
 * A public-form date field with `default: now` starts at today's date (UAMS-Web/uams-statamic-archive#2885).
 *
 * `AbstractJsDriver::getInitialFormData()` preprocesses the blueprint fields, and
 * `App\Fieldtypes\Date` passes the `now` sentinel through unchanged for the CP, so
 * without a public-side substitution the form's `x-data` carries the string "now",
 * which a native date input cannot display. Gravity Forms' `{date_mdy}` default,
 * used by 158 exported fields, is the behavior being matched.
 *
 * Rendered through the real `{{ form:create }}` pipeline with `js="alpine_csp"`, the
 * driver public forms use.
 *
 * @command php artisan test --compact tests/Feature/Forms/DateDefaultNowPublicFormTest.php
 */
const DATE_DEFAULT_NOW_FORM_HANDLE = 'date_default_now';

/**
 * 03:30 UTC on 2026-03-10, which is still 2026-03-09 in America/Chicago.
 *
 * The two calendars disagree at this instant, so the expected date shows which
 * timezone "today" was read in.
 */
const DATE_DEFAULT_NOW_INSTANT = '2026-03-10 03:30:00';

$dateDefaultNowFormsDir = '';

/**
 * Register a form with two `default: now` date fields after their controls: a text field
 * whose default is the word "now", a fixed-default date, and a range date defaulting to now.
 */
function makeDateDefaultNowForm(): void
{
    app(BlueprintRepository::class)->setFallback(
        'forms.'.DATE_DEFAULT_NOW_FORM_HANDLE,
        fn () => Blueprint::makeFromFields([
            // The controls come first, so a guard that stopped the loop rather than
            // skipping one field would leave the date fields after them untouched.
            'greeting' => [
                'type' => 'text',
                'display' => 'Greeting',
                'default' => 'now',
            ],
            'fixed_date' => [
                'type' => 'date',
                'display' => 'Fixed Date',
                'mode' => 'single',
                'default' => '2025-12-25',
            ],
            'stay' => [
                'type' => 'date',
                'display' => 'Stay',
                'mode' => 'range',
                'default' => 'now',
            ],
            'visit_date' => [
                'type' => 'date',
                'display' => 'Visit Date',
                'mode' => 'single',
                'default' => 'now',
            ],
            'follow_up_date' => [
                'type' => 'date',
                'display' => 'Follow-up Date',
                'mode' => 'single',
                'default' => 'now',
            ],
        ]),
    );

    $form = makeForm(DATE_DEFAULT_NOW_FORM_HANDLE);
    $form->title('Date Default Now');
    $form->save();
}

/**
 * Render the form through the real form:create pipeline.
 */
function renderDateDefaultNowForm(): string
{
    $viewsDir = storage_path('framework/testing/views-'.uniqid('', true));
    File::ensureDirectoryExists($viewsDir);
    View::addNamespace('date_default_now_test', $viewsDir);
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.DATE_DEFAULT_NOW_FORM_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    $html = (string) View::make('date_default_now_test::form')->render();

    File::deleteDirectory($viewsDir);

    return $html;
}

/**
 * Decode the form's `x-data` attribute into the initial data array.
 *
 * @return array<string, mixed>
 */
function dateDefaultNowXData(string $html): array
{
    preg_match('/<form\b[^>]*\bx-data="([^"]*)"/', $html, $match);
    $encoded = mustBeString($match[1] ?? null, 'the form x-data attribute');

    /** @var array<string, mixed> */
    return mustBeArray(
        json_decode(html_entity_decode($encoded, ENT_QUOTES, 'UTF-8'), true),
        'the decoded form x-data',
    );
}

/**
 * Return the opening tag of the input whose `name` is the given handle.
 */
function dateDefaultNowInputTag(string $html, string $handle): string
{
    preg_match('/<input\b[^>]*\bname="'.preg_quote($handle, '/').'"[^>]*>/', $html, $match);

    return mustBeString($match[0] ?? null, 'the '.$handle.' input tag');
}

beforeEach(function () use (&$dateDefaultNowFormsDir): void {
    $dateDefaultNowFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($dateDefaultNowFormsDir);
    Config::set('statamic.forms.forms', $dateDefaultNowFormsDir);
    Config::set('statamic.system.display_timezone', 'America/Chicago');
    Carbon::setTestNow(Carbon::parse(DATE_DEFAULT_NOW_INSTANT, 'UTC'));
});

afterEach(function () use (&$dateDefaultNowFormsDir): void {
    Carbon::setTestNow();

    foreach ([$dateDefaultNowFormsDir, storage_path('forms/'.DATE_DEFAULT_NOW_FORM_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('starts a default-now date field at today in the display timezone', function (): void {
    makeDateDefaultNowForm();

    $xData = dateDefaultNowXData(renderDateDefaultNowForm());

    // Today in America/Chicago, the date a visitor's calendar shows, and the
    // `Y-m-d` shape a native date input accepts. UTC would give 2026-03-10.
    expect($xData['visit_date'] ?? null)->toBe('2026-03-09')
        ->and($xData['follow_up_date'] ?? null)->toBe('2026-03-09');
});

it('falls back to the application timezone without a display timezone', function (): void {
    Config::set('statamic.system.display_timezone', null);
    Config::set('app.timezone', 'America/Los_Angeles');
    makeDateDefaultNowForm();

    $xData = dateDefaultNowXData(renderDateDefaultNowForm());

    // 20:30 on 2026-03-09 in Los Angeles, where UTC is already 2026-03-10.
    expect($xData['visit_date'] ?? null)->toBe('2026-03-09');
});

it('leaves other defaults as they were', function (): void {
    makeDateDefaultNowForm();

    $xData = dateDefaultNowXData(renderDateDefaultNowForm());

    // A text field whose default happens to be the word "now" is not a date.
    expect($xData['greeting'] ?? null)->toBe('now');

    // A date field with a fixed default keeps it.
    expect($xData['fixed_date'] ?? null)->not->toBe('2026-03-09');

    // A range field preprocesses `now` to a start/end pair, which its two inputs read.
    expect($xData['stay'] ?? null)->toBeArray();
});

it('binds the rendered date input to the initial data with a plain x-model', function (): void {
    makeDateDefaultNowForm();

    $input = dateDefaultNowInputTag(renderDateDefaultNowForm(), 'visit_date');

    // A native date input, bound with a plain `x-model` (UAMS-Web/uams-statamic-archive#2800), so Alpine writes
    // the `x-data` value into it on load rather than reading the input's own value.
    expect($input)->toContain('type="date"')
        ->and($input)->toContain('x-model="visit_date"')
        ->and($input)->not->toContain('x-model.fill');
});

it('lets a value from a failed validation win over the default', function (): void {
    makeDateDefaultNowForm();

    // A failed submission re-renders the form with the visitor's own input.
    $this->app['session.store']->flash('_old_input', ['visit_date' => '2026-01-02']);
    request()->setLaravelSession($this->app['session.store']);

    $xData = dateDefaultNowXData(renderDateDefaultNowForm());

    // The submitted date, as the Date fieldtype preprocesses it, not today. That
    // shape is not one a native date input can display either; it predates UAMS-Web/uams-statamic-archive#2885.
    expect($xData['visit_date'] ?? null)->toBe('2026-01-02T00:00:00.000Z');

    // A field the visitor did not submit still starts at today.
    expect($xData['follow_up_date'] ?? null)->toBe('2026-03-09');
});

it('keeps the Control Panel preprocessing of now', function (): void {
    makeDateDefaultNowForm();

    $form = mustExist(Form::find(DATE_DEFAULT_NOW_FORM_HANDLE), 'the date form');

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $form->blueprint();

    // The CP reads the blueprint fields directly, not the public driver, and the
    // Date fieldtype still hands it the sentinel.
    /** @var Fields $fields */
    $fields = $blueprint->fields()->preProcess();

    expect(data_get($fields->values(), 'visit_date'))->toBe('now');
});

it('does not refill a date the visitor left empty on a failed submission', function (): void {
    makeDateDefaultNowForm();

    // The visitor cleared the date and the submission failed validation.
    $this->app['session.store']->flash('_old_input', ['visit_date' => null]);
    request()->setLaravelSession($this->app['session.store']);

    $xData = dateDefaultNowXData(renderDateDefaultNowForm());

    expect($xData['visit_date'] ?? null)->not->toBe('2026-03-09');

    // The next default-now field, which the visitor did not submit, still starts at today.
    expect($xData['follow_up_date'] ?? null)->toBe('2026-03-09');
});
