<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use JoshDaugherty\IpaUnicodeInventory\TranscriptionValidator;

/**
 * Validates that a string uses only Unicode scalars allowed by the
 * joshdaugherty/ipa-unicode-inventory dataset (default **corpus_inclusive** profile).
 * Delegates to {@see TranscriptionValidator} (v1.5+): Wikimedia slash/bracket strip preset,
 * optional `normalization.json`, Wikimedia legacy ASCII stress/length, and optional
 * Google/TTS normalization — all in the package pipeline.
 *
 * Slash and square-bracket delimiters: when **disallowed**, `/` `[` `]` must not appear.
 * When **allowed**, {@see TranscriptionValidator::STRIP_DELIMITERS_WIKIMEDIA_SLASH_BRACKETS}
 * removes only those three scalars before normalization (Wikimedia `$stripRegex` parity).
 *
 * Final allowlist checks use **scalar** segmentation by default (same as prior app behavior).
 * {@see TranscriptionValidator::SEGMENT_GRAPHEME_CLUSTER} is available from the package when
 * `ext-intl` is loaded; this rule does not enable it yet.
 *
 * Empty values are skipped so `required` can enforce presence.
 *
 * String rule parameters are comma-separated in this order (trailing segments may be
 * omitted for defaults). Use `0`/`false` or `1`/`true` for each flag.
 * 1. `allowDelimiters`
 * 2. `normalize`
 * 3. `googleTtsNormalization` (only valid when `normalize` is true)
 *
 * @see https://github.com/joshdaugherty/ipa-unicode-inventory/releases/tag/v1.5.0
 * @see https://packagist.org/packages/joshdaugherty/ipa-unicode-inventory
 */
class InternationalPhoneticAlphabet extends BaseValidationRule implements ValidatorAwareRule
{
    /**
     * Pattern for IPA slashes and square brackets when `allowDelimiters` is false.
     */
    private const string SLASH_BRACKET_DISALLOWED_PATTERN = '/[\/\[\]]/u';

    /**
     * Cached validators keyed by delimiter, normalize, Google/TTS, and segmentation mode.
     *
     * @var array<string, TranscriptionValidator>
     */
    private static array $transcriptionValidators = [];

    /**
     * Whether delimiter characters may appear in the value.
     */
    private readonly bool $allowDelimiters;

    /**
     * Whether to normalize before matching.
     */
    private readonly bool $normalize;

    /**
     * Whether to apply Google TTS normalization (requires normalize).
     */
    private readonly bool $googleTtsNormalization;

    /**
     * Validator instance set by Laravel when the rule runs (used for human-readable
     * :attribute replacement on nested keys such as last_name.phonetic_text).
     */
    private ?Validator $validator = null;

    /**
     * Create a new rule instance.
     *
     * @param  bool|int|string|null  $allowDelimiters  When true, `/`, `[`, and `]` may appear and are removed before matching.
     * @param  bool|int|string|null  $normalize  When true, apply package + legacy ASCII IPA normalization before matching.
     * @param  bool|int|string|null  $googleTtsNormalization  When true, use Google TTS normalization (requires normalize).
     */
    public function __construct(
        bool|int|string|null $allowDelimiters = false,
        bool|int|string|null $normalize = false,
        bool|int|string|null $googleTtsNormalization = false
    ) {
        // Coerce arguments so Laravel comma-separated rule parameters work
        $this->allowDelimiters = $this->coerceToBool($allowDelimiters);
        $this->normalize = $this->coerceToBool($normalize);
        $this->googleTtsNormalization = $this->coerceToBool($googleTtsNormalization);

        // Check whether Google TTS mode was requested without normalization
        if ($this->googleTtsNormalization && ! $this->normalize) {
            throw new InvalidArgumentException(
                'InternationalPhoneticAlphabet: Google TTS normalization requires normalize to be true.'
            );
        }
    }

    /**
     * Receive the validator so {@see getDisplayableAttribute()} can resolve labels.
     *
     * @param  Validator  $validator  The active Laravel validator
     * @return $this
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Resolve the user-facing attribute name (matches Statamic nested field labels).
     *
     * @param  string  $attribute  Raw rule attribute key (e.g. last_name.phonetic_text)
     * @return string Label for validation messages
     */
    private function displayableAttribute(string $attribute): string
    {
        return $this->validator instanceof Validator
            ? $this->validator->getDisplayableAttribute($attribute)
            : $attribute;
    }

    /**
     * Coerce a validation rule argument to bool (Laravel passes strings from the rule
     * string).
     *
     * @param  bool|int|string|null  $value  Raw constructor or rule parameter
     * @return bool Coerced boolean
     */
    private function coerceToBool(
        bool|int|string|null $value
    ): bool {
        // Return booleans unchanged
        if (\is_bool($value)) {
            return $value;
        }

        // Treat null as false
        if ($value === null) {
            return false;
        }

        // Treat non-zero integers as true
        if (\is_int($value)) {
            return $value !== 0;
        }

        // Trim string input for comparison
        $trimmed = trim($value);

        // Treat empty string as false
        if ($trimmed === '') {
            return false;
        }

        // Parse common boolean string forms from validation rule parameters
        $parsed = filter_var(
            $trimmed,
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        );

        // Return parsed boolean when filter recognized the value
        if ($parsed !== null) {
            return $parsed;
        }

        // Fall back to false for unrecognized strings
        return false;
    }

    /**
     * Validate the non-empty string value against the IPA Unicode inventory.
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
        // Check whether IPA slashes or brackets appear when delimiters are disallowed
        if (
            ! $this->allowDelimiters
            && preg_match(
                self::SLASH_BRACKET_DISALLOWED_PATTERN,
                $value
            ) === 1
        ) {
            $fail(
                __(
                    'statamic::validation.international_phonetic_alphabet.delimiters_disallowed',
                    [
                        'attribute' => $this->displayableAttribute($attribute),
                    ]
                )
            );

            return;
        }

        // Delegate full pipeline to the package (scalar segmentation; see class docblock)
        if (! $this->transcriptionValidator($this->allowDelimiters, $this->normalize, $this->googleTtsNormalization)->isValid($value)) {
            $fail(
                __(
                    'statamic::validation.international_phonetic_alphabet.invalid',
                    [
                        'attribute' => $this->displayableAttribute($attribute),
                    ]
                )
            );
        }
    }

    /**
     * Return a cached {@see TranscriptionValidator} for the rule’s option tuple.
     */
    private function transcriptionValidator(
        bool $allowDelimiters,
        bool $normalize,
        bool $googleTtsNormalization
    ): TranscriptionValidator {
        $key = ($allowDelimiters ? '1' : '0')
            .($normalize ? '1' : '0')
            .($googleTtsNormalization ? '1' : '0')
            .'.'.TranscriptionValidator::SEGMENT_SCALARS;

        if (! isset(self::$transcriptionValidators[$key])) {
            self::$transcriptionValidators[$key] = TranscriptionValidator::fromDisk(
                null,
                null,
                $allowDelimiters
                    ? TranscriptionValidator::STRIP_DELIMITERS_WIKIMEDIA_SLASH_BRACKETS
                    : TranscriptionValidator::STRIP_DELIMITERS_NONE,
                null,
                $normalize,
                $normalize,
                $normalize && $googleTtsNormalization,
                TranscriptionValidator::SEGMENT_SCALARS,
                false
            );
        }

        return self::$transcriptionValidators[$key];
    }
}
