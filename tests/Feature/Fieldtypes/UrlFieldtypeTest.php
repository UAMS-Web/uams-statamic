<?php

declare(strict_types=1);

use App\Fieldtypes\Url;
use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Tests for App\Fieldtypes\Url: its Control Panel identity, the config lists it hands the Input
 * trait, and its validation rules.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/UrlFieldtypeTest.php
 */
uses(TestCase::class);

covers(Url::class);

/**
 * Build a Url fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function urlFieldtype(array $config = []): Url
{
    // Build the fieldtype under test
    $fieldtype = new Url;

    // Bind a Field with the given config over the type handle
    $fieldtype->setField(new Field('url', array_merge(['type' => 'url'], $config)));

    return $fieldtype;
}

/**
 * Call a protected Url method via reflection.
 *
 * @param  string  $method  Protected method name to invoke
 * @return mixed Reflected method return value
 */
function urlCall(string $method): mixed
{
    // Reflect the protected method and invoke it on a fresh fieldtype
    return (new ReflectionMethod(Url::class, $method))->invoke(urlFieldtype());
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = urlFieldtype();

    expect(Url::title())->toBe('URL')
        ->and($fieldtype->component())->toBe('url')
        ->and($fieldtype->icon())->toBe('web')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeTrue();
});

it('exposes its search keywords exactly', function (): void {
    expect(urlFieldtype()->keywords())->toBe([
        'address',
        'communication',
        'contact',
        'link',
        'url',
        'web address',
        'web',
        'website',
    ]);
});

it('belongs to its fieldtype categories exactly', function (): void {
    expect(urlFieldtype()->categories())->toBe([
        'special',
    ]);
});

it('allows its autocomplete keys exactly', function (): void {
    expect(urlCall('getAllowedAutocompleteKeys'))->toBe([
        'url',
    ]);
});

it('pins every config field override exactly', function (): void {
    expect(urlCall('getConfigFieldOverrides'))->toBe([
        'input_type' => [
            'default' => 'url',
        ],
        'autocapitalize' => [
            'default' => 'off',
        ],
        'autocorrect' => [
            'default' => 'off',
        ],
        'inputmode' => [
            'default' => 'url',
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

it('pins every hidden config field handle exactly', function (): void {
    expect(urlCall('getHiddenConfigFields'))->toBe([
        'accesskey',
        'anchor',
        'antlers',
        'append',
        'autocapitalize',
        'autocomplete_query',
        'autocorrect',
        'calculation_type',
        'class',
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
    $rules = urlFieldtype()->rules();

    expect($rules)->toBe(['url'])
        ->and(Validator::make(['value' => 'https://uams.edu'], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 'not a url'], ['value' => $rules])->passes())->toBeFalse();
});
