<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use App\Support\Http\OutboundUrlGuard;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Throwable;

/**
 * Custom validation rule for validating RSS feed URLs
 *
 * This rule ensures URLs point to valid RSS feeds by:
 *   - Validating the URL format
 *   - Fetching the feed content via HTTP
 *   - Parsing the XML content
 *   - Verifying the root element is `<rss>` (RSS 2.0) or `<rdf:RDF>` (RSS 1.0)
 *   - Checking for required elements: `<channel>` with `<title>` and `<link>`
 *
 * First, the input value is validated to ensure it is a valid URL using PHP's
 * built-in URL validation. If not, the validation fails with a specific message
 * and returns early to prevent further validation.
 *
 * Then, an HTTP request is made to fetch the feed content. If the request fails
 * or returns a non-200 status code, validation fails with a specific message.
 *
 * The XML content is then parsed and validated to ensure:
 *   - The root element is `<rss>` (RSS 2.0) or `<rdf:RDF>` (RSS 1.0)
 *   - For RSS 2.0: `<channel>` element exists with `<title>` and `<link>`
 *   - For RSS 1.0: `<channel>` or `<item>` elements exist
 *
 * If any of these checks fail, the validation fails with a specific message.
 */
class RssFeedUrl extends BaseValidationRule
{
    /**
     * Validate that the input value is a valid RSS feed URL.
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
        // Check if the value is a valid URL using PHP's built-in URL validation
        if (
            ! filter_var(
                $value,
                FILTER_VALIDATE_URL
            )
        ) {
            // Fail validation with a specific message
            $fail(__('statamic::validation.url'));

            // Return early to prevent further validation
            return;
        }

        // Reject internal/loopback/link-local/private targets before fetching so
        // the save-time validation cannot be used to probe internal services (SSRF).
        if (! OutboundUrlGuard::isAllowedUrl($value)) {
            $fail(__('statamic::validation.url_feed.rss.not_accessible'));

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
            $fail(__('statamic::validation.url_feed.rss.not_accessible'));

            return;
        }

        // Check if HTTP request failed
        if ($response->failed()) {
            // Fail validation with a specific message
            $fail(__('statamic::validation.url_feed.rss.not_accessible'));

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
            // Fail validation with a specific message
            $fail(__('statamic::validation.xml_valid'));

            // Return early to prevent further validation
            return;
        }

        // ---
        // Validate RSS feed structure
        // ---

        // Get the root element name
        $rootName = $xml->getName();

        // Check if the root element is `<rss>` (RSS 2.0)
        if ($rootName === 'rss') {
            // ---
            // Validate RSS 2.0 structure
            // ---

            // Check if the channel, its title or its link is missing. property_exists()
            // sees a SimpleXML child element, empty or not, and a missing channel
            // reads as an element with no children
            if (! property_exists($xml->channel, 'title') || ! property_exists($xml->channel, 'link')) {
                // Fail validation with a specific message
                $fail(__('statamic::validation.url_feed.rss.channel_elements'));
            }
        } elseif (
            $rootName === 'RDF'
            || $rootName === 'rdf:RDF'
        ) { // Check if the root element is `<RDF>` or `<rdf:RDF>` (RSS 1.0)
            // Check if channel or item elements exist (RSS 1.0 structure)
            if (
                (! property_exists($xml, 'channel') || $xml->channel === null)
                && (! property_exists($xml, 'item') || $xml->item === null)
            ) {
                // Fail validation with a specific message
                $fail(__('statamic::validation.url_feed.rss.rss_1_0_elements'));
            }
        } else { // If the root element is neither `<rss>` nor `<RDF>`, fail validation
            // Fail validation with a specific message
            $fail(__('statamic::validation.url_feed.rss.root_rss'));
        }

        // Clean up variables to free up memory
        unset(
            $response,
            $body,
            $xml,
            $rootName
        );
    }
}
