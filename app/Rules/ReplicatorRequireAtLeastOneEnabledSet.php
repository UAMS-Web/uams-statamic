<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * Validates that a Replicator field has at least one enabled set when the field
 * is required by its validation config.
 *
 * Used when the Replicator's blueprint validation includes any Laravel "required"
 * rule (required, required_if, required_unless, etc.). When that requirement
 * applies, the value must be an array containing at least one set with `enabled`
 * not false. Sets with `enabled: false` do not count.
 *
 * Respects "sometimes": when validation contains "sometimes" and the optional
 * "if" condition is not met, the rule does not fail (field is not validated).
 * When "sometimes" is present without "if", Laravel's sometimes behavior applies
 * (rules run only when the attribute is present).
 *
 * Uses FieldRequiredHelper for determining whether the field is required.
 */
class ReplicatorRequireAtLeastOneEnabledSet implements DataAwareRule, ValidationRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Create a new rule instance.
     *
     * @param  array<string, mixed>  $fieldConfig  Field config with 'validate' and optional 'if' (for sometimes)
     */
    public function __construct(
        /**
         * Field config containing 'validate' and optionally 'if' for sometimes.
         */
        protected array $fieldConfig
    ) {}

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data  All of the data under validation
     * @return static This instance for method chaining
     */
    public function setData(
        array $data
    ): static {
        // Set the data under validation
        $this->data = $data;

        // Return this instance for method chaining
        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  The name of the attribute being validated
     * @param  mixed  $value  The value of the attribute (Replicator sets array)
     * @param  Closure  $fail  The closure to call if validation fails
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // ---
        // Skip when value is not an array (other rules handle type)
        // ---

        // Check if the value is an array
        if (! \is_array($value)) {
            // Return early
            return;
        }

        // ---
        // Skip unless the field is required for this request (handles sometimes + "if")
        // ---

        // Check if the field is required for this request
        if (
            ! FieldRequiredHelper::fieldIsRequiredForRequest(
                $this->fieldConfig,
                $this->data,
                $attribute
            )
        ) {
            // Return early
            return;
        }

        // ---
        // Count sets that are enabled (enabled !== false; missing enabled counts as
        // enabled)
        // ---

        /** @var array<int, array<string, mixed>> $value */
        $enabledCount = $this->countEnabledSets($value);

        // Check if there are any enabled sets
        if ($enabledCount > 0) {
            // Return early
            return;
        }

        // Fail the validation, since there are no enabled sets
        $fail(__('statamic::validation.replicator.at_least_one_enabled_set'));
    }

    /**
     * Count sets in the replicator value that have enabled !== false.
     *
     * @param  array<int, array<string, mixed>>  $value  The replicator value
     * @return int The number of sets with enabled !== false
     */
    private function countEnabledSets(
        array $value
    ): int {
        // Initialize a counter for the number of sets with enabled !== false
        $count = 0;

        // Loop through each set in the replicator value
        foreach ($value as $set) {
            // Check if the set has an enabled key and it is not false
            if (
                Arr::get(
                    $set,
                    'enabled',
                    true
                ) !== false
            ) {
                // Increment the counter
                $count++;
            }
        }

        // Return the number of sets with enabled !== false
        return $count;
    }
}
