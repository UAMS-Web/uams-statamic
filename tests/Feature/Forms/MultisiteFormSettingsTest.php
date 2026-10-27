<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Facades\Form as FormFacade;
use Statamic\Facades\User;
use Statamic\Facades\YAML;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Fields;
use Statamic\Forms\Form;
use Tests\Support\ThrowawayRoot;
use Tests\TestCase;
use UAMSWeb\FormSettings\CpForms\FormConfigurationSectionLocator;
use UAMSWeb\FormSettings\Http\Controllers\FormsController;

uses(TestCase::class);

/**
 * The `uams-web/statamic-multisite-forms` Sites section reaches the app's form
 * settings screen and its values survive a save through the app's controller
 * (UAMS-Web/uams-statamic-archive#3066).
 *
 * The app replaces core's `FormsController` with its own, which rebuilds the
 * settings blueprint and writes the form itself, so the add-on's section is
 * only present, and only saved, if that controller keeps core's
 * `Form::appendConfigFields()` path intact.
 *
 * An override row's `email` grid offers exactly the base email grid's columns
 * (UAMS-Web/uams-statamic-archive#3152, UAMS-Web/uams-statamic-archive#3164), so a row set in the Control Panel saves and validates as a
 * base row does.
 *
 * @command  php artisan test --compact tests/Feature/Forms/MultisiteFormSettingsTest.php
 */

/**
 * Handle of the form each test creates in the throwaway forms directory.
 */
const MULTISITE_FORM_SETTINGS_HANDLE = 'multisite_form_settings_probe';

/**
 * The throwaway forms directory for this process, so a save never writes into
 * the `resources/forms` submodule.
 */
function multisiteFormSettingsFormsPath(): string
{
    return storage_path('framework/testing/multisite-form-settings/'.ThrowawayRoot::segment());
}

/**
 * The settings blueprint the app's controller builds for a form.
 *
 * @param  Form  $form  The form whose settings screen is built.
 * @return Blueprint The blueprint the Control Panel renders and saves against.
 */
function multisiteFormSettingsBlueprint(Form $form): Blueprint
{
    return mustBe(Blueprint::class, (new ReflectionMethod(FormsController::class, 'editFormBlueprint'))
        ->invoke(app(FormsController::class), $form), 'blueprint');
}

beforeEach(function (): void {
    // Point the form repository at an empty throwaway directory
    File::deleteDirectory(multisiteFormSettingsFormsPath());
    File::ensureDirectoryExists(multisiteFormSettingsFormsPath());
    Config::set('statamic.forms.forms', multisiteFormSettingsFormsPath());

    // Create the probe form there
    $form = mustBe(Form::class, FormFacade::make(MULTISITE_FORM_SETTINGS_HANDLE), 'form');
    $form->title('Multisite Probe');

    // Give it the default confirmation message a save requires (UAMS-Web/uams-statamic-archive#3162)
    $form->merge(['confirmation_settings_default' => [
        'confirmation_type' => 'message',
        'confirmation_message' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Thanks']]]],
    ]]);
    $form->save();
});

afterEach(function (): void {
    File::deleteDirectory(multisiteFormSettingsFormsPath());
});

it('shows the Sites section on the form settings screen', function (): void {
    $form = mustBe(Form::class, FormFacade::find(MULTISITE_FORM_SETTINGS_HANDLE), 'form');

    /** @var array<string, mixed> $contents */
    $contents = multisiteFormSettingsBlueprint($form)->contents();

    // Find the section carrying the add-on's assignment field
    $sites = FormConfigurationSectionLocator::locateField($contents, 'sites');
    expect($sites)->not->toBeNull();

    /** @var array{tab: string, section: int, field: int} $sites */
    $section = data_get($contents, "tabs.{$sites['tab']}.sections.{$sites['section']}");
    $fields = data_get($section, 'fields');
    Assert::assertIsArray($fields);
    expect(data_get($section, 'display'))->toBe('Sites')
        ->and(array_column($fields, 'handle'))->toContain('sites', 'site_overrides');
});

