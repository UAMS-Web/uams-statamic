<?php

declare(strict_types=1);

use App\Rules\TermsSubsetOf;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Behavior of the App\Rules\TermsSubsetOf validation rule: the attribute's taxonomy
 * term selections must be a subset of another root field's terms (by handle),
 * normalizing bare slugs against the taxonomy inferred from `taxonomy::slug` values.
 * Empty child passes; an empty parent (with a non-empty child) and a non-subset
 * child both fail. Construction requires a non-empty parent handle and validation
 * requires the validator to be set.
 *
 * @command  php artisan test --compact tests/Feature/Rules/TermsSubsetOfRuleTest.php
 */
it('rejects an empty parent field handle in the constructor', function () {
    expect(fn () => new TermsSubsetOf('  '))->toThrow(InvalidArgumentException::class);
});

it('throws when validated without a validator set', function () {
    $rule = new TermsSubsetOf('work_location');

    expect(fn () => $rule->validate(
        'job_title.0.role_work_location',
        ['geographic_locations::a'],
        static function (): void {},
    ))->toThrow(LogicException::class);
});

it('passes when the child field is empty', function () {
    $validator = Validator::make(
        ['work_location' => [], 'role_work_location' => []],
        ['role_work_location' => [new TermsSubsetOf('work_location')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('fails when the parent is empty but the child has terms', function () {
    $validator = Validator::make(
        ['work_location' => [], 'role_work_location' => ['geographic_locations::little-rock']],
        ['role_work_location' => [new TermsSubsetOf('work_location')]],
    );

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->messages())->toHaveKey('role_work_location');
    expect(strtolower($validator->errors()->first('role_work_location')))->toContain('empty');
});

it('fails when the child is not a subset of the parent', function () {
    $validator = Validator::make(
        [
            'work_location' => ['geographic_locations::little-rock'],
            'role_work_location' => [
                'geographic_locations::little-rock',
                'geographic_locations::north-little-rock',
            ],
        ],
        ['role_work_location' => [new TermsSubsetOf('work_location')]],
    );

    expect($validator->passes())->toBeFalse();
    expect(strtolower($validator->errors()->first('role_work_location')))->toContain('among');
});

it('passes when a slug-only parent matches a prefixed child via the taxonomy hint', function () {
    $validator = Validator::make(
        ['work_location' => ['little-rock'], 'role_work_location' => ['geographic_locations::little-rock']],
        ['role_work_location' => [new TermsSubsetOf('work_location')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('passes when child and parent are slug-only subset values', function () {
    $validator = Validator::make(
        ['work_location' => ['little-rock', 'north-little-rock'], 'role_work_location' => ['little-rock']],
        ['role_work_location' => [new TermsSubsetOf('work_location')]],
    );

    expect($validator->passes())->toBeTrue();
});

/**
 * Run the rule directly against a validator over the given data, returning every
 * failure message unaltered by the validator's own placeholder replacement.
 *
 * @param  array<string, mixed>  $data  Root data the parent field is read from
 * @param  mixed  $value  Child field value
 * @return list<string> Failure messages, in the order the rule raised them
 */
function termsSubsetOfMessages(array $data, mixed $value): array
{
    $messages = [];

    (new TermsSubsetOf('work_location'))
        ->setValidator(Validator::make($data, [], [], [
            'work_location' => 'Work Location',
            'role_work_location' => 'Role Work Location',
        ]))
        ->validate('role_work_location', $value, function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

    return $messages;
}

it('raises one empty-parent message naming the parent field', function () {
    expect(termsSubsetOfMessages(['work_location' => []], ['a', 'b']))->toBe([__(
        'statamic::validation.terms_subset_of.parent_empty',
        ['parent' => 'Work Location'],
    )]);
});

it('raises one not-subset message naming both fields', function () {
    expect(termsSubsetOfMessages(['work_location' => ['a']], ['b', 'c']))->toBe([__(
        'statamic::validation.terms_subset_of.not_subset',
        ['attribute' => 'Role Work Location', 'parent' => 'Work Location'],
    )]);
});

it('compares term ids strictly, so numeric-looking slugs do not match loosely', function () {
    expect(termsSubsetOfMessages(['work_location' => ['10']], ['1e1']))->toHaveCount(1)
        ->and(termsSubsetOfMessages(['work_location' => ['10']], ['10']))->toBe([]);
});

it('ignores blank and whitespace-padded child entries', function (array $parent, array $child) {
    expect(termsSubsetOfMessages(['work_location' => $parent], $child))->toBe([]);
})->with([
    'blank entry without a hint' => [['a'], ['a', '  ']],
    'blank entry with a hint' => [['tax::a'], ['tax::a', '  ']],
    'padded entry' => [['a'], [' a ']],
    'empty string and null entries' => [['a'], ['', null, 'a']],
    'false entry' => [['a'], [false, 'a']],
]);

it('reads scalar child entries as strings and skips nested values', function () {
    expect(termsSubsetOfMessages(['work_location' => ['5', '1']], [5, true, ['x']]))->toBe([])
        ->and(termsSubsetOfMessages(['work_location' => ['5']], [6]))->toHaveCount(1);
});

it('takes the taxonomy hint from the part before the first separator', function () {
    expect(termsSubsetOfMessages(['work_location' => ['tax::a::b']], ['a::b']))->toHaveCount(1)
        ->and(termsSubsetOfMessages(['work_location' => ['tax::a::b', 'c']], ['tax::c']))->toBe([]);
});

it('treats a blank or non-string single value as no selection', function (mixed $value) {
    expect(termsSubsetOfMessages(['work_location' => []], $value))->toBe([]);
})->with([
    'null' => [null],
    'empty string' => [''],
    'whitespace' => ['   '],
    'integer' => [5],
]);

it('accepts a single string value that the parent contains', function () {
    expect(termsSubsetOfMessages(['work_location' => ['tax::a']], 'tax::a'))->toBe([])
        ->and(termsSubsetOfMessages(['work_location' => ['tax::a']], 'tax::b'))->toHaveCount(1);
});
