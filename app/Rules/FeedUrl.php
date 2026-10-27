<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating feed URLs, specifically for Atom and RSS
 * feeds
 *
 * This rule ensures URLs conform to the standard formats for feed platforms where
 * feeds are commonly used (Atom or RSS):
 * - Atom:
 *     - `https://example.com/feed.atom`
 *     - Any URL pointing to a valid Atom feed
 * - RSS:
 *     - `https://example.com/feed.xml`
 *     - `https://example.com/rss`
 *     - Any URL pointing to a valid RSS feed
 *
 * It rejects invalid formats, unsupported feed types or malformed feed content.
 */
class FeedUrl extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the feed URL format for Atom or RSS.
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
        // Create instance of the Atom validation rule to delegate validation to
        $atomRule = new AtomFeedUrl;

        // Create instance of the RSS validation rule to delegate validation to
        $rssRule = new RssFeedUrl;

        // ---
        // Check if the value is valid for either Atom or RSS using the isValid method
        // from the base validation rule.
        // ---
        $isValid = $atomRule->isValid($value) || $rssRule->isValid($value);

        // ---
        // If neither Atom nor RSS validation passed, fail with a comprehensive error
        // message
        // ---

        // Check if neither Atom nor RSS validation passed
        if (! $isValid) {
            // Fail with a comprehensive error message
            $fail(__('statamic::validation.url_feed.valid'));
        }

        // Clean up variables to free up memory
        unset(
            $atomRule,
            $rssRule,
            $isValid
        );
    }
}