it('keeps a form\'s sites and site overrides through a save', function (): void {
    // Sign in as a super user, who may assign any site
    $user = mustBe(FileStatamicUser::class, User::make(), 'user');
    $user->email('multisite-probe@example.com');
    $user->makeSuper();
    $this->actingAs($user);

    $form = mustBe(Form::class, FormFacade::find(MULTISITE_FORM_SETTINGS_HANDLE), 'form');

    // Pre-process the form's current settings the way the publish form does before posting
    $blueprint = multisiteFormSettingsBlueprint($form);
    $values = array_merge(mustBe(Collection::class, $form->data(), 'form data')->all(), [
        'title' => $form->title(),
        'honeypot' => $form->honeypot(),
        'store' => $form->store(),
        'email' => $form->email(),
    ]);
    $stored = mustBe(Fields::class, mustBe(Fields::class, $blueprint->fields(), 'fields')->addValues($values), 'fields');
    $posted = mustBe(Collection::class, mustBe(Fields::class, $stored->preProcess(), 'fields')->values(), 'values')->all();

    // Post them with one site assigned and one override row for that site
    $request = Request::create('/', 'PATCH', [
        ...$posted,
        'sites' => ['health'],
        'site_overrides' => [
            ['_id' => 'row1', 'site' => ['health'], 'title' => 'Health Probe', 'store' => null, 'email' => []],
        ],
    ]);

    app(FormsController::class)->update($form, $request);

    // Read the form file the save wrote, rather than the instance that was saved
    $file = multisiteFormSettingsFormsPath().'/'.MULTISITE_FORM_SETTINGS_HANDLE.'.yaml';
    $saved = YAML::parse(File::get($file));

    expect(data_get($saved, 'sites'))->toBe(['health'])
        ->and(data_get($saved, 'site_overrides.0.site'))->toBe('health')
        ->and(data_get($saved, 'site_overrides.0.title'))->toBe('Health Probe');
})->group('serial');

/**
 * The columns of the grid a field carries, located by the field's handle in the contents.
 *
 * @param  array<string, mixed>  $contents  Blueprint contents.
 * @param  string  $handle  Handle of a top-level field in a section.
 * @return list<array<string, mixed>> The grid's columns.
 */
function multisiteFormSettingsGridColumns(array $contents, string $handle): array
{
    $location = FormConfigurationSectionLocator::locateField($contents, $handle);
    Assert::assertNotNull($location, "no {$handle} field in the contents");

    /** @var list<array<string, mixed>> $columns */
    $columns = data_get($contents, "tabs.{$location['tab']}.sections.{$location['section']}.fields.{$location['field']}.field.fields");

    return $columns;
}

/**
 * The columns of the `email` grid inside a site override row.
 *
 * @param  array<string, mixed>  $contents  Blueprint contents.
 * @return list<array<string, mixed>> The override row's email columns.
 */
function multisiteFormSettingsOverrideEmailColumns(array $contents): array
{
    $email = collect(multisiteFormSettingsGridColumns($contents, 'site_overrides'))->firstWhere('handle', 'email');
    Assert::assertIsArray($email);

    /** @var list<array<string, mixed>> $columns */
    $columns = data_get($email, 'field.fields');

    return $columns;
}

/**
 * An email row in stored form, filling the app's columns.
 *
 * @param  array<string, mixed>  $overrides  Values to change.
 * @return array<string, mixed>
 */
function multisiteFormSettingsEmailRow(array $overrides = []): array
{
    return [
        'name' => 'Notify the office',
        'enabled' => true,
        'to_type' => 'email',
        'to' => 'office@example.com',
        'cc_type' => 'email',
        'bcc_type' => 'email',
        'reply_to_type' => 'email',
        'from' => 'forms@example.com',
        'from_name' => 'UAMS Forms',
        'subject' => 'New submission',
        'message' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]]],
        'conditional_logic' => ['if' => ['department' => 'cardiology'], 'always_save' => false],
        ...$overrides,
    ];
}

/**
 * Pre-process stored settings through the blueprint the way the publish form does before posting.
 *
 * @param  array<string, mixed>  $values  Stored settings.
 * @return array<string, mixed> The values as posted.
 */
function multisiteFormSettingsPosted(Form $form, array $values): array
{
    $blueprint = multisiteFormSettingsBlueprint($form);
    $stored = mustBe(Fields::class, mustBe(Fields::class, $blueprint->fields(), 'fields')->addValues($values), 'fields');

    /** @var array<string, mixed> $posted */
    $posted = mustBe(Collection::class, mustBe(Fields::class, $stored->preProcess(), 'fields')->values(), 'values')->all();

    return $posted;
}

/**
 * The validation errors a save of the given stored settings reports.
 *
 * @param  array<string, mixed>  $values  Stored settings.
 * @return list<string> The paths of the fields in error.
 */
function multisiteFormSettingsErrorPaths(Form $form, array $values): array
{
    $posted = multisiteFormSettingsPosted($form, $values);
    $fields = mustBe(Fields::class, mustBe(Fields::class, multisiteFormSettingsBlueprint($form)->fields(), 'fields')->addValues($posted), 'fields');

    try {
        $fields->validate();
    } catch (ValidationException $exception) {
        return array_keys($exception->errors());
    }

    return [];
}

