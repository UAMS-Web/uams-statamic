<?php

declare(strict_types=1);

namespace App\Rules;

use App\Providers\ValidationServiceProvider;
use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for classifying a URL as first-party (a domain UAMS
 * owns) or not, usable in BOTH directions from this one class.
 *
 * The set of first-party domains lives in `config/first_party.php`
 * (`first_party.domains`) — at minimum `uams.edu` and `uamshealth.com` — and is
 * the SINGLE source of truth for first-party-domain detection across the
 * application. "First-party" means a domain we own; note `uamshealth.com` is
 * first-party but is not a `uams.*` host, which is why the concept is named for
 * ownership rather than the UAMS brand. A host counts as first-party when it
 * exactly equals a configured domain or is a subdomain of one:
 *   - `uams.edu`, `www.uams.edu`, `anything.uams.edu` → first-party,
 *   - `uamshealth.com`, `my.uamshealth.com` → first-party,
 *   - `uams.edu.evil.com`, `notuams.edu` → NOT first-party (look-alikes).
 *
 * The host test is `$host === $domain || str_ends_with($host, '.'.$domain)` — a
 * boundary-anchored comparison, never a naive `str_contains`, so a domain that
 * merely appears as a substring of an unrelated host is not misclassified.
 *
 * The direction is selected by the constructor `$mustBeFirstParty` flag, exposed
 * as two registered aliases in {@see ValidationServiceProvider}:
 *   - `first_party_url` — the value MUST be a first-party URL,
 *   - `non_first_party_url` — the value MUST NOT be a first-party URL (i.e. it
 *     must be an external, non-UAMS website).
 *
 * In both directions, the value is first validated to be a URL (mirroring
 * {@see TriProfileUrl}); a non-URL fails with the standard URL message. Empty
 * values are skipped by the base rule so an optional field still passes.
 */
class FirstPartyUrl extends BaseValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  bool  $mustBeFirstParty  When true, the URL must be first-party (`first_party_url`); when false,
     *                                  it must NOT be first-party (`non_first_party_url`)
     */
    public function __construct(
        private readonly bool $mustBeFirstParty = true
    ) {}

    /**
     * Determine whether the given URL belongs to a configured first-party domain.
     *
     * This is the SINGLE place first-party-domain membership is decided. It parses
     * the host, lowercases it, and matches it against `config('first_party.domains')`
     * using a boundary-anchored test (exact match or `.`-prefixed subdomain), so
     * look-alike hosts such as `uams.edu.evil.com` or `notuams.edu` are correctly
     * rejected. A value without a parseable host is treated as non-first-party.
     *
     * @param  string  $url  The URL to classify
     * @return bool True when the URL's host is a first-party domain or subdomain, false otherwise
     */
    public static function isFirstPartyUrl(
        string $url
    ): bool {
        // Extract the host component from the URL
        $host = \parse_url(
            $url,
            PHP_URL_HOST
        );

        // ---
        // A value without a parseable host (e.g. a relative path) cannot be a
        // first-party URL. Treat it as non-first-party rather than throwing.
        // ---

        // Check if no host could be extracted
        if (! \is_string($host)) {
            // No host means it cannot be a first-party URL
            return false;
        }

        // Lowercase the host so matching is case-insensitive (DNS is case-insensitive)
        $host = \mb_strtolower($host);

        // Strip a single trailing root dot: the absolute-FQDN form `uams.edu.`
        // (RFC 1034) resolves to the same site as `uams.edu`, but the configured
        // domains carry no trailing dot — without this, `uams.edu.` would slip the
        // `non_first_party_url` guard (and a real UAMS URL would fail `first_party_url`).
        $host = rtrim($host, '.');

        // ---
        // A host is first-party iff it exactly equals a configured domain OR ends
        // with `.`+domain (a subdomain). This boundary-anchored test rejects
        // look-alikes such as `uams.edu.evil.com` (ends with `.evil.com`, not
        // `.uams.edu`) and `notuams.edu` (does not equal `uams.edu` and does not
        // end with `.uams.edu`).
        // ---
        return \array_any(
            self::firstPartyDomains(),
            static fn (string $domain): bool => $host === $domain || \str_ends_with($host, '.'.$domain),
        );
    }

    /**
     * Resolve the configured set of first-party domains, normalized to bare,
     * lowercase, non-empty registrable domains (no scheme, port, path, or leading
     * dot).
     *
     * @return list<string> The normalized first-party domains from `config('first_party.domains')`
     */
    private static function firstPartyDomains(): array
    {
        // Read the configured first-party domains, defaulting to an empty list
        $configured = config('first_party.domains', []);

        // Guard against a misconfigured non-array value
        if (! \is_array($configured)) {
            // Treat anything that is not an array as no configured domains
            return [];
        }

        // Initialize the normalized domain list
        $domains = [];

        // Loop through each configured domain entry
        foreach ($configured as $domain) {
            // Skip non-scalar entries that cannot be a domain string
            if (! \is_scalar($domain)) {
                continue;
            }

            // Normalize to a trimmed, lowercase string with any leading dot removed
            $normalized = \ltrim(
                \mb_strtolower(trim((string) $domain)),
                '.'
            );

            // Skip empty entries so an empty config value never matches every host
            if ($normalized !== '') {
                // Add the normalized domain to the list
                $domains[] = $normalized;
            }
        }

        // Return the normalized list of first-party domains
        return $domains;
    }

    /**
     * Validate that the value is a URL and that its host is (or is not) a
     * first-party domain, depending on the configured direction.
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
        // Validate that the value is a valid URL using PHP's built-in URL validation,
        // mirroring TriProfileUrl. A non-URL fails with the standard URL message.
        // ---

        // Check if the value is not a valid URL
        if (
            ! filter_var(
                $value,
                FILTER_VALIDATE_URL
            )
        ) {
            // If the value is not a valid URL, fail validation with the URL message
            $fail(__('statamic::validation.url'));

            // Return early to prevent further validation
            return;
        }

        // Classify the URL against the single first-party-domain source
        $isFirstParty = self::isFirstPartyUrl($value);

        // ---
        // Enforce the configured direction. For `first_party_url` the URL must be
        // first-party; for `non_first_party_url` it must not be.
        // ---

        // Check if the URL is first-party but a non-first-party URL was required
        if (! $this->mustBeFirstParty && $isFirstParty) {
            // A first-party URL is not allowed here (e.g. a non-UAMS external website field)
            $fail(__('statamic::validation.url_non_first_party'));

            // Return after failing validation
            return;
        }

        // Check if the URL is not first-party but a first-party URL was required
        if ($this->mustBeFirstParty && ! $isFirstParty) {
            // A non-first-party URL is not allowed where a first-party URL is required
            $fail(__('statamic::validation.url_first_party'));
        }
    }
}
