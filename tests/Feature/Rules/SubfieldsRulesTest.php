<?php

declare(strict_types=1);

use App\Rules\BaseRules\BaseSubfieldsRule;
use App\Rules\SubfieldsAll;
use App\Rules\SubfieldsAny;
use App\Rules\SubfieldsList;
use Tests\TestCase;

uses(TestCase::class);

covers(BaseSubfieldsRule::class, SubfieldsAll::class, SubfieldsAny::class, SubfieldsList::class);

/**
 * Behavior of the subfield-group rules: `SubfieldsAll` needs every subfield
 * filled and names the empty ones, `SubfieldsAny` needs one, and `SubfieldsList`
 * needs one of the subfields it is given. A subfield holding `'0'`, `0` or `false`
 * counts as filled; `null`, `''` and `[]` do not. A value that is neither an array
 * nor an object has no subfields and is left alone.
 *
 * @command  php artisan test --compact tests/Feature/Rules/SubfieldsRulesTest.php
 */

/**
 * Run a subfields rule and return every message it failed with.
 *
 * @return list<mixed>
 */
function subfieldsRuleMessages(BaseSubfieldsRule $rule, mixed $value): array
{
    $messages = [];

    $rule->validate('address', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

/**
 * Subfield values the base rule counts as filled.
 *
 * @return array<string, array{mixed}>
 */
function subfieldsFilledValues(): array
{
    return [
        'text' => ['Little Rock'],
        'string zero' => ['0'],
        'integer zero' => [0],
        'false' => [false],
        'non-empty list' => [['a']],
    ];
}

/**
 * Subfield values the base rule counts as empty.
 *
 * @return array<string, array{mixed}>
 */
function subfieldsEmptyValues(): array
{
    return [
        'null' => [null],
        'empty string' => [''],
        'empty list' => [[]],
        'float zero' => [0.0],
    ];
}

it('passes SubfieldsAll when every subfield is filled', function (mixed $filled): void {
    expect(subfieldsRuleMessages(new SubfieldsAll, ['city' => 'Little Rock', 'zip' => $filled]))->toBe([]);
})->with(subfieldsFilledValues());

it('fails SubfieldsAll naming every empty subfield in order', function (mixed $empty): void {
    expect(subfieldsRuleMessages(new SubfieldsAll, ['city' => $empty, 'state' => 'AR', 'zip' => '']))
        ->toBe(['All subfields of :attribute must have values. Missing values for: city, zip']);
})->with(subfieldsEmptyValues());

it('reads an object value as its subfields', function (): void {
    expect(subfieldsRuleMessages(new SubfieldsAll, (object) ['city' => 'Little Rock', 'zip' => null]))
        ->toBe(['All subfields of :attribute must have values. Missing values for: zip'])
        ->and(subfieldsRuleMessages(new SubfieldsAny, (object) ['city' => 'Little Rock']))->toBe([])
        ->and(subfieldsRuleMessages(new SubfieldsList(['zip']), (object) ['zip' => '72205']))->toBe([]);
});

it('passes SubfieldsAny when one subfield is filled', function (mixed $filled): void {
    expect(subfieldsRuleMessages(new SubfieldsAny, ['city' => null, 'state' => '', 'zip' => $filled]))->toBe([]);
})->with(subfieldsFilledValues());

it('fails SubfieldsAny when every subfield is empty', function (mixed $empty): void {
    expect(subfieldsRuleMessages(new SubfieldsAny, ['city' => $empty, 'zip' => null]))
        ->toBe([__('statamic::validation.subfields.any.one_subfield', ['attribute' => 'address'])]);
})->with(subfieldsEmptyValues());

it('fails SubfieldsAny on a group with no subfields', function (): void {
    expect(subfieldsRuleMessages(new SubfieldsAny, []))
        ->toBe([__('statamic::validation.subfields.any.one_subfield', ['attribute' => 'address'])]);
});

it('runs SubfieldsAny on an empty field, and leaves SubfieldsAll and SubfieldsList to required', function (): void {
    expect((new SubfieldsAny)->implicit)->toBeTrue()
        ->and(new SubfieldsAll)->not->toHaveProperty('implicit')
        ->and(new SubfieldsList(['zip']))->not->toHaveProperty('implicit');
});

it('passes SubfieldsList when one listed subfield is filled', function (mixed $filled): void {
    expect(subfieldsRuleMessages(new SubfieldsList(['phone', 'email']), ['phone' => null, 'email' => $filled, 'fax' => '']))->toBe([]);
})->with(subfieldsFilledValues());

it('fails SubfieldsList when only unlisted subfields are filled, listing the subfields it needs', function (mixed $empty): void {
    expect(subfieldsRuleMessages(new SubfieldsList(['phone', 'email']), ['phone' => $empty, 'fax' => '501-555-0100']))
        ->toBe([__('statamic::validation.subfields.list.one_of_subfields', ['attribute' => 'address', 'subfields' => 'phone, email'])]);
})->with(subfieldsEmptyValues());

it('leaves a value with no subfields alone', function (BaseSubfieldsRule $rule, mixed $value): void {
    expect(subfieldsRuleMessages($rule, $value))->toBe([]);
})->with([
    'all, string' => [new SubfieldsAll, 'Little Rock'],
    'all, null' => [new SubfieldsAll, null],
    'any, integer' => [new SubfieldsAny, 72205],
    'any, null' => [new SubfieldsAny, null],
    'list, string' => [new SubfieldsList(['zip']), 'Little Rock'],
]);

it('keeps the data it is given and returns itself', function (): void {
    $rule = new SubfieldsAny;

    expect($rule->setData(['address' => ['zip' => '72205']]))->toBe($rule);
    expect((fn (): array => $this->data)->call($rule))->toBe(['address' => ['zip' => '72205']]);
});
