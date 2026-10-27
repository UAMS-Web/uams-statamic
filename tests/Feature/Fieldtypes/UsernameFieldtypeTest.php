<?php

declare(strict_types=1);

use App\Fieldtypes\Username;
use Statamic\Facades\Antlers;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for the `Username` fieldtype — the Statamic analog of Gravity Forms'
 * User Registration add-on `username` field. Covers the fieldtype contract
 * (selectable in forms, reuses the `custom_text` Control Panel component) and
 * the username semantics surfaced to the public form (`autocomplete="username"`,
 * spellcheck off) — the AC of issue UAMS-Web/uams-statamic-archive#312.
 *
 * Mutation: 100% on App\Fieldtypes\Username (75 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/UsernameFieldtypeTest.php
 */

/**
 * Resolve the `Username` fieldtype from a blueprint field of `type: username`,
 * confirming the class is auto-discovered under the application handle.
 */
function usernameFieldtypeUnderTest(): Username
{
    // Build a blueprint field that selects the username fieldtype by handle.
    $field = new Field(
        'account_username',
        [
            'type' => 'username',
        ]
    );

    // Confirm the handle resolves to our application fieldtype. Asserted on the call
    // rather than the annotated variable — the `@var` below is what would make an
    // instanceof over `$fieldtype` statically redundant.
    expect($field->fieldtype())->toBeInstanceOf(Username::class);

    /** @var Username $fieldtype */
    $fieldtype = $field->fieldtype();

    $fieldtype->setField($field);

    return $fieldtype;
}

it('exposes the fieldtype contract', function () {
    $fieldtype = usernameFieldtypeUnderTest();

    expect($fieldtype->selectableInForms())->toBeTrue();
    expect(Username::title())->toBe('Username');
    expect(Username::handle())->toBe('username');
    expect($fieldtype->component())->toBe('custom_text');
    expect($fieldtype->icon())->toBe('user-avatar');
});

it('carries username semantics in the preload config', function () {
    $config = usernameFieldtypeUnderTest()->preload()['config'];

    expect(data_get($config, 'input_type'))->toBe('text');
    expect(data_get($config, 'autocomplete'))->toBe('username');
    expect(data_get($config, 'spellcheck'))->toBe('off');
});

it('disables autocorrect and autocapitalize via config overrides', function () {
    $method = new ReflectionMethod(Username::class, 'getConfigFieldOverrides');
    $method->setAccessible(true);

    $overrides = $method->invoke(new Username);

    expect(data_get($overrides, 'autocorrect.default'))->toBe('off');
    expect(data_get($overrides, 'autocapitalize.default'))->toBe('off');
    expect(data_get($overrides, 'autocomplete.default'))->toBe('username');
});

it('restricts the autocomplete keys to username', function () {
    $method = new ReflectionMethod(Username::class, 'getAllowedAutocompleteKeys');
    $method->setAccessible(true);

    expect($method->invoke(new Username))->toBe(['username']);
});

it('renders a username input from the public form template', function () {
    $template = file_get_contents(
        resource_path('views/vendor/statamic/forms/fields/username.antlers.html')
    );

    expect($template)->toBeString();

    $out = (string) Antlers::parse(
        is_string($template) ? $template : '',
        [
            'handle' => 'account_username',
            'id' => 'form-account_username',
            'type' => 'username',
            'input_type' => 'text',
            'autocomplete' => 'username',
            'spellcheck' => 'off',
            'visibility' => 'visible',
            'value' => '',
            'validate' => [],
        ],
        true
    );

    expect($out)->toContain('<input');
    expect($out)->toContain('type="text"');
    expect($out)->toContain('autocomplete="username"');
});
