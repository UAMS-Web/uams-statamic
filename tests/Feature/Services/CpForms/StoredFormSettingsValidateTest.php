<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Form as FormFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Fields;
use Statamic\Forms\Form;
use Tests\Support\FormsSubmodule;
use Tests\TestCase;
use UAMSWeb\FormSettings\Http\Controllers\FormsController;

uses(TestCase::class);

/**
 * Every form stored in `resources/forms` saves through the Control Panel's
 * validate path once the form settings rules bind (UAMS-Web/uams-statamic-archive#3082).
 *
 * The email grid's `name`, `{kind}_type`, `subject` and `message` columns are
 * required, so a stored email row missing one would stop its form from being
 * saved in the Control Panel. This runs each form's stored settings the way the
 * publish form posts them (pre-processed, with field defaults) through the
 * blueprint the app's controller builds, and expects no errors. Since UAMS-Web/uams-statamic-archive#3162 that
 * includes a default confirmation message, which each stored form now carries.
 *
 * @command  php artisan test --compact tests/Feature/Services/CpForms/StoredFormSettingsValidateTest.php
 */

/**
 * The validation errors the Control Panel would report on saving a stored form
 * unchanged.
 *
 * @param  Form  $form  A form loaded from `resources/forms`.
 * @return array<string, mixed> Errors keyed by field path, empty when the form saves.
 */
function storedFormSettingsErrors(Form $form): array
{
    // Build the blueprint through the controller's protected builder
    $blueprint = mustBe(Blueprint::class, (new ReflectionMethod(FormsController::class, 'editFormBlueprint'))
        ->invoke(app(FormsController::class), $form), 'blueprint');

    // Assemble the stored values the way the controller's edit() action does
    $values = array_merge(mustBe(Collection::class, $form->data(), 'form data')->all(), [
        'handle' => $form->handle(),
        'title' => $form->title(),
        'honeypot' => $form->honeypot(),
        'store' => $form->store(),
        'email' => $form->email(),
    ]);

    // Pre-process them the way the publish form does before posting
    $stored = mustBe(Fields::class, mustBe(Fields::class, $blueprint->fields(), 'fields')->addValues($values), 'fields');
    $posted = mustBe(Collection::class, mustBe(Fields::class, $stored->preProcess(), 'fields')->values(), 'values')->all();

    // Validate the posted values against every rule the blueprint binds
    $submitted = mustBe(Fields::class, mustBe(Fields::class, $blueprint->fields(), 'fields')->addValues($posted), 'fields');

    try {
        $submitted->validate();
    } catch (ValidationException $exception) {
        /** @var array<string, mixed> $errors */
        $errors = $exception->errors();

        return $errors;
    }

    return [];
}

it('saves every stored form through the Control Panel validate path', function (): void {
    FormsSubmodule::skipUnlessInitialized();

    $forms = FormFacade::all();
    expect($forms)->not->toBeEmpty();

    // Collect the errors each stored form would raise, keeping only the failures
    $failures = [];
    foreach ($forms as $stored) {
        $form = mustBe(Form::class, $stored, 'form');
        $errors = storedFormSettingsErrors($form);
        if ($errors !== []) {
            $failures[mustBeString($form->handle(), 'form handle')] = array_keys($errors);
        }
    }

    expect($failures)->toBe([]);
});

it('reports a stored email row that leaves a required column empty', function (): void {
    FormsSubmodule::skipUnlessInitialized();

    // Take a stored form with an email row and empty its name column in memory
    $form = mustBe(Form::class, FormFacade::find('spread_kindness'), 'the spread_kindness form');
    $rows = $form->email();
    expect($rows)->toBeArray()->not->toBeEmpty();

    /** @var list<array<string, mixed>> $rows */
    $rows[0]['name'] = '';
    $form->email($rows);

    expect(array_keys(storedFormSettingsErrors($form)))->toBe(['email.0.name']);
});

it('reports a stored form whose default confirmation message is empty', function (): void {
    FormsSubmodule::skipUnlessInitialized();

    // Take a stored form and clear its seeded confirmation message in memory (UAMS-Web/uams-statamic-archive#3162)
    $form = mustBe(Form::class, FormFacade::find('spread_kindness'), 'the spread_kindness form');
    $settings = mustBeArray($form->get('confirmation_settings_default'), 'the stored confirmation settings');
    expect($settings)->toHaveKey('confirmation_message');

    $settings['confirmation_message'] = null;
    $form->set('confirmation_settings_default', $settings);

    expect(array_keys(storedFormSettingsErrors($form)))->toBe(['confirmation_settings_default.confirmation_message']);
});
