<?php

declare(strict_types=1);

namespace App\Rules;

use App\Scopes\SpecialtyTaxonomyTermType as SpecialtyTaxonomyTermTypeScope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Validates taxonomy term ids against `specialty_subspecialty_type`, taxonomy set,
 * and optional `medical_query`, matching App\Scopes\SpecialtyTaxonomyTermType query
 * scope aliases (e.g., `specialty`, `subspecialty_acgme`, `medical_research_specialty`).
 *
 * Pass the same tokens as in field `query_scopes`, or comma-separated research forms:
 *   - `specialty_taxonomy_term_type:specialty`
 *   - `specialty_taxonomy_term_type:specialty_type_only` (type only, any taxonomy)
 *   - `specialty_taxonomy_term_type:specialty_acgme`
 *   - `specialty_taxonomy_term_type:subspecialty_non_accredited`
 *   - `specialty_taxonomy_term_type:medical_research_specialty`
 *   - `specialty_taxonomy_term_type:medical_specialty` (shorthand)
 *   - `specialty_taxonomy_term_type:specialty,medical`
 *   - `specialty_taxonomy_term_type:subspecialty,non-medical`
 */
class SpecialtyTaxonomyTermType implements ValidationRule
{
    /**
     * Parsed expectation from the rule parameters, or null when unknown.
     *
     * @var array{type: string, taxonomies: array<int, string>, medical_query: bool|null}|null
     */
    private ?array $expectation;

    /**
     * Raw rule parameters as a single display string (for error messages).
     */
    private readonly string $ruleParameterDisplay;

    /**
     * Create a rule bound to scope alias tokens (same as `query_scopes`) or comma forms.
     *
     * @param  string  ...$arguments  One scope/shorthand alias, or two tokens such as `specialty`, `medical`.
     */
    public function __construct(
        string ...$arguments
    ) {
        // Remember parameters for invalid-config messages
        $this->ruleParameterDisplay = implode(
            ',',
            $arguments
        );

        // Resolve using the same parser as validation comma-parameters and scope aliases
        $this->expectation = SpecialtyTaxonomyTermTypeScope::expectationFromRuleArguments(
            ...$arguments
        );
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  The name of the attribute being validated.
     * @param  mixed  $value  Term id or list of term ids (terms field shapes).
     * @param  Closure  $fail  Adds a validation error message.
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Check whether the rule parameter parsed to a supported expectation
        if ($this->expectation === null) {
            // Fail fast when the rule parameters are not encodable
            $fail(__(
                'statamic::validation.specialty_taxonomy_term_type.invalid_rule_config',
                [
                    'alias' => $this->ruleParameterDisplay,
                ]
            ));

            return;
        }

        // Normalize the field value to a list of string term identifiers
        $ids = $this->normalizeTermIds($value);

        // Skip further checks when there is nothing to validate
        if ($ids === []) {
            return;
        }

        // Validate each selected term against taxonomy and blueprint fields
        foreach ($ids as $id) {
            // Load the term by Statamic id (e.g. `specialties_acgme::slug`)
            $term = Term::find($id);

            // Check whether the term exists in the repository
            if ($term === null) {
                // Record a not-found error for ids the repository cannot resolve
                $fail(__(
                    'statamic::validation.specialty_taxonomy_term_type.not_found',
                    [
                        'id' => $id,
                    ]
                ));

                return;
            }

            // Narrow the repository result to `LocalizedTerm` or `Term` for analysis
            \assert(
                $term instanceof LocalizedTerm
                || $term instanceof TaxonomyTerm,
                'Term::find must return a LocalizedTerm or Term instance.'
            );

            // Resolve the taxonomy handle once for comparison and error text
            /** @var string $taxonomyHandle */
            $taxonomyHandle = $term->taxonomyHandle();

            // Check whether the term taxonomy matches this rule variant
            if (! $this->taxonomyMatchesExpectation($taxonomyHandle)) {
                // Record a wrong-taxonomy error with the resolved handle for context
                $fail(__(
                    'statamic::validation.specialty_taxonomy_term_type.wrong_taxonomy',
                    [
                        'id' => $id,
                        'taxonomy' => $taxonomyHandle,
                    ]
                ));

                return;
            }

            // Read the blueprint specialty/subspecialty field from term data
            /** @var LocalizedTerm $term */
            /** @var bool|float|int|string|null $typeRaw */
            $typeRaw = $term->get(
                SpecialtyTaxonomyTermTypeScope::TYPE_FIELD
            );

            // Check whether the stored type matches the expected scope type
            if ($typeRaw !== $this->expectation['type']) {
                // Record a type mismatch against the configured scope alias
                $fail(__(
                    'statamic::validation.specialty_taxonomy_term_type.wrong_type',
                    [
                        'id' => $id,
                        'type' => \is_string($typeRaw) ? $typeRaw : (string) $typeRaw,
                        'expected' => $this->expectation['type'],
                    ]
                ));

                return;
            }

            // Research aliases require matching `medical_query` on the term
            if ($this->expectation['medical_query'] !== null) {
                /** @var LocalizedTerm $term */
                $medicalRaw = $term->get(
                    SpecialtyTaxonomyTermTypeScope::MEDICAL_QUERY_FIELD
                );
                $actualBool = $this->boolFromTermValue($medicalRaw);

                if (
                    $actualBool === null
                    || $actualBool !== $this->expectation['medical_query']
                ) {
                    $fail(__(
                        'statamic::validation.specialty_taxonomy_term_type.wrong_medical_query',
                        [
                            'id' => $id,
                            'negation' => $this->expectation['medical_query'] ? '' : 'not ',
                            'actual' => $this->medicalQueryDisplay($medicalRaw),
                        ]
                    ));

                    return;
                }
            }
        }
    }

