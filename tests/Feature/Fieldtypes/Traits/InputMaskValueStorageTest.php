<?php

declare(strict_types=1);

use App\Fieldtypes\Traits\InputMaskValueStorage;
use Tests\Support\Doubles\InputMaskValueStorageHarness;
use Tests\TestCase;

covers(InputMaskValueStorage::class);

uses(TestCase::class);

/**
 * Behavior of the InputMaskValueStorage trait (app/Fieldtypes/Traits/InputMaskValueStorage.php):
 * whether a mask is on, which half of a {masked, unmasked} pair is stored, and the
 * numeric cast applied to a numeric string under a number or range mask.
 *
 * The mask-class check is a loose in_array(), so a boolean true class counts as
 * numeric; the dataset pins that as current behavior.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/InputMaskValueStorageTest.php
 */
function inputMaskStorageHarness(): InputMaskValueStorageHarness
{
    return new InputMaskValueStorageHarness;
}

it('treats the mask as enabled only when input_mask is boolean true', function (array $config, bool $expected): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(inputMaskStorageHarness()->enabled($typedConfig))->toBe($expected);
})->with([
    'true' => [['input_mask' => true], true],
    'false' => [['input_mask' => false], false],
    'string true' => [['input_mask' => 'true'], false],
    'integer one' => [['input_mask' => 1], false],
    'absent' => [[], false],
]);

it('stores the masked half of a structured value by default', function (): void {
    $value = ['masked' => '1,234.50', 'unmasked' => '1234.5'];

    expect(inputMaskStorageHarness()->store($value, []))->toBe('1,234.50')
        ->and(inputMaskStorageHarness()->store($value, ['input_mask_store_unmasked' => false]))->toBe('1,234.50')
        ->and(inputMaskStorageHarness()->store($value, ['input_mask_store_unmasked' => 'true']))->toBe('1,234.50');
});

it('stores the unmasked half when configured to', function (): void {
    $value = ['masked' => '(501) 555-0100', 'unmasked' => '5015550100'];

    expect(inputMaskStorageHarness()->store($value, ['input_mask_store_unmasked' => true]))->toBe('5015550100');
});

it('stores a value that is not a complete pair as it is', function (mixed $value): void {
    expect(inputMaskStorageHarness()->store($value, ['input_mask_store_unmasked' => true]))->toBe($value);
})->with([
    'plain string' => ['abc'],
    'masked only' => [['masked' => 'a']],
    'unmasked only' => [['unmasked' => 'a']],
    'null masked half' => [['masked' => null, 'unmasked' => 'a']],
    'null' => [null],
]);

it('casts a numeric string to a float under a number or range mask', function (mixed $class, mixed $value, mixed $expected): void {
    expect(inputMaskStorageHarness()->store($value, ['input_mask_class' => $class]))->toBe($expected);
})->with([
    'number mask, numeric string' => ['MaskedNumber', '1234.5', 1234.5],
    'range mask, numeric string' => ['MaskedRange', '7', 7.0],
    'number mask, non-numeric string' => ['MaskedNumber', '1,234.50', '1,234.50'],
    'number mask, integer' => ['MaskedNumber', 7, 7],
    'pattern mask, numeric string' => ['MaskedPattern', '1234', '1234'],
    'no mask class, numeric string' => [null, '1234', '1234'],
    'boolean true class, numeric string' => [true, '12', 12.0],
]);

it('casts the stored half of a pair under a number mask', function (): void {
    $value = ['masked' => '1,234.50', 'unmasked' => '1234.5'];

    expect(inputMaskStorageHarness()->store($value, ['input_mask_class' => 'MaskedNumber', 'input_mask_store_unmasked' => true]))
        ->toBe(1234.5);
});
