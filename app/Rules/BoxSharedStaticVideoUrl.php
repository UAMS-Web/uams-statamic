<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use App\Support\BoxSharedStaticDirectUrl;
use Closure;

/**
 * Validates Box.com direct links whose extension maps to a `video` MIME category.
 */
class BoxSharedStaticVideoUrl extends BaseValidationRule
{
    /**
     * Validate the URL is a Box direct link to a video MIME category.
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
        // Parse the URL into Box static parts when possible
        $parsed = BoxSharedStaticDirectUrl::parse($value);

        // Fail when parsing fails or the MIME category is not video
        if (
            $parsed === null
            || $parsed['category'] !== 'video'
        ) {
            $fail(__('statamic::validation.url_box_shared_static.video'));
        }
    }
}
