<?php

declare(strict_types=1);

namespace App\Tags;

use App\Enums\Language;
use App\Enums\LanguageBCP47;
use App\Support\RequestAttributeMemo;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: JSON-encode Schema.org `Language` nodes (or Text fallbacks) for
 * JSON-LD property values.
 *
 * `SchemaOutputService` decodes Antlers output that starts with `{` or `[`, so
 * this tag returns a single JSON object, a JSON array, or a JSON string for
 * Text when a code has no enum case.
 *
 * Parameters:
 *   - codes: comma-separated string, or bound array of strings (`:codes="language"`)
 *   - standard: `bcp47` (default) uses `LanguageBCP47`; `iso639` uses `Language`
 *   - delimiter: splits `codes` when it is a string (default `,`)
 *
 * Usage:
 *   {{ schema_language codes="en" standard="bcp47" }}
 *   {{ schema_language codes="en,es" standard="bcp47" }}
 *   {{ schema_language :codes="language" standard="bcp47" }}
 *
 * Other PHP (e.g., `SchemaPronounceableText`) can call
 * `SchemaLanguage::jsonLdForSingleLanguageCode()` for one `Language` node.
 * That helper memoizes by trimmed code and standard on the current request so
 * large graphs do not rebuild identical `Language` maps repeatedly.
 */
class SchemaLanguage extends Tags
{
    /**
     * Request attribute name for memoized `jsonLdForSingleLanguageCode` results.
     */
    private const string REQUEST_LANGUAGE_JSONLD_CACHE_ATTRIBUTE = '_app_schema_language_jsonld_single';

    /**
     * Registered Antlers handle `{{ schema_language }}`
     *
     * @var string
     */
    protected static $handle = 'schema_language';

    /**
     * Build JSON text for language codes and return it for Antlers output.
     *
     * @return string JSON object, array JSON, JSON string, or empty string
     */
    public function index(): string
    {
        // ---
        // Read tag parameters (unwrap Statamic Value when present)
        // ---

        // Get the codes parameter
        /** @var Parameters $params */
        $params = $this->params;
        $codesRaw = $params->get('codes');

        // Check if the codes parameter is a Value
        if ($codesRaw instanceof Value) {
            // Unwrap the Value
            $codesRaw = $codesRaw->value();
        }

        // Return early when codes are absent or empty (skip delimiter/standard reads)
        if (
            in_array($codesRaw, ['', null, false], true)
        ) {
            return '';
        }

        // Return early when a bound list is empty
        if (\is_array($codesRaw) && $codesRaw === []) {
            return '';
        }

        // ---
        // Read delimiter for string splitting with a sensible default
        // ---

        // Get the delimiter parameter
        $delimiter = $params->get('delimiter', ',');

        // Check if the delimiter parameter is a Value
        if ($delimiter instanceof Value) {
            // Unwrap the Value
            $delimiter = $delimiter->value();
        }

        // ---
        // Read language standard (bcp47 vs iso639)
        // ---

        // Get the standard parameter
        $standardRaw = $params->get('standard', 'bcp47');

        // Check if the standard parameter is a Value
        if ($standardRaw instanceof Value) {
            // Unwrap the Value
            $standardRaw = $standardRaw->value();
        }

        // Normalize delimiter to a non-empty string
        $delimiterStr = \is_string($delimiter) && $delimiter !== ''
            ? $delimiter
            : ',';

        // Normalize codes into a unique ordered list of non-empty strings
        $codes = $this->normalizeLanguageCodes(
            $codesRaw,
            $delimiterStr
        );

        // Check if no codes remain after normalization
        if ($codes === []) {
            // Return empty string when no codes remain
            return '';
        }

        // Normalize standard to bcp47 or iso639
        $standard = self::normalizeStandardStatic($standardRaw);

        // ---
        // Build JSON-LD payload (single code avoids list + wrap overhead)
        // ---

        // Check if exactly one code was requested
        if (\count($codes) === 1) {
            $payload = self::buildLanguageJsonLdItemStatic(
                $codes[0],
                $standard
            );
        } else {
            // Initialize the items array
            $items = [];

            // Loop through the codes
            foreach ($codes as $code) {
                // Append Language map or Text string for this code
                $items[] = self::buildLanguageJsonLdItemStatic(
                    $code,
                    $standard
                );
            }

            // Use a list when multiple codes were provided
            $payload = $this->wrapPayload($items);

            unset($items);
        }

        // Attempt to encode the payload to JSON for schema property parsing
        try {
            // Encode the payload to JSON
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            // Return empty string when encoding fails
            return '';
        }

        // Return the JSON
        return $json;
    }

    /**
     * Build JSON-LD for a single language code (shared by the tag and callers).
     *
     * @param  string  $code  BCP 47 or ISO 639 code (trimmed)
     * @param  mixed  $standardRaw  `bcp47` (default) or `iso639` token
     * @return array<string, string>|string Language node, plain code string when unknown, or empty string when code is
     *                                      empty after trim
     */
    public static function jsonLdForSingleLanguageCode(
        string $code,
        mixed $standardRaw = 'bcp47'
    ): array|string {
        // Trim whitespace from the language code
        $trimmed = trim($code);

        // Check if the code is empty after trimming
        if ($trimmed === '') {
            // Return empty string so callers can omit `inLanguage`
            return '';
        }

        // Normalize standard before memo lookup (same as build path)
        $standard = self::normalizeStandardStatic($standardRaw);

        // Build the memo key for this code and standard
        $memoKey = self::languageJsonLdMemoKey(
            $trimmed,
            $standard
        );

        // Reuse or build the Language map or Text fallback for this code
        /** @var array<string, string>|string $resolved */
        $resolved = RequestAttributeMemo::remember(
            self::REQUEST_LANGUAGE_JSONLD_CACHE_ATTRIBUTE,
            $memoKey,
            static fn (): array|string => self::buildLanguageJsonLdItemStatic(
                $trimmed,
                $standard
            )
        );

        return $resolved;
    }

