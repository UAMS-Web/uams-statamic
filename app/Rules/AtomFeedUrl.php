<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use App\Support\Http\OutboundUrlGuard;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Throwable;

/**
 * Custom validation rule for validating Atom feed URLs
 *
 * This rule ensures URLs point to valid Atom feeds by:
 *   - Validating the URL format
 *   - Fetching the feed content via HTTP
 *   - Parsing the XML content
 *   - Verifying the root element is `<feed>` in the Atom namespace
 *   - Checking for required elements: `<title>`, `<id>`, and `<updated>`, also in
 *     the Atom namespace
 *
 * First, the input value is validated to ensure it is a valid URL using PHP's
 * built-in URL validation. If not, the validation fails with a specific
 * message and returns early to prevent further validation.
 *
 * Then, an HTTP request is made to fetch the feed content. If the request
 * fails or returns a non-200 status code, validation fails with a specific
 * message.
 *
 * The XML content is then parsed and validated to ensure:
 *   - The root element is `<feed>`
 *   - The root element is in the Atom namespace, `http://www.w3.org/2005/Atom`,
 *     whether a default declaration or a prefix puts it there (RFC 4287 binds Atom
 *     to the namespace, never to a prefix)
 *   - Required elements `<title>`, `<id>`, and `<updated>` are present as children
 *     in the Atom namespace
 *
 * If any of these checks fail, the validation fails with a specific message.
 */
class AtomFeedUrl extends BaseValidationRule
{
    /**
     * Validate that the input value is a valid Atom feed URL.
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
        // ---
        // Validate that the value is a valid URL using PHP's built-in URL validation
        // ---

        // Check if the value is a valid URL
        if (
            ! filter_var(
                $value,
                FILTER_VALIDATE_URL
            )
        ) {
            // If the value is not a valid URL, fail validation with a specific message
            $fail(__('statamic::validation.url'));

            // Return early to prevent further validation
            return;
        }

        // Reject internal/loopback/link-local/private targets before fetching so
        // the save-time validation cannot be used to probe internal services (SSRF).
        if (! OutboundUrlGuard::isAllowedUrl($value)) {
            $fail(__('statamic::validation.url_feed.atom.not_accessible'));

            return;
        }

        // ---
        // Fetch the feed content via HTTP
        // ---

        // Fetch the feed through the egress guard: the connection is pinned to a
        // vetted public IP (no DNS-rebinding TOCTOU) and each redirect hop is
        // independently re-validated and re-pinned (no public→internal pivot).
        try {
            $response = OutboundUrlGuard::fetch(
                'GET',
                $value,
                static fn (PendingRequest $request): PendingRequest => $request->timeout(10),
                5
            );
        } catch (Throwable) {
            // A blocked/unvetted host, a blocked redirect hop, or any transport
            // error is a failed fetch.
            $fail(__('statamic::validation.url_feed.atom.not_accessible'));

            return;
        }

        // Check if HTTP request failed
        if ($response->failed()) {
            // If the request failed, fail validation with a specific message
            $fail(__('statamic::validation.url_feed.atom.not_accessible'));

            // Return early to prevent further validation
            return;
        }

        // Get the response body as a string
        $body = $response->body();

        // ---
        // Parse the XML content
        // ---

        // Parse with libxml's internal error handling on, then restore the mode found,
        // on every path: a mode left on hides the warnings other code relies on (UAMS-Web/uams-statamic-archive#2994)
        $previousLibxmlErrors = \libxml_use_internal_errors(true);

        try {
            // Attempt to parse the XML content
            $xml = \simplexml_load_string($body);
        } finally {
            // Clear XML errors and restore the previous mode
            \libxml_clear_errors();
            \libxml_use_internal_errors($previousLibxmlErrors);
        }

        // Check if XML parsing failed
        if ($xml === false) {
            // If XML parsing failed, fail validation with a specific message
            $fail(__('statamic::validation.xml_valid'));

            // Return early to prevent further validation
            return;
        }

        // ---
        // Validate Atom feed structure
        // ---

        // Define the Atom namespace URI
        $atomNamespace = 'http://www.w3.org/2005/Atom';

        // Check if the root element is `<feed>`
        if ($xml->getName() !== 'feed') {
            // If the root element is not `<feed>`, fail validation with a specific message
            $fail(__('statamic::validation.url_feed.atom.root_feed'));

            // Return early to prevent further validation
            return;
        }

        // Read the namespace the root element itself is in, which a default declaration
        // and a prefix set alike (UAMS-Web/uams-statamic-archive#2955). A namespace in scope is not enough: one
        // declared only by a descendant must not qualify an unqualified `<feed>`
        // (UAMS-Web/uams-statamic-archive#2949), and one bound for an attribute does not put the element in it.
        $rootNamespace = \dom_import_simplexml($xml)->namespaceURI;

        // Check if the root element is in the Atom namespace
        if ($rootNamespace !== $atomNamespace) {
            // If the namespace doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.url_feed.atom.atom_namespace'));

            // Return early to prevent further validation
            return;
        }

        // Read the root's children in the Atom namespace, so a prefixed `<a:title>` is
        // found as readily as an unprefixed one under a default declaration (UAMS-Web/uams-statamic-archive#2955)
        $atomChildren = $xml->children($atomNamespace);

        // Check if required Atom feed elements are present
        if (
            ! property_exists($atomChildren, 'title')
            || ! property_exists($atomChildren, 'id')
            || ! property_exists($atomChildren, 'updated')
        ) {
            // If required elements are missing, fail validation with a specific message
            $fail(__('statamic::validation.url_feed.atom.required_elements'));
        }

        // Clean up variables to free up memory
        unset(
            $response,
            $body,
            $xml,
            $atomNamespace,
            $rootNamespace,
            $atomChildren
        );
    }
}
