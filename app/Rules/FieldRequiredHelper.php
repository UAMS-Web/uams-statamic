<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use UAMSWeb\FormSettings\Support\ConditionalLogicEvaluator;

/**
 * Helper for determining whether a field is required for the current request
 * based on its validate config and optional "if" condition (Statamic-style).
 *
 * Used by ReplicatorRequireAtLeastOneEnabledSet and ReplicatorMinSets to decide
 * when to apply field-level validation. Supports required*, "sometimes", and
 * Statamic field conditions, read by the same {@see ConditionalLogicEvaluator}
 * that gates form notifications (UAMS-Web/uams-statamic-archive#2890).
 */
class FieldRequiredHelper
{
    /**
     * Required-rule prefixes that indicate the field is required when they apply.
     *
     * @var array<int, string>
     */
    public const array REQUIRED_RULE_PREFIXES = [
        'required',
        'required_if',
        'required_unless',
        'required_if_accepted',
        'required_if_declined',
        'required_with',
        'required_with_all',
        'required_without',
        'required_without_all',
        'required_array',
    ];

    /**
     * The Statamic field-condition keys that gate a field's visibility, and so its
     * required check.
     *
     * @var array<int, string>
     */
    public const array CONDITION_KEYS = [
        'if',
        'if_any',
        'show_when',
        'show_when_any',
        'unless',
        'unless_any',
        'hide_when',
        'hide_when_any',
    ];

    /**
     * Whether the field's validate config contains any required* rule.
     *
     * @param  array<string, mixed>  $fieldConfig  Field config with 'validate' key
     * @return bool True if validate contains a required* rule
     */
    public static function configHasRequiredRule(
        array $fieldConfig
    ): bool {
        // Get the validate rules for the field config
        $validate = self::getValidateRules($fieldConfig);

        // Loop through each validate rule
        foreach ($validate as $rule) {
            // Loop through each required rule prefix
            foreach (self::REQUIRED_RULE_PREFIXES as $prefix) {
                // Check if the rule string starts with the required rule prefix
                if (
                    str_starts_with(
                        $rule,
                        $prefix
                    )
                ) {
                    // Return true since the rule string starts with the required rule prefix
                    return true;
                }
            }
        }

        // ---
        // Return false to indicate that the field's validate config does not contain any
        // required* rules
        // ---
        return false;
    }

    /**
     * Whether the field is required for this request (required* rules + "if").
     *
     * Considers "sometimes" and Statamic "if" config: when sometimes is present
     * and the "if" condition is not met, returns false.
     *
     * @param  array<string, mixed>  $fieldConfig  Field config with 'validate' and optional 'if'
     * @param  array<string, mixed>  $data  All of the data under validation
     * @param  string  $attribute  The name of the attribute being validated
     * @return bool True if the field is required for this request
     */
    public static function fieldIsRequiredForRequest(
        array $fieldConfig,
        array $data,
        string $attribute
    ): bool {
        // Check if the field has sometimes and the "if" condition is not met
        // Check if the "if" condition is not met
        if (self::validationHasSometimes($fieldConfig) && ! self::sometimesConditionMet(
            $fieldConfig,
            $data,
            $attribute
        )) {
            // Return false to indicate that the field is not required for this request
            return false;
        }

        // Get the required rules only
        $requiredRules = self::getRequiredRulesOnly($fieldConfig);

        // Check if the required rules are empty. A validator with no rules never
        // fails, so removing this return still yields false.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($requiredRules === []) {
            // Return false to indicate that the field is not required for this request
            return false;
        }

        // Set the test data to the data under validation
        $testData = $data;

        // Set the test data for the attribute to an empty array
        Arr::set($testData, $attribute, []);

        // Create a new validator and validate the test data
        $validator = Validator::make(
            $testData,
            [$attribute => $requiredRules]
        );

