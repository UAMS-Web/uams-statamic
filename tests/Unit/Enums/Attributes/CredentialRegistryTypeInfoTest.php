<?php

declare(strict_types=1);

use App\Enums\Attributes\CredentialRegistryTypeInfo;

/**
 * Behavior of App\Enums\Attributes\CredentialRegistryTypeInfo: the constructor
 * defaults that let a registry term omit any field it has no value for.
 *
 * Mutation: every mutant of the class is killed by this file, measured without
 * `--covered-only` (#859). The generated `App\Enums\CredentialRegistry` cases pass
 * every argument, so the defaults are reached only by a term built with fields
 * omitted, which is what these tests do.
 *
 * @command php artisan test --compact tests/Unit/Enums/Attributes/CredentialRegistryTypeInfoTest.php
 */
it('defaults every text field to an empty string', function (string $property) {
    expect((new CredentialRegistryTypeInfo)->{$property})->toBe('');
})->with([
    'slug',
    'uri',
    'shorthand_uri',
    'label',
    'definition',
    'comment',
    'usage_note',
    'term_type',
    'status',
    'same_as',
    'subclass_of',
]);

it('defaults every list field to an empty list', function (string $property) {
    expect((new CredentialRegistryTypeInfo)->{$property})->toBe([]);
})->with([
    'children',
    'ancestors',
    'descendants',
    'property_names',
    'in_range_of',
]);

it('keeps the values a term passes and defaults the rest', function () {
    $info = new CredentialRegistryTypeInfo(
        slug: 'Competency',
        label: 'Competency',
        property_names: ['ceasn:alignTo'],
    );

    expect($info->slug)->toBe('Competency')
        ->and($info->label)->toBe('Competency')
        ->and($info->property_names)->toBe(['ceasn:alignTo'])
        ->and($info->uri)->toBe('')
        ->and($info->children)->toBe([]);
});
