<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use App\Support\BoxSharedStaticDirectUrl;
use Closure;

/**
 * Validates Box.com HTTPS direct links under `/shared/static/` with a known MIME
 * mapping from the file extension.
 */
class BoxSharedStaticUrl extends BaseValidationRule
{
    /**
     * Validate the Box shared static URL shape and extension MIME mapping.
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

        // Fail when the URL does not match the Box direct link contract
        if ($parsed === null) {
            $fail(__('statamic::validation.url_box_shared_static.base'));
        }
    }
}