        // Return true if the validator fails, false otherwise
        return $validator->fails();
    }

    /**
     * Get the validate config as an array of rule strings.
     *
     * @param  array<string, mixed>  $fieldConfig  Field config with 'validate' key
     * @return array<int, string> The validate config as an array of rule strings
     */
    public static function getValidateRules(
        array $fieldConfig
    ): array {
        // Get the validate config from the field config
        $validate = $fieldConfig['validate'] ?? [];

        // Check if the validate config is a string
        if (\is_string($validate)) {
            // Map the validate config to an array of rule strings
            return array_map(
                trim(...),
                explode('|', $validate)
            );
        }

        // Check if the validate config is an array
        if (\is_array($validate)) {
            // Return the validate config as an array of rule strings
            /** @var array<int, string> $validate */
            return $validate;
        }

        // ---
        // Return an empty array to indicate that the validate config is not a string or
        // array
        // ---
        return [];
    }

    /**
     * Whether the validate config contains "sometimes".
     *
     * @param  array<string, mixed>  $fieldConfig  Field config with 'validate' key
     * @return bool True if validate contains "sometimes"
     */
    public static function validationHasSometimes(
        array $fieldConfig
    ): bool {
        // Get the validate rules from the field config
        $validateRules = self::getValidateRules($fieldConfig);

        // Check if the validate config contains "sometimes"
        // Return false to indicate that the validate config does not contain "sometimes"
        // Return true to indicate that the validate config contains "sometimes"
        return \in_array(
            'sometimes',
            $validateRules,
            true
        );
    }

    /**
     * Whether the field's conditions (when sometimes is present) are met given current
     * data.
     *
     * Any of {@see self::CONDITION_KEYS} gates the check, in every form Statamic saves:
     * an operator string, a bare value, a list of conditions on one field, and the
     * special values. They are read by {@see ConditionalLogicEvaluator}, so the required
     * check and the field's visibility agree (UAMS-Web/uams-statamic-archive#2890). A condition names a field in the
     * same set, so it is read against the values beside the attribute.
     *
     * With no condition keys the condition is met (Laravel's sometimes still gates by
     * attribute presence).
     *
     * @param  array<string, mixed>  $fieldConfig  Field config with optional condition keys
     * @param  array<string, mixed>  $data  All of the data under validation
     * @param  string  $attribute  The name of the attribute being validated
     * @return bool True if the conditions are met
     */
    public static function sometimesConditionMet(
        array $fieldConfig,
        array $data,
        string $attribute
    ): bool {
        // Keep only the condition keys from the field config. The evaluator reads
        // only these keys, in its own order, and ignores every other key, so
        // passing the whole config is equivalent.
        // @pest-mutate-ignore: UnwrapArrayIntersectKey
        $conditions = array_intersect_key(
            $fieldConfig,
            array_flip(self::CONDITION_KEYS)
        );

        // Check if the field config carries no condition keys. The evaluator
        // passes an empty condition set too, so removing this return is equivalent.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($conditions === []) {
            // Return true to indicate that the condition is met
            return true;
        }

        // Read the values beside the attribute, which the conditions name
        $prefix = self::resolveAttributePrefix($attribute);
        $scope = $prefix === '' ? $data : Arr::get($data, $prefix);

        /** @var array<string, mixed> $values */
        $values = \is_array($scope) ? $scope : [];

        // Evaluate the conditions against those values and return the result
        return (new ConditionalLogicEvaluator)->passes(
            $conditions,
            $values
        );
    }

    /**
     * Get only the required* rules from the validate config.
     *
     * @param  array<string, mixed>  $fieldConfig  Field config with 'validate' key
     * @return array<int, string> The required rules only
     */
    public static function getRequiredRulesOnly(
        array $fieldConfig
    ): array {
        // Get the validate config from the field config
        $validate = self::getValidateRules($fieldConfig);

        // Initialize an empty array for the required rules
        $required = [];

        // Loop through each validate rule
        foreach ($validate as $rule) {
            // Loop through each required rule prefix
            foreach (self::REQUIRED_RULE_PREFIXES as $prefix) {
                // Check if the rule string starts with the required rule prefix
                if (
                    str_starts_with($rule, $prefix)
                ) {
                    // Add the rule to the required rules array
                    $required[] = $rule;

                    // Break out of the loop since the rule has been added
                    break;
                }
            }
        }

        // Return the required rules array
        return $required;
    }

    /**
     * Resolve the parent path for the attribute so condition keys match data keys.
     *
     * e.g., "page_builder" -> "", "content.0.blocks" -> "content.0"
     *
     * @param  string  $attribute  The name of the attribute being validated
     * @return string The resolved attribute prefix
     */
    private static function resolveAttributePrefix(
        string $attribute
    ): string {
        // Split the attribute into parts
        $parts = explode(
            '.',
            $attribute
        );

        // Remove the last part
        array_pop($parts);

        // Join the parts back together with a dot and return the result
        return implode(
            '.',
            $parts
        );
    }
}
