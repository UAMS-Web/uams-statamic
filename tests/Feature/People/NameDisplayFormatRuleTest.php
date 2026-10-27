<?php

declare(strict_types=1);

use App\Rules\PersonNameDisplayFormatRule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * The `name_display_format` field's rule (UAMS-Web/uams-statamic-archive#3186): the format kept beside
 * `person_name` must be one the name allows once it is joined with the
 * nickname and pronunciation fields beside it.
 *
 * @command  php artisan test --compact tests/Feature/People/NameDisplayFormatRuleTest.php
 */
uses(TestCase::class);

/**
 * The legal name as `person_name` holds it after UAMS-Web/uams-statamic-archive#3186.
 *
 * @return array<string, mixed>
 */
function displayFormatRuleLegalName(): array
{
    return [
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => null],
        'no_middle_name' => true,
        'middle_name' => ['text' => null, 'phonetic_text' => null],
        'no_nickname' => true,
        'nickname' => ['text' => null, 'phonetic_text' => null],
        'last_name' => ['text' => 'McCoy', 'phonetic_text' => null],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => null,
    ];
}

/**
 * Whether the form data passes the `name_display_format` rule.
 *
 * @param  array<string, mixed>  $data
 */
function displayFormatRulePasses(array $data, bool $required = true): bool
{
    return Validator::make($data, [
        'name_display_format' => [new PersonNameDisplayFormatRule('person_name', $required, true)],
    ])->passes();
}

it('accepts a nickname format when the nickname field holds a nickname', function (): void {
    expect(displayFormatRulePasses([
        'person_name' => displayFormatRuleLegalName(),
        'nickname' => ['text' => 'Bones', 'phonetic_text' => null],
        'name_display_format' => 'nickname_only@ns',
    ]))->toBeTrue();
});

it('refuses a nickname format when the nickname field is empty', function (): void {
    expect(displayFormatRulePasses([
        'person_name' => displayFormatRuleLegalName(),
        'nickname' => ['text' => '', 'phonetic_text' => null],
        'name_display_format' => 'nickname_only@ns',
    ]))->toBeFalse();
});

it('accepts a format the legal name alone allows', function (): void {
    expect(displayFormatRulePasses([
        'person_name' => displayFormatRuleLegalName(),
        'name_display_format' => 'full_first_only@ns',
    ]))->toBeTrue();
});

it('requires a format once the name allows one, unless the field is optional', function (): void {
    $data = ['person_name' => displayFormatRuleLegalName(), 'name_display_format' => null];

    expect(displayFormatRulePasses($data))->toBeFalse()
        ->and(displayFormatRulePasses($data, required: false))->toBeTrue();
});

it('refuses a key no name allows', function (): void {
    expect(displayFormatRulePasses([
        'person_name' => displayFormatRuleLegalName(),
        'name_display_format' => 'not_a_format@ns',
    ]))->toBeFalse();
});