it('hands the email grid customizer contents that already carry the Sites section', function (): void {
    $form = mustBe(Form::class, FormFacade::find(MULTISITE_FORM_SETTINGS_HANDLE), 'form');

    // Read core's blueprint, which is what the customizer receives, before any customization
    $core = mustBe(Blueprint::class, (new ReflectionMethod(FormsController::class, 'resolveStatamicFormConfigurationBlueprint'))
        ->invoke(app(FormsController::class), $form), 'core blueprint');

    /** @var array<string, mixed> $contents */
    $contents = $core->contents();

    expect(array_column(multisiteFormSettingsOverrideEmailColumns($contents), 'handle'))->not->toContain('message')
        ->and(array_column(multisiteFormSettingsGridColumns($contents, 'email'), 'handle'))->not->toContain('message');
});

it('offers the base email grid\'s columns, in order, on a site override row', function (): void {
    $form = mustBe(Form::class, FormFacade::find(MULTISITE_FORM_SETTINGS_HANDLE), 'form');

    /** @var array<string, mixed> $contents */
    $contents = multisiteFormSettingsBlueprint($form)->contents();
    $base = multisiteFormSettingsGridColumns($contents, 'email');

    // Check the base grid carries the app's columns, so the comparison is not between two core grids
    expect(array_column($base, 'handle'))->toContain('name', 'enabled', 'to_type', 'from_name', 'message', 'conditional_logic')
        ->and(multisiteFormSettingsOverrideEmailColumns($contents))->toBe($base);
});

it('validates a site override row\'s recipient source as it does a base row\'s', function (array $row, string $column): void {
    $form = mustBe(Form::class, FormFacade::find(MULTISITE_FORM_SETTINGS_HANDLE), 'form');
    $confirmation = mustBeArray($form->get('confirmation_settings_default'), 'confirmation');
    $values = ['title' => 'Probe', 'submit' => 'Send', 'confirmation_settings_default' => $confirmation];

    // Check a base row and an override row with the same gap each report it on their own row
    expect(multisiteFormSettingsErrorPaths($form, [...$values, 'email' => [$row]]))->toBe(['email.0.'.$column])
        ->and(multisiteFormSettingsErrorPaths($form, [...$values, 'site_overrides' => [['site' => 'health', 'email' => [$row]]]]))
        ->toBe(['site_overrides.0.email.0.'.$column]);

    // Check the same rows pass once the recipient is valid
    $valid = multisiteFormSettingsEmailRow();
    expect(multisiteFormSettingsErrorPaths($form, [...$values, 'email' => [$valid], 'site_overrides' => [['site' => 'health', 'email' => [$valid]]]]))
        ->toBe([]);
})->with([
    'an address source with no address' => [multisiteFormSettingsEmailRow(['to' => null]), 'to'],
    'a field source with no field' => [multisiteFormSettingsEmailRow(['to_type' => 'field', 'to' => null]), 'to_field'],
    'a routing source with no rules' => [multisiteFormSettingsEmailRow(['to_type' => 'routing', 'to' => null]), 'to_routing'],
    'no subject' => [multisiteFormSettingsEmailRow(['subject' => null]), 'subject'],
]);

it('saves a site override row\'s app columns as it saves a base row\'s', function (): void {
    $user = mustBe(FileStatamicUser::class, User::make(), 'user');
    $user->email('multisite-probe@example.com');
    $user->makeSuper();
    $this->actingAs($user);

    $form = mustBe(Form::class, FormFacade::find(MULTISITE_FORM_SETTINGS_HANDLE), 'form');
    $row = multisiteFormSettingsEmailRow();

    // Post the same email row as a base row and as the health site's override row
    $posted = multisiteFormSettingsPosted($form, array_merge(mustBe(Collection::class, $form->data(), 'form data')->all(), [
        'title' => $form->title(),
        'honeypot' => $form->honeypot(),
        'store' => $form->store(),
        'email' => [$row],
        'sites' => ['health'],
        'site_overrides' => [['site' => 'health', 'email' => [$row]]],
    ]));

    app(FormsController::class)->update($form, Request::create('/', 'PATCH', $posted));

    // Read the form file the save wrote
    $saved = YAML::parse(File::get(multisiteFormSettingsFormsPath().'/'.MULTISITE_FORM_SETTINGS_HANDLE.'.yaml'));
    $base = mustBeArray(data_get($saved, 'email.0'), 'saved base row');
    $override = mustBeArray(data_get($saved, 'site_overrides.0.email.0'), 'saved override row');
    unset($base['id'], $override['id']);

    // Compare by key: the nested grid writes its columns in its own order, and stores the two
    // unset core toggles as false where the base row leaves them out, which sends the same way
    ksort($base);
    ksort($override);

    expect($override)->toHaveKeys(['enabled', 'to_type', 'from_name', 'message', 'conditional_logic'])
        ->and(array_diff_key($override, ['markdown' => true, 'attachments' => true]))->toBe($base)
        ->and([$override['markdown'] ?? null, $override['attachments'] ?? null])->toBe([false, false]);
})->group('serial');
