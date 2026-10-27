<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\RequestAttributeMemo;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Support\Str;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: JSON-encode Schema.org `PronounceableText` nodes for JSON-LD.
 *
 * When `phonetic_text` is empty after trim, emits JSON for `text_value` alone
 * as a string (schema.org Text), ignoring `in_language` and
 * `speech_to_text_markup`.
 *
 * When `phonetic_text` is set, builds `PronounceableText` objects in a JSON
 * array: slash-wrapped `phoneticText` and `speechToTextMarkup` from the tag
 * (defaults to `IPA` when markup is omitted). `inLanguage` is built via
 * `SchemaLanguage::jsonLdForSingleLanguageCode()` (same `Language` / Text rules
 * as `schema_language`).
 *
 * An **IPA** input emits **two** objects per row — the IPA form and an SSML
 * `<speak><phoneme alphabet="ipa">` form derived from the same input — because
 * speech engines consume SSML while the IPA form stays readable. Any other
 * markup emits one object in that markup, since SSML's `alphabet="ipa"` cannot
 * honestly be derived from X-SAMPA or SAPI.
 *
 * Identical tag parameters within the same HTTP request reuse a memoized JSON
 * string so large JSON-LD graphs do not repeat encoding work for repeated
 * names or credentials.
 *
 * Parameters:
 *   - pairs: optional; one or more `text` / `phonetic_text` rows as an array.
 *     A single row may be passed as one associative array, for example
 *     `[ 'text' => 'foo', 'phonetic_text' => 'fu' ]`, or multiple rows as a
 *     list of such maps. Each row accepts `text` or `text_value` for surface
 *     text. When `pairs` is set, `text_value` and `phonetic_text` on the tag
 *     are ignored. Surface text for each row is processed like
 *     `strip_tags` (no allowed tags) then `sanitize` (htmlspecialchars,
 *     ENT_QUOTES, Statamic charset, double_encode false). Shared options
 *     `in_language`, `language_standard`, and `speech_to_text_markup` apply
 *     to every row that has phonetic text.
 *   - text_value: surface text (required for output when `pairs` is omitted)
 *   - phonetic_text: pronunciation string (when set, adds markup fields)
 *   - in_language: single BCP 47 or ISO 639 code (optional; omit property if empty)
 *   - language_standard: `bcp47` (default) or `iso639` (same as
 *     `schema_language` `standard`)
 *   - speech_to_text_markup: optional; `speechToTextMarkup` when phonetic text is
 *     set (defaults to `IPA` when omitted or blank)
 *
 * Usage:
 *   {{ schema_pronounceable_text in_language="en" phonetic_text="ʃɪˈvɔːn" speech_to_text_markup="IPA" text_value="Siobhan" }}
 *   {{ schema_pronounceable_text :pairs="name_pairs" in_language="en" }}
 */
class SchemaPronounceableText extends Tags
{
    /**
     * Request attribute name for the per-request output cache.
     */
    private const string REQUEST_CACHE_ATTRIBUTE = '_app_schema_pronounceable_text';

    /**
     * Registered Antlers handle `{{ schema_pronounceable_text }}`
     *
     * @var string
     */
    protected static $handle = 'schema_pronounceable_text';

