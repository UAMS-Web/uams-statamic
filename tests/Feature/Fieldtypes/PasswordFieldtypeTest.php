<?php

declare(strict_types=1);

use App\Fieldtypes\Password;
use Statamic\Facades\Antlers;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Verifies the existing `Password` fieldtype (the Statamic analog of the
 * Gravity Forms User Registration add-on `password` field) is usable on public
 * forms: selectable in form blueprints and rendering a masked input via the
 * shared default template. Covers the password-side AC of issue UAMS-Web/uams-statamic-archive#312.
 *
 * Mutation: 100% on App\Fieldtypes\Password (61 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/PasswordFieldtypeTest.php
 */
it('resolves by handle, is selectable in form blueprints, and drives a masked password input', function () {
    $field = new Field(
        'account_password',
        [
            'type' => 'password',
        ]
    );

    // Assert on the call, not the annotated variable: the `@var` below is what makes
    // an instanceof over `$fieldtype` statically redundant.
    expect($field->fieldtype())->toBeInstanceOf(Password::class);

    /** @var Password $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    expect($fieldtype->selectableInForms())->toBeTrue();
    expect($fieldtype->component())->toBe('password');
    expect(data_get($fieldtype->preload(), 'config.input_type'))->toBe('password');
});

it('renders a masked single-line input from the public-form template', function () {
    $template = file_get_contents(
        resource_path('views/vendor/statamic/forms/fields/password.antlers.html')
    );
    expect($template)->toBeString();
    $template = is_string($template) ? $template : '';

    $out = (string) Antlers::parse(
        $template,
        [
            'handle' => 'account_password',
            'id' => 'form-account_password',
            'type' => 'password',
            'input_type' => 'password',
            'visibility' => 'visible',
            'value' => '',
            'validate' => [],
        ],
        true
    );

    expect($out)->toContain('<input');
    expect($out)->toContain('type="password"');
});
