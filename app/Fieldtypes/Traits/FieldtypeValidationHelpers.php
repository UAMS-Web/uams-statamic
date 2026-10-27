<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

use Statamic\Fields\Validator;

/**
 * Coordinator trait for Statamic custom fieldtype validation: blueprint `validate`
 * lists, the `required` flag, and (via SometimesValidationRules) `sometimes` handling
 * for conditional fields and nested `extraRules()` keys.
 *
 * Fieldtypes should `use FieldtypeValidationHelpers` only; do not compose
 * SometimesValidationRules directly.
 *
 * Typical `extraRules()` flow: `explodedBlueprintValidateRulesFromConfig()`, bail
 * unless `blueprintConfigMarksFieldRequired()`, build keyed rules, then
 * `conditionallyPrependSometimesToKeyedRuleSets()`. For `fieldRules()`,
 * `normalizeBlueprintValidateForSometimes()` on the raw `validate` config.
 *
 * @see  SometimesValidationRules
 */
trait FieldtypeValidationHelpers
{
    use SometimesValidationRules;

    /**
     * Explode a rules value the same way Statamic merges field rules (pipe string or list).
     *
     * @param  mixed  $rules  Pipe string, list of rules, empty, or null
     * @return array<int, string|object>
     */
    protected function explodeStatamicValidationRules(mixed $rules): array
    {
        /** @var array<int, string|object> $explodedRules */
        $explodedRules = Validator::explodeRules($rules);

        return $explodedRules;
    }

    /**
     * Exploded `validate` entry from a field config array (see `Field::config()`).
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string|object>
     */
    protected function explodedBlueprintValidateRulesFromConfig(array $config): array
    {
        return $this->explodeStatamicValidationRules($config['validate'] ?? []);
    }

    /**
     * Whether the field is required from blueprint `required` or a `required` validate rule.
     *
     * @param  array<string, mixed>  $config  Field config from `Field::config()`
     */
    protected function blueprintConfigMarksFieldRequired(array $config): bool
    {
        $rules = $this->explodedBlueprintValidateRulesFromConfig($config);

        // Bare ``in_array`` (not ``\in_array``): Pest's TrueToFalse mutator skips the
        // strict-flag ``true`` on ``in_array`` / ``array_search`` by function name.
        return ! empty($config['required']) || in_array('required', $rules, true);
    }

    /**
     * Whether an exploded rule list includes a given string rule name.
     *
     * @param  array<int, string|object>  $explodedRules
     */
    protected function explodedValidateRulesContain(
        array $explodedRules,
        string $ruleName
    ): bool {
        // Bare ``in_array`` (not ``\in_array``): Pest's TrueToFalse mutator skips the
        // strict-flag ``true`` on ``in_array`` / ``array_search`` by function name.
        return in_array($ruleName, $explodedRules, true);
    }
}