    /**
     * Build JSON text for PronounceableText object(s) and return it for Antlers.
     *
     * @return string JSON string (Text), JSON array of one `PronounceableText`,
     *                or empty string when `text_value` is empty after trim
     */
    public function index(): string
    {
        // Read language standard once for either code path
        /** @var Parameters $params */
        $params = $this->params;
        $standardRaw = $params->get('language_standard', 'bcp47');

        // Unwrap language_standard when it is a Statamic Value
        if ($standardRaw instanceof Value) {
            $standardRaw = $standardRaw->value();
        }

        // Read shared language and markup parameters
        $inLanguageRaw = $this->unwrapParam('in_language');
        $markupRaw = $this->unwrapParam('speech_to_text_markup');

        // Branch on structured pair input versus legacy flat parameters
        if ($params->has('pairs')) {
            // Unwrap the pairs payload for cache key and rendering
            $pairsRaw = $this->unwrapParam('pairs');

            // Normalize to a list of text/phonetic rows
            $normalizedPairs = $this->normalizePairsList($pairsRaw);

            // Build a stable fingerprint for memoization
            $cacheKey = $this->cacheKeyFromPairsInputs(
                $normalizedPairs,
                $inLanguageRaw,
                $standardRaw,
                $markupRaw
            );

            // Reuse or compute JSON for duplicate tag calls in the same request
            /** @var string $pairsOutput */
            $pairsOutput = RequestAttributeMemo::remember(
                self::REQUEST_CACHE_ATTRIBUTE,
                $cacheKey,
                fn (): string => $this->computeOutputFromPairs(
                    $normalizedPairs,
                    $inLanguageRaw,
                    $standardRaw,
                    $markupRaw
                )
            );

            return (string) $pairsOutput;
        }

        // Unwrap legacy flat parameters once for cache key and computation
        $textValueRaw = $this->unwrapParam('text_value');
        $phoneticRaw = $this->unwrapParam('phonetic_text');

        // Build a stable fingerprint for memoization
        $cacheKey = $this->cacheKeyFromUnwrappedInputs(
            $textValueRaw,
            $phoneticRaw,
            $inLanguageRaw,
            $standardRaw,
            $markupRaw
        );

        // Reuse or compute JSON for duplicate tag calls in the same request
        /** @var string $flatOutput */
        $flatOutput = RequestAttributeMemo::remember(
            self::REQUEST_CACHE_ATTRIBUTE,
            $cacheKey,
            fn (): string => $this->computeOutput(
                $textValueRaw,
                $phoneticRaw,
                $inLanguageRaw,
                $standardRaw,
                $markupRaw
            )
        );

        return (string) $flatOutput;
    }

