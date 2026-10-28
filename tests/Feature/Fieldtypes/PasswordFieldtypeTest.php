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
/**
 * Build a Password fieldtype bound to a blueprint field of `type: password`.
 */
function passwordFieldtypeUnderTest(): Password
{
    // Bind a Field so config-dependent methods resolve
    $fieldtype = new Password;
    $fieldtype->setField(new Field('account_password', ['type' => 'password']));

    return $fieldtype;
}

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

it('uses the security-lock icon', function () {
    expect((new Password)->icon())->toBe('security-lock');
});

it('exposes its search keywords exactly', function (): void {
    expect((new Password)->keywords())->toBe([
        'authentication',
        'password',
        'secure',
        'security',
    ]);
});

it('belongs to its fieldtype categories exactly', function (): void {
    expect((new Password)->categories())->toBe([
        'number',
        'text',
        'special',
    ]);
});

it('allows its autocomplete keys exactly', function (): void {
    expect((new ReflectionMethod(Password::class, 'getAllowedAutocompleteKeys'))->invoke(passwordFieldtypeUnderTest()))->toBe([
        'current-password',
        'new-password',
    ]);
});

it('pins every config field override exactly', function (): void {
    expect((new ReflectionMethod(Password::class, 'getConfigFieldOverrides'))->invoke(passwordFieldtypeUnderTest()))->toBe([
        'input_type' => [
            'default' => 'password',
        ],
        'autocorrect' => [
            'default' => 'off',
        ],
        'maxlength' => [
            'instructions' => 'Set the maximum number of characters for the password. Consider security implications.',
        ],
        'minlength' => [
            'instructions' => 'Set the minimum number of characters required for a secure password.',
        ],
        'pattern' => [
            'instructions' => 'Set a regular expression pattern for password validation.

Example: `^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[@$!%*?&])[A-Za-z\\d@$!%*?&]{8,}$` for strong password requirements.',
        ],
        'spellcheck' => [
            'default' => 'off',
        ],
        'writingsuggestions' => [
            'default' => 'off',
        ],
        'calculation_type' => [
            'default' => 'text',
        ],
    ]);
});

it('unsets no config fields', function (): void {
    expect((new ReflectionMethod(Password::class, 'getUnsetConfigFields'))->invoke(passwordFieldtypeUnderTest()))->toBe([]);
});

it('pins every hidden config field handle exactly', function (): void {
    expect((new ReflectionMethod(Password::class, 'getHiddenConfigFields'))->invoke(passwordFieldtypeUnderTest()))->toBe([
        'accesskey',
        'anchor',
        'antlers',
        'append',
        'autocorrect',
        'calculation_type',
        'class',
        'default',
        'dir',
        'draggable',
        'enterkeyhint',
        'debounce',
        'exportparts',
        'focus',
        'form',
        'id',
        'inert',
        'inputmode',
        'is',
        'itemid',
        'itemprop',
        'itemref',
        'itemscope',
        'itemtype',
        'lang',
        'name',
        'nonce',
        'part',
        'popover',
        'prepend',
        'role',
        'size',
        'slot',
        'spellcheck',
        'style',
        'tabindex',
        'title',
        'translate',
        'virtualkeyboardpolicy',
        'writingsuggestions',
    ]);
});