    /**
     * Build a stable memo key for a trimmed code and normalized standard.
     *
     * @param  string  $trimmed  Non-empty trimmed language code
     * @param  string  $standard  `bcp47` or `iso639`
     * @return string Memo key string
     */
    private static function languageJsonLdMemoKey(
        string $trimmed,
        string $standard
    ): string {
        // Separate code and standard with a record-separator byte
        return $trimmed."\x1e".$standard;
    }

    /**
     * Build one Schema.org Language object or a Text fallback string.
     *
     * @param  string  $code  Stored language code from the entry field
     * @param  string  $standard  `bcp47` or `iso639`
     * @return array<string, string>|string Language node or plain code string
     */
    private static function buildLanguageJsonLdItemStatic(
        string $code,
        string $standard
    ): array|string {
        // Check if ISO 639 enum should drive labels
        if ($standard === 'iso639') {
            // Resolve the ISO 639 enum case for the code
            $isoCase = Language::tryFrom($code);

            // Fall back to Text when the code is unknown
            if ($isoCase === null) {
                return $code;
            }

            // Return a Language node with English name and stored code
            return [
                '@type' => 'Language',
                'name' => $isoCase->name(),
                'alternateName' => $code,
            ];
        }

        // Resolve the BCP 47 enum case for the code
        $bcpCase = LanguageBCP47::tryFrom($code);

        // Fall back to Text when the code is unknown
        if ($bcpCase === null) {
            return $code;
        }

        // Return a Language node with registry description and stored code
        return [
            '@type' => 'Language',
            'name' => $bcpCase->description(),
            'alternateName' => $code,
        ];
    }

    /**
     * Normalize standard parameter to `bcp47` or `iso639`.
     *
     * @param  mixed  $raw  Standard parameter value
     * @return string `bcp47` or `iso639`
     */
    private static function normalizeStandardStatic(
        mixed $raw
    ): string {
        // Coerce non-string input to empty string
        $token = \is_string($raw) ? strtolower(trim($raw)) : '';

        // Check for ISO 639 aliases
        if (
            $token === 'iso639'
            || $token === 'iso-639'
        ) {
            // Return ISO 639
            return 'iso639';
        }

        // Default to BCP 47 for unknown or empty tokens
        return 'bcp47';
    }

    /**
     * Coerce `codes` to a unique list of trimmed non-empty strings.
     *
     * String input: comma uses `explode`; other delimiters use whitespace-tolerant
     * `preg_split` (same visible behavior as the comma path after per-segment trim).
     *
     * @param  mixed  $raw  String or list of strings (or Value-wrapped)
     * @param  string  $delimiter  Separator when $raw is a string
     * @return list<string>
     */
    private function normalizeLanguageCodes(
        mixed $raw,
        string $delimiter
    ): array {
        // Handle list input from bound fields or arrays
        if (\is_array($raw)) {
            // ---
            // Collect trimmed string codes from the list
            // ---

            // Initialize the out array
            $out = [];

            // Loop through the raw items
            foreach ($raw as $item) {
                // Unwrap Statamic Value entries when present
                if ($item instanceof Value) {
                    $item = $item->value();
                }

                // Check for a non-empty string segment
                if (\is_string($item)) {
                    // Trim whitespace from the segment
                    $trimmed = trim($item);

                    // Skip empty segments after trimming
                    if ($trimmed !== '') {
                        $out[] = $trimmed;
                    }
                }
            }

            // Return unique codes in first-seen order
            return array_values(array_unique($out));
        }

        // Require string input for delimiter splitting
        if (! \is_string($raw)) {
            return [];
        }

        // Trim the full codes string
        $trimmedRaw = trim($raw);

        // Check for empty string after trimming
        if ($trimmedRaw === '') {
            return [];
        }

        // ---
        // Split the string on delimiter boundaries
        // ---

        // Use explode for comma (default) to avoid regex overhead
        if ($delimiter === ',') {
            $parts = explode(
                ',',
                $trimmedRaw
            );
        } else {
            // Build the regex pattern for arbitrary delimiters
            $pattern = '/\s*'.preg_quote(
                $delimiter,
                '/'
            ).'\s*/';

            // Split the string on the regex pattern
            $parts = preg_split(
                $pattern,
                $trimmedRaw,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            // Check if splitting failed
            if (! \is_array($parts)) {
                return [];
            }
        }

        // ---
        // Collect trimmed non-empty unique segments
        // ---

        // Initialize the out array
        $out = [];

        // Loop through the parts
        foreach ($parts as $part) {
            // Trim each part
            $segment = trim($part);

            // Skip empty segments
            if ($segment !== '') {
                $out[] = $segment;
            }
        }

        // Return unique codes in first-seen order
        return array_values(array_unique($out));
    }

    /**
     * Return a single item as-is, or a list when multiple codes were given.
     *
     * @param  list<string|array<string, string>>  $items  One JSON-LD value per normalized code: either a `Language` property map (string
     *                                                     keys and values) or a plain string for schema.org Text when the code is unknown
     * @return string|array<string, string>|list<string|array<string, string>> The sole Text string or `Language` map when exactly one code was given, or the
     *                                                                         ordered list of those values when multiple codes were given
     */
    private function wrapPayload(
        array $items
    ): mixed {
        // Check if exactly one item should surface without a wrapping array
        if (\count($items) === 1) {
            return $items[0];
        }

        // Return the full list for multiple codes
        return $items;
    }
}