    /**
     * Compare a term taxonomy handle to the rule’s expected taxonomy set.
     *
     * @param  string  $taxonomyHandle  Handle from the loaded term.
     */
    private function taxonomyMatchesExpectation(
        string $taxonomyHandle
    ): bool {
        // Only reachable from validate() after the null-expectation early return
        /** @var array{type: string, taxonomies: array<int, string>, medical_query: bool|null} $expectation */
        $expectation = $this->expectation;

        // An empty taxonomy set (a `*_type_only` alias) imposes no taxonomy
        // constraint — the field's own `taxonomies` is the sole restriction — so any
        // handle is accepted here and only `specialty_subspecialty_type` is enforced.
        if ($expectation['taxonomies'] === []) {
            return true;
        }

        return \in_array(
            $taxonomyHandle,
            $expectation['taxonomies'],
            true
        );
    }

    /**
     * Normalize stored term values to bool when possible.
     */
    private function boolFromTermValue(mixed $raw): ?bool
    {
        if (\is_bool($raw)) {
            return $raw;
        }

        if (in_array($raw, ['true', '1', 1], true)) {
            return true;
        }

        if (in_array($raw, ['false', '0', 0], true)) {
            return false;
        }

        return null;
    }

    /**
     * Human-readable medical_query value for validation messages.
     */
    private function medicalQueryDisplay(mixed $raw): string
    {
        $bool = $this->boolFromTermValue($raw);

        if ($bool !== null) {
            return $bool ? 'true' : 'false';
        }

        if ($raw === null || $raw === '') {
            return 'empty';
        }

        return \is_scalar($raw) ? (string) $raw : 'invalid';
    }

    /**
     * Build a list of non-empty string term ids from the field value.
     *
     * @param  mixed  $value  Raw field value (string, list of strings, etc.).
     * @return array<int, string> Term ids to validate.
     */
    private function normalizeTermIds(mixed $value): array
    {
        // Check if the field submitted a list of term identifiers
        if (\is_array($value)) {
            // Initialize the normalized id list for array input
            $out = [];
            // Walk each array entry and collect usable string ids
            foreach ($value as $item) {
                // Skip entries that are not a non-empty string handle
                if (! \is_string($item)) {
                    continue;
                }

                if ($item === '') {
                    continue;
                }

                // Append a normalized string id to the output list
                $out[] = $item;
            }

            // Return all string ids extracted from the array
            return $out;
        }

        // Check if the field submitted a single non-empty string id
        if (
            \is_string($value)
            && $value !== ''
        ) {
            // Return a one-element list for scalar string input
            return [
                $value,
            ];
        }

        // Return an empty list when the value is not a usable string or list
        return [];
    }
}
