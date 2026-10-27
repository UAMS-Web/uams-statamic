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