    /**
     * Build a stable cache key from unwrapped tag parameters.
     *
     * @param  mixed  $textValueRaw  Unwrapped `text_value`
     * @param  mixed  $phoneticRaw  Unwrapped `phonetic_text`
     * @param  mixed  $inLanguageRaw  Unwrapped `in_language`
     * @param  mixed  $standardRaw  Unwrapped `language_standard`
     * @param  mixed  $markupRaw  Unwrapped `speech_to_text_markup`
     * @return string Hash suitable for request cache lookup
     */
    private function cacheKeyFromUnwrappedInputs(
        mixed $textValueRaw,
        mixed $phoneticRaw,
        mixed $inLanguageRaw,
        mixed $standardRaw,
        mixed $markupRaw
    ): string {
        // Hash normalized parameter tuple for deterministic memo keys
        return hash(
            'xxh128',
            json_encode(
                [
                    'm' => $markupRaw,
                    'p' => $phoneticRaw,
                    's' => $standardRaw,
                    'i' => $inLanguageRaw,
                    't' => $textValueRaw,
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            )
        );
    }

    /**
     * Build a stable cache key for the `pairs` code path.
     *
     * @param  array<int, array{text: string, phonetic_text: string}>  $normalizedPairs
     * @param  mixed  $inLanguageRaw  Unwrapped `in_language`
     * @param  mixed  $standardRaw  Unwrapped `language_standard`
     * @param  mixed  $markupRaw  Unwrapped `speech_to_text_markup`
     * @return string Hash suitable for request cache lookup
     */
    private function cacheKeyFromPairsInputs(
        array $normalizedPairs,
        mixed $inLanguageRaw,
        mixed $standardRaw,
        mixed $markupRaw
    ): string {
        // Hash normalized pairs plus shared options for deterministic memo keys
        return hash(
            'xxh128',
            json_encode(
                [
                    'mode' => 'pairs',
                    'i' => $inLanguageRaw,
                    'm' => $markupRaw,
                    'p' => $normalizedPairs,
                    's' => $standardRaw,
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            )
        );
    }

    /**
     * Normalize `pairs` tag input to a list of trimmed text/phonetic strings.
     *
     * @param  mixed  $raw  Unwrapped `pairs` value (plain arrays and/or `Values` rows)
     * @return array<int, array{text: string, phonetic_text: string}> Zero or more rows
     */
    private function normalizePairsList(
        mixed $raw
    ): array {
        // Grid / augmented blueprint fields often surface each row as `Values`;
        // coerce to a plain associative array before list handling.
        if ($raw instanceof Values) {
            $raw = $raw->toRawArray();
        }

        // Return no rows when the payload is missing or not an array
        if (! \is_array($raw)) {
            return [];
        }

        // Treat an empty array as no rows
        if ($raw === []) {
            return [];
        }

        // Wrap a single associative row as a one-element list
        if ($this->isSinglePairAssociativeRow($raw)) {
            $one = $this->normalizeOnePairRow($raw);

            // Omit the row when it does not contain usable text
            if ($one === null) {
                return [];
            }

            return [$one];
        }

        // Collect normalized rows from a list of pair maps
        $out = [];

        foreach ($raw as $row) {
            // Unwrap Statamic Value rows before reading keys
            if ($row instanceof Value) {
                $row = $row->value();
            }

            // Unwrap augmented grid row objects to plain maps for text/phonetic keys
            if ($row instanceof Values) {
                $row = $row->toRawArray();
            }

            // Skip non-array entries inside the list
            if (! \is_array($row)) {
                continue;
            }

            // Normalize this list element when it carries text
            $normalized = $this->normalizeOnePairRow($row);

            // Skip rows without surface text after coercion
            if ($normalized === null) {
                continue;
            }

            $out[] = $normalized;
        }

        // Return the normalized list of pair rows
        return $out;
    }

    /**
     * Strip tags and escape pair surface text like core Statamic modifiers.
     *
     * Uses Statamic\Support\Str::stripTags() with an empty allowlist (same as
     * the `strip_tags` modifier with no parameters), then htmlspecialchars()
     * with ENT_QUOTES, the Statamic system charset, and double_encode false
     * (same defaults as the `sanitize` modifier).
     *
     * @param  string  $trimmedText  Row surface text after trim
     * @return string Text ready for JSON-LD textValue or plain Text output
     */
    private function preparePairsSurfaceText(
        string $trimmedText
    ): string {
        // Strip all tags using Statamic tag stripping semantics
        /** @var string $stripped */
        $stripped = Str::stripTags(
            $trimmedText,
            []
        );

        // Read the configured system charset for the sanitize encoding
        $charset = config(
            'statamic.system.charset',
            'UTF-8'
        );

        // Escape HTML special characters like the sanitize modifier
        return htmlspecialchars(
            $stripped,
            ENT_QUOTES,
            \is_string($charset) ? $charset : 'UTF-8',
            false
        );
    }

    /**
     * Check whether an array is one associative pair map (not a list of rows).
     *
     * @param  array<mixed>  $raw  Candidate array from `pairs`
     * @return bool True when the array is one associative pair map (not a list of rows)
     */
    private function isSinglePairAssociativeRow(
        array $raw
    ): bool {
        // Lists of rows use integer keys in order
        if (array_is_list($raw)) {
            return false;
        }

        // Require at least one known surface-text key
        return array_key_exists('text', $raw)
            || array_key_exists('text_value', $raw);
    }

    /**
     * Coerce one row map to trimmed text and phonetic strings.
     *
     * @param  array<mixed>  $row  Single pair map
     * @return array{text: string, phonetic_text: string}|null Null when text is absent
     */
    private function normalizeOnePairRow(
        array $row
    ): ?array {
        // Read surface text from either supported key
        $textRaw = $row['text'] ?? $row['text_value'] ?? null;

        // Unwrap Statamic Value for surface text when present
        if ($textRaw instanceof Value) {
            $textRaw = $textRaw->value();
        }

        // Reject rows with no surface text field
        if (
            in_array($textRaw, ['', null, false], true)
        ) {
            return null;
        }

        // Coerce surface text to a trimmed string
        $text = \is_string($textRaw) ? trim($textRaw) : '';

        // Skip rows whose surface text is empty after trim
        if ($text === '') {
            return null;
        }

        // Strip tags then escape like Statamic `strip_tags|sanitize` modifiers
        $text = $this->preparePairsSurfaceText($text);

        // Skip rows whose surface text is empty after stripping tags
        if ($text === '') {
            return null;
        }

        // Read optional phonetic string for this row
        $phoneticRaw = $row['phonetic_text'] ?? null;

        // Unwrap Statamic Value for phonetic text when present
        if ($phoneticRaw instanceof Value) {
            $phoneticRaw = $phoneticRaw->value();
        }

        // Coerce phonetic text to a trimmed string
        $phonetic = \is_string($phoneticRaw) ? trim($phoneticRaw) : '';

        // Return the normalized pair map for downstream JSON building
        return [
            'phonetic_text' => $phonetic,
            'text' => $text,
        ];
    }

    /**
     * Build JSON output from normalized pair rows and shared options.
     *
     * @param  array<int, array{text: string, phonetic_text: string}>  $pairs
     * @param  mixed  $inLanguageRaw  Unwrapped `in_language`
     * @param  mixed  $standardRaw  Unwrapped `language_standard`
     * @param  mixed  $markupRaw  Unwrapped `speech_to_text_markup`
     * @return string JSON string (Text), JSON array of one `PronounceableText`, or empty string when
     *                no rows are present after normalization
     */
    private function computeOutputFromPairs(
        array $pairs,
        mixed $inLanguageRaw,
        mixed $standardRaw,
        mixed $markupRaw
    ): string {
        // Return nothing when there are no rows after normalization
        if ($pairs === []) {
            return '';
        }

        // Coerce in_language to a trimmed string
        $inLanguageCode = \is_string($inLanguageRaw) ? trim($inLanguageRaw) : '';

        // Coerce speech_to_text_markup to a trimmed string
        $markupHint = \is_string($markupRaw) ? trim($markupRaw) : '';

        // Initialize inLanguage payload to null for omission when absent
        $inLanguage = null;

        // Resolve inLanguage once when any row may use phonetic markup
        if ($inLanguageCode !== '') {
            // Build Language JSON-LD or Text fallback for the code
            $inLanguage = SchemaLanguage::jsonLdForSingleLanguageCode(
                $inLanguageCode,
                $standardRaw
            );
        }

        // Collect Text or PronounceableText fragments for each row
        $fragments = [];

        foreach ($pairs as $pair) {
            // Build the JSON fragments for this row, one or two per phonetic row
            $rowFragments = $this->pairFragments(
                $pair['text'],
                $pair['phonetic_text'],
                $inLanguage,
                $markupHint
            );

            // Append every fragment the row produced, skipping empty rows
            foreach ($rowFragments as $fragment) {
                $fragments[] = $fragment;
            }
        }

        // Return nothing when every row was skipped
        if ($fragments === []) {
            return '';
        }

        // Encode the combined fragment list as JSON for Antlers output
        return $this->encodeFragments($fragments);
    }

    /**
     * Build the schema fragments for one row: a plain Text string, or one or
     * two `PronounceableText` maps.
     *
     * An IPA input yields **two** objects — the IPA form and an SSML form
     * derived from it — because speech engines consume SSML while the IPA form
     * stays readable and auditable. Both come from the single IPA input, so no
     * caller has to supply the SSML.
     *
     * A non-IPA markup yields **one** object in that markup. SSML's
     * `<phoneme alphabet="ipa">` cannot be derived from X-SAMPA or SAPI, and
     * emitting it anyway would assert an alphabet the input is not written in.
     *
     * @param  string  $textValue  Surface text for `textValue`
     * @param  string  $phoneticText  Pronunciation string for `phoneticText`
     * @param  array<string, string>|string|null  $inLanguage  Resolved language payload
     * @param  string  $markupHint  Markup naming the alphabet the input uses
     * @return array<int, array<string, mixed>|string> Zero fragments to skip the row, one Text
     *                                                 string, one map, or an IPA map and its SSML sibling
     */
    private function pairFragments(
        string $textValue,
        string $phoneticText,
        array|string|null $inLanguage,
        string $markupHint
    ): array {
        // Skip rows with no display text
        if ($textValue === '') {
            return [];
        }

        // Emit plain Text when phonetic text is absent after trim
        if ($phoneticText === '') {
            return [$textValue];
        }

        // Resolve the markup naming the alphabet the input is written in
        $markup = $markupHint !== ''
            ? strtoupper($markupHint)
            : 'IPA';

        // Build the pronounceable node with shared type and text value
        $phoneticNode = $this->basePronounceableNode(
            $textValue,
            $inLanguage
        );

        // Record the alphabet the phonetic input itself uses
        $phoneticNode['speechToTextMarkup'] = $markup;

        // Wrap phonetic text in slashes per IPA presentation convention
        $phoneticNode['phoneticText'] = sprintf('/%s/', $phoneticText);

        // Emit the input's own form alone when it is not IPA
        if ($markup !== 'IPA') {
            return [$phoneticNode];
        }

        // Build the SSML sibling from the same IPA input
        $ssmlNode = $this->basePronounceableNode(
            $textValue,
            $inLanguage
        );

        // Mark the derived node as SSML rather than the input's IPA
        $ssmlNode['speechToTextMarkup'] = 'SSML';

        // Derive the SSML document from the IPA and the surface text
        $ssmlNode['phoneticText'] = $this->ssmlPhonemeFromIpa(
            $textValue,
            $phoneticText
        );

        // Return the IPA form and its derived SSML sibling
        return [$phoneticNode, $ssmlNode];
    }

    /**
     * Build an SSML `<phoneme>` document wrapping surface text with its IPA.
     *
     * **Both arguments reach this method in different states, and that is why
     * `double_encode` is off.** The `pairs` path escapes surface text through
     * `preparePairsSurfaceText()` before it gets here; the single `text_value`
     * path does not, so `A & B` arrives raw. `double_encode: false` is correct
     * for both: it escapes the raw `&` and leaves an already-escaped `&amp;`
     * alone, where escaping unconditionally would emit `&amp;amp;` for the
     * pairs path and escaping not at all would emit malformed XML for the
     * single path.
     *
     * `$phoneticText` is only trimmed upstream, never sanitized, so it is
     * escaped here before entering an XML attribute value.
     *
     * @param  string  $textValue  Surface text, raw or already escaped
     * @param  string  $phoneticText  IPA pronunciation, never sanitized upstream
     * @return string SSML document for `phoneticText`
     */
    private function ssmlPhonemeFromIpa(
        string $textValue,
        string $phoneticText
    ): string {
        // Escape the phonetic string for an XML attribute value
        $attribute = htmlspecialchars(
            $phoneticText,
            ENT_QUOTES | ENT_XML1,
            'UTF-8',
            false
        );

        // Escape surface text for XML without re-encoding existing entities
        $content = htmlspecialchars(
            $textValue,
            ENT_QUOTES | ENT_XML1,
            'UTF-8',
            false
        );

        // Wrap the surface text in an IPA phoneme inside a speak document
        return sprintf(
            '<speak><phoneme alphabet="ipa" ph="%s">%s</phoneme></speak>',
            $attribute,
            $content
        );
    }

    /**
     * JSON-encode a list of Text strings and/or PronounceableText maps.
     *
     * @param  array<int, array<string, mixed>|string>  $fragments  List of Text strings and/or PronounceableText maps
     * @return string JSON string (Text), JSON array of one `PronounceableText`, or empty string when
     *                no fragments are present
     */
    private function encodeFragments(
        array $fragments
    ): string {
        // Single plain Text fragment mirrors legacy string JSON output
        if (
            \count($fragments) === 1
            && \is_string($fragments[0])
        ) {
            try {
                // Encode the surface string without a wrapping object
                return json_encode(
                    $fragments[0],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
            } catch (\JsonException) {
                // Return empty string when encoding fails
                return '';
            }
        }

        // Single pronounceable object stays wrapped in a one-element array
        if (
            \count($fragments) === 1
            && \is_array($fragments[0])
        ) {
            try {
                // Encode a one-element array for schema property parsing
                return json_encode(
                    [
                        $fragments[0],
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
            } catch (\JsonException) {
                // Return empty string when encoding fails
                return '';
            }
        }

        // Multiple fragments encode as a mixed JSON array
        try {
            return json_encode(
                $fragments,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            // Return empty string when encoding fails
            return '';
        }
    }

    /**
     * Build JSON output from unwrapped tag parameters.
     *
     * @param  mixed  $textValueRaw  Unwrapped `text_value`
     * @param  mixed  $phoneticRaw  Unwrapped `phonetic_text`
     * @param  mixed  $inLanguageRaw  Unwrapped `in_language`
     * @param  mixed  $standardRaw  Unwrapped `language_standard`
     * @param  mixed  $markupRaw  Unwrapped `speech_to_text_markup`
     * @return string JSON string (Text), JSON array of one `PronounceableText`, or empty string when
     *                `text_value` is empty after trim and no phonetic text is present
     */
    private function computeOutput(
        mixed $textValueRaw,
        mixed $phoneticRaw,
        mixed $inLanguageRaw,
        mixed $standardRaw,
        mixed $markupRaw
    ): string {
        // ---
        // Read and normalize required text value
        // ---

        // Check if text_value is empty/null/false before normalization
        if (
            in_array($textValueRaw, ['', null, false], true)
        ) {
            // Return empty string when no raw text is provided
            return '';
        }

        // Coerce text_value to a trimmed string
        $textValue = \is_string($textValueRaw) ? trim($textValueRaw) : '';

        // Check if text_value is empty after normalization
        if ($textValue === '') {
            // Return empty string when no display text is provided
            return '';
        }

        // ---
        // Read optional phonetic text and language parameters
        // ---

        // Coerce phonetic_text to a trimmed string
        $phoneticText = \is_string($phoneticRaw) ? trim($phoneticRaw) : '';

        // Check if phonetic text is absent so we emit plain Text JSON only
        if ($phoneticText === '') {
            // Encode a single plain-text fragment like the pairs code path
            return $this->encodeFragments([$textValue]);
        }

        // Coerce in_language to a trimmed string
        $inLanguageCode = \is_string($inLanguageRaw) ? trim($inLanguageRaw) : '';

        // Coerce speech_to_text_markup to a trimmed string for the schema property
        $markupHint = \is_string($markupRaw) ? trim($markupRaw) : '';

        // ---
        // Resolve inLanguage via SchemaLanguage when a code is present
        // ---

        // Initialize inLanguage payload to null for omission when absent
        $inLanguage = null;

        // Check whether a non-empty language code was provided
        if ($inLanguageCode !== '') {
            // Build Language JSON-LD or Text fallback for the code
            $inLanguage = SchemaLanguage::jsonLdForSingleLanguageCode(
                $inLanguageCode,
                $standardRaw
            );
        }

        // ---
        // Build one PronounceableText map with phonetic fields
        // ---

        // Build the pronounceable fragments using the shared pair helper
        $fragments = $this->pairFragments(
            $textValue,
            $phoneticText,
            $inLanguage,
            $markupHint
        );

        // Encode the IPA fragment and any derived SSML sibling as JSON
        return $this->encodeFragments($fragments);
    }

    /**
     * Build the shared properties for a `PronounceableText` object.
     *
     * @param  string  $textValue  Surface text for `textValue`
     * @param  array<string, string>|string|null  $inLanguage  Language map, Text string, or null to omit
     * @return array<string, mixed> PronounceableText property map without markup fields
     */
    private function basePronounceableNode(
        string $textValue,
        array|string|null $inLanguage
    ): array {
        // Start the node with type and text value
        $node = [
            '@type' => 'PronounceableText',
            'textValue' => $textValue,
        ];

        // Check if inLanguage should be attached
        if (
            $inLanguage !== null
            && $inLanguage !== ''
        ) {
            // Attach resolved Language or Text fallback
            $node['inLanguage'] = $inLanguage;
        }

        // Return the base pronounceable map
        return $node;
    }

    /**
     * Read a tag parameter and unwrap Statamic Value instances.
     *
     * @param  string  $key  Parameter name
     * @return mixed Raw parameter value after optional Value unwrap
     */
    private function unwrapParam(
        string $key
    ): mixed {
        // Read the raw parameter value
        /** @var Parameters $params */
        $params = $this->params;
        $raw = $params->get($key);

        // Check if the parameter is wrapped as a Statamic Value
        if ($raw instanceof Value) {
            // Unwrap the Value for downstream string handling
            return $raw->value();
        }

        // Return the raw parameter when not wrapped
        return $raw;
    }
}
