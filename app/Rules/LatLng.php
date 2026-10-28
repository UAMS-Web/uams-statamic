<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating comma-separated latitude and longitude
 * pairs.
 *
 * This rule ensures the input is a pair of decimal latitude and longitude values
 * separated by a comma (e.g., `37.7749,-122.4194`), without cardinal direction
 * indicators. It reuses Latitude and Longitude rules to validate each component,
 * ensuring latitude is between `-90.0` and `90.0`, and longitude is between
 * `-180.0` and `180.0`. It rejects invalid formats, non-numeric values, or
 * values outside the valid ranges.
 */
class LatLng extends BaseValidationRule
{
    private readonly Latitude $latitudeRule;

    private readonly Longitude $longitudeRule;

    /**
     * Create a new LatLng rule instance.
     */
    public function __construct()
    {
        $this->latitudeRule = new Latitude;
        $this->longitudeRule = new Longitude;
    }

    /**
     * Validate that the input value conforms to the latitude-longitude pair format.
     *
     * @param  string  $attribute  The name of the field being validated
     * @param  string  $value  The prepared string value to validate
     * @param  Closure  $fail  The closure to call if validation fails
     */
    protected function validateValue(
        string $attribute,
        string $value,
        Closure $fail
    ): void {
        /**
         * Split the value into latitude and longitude using comma as delimiter,
         * allowing optional whitespace around the comma. A half left empty is not a
         * pair: the coordinate rules skip an empty value, so it would otherwise pass.
         * Whitespace around the pair needs no trimming, since `is_numeric()` accepts
         * it around a number.
         */
        $coordinates = preg_split(
            '/\s*,\s*/',
            $value
        );
        if (
            // phpstan requires this check; `preg_split()` fails only on a PCRE
            // error, which this constant pattern cannot raise.
            // @pest-mutate-ignore: FalseToTrue
            $coordinates === false
            || \count($coordinates) !== 2
            || $coordinates[0] === ''
            || $coordinates[1] === ''
        ) {
            $fail(__('statamic::validation.lat_lng.comma_separated'));

            return;
        }

        // Validate each half with its coordinate rule, whose messages name no attribute
        $this->latitudeRule->validate($attribute, $coordinates[0], $fail);
        $this->longitudeRule->validate($attribute, $coordinates[1], $fail);
    }
}
