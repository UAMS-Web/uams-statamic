<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a string is valid XPath 1.0-style syntax by attempting to
 * evaluate it against a minimal DOM. Empty strings pass (optional field).
 */
class XpathSyntax implements ValidationRule
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

        // Try to evaluate the XPath expression
        try {
            // Create a new DOMDocument
            $doc = new DOMDocument;

            // Load the XML content. Syntax is checked the same against an empty document,
            // so removing this load is a surviving, equivalent mutant; no marker can reach a
            // mutation of the statement it sits on.
            $doc->loadXML('<r/>');

            // Create a new DOMXPath object
            $xpath = new DOMXPath($doc);

            // Execute the XPath query. An invalid expression returns false whatever
            // libxml's error mode, while the warning that also signals it is collected
            // instead of raised once internal errors are on (UAMS-Web/uams-statamic-archive#2994), so the return value
            // decides.
            $result = $xpath->query($value);
        } catch (\Throwable) {
            // A warning raised into an exception means the same as a false return
            $result = false;
        }

        // Fail validation when the expression could not be evaluated. The line lives in
        // Statamic's validation file, which the bare `validation.` key never reached, so
        // the editor saw the key itself.
        if ($result === false) {
            $fail(__('statamic::validation.xpath_syntax', ['attribute' => $attribute]));
        }
    }
}
