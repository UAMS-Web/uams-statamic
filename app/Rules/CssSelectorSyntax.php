<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\CssSelector\Parser\Parser;

/**
 * Validates that a string is valid CSS selector syntax by parsing it with
 * Symfony CssSelector. Empty strings pass (optional field).
 */
class CssSelectorSyntax implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  The name of the field being validated
     * @param  mixed  $value  The value of the field being validated
     * @param  Closure  $fail  The closure to call if validation fails
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Check if the value is a string
        if (! \is_string($value)) {
            // Fail validation with the error message
            $fail(__('validation.string'));

            // Return early to skip validation
            return;
        }

        // Check if the value is empty
        if ($value === '') {
            // Return early to skip validation
            return;
        }

        // Try to evaluate the CSS selector expression
        try {
            // Symfony marks the CssSelector `Parser` @internal, but it is the only
            // public-surface way to validate CSS selector *syntax*: the public
            // `CssSelectorConverter` additionally requires XPath-translatability and
            // wrongly rejects syntactically valid selectors such as `div::before`.
            // Suppress the Bleeding Edge internal-class rule for this deliberate use.
            // @phpstan-ignore new.internalClass, method.internalClass
            $parser = new Parser;

            // Parse the CSS selector expression (throws on invalid syntax)
            // @phpstan-ignore method.internalClass
            $parser->parse($value);
        } catch (\Throwable) {
            // Fail validation with the error message. The line lives in Statamic's
            // validation file, which the bare `validation.` key never reached, so the
            // editor saw the key itself.
            $fail(__('statamic::validation.css_selector_syntax', ['attribute' => $attribute]));
        }
    }
}
