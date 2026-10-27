<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a Replicator field has at least min_sets sets when the field
 * is required by its validation config.
 *
 * Only runs when the field's validate config includes a required* rule and that
 * requirement applies for the current request (respects "sometimes" and Statamic
 * "if" conditions). When it runs, the value must be an array with at least
 * min_sets items (total sets, including disabled). Uses FieldRequiredHelper to
 * determine whether the field is required.
 */
class ReplicatorMinSets implements DataAwareRule, ValidationRule
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
     * @param  array<string, mixed>  $fieldConfig  Field config with optional 'min_sets' and 'validate'
     */
    public function __construct(
        /**
         * Field config containing 'validate', optional 'if', and optional 'min_sets'.
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

        // Get the min_sets value from the field config
        /** @var scalar|null $minSets */
        $minSets = $this->fieldConfig['min_sets'] ?? null;

        // Check if min_sets is not set or is less than or equal to 0
        if (
            $minSets === null
            || (int) $minSets <= 0
        ) {
            // Return early
            return;
        }

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

        // Convert min_sets to an integer
        $min = (int) $minSets;

        // Check if the number of sets is greater than or equal to min_sets
        if (\count($value) >= $min) {
            // Return early
            return;
        }

        // Check if min_sets is equal to 1
        if ($min === 1) {
            // Fail the validation with the one set message
            $fail(__('statamic::validation.replicator.min_sets.one'));
        } else {
            // Fail the validation with the multiple sets message
            $fail(__(
                'statamic::validation.replicator.min_sets.multiple',
                ['min' => $min]
            ));
        }
    }
}
