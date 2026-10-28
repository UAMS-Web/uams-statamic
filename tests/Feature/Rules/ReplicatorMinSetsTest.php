<?php

declare(strict_types=1);

use App\Rules\ReplicatorMinSets;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Behavior of the App\Rules\ReplicatorMinSets validation rule: enforce min_sets
 * only when the field is required for the request (via FieldRequiredHelper), the
 * value is an array, and min_sets is a positive integer. Below the threshold it
 * fails with the singular or plural message depending on min_sets.
 *
 * @command  php artisan test --compact tests/Feature/Rules/ReplicatorMinSetsTest.php
 */

/**
 * Run the rule and return the fail messages it emitted (empty when it passes).
 *
 * @param  array<string, mixed>  $fieldConfig
 * @param  array<string, mixed>  $data
 * @return list<string>
 */
function replicatorMinSetsMessages(array $fieldConfig, array $data, string $attribute, mixed $value): array
{
    $rule = (new ReplicatorMinSets($fieldConfig))->setData($data);

    $messages = [];
    $rule->validate($attribute, $value, function (string $message) use (&$messages) {
        $messages[] = $message;
    });

    return $messages;
}

it('fails when required and fewer than min_sets', function () {
    $messages = replicatorMinSetsMessages(
        ['validate' => ['required'], 'min_sets' => 2],
        ['blocks' => []],
        'blocks',
        [['type' => 'text', '_id' => '1', 'enabled' => true]],
    );

    expect($messages)->toHaveCount(1);
});

it('passes when required and at least min_sets', function () {
    $messages = replicatorMinSetsMessages(
        ['validate' => ['required'], 'min_sets' => 2],
        ['blocks' => []],
        'blocks',
        [
            ['type' => 'text', '_id' => '1', 'enabled' => true],
            ['type' => 'text', '_id' => '2', 'enabled' => true],
        ],
    );

    expect($messages)->toBeEmpty();
});

it('passes when not required even with fewer than min_sets', function () {
    $messages = replicatorMinSetsMessages(
        ['min_sets' => 2],
        ['blocks' => []],
        'blocks',
        [['type' => 'text', '_id' => '1', 'enabled' => true]],
    );

    expect($messages)->toBeEmpty();
});

it('skips a non-array value', function () {
    $messages = replicatorMinSetsMessages(
        ['validate' => ['required'], 'min_sets' => 2],
        ['blocks' => []],
        'blocks',
        'not-an-array',
    );

    expect($messages)->toBeEmpty();
});

it('skips when min_sets is zero or negative', function (int $minSets) {
    $messages = replicatorMinSetsMessages(
        ['validate' => ['required'], 'min_sets' => $minSets],
        ['blocks' => []],
        'blocks',
        [],
    );

    expect($messages)->toBeEmpty();
})->with(['zero' => [0], 'negative' => [-1]]);

it('uses the singular message when min_sets is one and the plural message otherwise', function () {
    $one = replicatorMinSetsMessages(
        ['validate' => ['required'], 'min_sets' => 1],
        ['blocks' => []],
        'blocks',
        [],
    );

    $multiple = replicatorMinSetsMessages(
        ['validate' => ['required'], 'min_sets' => 3],
        ['blocks' => []],
        'blocks',
        [],
    );

    expect($one[0] ?? null)->toBe(__('statamic::validation.replicator.min_sets.one'));
    expect($multiple[0] ?? null)->toBe(__('statamic::validation.replicator.min_sets.multiple', ['min' => 3]));
});

it('reads a string min_sets as its integer', function () {
    $messages = static fn (string $minSets): array => replicatorMinSetsMessages(
        ['validate' => ['required'], 'min_sets' => $minSets],
        ['blocks' => []],
        'blocks',
        [],
    );

    expect($messages('1'))->toBe([__('statamic::validation.replicator.min_sets.one')])
        ->and($messages('2'))->toBe([__('statamic::validation.replicator.min_sets.multiple', ['min' => 2])]);
});

it('skips when min_sets is not configured', function () {
    expect(replicatorMinSetsMessages(['validate' => ['required']], ['blocks' => []], 'blocks', []))->toBe([]);
});
