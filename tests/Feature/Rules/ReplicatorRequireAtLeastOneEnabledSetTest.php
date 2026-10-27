<?php

declare(strict_types=1);

use App\Rules\ReplicatorRequireAtLeastOneEnabledSet;
use Tests\TestCase;

uses(TestCase::class);

covers(ReplicatorRequireAtLeastOneEnabledSet::class);

/**
 * Behavior of App\Rules\ReplicatorRequireAtLeastOneEnabledSet: when the field is
 * required for the request (via FieldRequiredHelper — required* rules, "sometimes"
 * + Statamic "if", required_if), the value must be an array with at least one set
 * whose `enabled` is not false (a missing `enabled` key counts as enabled). Non-
 * array values are skipped.
 *
 * @command  php artisan test --compact tests/Feature/Rules/ReplicatorRequireAtLeastOneEnabledSetTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * Run the rule and report whether it failed.
 *
 * @param  array<string, mixed>  $fieldConfig
 * @param  array<string, mixed>  $data
 */
function replicatorRequireEnabledSetFailed(array $fieldConfig, array $data, string $attribute, mixed $value): bool
{
    $rule = (new ReplicatorRequireAtLeastOneEnabledSet($fieldConfig))->setData($data);

    $failed = false;
    $rule->validate($attribute, $value, function () use (&$failed) {
        $failed = true;
    });

    return $failed;
}

it('passes when at least one set is enabled', function () {
    $failed = replicatorRequireEnabledSetFailed(
        ['validate' => ['required']],
        ['page_builder' => []],
        'page_builder',
        [
            ['type' => 'hero', 'enabled' => true],
            ['type' => 'text', 'enabled' => false],
        ],
    );

    expect($failed)->toBeFalse();
});

it('passes when a set has no enabled key (defaults to enabled)', function () {
    $failed = replicatorRequireEnabledSetFailed(
        ['validate' => ['required']],
        ['page_builder' => []],
        'page_builder',
        [['type' => 'hero']],
    );

    expect($failed)->toBeFalse();
});

it('fails when required and zero sets are enabled', function () {
    $failed = replicatorRequireEnabledSetFailed(
        ['validate' => ['required']],
        ['page_builder' => []],
        'page_builder',
        [
            ['type' => 'hero', 'enabled' => false],
            ['type' => 'text', 'enabled' => false],
        ],
    );

    expect($failed)->toBeTrue();
});

it('fails when required and the array is empty', function () {
    $failed = replicatorRequireEnabledSetFailed(
        ['validate' => ['required']],
        ['page_builder' => []],
        'page_builder',
        [],
    );

    expect($failed)->toBeTrue();
});

it('skips when the value is not an array', function () {
    $failed = replicatorRequireEnabledSetFailed(
        ['validate' => ['required']],
        ['page_builder' => null],
        'page_builder',
        'not an array',
    );

    expect($failed)->toBeFalse();
});

it('passes when sometimes is present and the if condition is not met', function () {
    $failed = replicatorRequireEnabledSetFailed(
        ['validate' => ['sometimes', 'required'], 'if' => ['title_options' => 'hero']],
        ['page_builder' => [['type' => 'hero', 'enabled' => false]], 'title_options' => 'basic'],
        'page_builder',
        [['type' => 'hero', 'enabled' => false]],
    );

    expect($failed)->toBeFalse();
});

it('fails when sometimes is present, the if condition is met, and zero sets are enabled', function () {
    $failed = replicatorRequireEnabledSetFailed(
        ['validate' => ['sometimes', 'required'], 'if' => ['title_options' => 'hero']],
        ['page_builder' => [['type' => 'hero', 'enabled' => false]], 'title_options' => 'hero'],
        'page_builder',
        [['type' => 'hero', 'enabled' => false]],
    );

    expect($failed)->toBeTrue();
});

it('fails when the if uses Statamic "equals {value}" format and the condition is met', function () {
    $failed = replicatorRequireEnabledSetFailed(
        ['validate' => ['sometimes', 'required'], 'if' => ['title_options' => 'equals hero']],
        ['header_hero' => [['type' => 'new_set', 'enabled' => false]], 'title_options' => 'hero'],
        'header_hero',
        [['type' => 'new_set', 'enabled' => false]],
    );

    expect($failed)->toBeTrue();
});

it('passes when a required_if condition is not met', function () {
    $failed = replicatorRequireEnabledSetFailed(
        ['validate' => ['required_if:title_options,hero']],
        ['page_builder' => [['type' => 'hero', 'enabled' => false]], 'title_options' => 'basic'],
        'page_builder',
        [['type' => 'hero', 'enabled' => false]],
    );

    expect($failed)->toBeFalse();
});

it('fails when a required_if condition is met and zero sets are enabled', function () {
    $failed = replicatorRequireEnabledSetFailed(
        ['validate' => ['required_if:title_options,hero']],
        ['page_builder' => [['type' => 'hero', 'enabled' => false]], 'title_options' => 'hero'],
        'page_builder',
        [['type' => 'hero', 'enabled' => false]],
    );

    expect($failed)->toBeTrue();
});
