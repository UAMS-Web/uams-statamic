<?php

declare(strict_types=1);

namespace App\Rules;

use App\Fieldtypes\SchemaType as SchemaTypeFieldtype;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ensures a chained schema_type value either ends on a leaf (no further drill-down)
 * or sets chain_complete after the stop sentinel when further options exist.
 */
final readonly class ChainedSchemaTypeComplete implements ValidationRule
{
    public function __construct(
        private SchemaTypeFieldtype $fieldtype
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        if ($this->fieldtype->chainedDrilldownResolvedForRequired($value)) {
            return;
        }

        $fail(__('statamic::validation.schema_type.chained_incomplete'));
    }
}
