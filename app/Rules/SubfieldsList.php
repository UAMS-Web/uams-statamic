<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseSubfieldsRule;
use Closure;

/**
 * Validation rule that requires at least one of the specified subfields to have a
 * value.
 *
 * This rule checks if at least one of the specified subfields within the current
 * field group has a non-null, non-empty value.
 *
 * Usage:
 * 'field' => [new SubfieldsList(['subfield1', 'subfield2'])]
 */
class SubfieldsList extends BaseSubfieldsRule
{
    /**
     * Create a new rule instance.
     *
     * @param  array<string>  $subfields  Array of subfield names to check
     */
    public function __construct(
        /**
         * The subfields to check for values.
         */
        protected array $subfields
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // ---
        // Normalize field data to array
        // ---

        // Normalize the value to an array, or return early if it can't be normalized
        $fieldData = $this->normalizeFieldData($value);
        if ($fieldData === null) {
            // Return early since value can't have subfields
            return;
        }

        // ---
        // Check if at least one of the specified subfields has a value
        // ---

        // Loop through each specified subfield
        foreach ($this->subfields as $subfieldKey) {
            // Check if the subfield is present with a non-empty value
            if ($this->subfieldHasValue($fieldData[$subfieldKey] ?? null)) {
                // Return early, since one subfield with a value satisfies the rule
                return;
            }
        }

        // ---
        // No specified subfield has a value, so validation fails
        // ---

        // Fail validation with a specific message
        $fail(__('statamic::validation.subfields.list.one_of_subfields', [
            'attribute' => $attribute,
            'subfields' => \implode(', ', $this->subfields),
        ]));
    }
}
