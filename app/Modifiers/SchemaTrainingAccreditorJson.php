<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\People\TrainingAccreditorRegistry;
use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: resolve a training set's specialty term id to its program
 * accreditor block as a JSON object string, or empty when non-accredited.
 *
 * Emitted on an `EducationalOccupationalProgram`'s `accreditedBy` property in the
 * residency/fellowship schema output; an empty return omits the property entirely
 * (no `null`/empty object).
 *
 * Usage: `{{ specialty }}{{ if index == 0 }}{{ id | schema_training_accreditor_json }}{{ /if }}{{ /specialty }}`
 *
 * @see TrainingAccreditorRegistry
 */
class SchemaTrainingAccreditorJson extends Modifier
{
    /**
     * Resolve a specialty term canonical id to its accreditor JSON object string.
     *
     * @param  mixed  $value  Specialty term canonical id (`taxonomy::slug`) or `Value` wrapping one
     * @param  array<int|string, mixed>  $params  Unused modifier parameters
     * @param  array<int|string, mixed>  $context  Unused Statamic modifier context
     * @return string Accreditor JSON object string, or empty string when non-accredited
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): string {
        // Unwrap Statamic Value wrappers when present
        if ($value instanceof Value) {
            $value = $value->value();
        }

        // Emit nothing for non-string ids
        if (! is_string($value)) {
            return '';
        }

        // Resolve the accreditor block JSON for the specialty term (trim is
        // load-bearing: the registry does not normalize whitespace).
        $json = TrainingAccreditorRegistry::jsonForSpecialtyTermId(trim($value));

        // Coalesce a non-accredited result to empty Antlers output
        return $json ?? '';
    }
}
