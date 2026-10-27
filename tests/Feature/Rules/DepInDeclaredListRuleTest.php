<?php

declare(strict_types=1);

use App\Rules\DepInDeclaredList;
use Illuminate\Support\Facades\Validator;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Fields;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Behavior of the App\Rules\DepInDeclaredList validation rule: every Epic
 * Department (DEP) ID on a MyChart scheduling widget's `dep` replicator must be
 * drawn from the location's top-level `epic_dep_ids` replicator (read as a
 * sibling via the validator data). An empty widget passes; an empty declared
 * list with a populated widget fails ("declare them first"); a widget DEP not in
 * the declared list fails and names the offending ID. The rule resolves the
 * top-level declared list even when attached to a nested replicator subfield,
 * and the registered `dep_in_declared_list` string alias mirrors the object rule.
 *
 * @command  php artisan test --compact tests/Feature/Rules/DepInDeclaredListRuleTest.php
 */

/**
 * Build an `epic_dep_ids` replicator value from a list of DEP ID strings.
 *
 * @param  array<int, string>  $deps
 * @return array<int, array<string, mixed>>
 */
function epicDepIdRows(array $deps): array
{
    return array_map(
        static fn (string $dep): array => ['type' => 'new_set', 'enabled' => true, 'dep' => $dep],
        $deps,
    );
}

it('rejects an empty parent field handle in the constructor', function () {
    expect(fn () => new DepInDeclaredList('  '))->toThrow(InvalidArgumentException::class);
});

it('throws when validated without a validator set', function () {
    $rule = new DepInDeclaredList('epic_dep_ids');

    expect(fn () => $rule->validate('dep', epicDepIdRows(['123']), static function (): void {}))
        ->toThrow(LogicException::class);
});

it('passes when the widget dep is empty', function () {
    $validator = Validator::make(
        ['epic_dep_ids' => epicDepIdRows(['123']), 'dep' => []],
        ['dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('passes when every widget dep is in the declared list', function () {
    $validator = Validator::make(
        ['epic_dep_ids' => epicDepIdRows(['123', '456', '789']), 'dep' => epicDepIdRows(['456', '123'])],
        ['dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('fails and names the offending DEP ID when one is not declared', function () {
    $validator = Validator::make(
        ['epic_dep_ids' => epicDepIdRows(['123', '456']), 'dep' => epicDepIdRows(['123', '999'])],
        ['dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->first('dep'))->toContain('999');
});

it('fails when any of multiple widget rows is not declared', function () {
    $validator = Validator::make(
        ['epic_dep_ids' => epicDepIdRows(['100']), 'dep' => epicDepIdRows(['100', '200', '300'])],
        ['dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeFalse();
});

it('fails when the declared list is empty but the widget has deps', function () {
    $validator = Validator::make(
        ['epic_dep_ids' => [], 'dep' => epicDepIdRows(['123'])],
        ['dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeFalse();
    expect(strtolower($validator->errors()->first('dep')))->toContain('declare');
    // Exactly one message — the canonical-list failure short-circuits before the
    // per-DEP loop (which would otherwise also flag every widget DEP).
    expect($validator->errors()->get('dep'))->toHaveCount(1);
});

it('passes when both the widget and the declared list are empty', function () {
    $validator = Validator::make(
        ['epic_dep_ids' => [], 'dep' => []],
        ['dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('ignores empty-string dep rows', function () {
    $validator = Validator::make(
        ['epic_dep_ids' => epicDepIdRows(['123']), 'dep' => epicDepIdRows(['123', ''])],
        ['dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('ignores whitespace-only dep rows', function () {
    $validator = Validator::make(
        ['epic_dep_ids' => epicDepIdRows(['123']), 'dep' => epicDepIdRows(['123', '   '])],
        ['dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('drops a falsy non-string scalar dep that coerces to an empty string', function () {
    // A junk boolean coerces to '' and must be dropped (not treated as a DEP),
    // so the lone valid row still passes.
    $validator = Validator::make(
        ['epic_dep_ids' => epicDepIdRows(['123']), 'dep' => [
            ['type' => 'new_set', 'dep' => '123'],
            ['type' => 'new_set', 'dep' => false],
        ]],
        ['dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('normalizes integer dep values to strings before comparison', function () {
    $validator = Validator::make(
        ['epic_dep_ids' => [['type' => 'new_set', 'dep' => 123]], 'dep' => epicDepIdRows(['123'])],
        ['dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('compares DEP IDs strictly so a numeric-equal but distinct id is rejected', function () {
    // '01' and '1' are loosely equal in PHP but are different DEP IDs (leading
    // zeros are significant). Strict comparison must reject '01' against ['1'].
    $validator = Validator::make(
        ['epic_dep_ids' => epicDepIdRows(['1']), 'dep' => epicDepIdRows(['01'])],
        ['dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeFalse();
});

it('resolves the top-level declared list when attached to a nested replicator subfield', function () {
    // Proves the production wiring: the rule sits on mychart_scheduling_options.*.dep
    // yet resolves epic_dep_ids from the entry root via the validator data.
    $validator = Validator::make(
        [
            'epic_dep_ids' => epicDepIdRows(['123', '456']),
            'mychart_scheduling_options' => [
                ['type' => 'new_set', 'dep' => epicDepIdRows(['123'])],
                ['type' => 'new_set', 'dep' => epicDepIdRows(['999'])],
            ],
        ],
        ['mychart_scheduling_options.*.dep' => [new DepInDeclaredList('epic_dep_ids')]],
    );

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->first('mychart_scheduling_options.1.dep'))->toContain('999');
    // The in-list widget (row 0) produced no error.
    expect($validator->errors()->has('mychart_scheduling_options.0.dep'))->toBeFalse();
});

it('mirrors the object rule through the registered dep_in_declared_list string alias', function () {
    $valid = Validator::make(
        ['epic_dep_ids' => epicDepIdRows(['123']), 'dep' => epicDepIdRows(['123'])],
        ['dep' => ['dep_in_declared_list:epic_dep_ids']],
    );
    $invalid = Validator::make(
        ['epic_dep_ids' => epicDepIdRows(['123']), 'dep' => epicDepIdRows(['999'])],
        ['dep' => ['dep_in_declared_list:epic_dep_ids']],
    );

    expect($valid->passes())->toBeTrue();
    expect($invalid->passes())->toBeFalse();
    // AC1 message names the offending ID on the production (string-alias) path.
    expect($invalid->errors()->first('dep'))->toContain('999');
});

it('wires dep_in_declared_list onto only the widget dep field in the clinical_locations blueprint', function () {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find('collections.clinical_locations.location');

    /** @var Fields $fields */
    $fields = $blueprint->fields()->addValues([
        'epic_dep_ids' => epicDepIdRows(['123']),
        'mychart_scheduling_options' => [
            ['type' => 'new_set', 'dep' => epicDepIdRows(['123'])],
        ],
    ]);

    /** @var Statamic\Fields\Validator $validator */
    $validator = $fields->validator();

    /** @var array<string, mixed> $rules */
    $rules = $validator->rules();

    // The widget dep replicator carries the rule on its nested path...
    expect($rules)->toHaveKey('mychart_scheduling_options.0.dep');
    expect($rules['mychart_scheduling_options.0.dep'])->toContain('dep_in_declared_list:epic_dep_ids');

    // ...but the top-level declared list's own dep subfield does NOT (scoping / AC5).
    expect($rules['epic_dep_ids.0.dep'] ?? [])->not->toContain('dep_in_declared_list:epic_dep_ids');
});
