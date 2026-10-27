<?php

declare(strict_types=1);

use App\Fieldtypes\Email;
use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Tests for App\Fieldtypes\Email: its Control Panel identity, the config lists it hands the Input
 * trait, and its validation rules.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/EmailFieldtypeTest.php
 */
uses(TestCase::class);

covers(Email::class);

/**
 * Build a Email fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function emailFieldtype(array $config = []): Email
{
    // Build the fieldtype under test
    $fieldtype = new Email;

    // Bind a Field with the given config over the type handle
    $fieldtype->setField(new Field('email', array_merge(['type' => 'email'], $config)));

    return $fieldtype;
}

/**
 * Call a protected Email method via reflection.
 *
 * @param  string  $method  Protected method name to invoke
 * @return mixed Reflected method return value
 */
function emailCall(string $method): mixed
{
    // Reflect the protected method and invoke it on a fresh fieldtype
    return (new ReflectionMethod(Email::class, $method))->invoke(emailFieldtype());
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = emailFieldtype();

    expect(Email::title())->toBe('Email')
        ->and($fieldtype->component())->toBe('email')
        ->and($fieldtype->icon())->toBe('mail-sign-at')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeTrue();
});

it('exposes its search keywords exactly', function (): void {
    expect(emailFieldtype()->keywords())->toBe([
        'address',
        'communication',
        'contact',
        'email',
        'mail',
    ]);
});

it('belongs to its fieldtype categories exactly', function (): void {
    expect(emailFieldtype()->categories())->toBe([
        'number',
        'text',
        'special',
    ]);
});

it('allows its autocomplete keys exactly', function (): void {
    expect(emailCall('getAllowedAutocompleteKeys'))->toBe([
        'email',
    ]);
});

it('pins every config field override exactly', function (): void {
    expect(emailCall('getConfigFieldOverrides'))->toBe([
        'input_type' => [
            'default' => 'email',
        ],
        'autocomplete_query' => [
            'default' => 'on',
        ],
        'autocomplete' => [
            'default' => 'email',
        ],
        'autocapitalize' => [
            'default' => 'off',
        ],
        'autocorrect' => [
            'default' => 'off',
        ],
        'inputmode' => [
            'default' => 'email',
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
    expect(emailCall('getUnsetConfigFields'))->toBe([]);
});

it('pins every hidden config field handle exactly', function (): void {
    expect(emailCall('getHiddenConfigFields'))->toBe([
        'accesskey',
        'anchor',
        'antlers',
        'append',
        'autocapitalize',
        'autocomplete_query',
        'autocomplete',
        'autocorrect',
        'calculation_type',
        'class',
        'contenteditable',
        'default',
        'debounce',
        'dir',
        'dirname',
        'draggable',
        'enterkeyhint',
        'exportparts',
        'focus',
        'form',
        'id',
        'inert',
        'inputmode',
        'inputmode',
        'is',
        'itemid',
        'itemprop',
        'itemref',
        'itemscope',
        'itemtype',
        'lang',
        'list',
        'maxlength',
        'minlength',
        'name',
        'nonce',
        'part',
        'pattern',
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

it('validates its content with its rule', function (): void {
    $rules = emailFieldtype()->rules();

    expect($rules)->toBe(['email'])
        ->and(Validator::make(['value' => 'web@uams.edu'], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 'not-an-email'], ['value' => $rules])->passes())->toBeFalse();
});
