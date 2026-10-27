<?php

declare(strict_types=1);

namespace App\Rules\BaseRules;

use App\Scopes\BoardCertifications;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Validates taxonomy term IDs against the `certifications` taxonomy and whether
 * the term is a board certification, matching the `BoardCertifications` query
 * scope (`board_certifications` vs `other_certifications`).
 *
 * A term is a board certification when `board_certification_query` is strictly
 * `true` and its `credential_kind` is not a focused practice designation or an
 * assessment-based certificate (`BoardCertifications::isBoardCertification()`).
 * The board variant requires a board certification; the other variant requires
 * any other certifications term.
 *
 * `Term::find()` commonly returns `LocalizedTerm` (not only `Term`), so this
 * rule must not require the concrete `Statamic\Taxonomies\Term` type.
 */
abstract class AbstractCertificationTermBoardQueryRule implements ValidationRule
{
    /**
     * Translation key prefix under `statamic::validation` for this rule.
     *
     * @return string Prefix such as `board_certification_term`.
     */
    abstract protected function translationKeyPrefix(): string;

    /**
     * Whether the term must be a board certification.
     *
     * @return bool True when this rule requires a board certification; false
     *              when it requires any other certifications term (other alias).
     */
    abstract protected function requiresBoardCertificationQueryTrue(): bool;

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
        // Normalize the field value to a list of string term identifiers
        $ids = $this->normalizeTermIds($value);

        // Validate each selected term against taxonomy and board-query flag
        foreach ($ids as $id) {
            // Load the term by Statamic id (e.g. `certifications::slug`)
            $term = Term::find($id);

            // Check whether the term exists in the repository
            if ($term === null) {
                // Record a not-found error for ids the repository cannot resolve
                $fail(__(
                    'statamic::validation.'.$this->translationKeyPrefix().'.not_found',
                    [
                        'id' => $id,
                    ]
                ));

                // Stop validating further ids after a missing term
                return;
            }

            // Narrow the repository result to `LocalizedTerm` or `Term`
            \assert(
                $term instanceof LocalizedTerm
                || $term instanceof TaxonomyTerm,
                'Term::find must return a LocalizedTerm or Term instance.'
            );

            // Resolve the taxonomy handle once for comparison and error text
            /** @var string|null $taxonomyHandle Taxonomy handle owning the term. */
            $taxonomyHandle = $term->taxonomyHandle();

            // Check whether the term belongs to the certifications taxonomy
            if ($taxonomyHandle !== BoardCertifications::CERTIFICATIONS_TAXONOMY) {
                // Record a wrong-taxonomy error with the resolved handle for context
                $fail(__(
                    'statamic::validation.'.$this->translationKeyPrefix().'.wrong_taxonomy',
                    [
                        'id' => $id,
                        'taxonomy' => $taxonomyHandle,
                    ]
                ));

                // Stop validating further ids after a taxonomy mismatch
                return;
            }

            // Decide from the flag and the kind whether the term is a board certification
            /** @var LocalizedTerm $term `Term::find` resolves to a LocalizedTerm exposing `get`. */
            $isBoardCertification = BoardCertifications::isBoardCertification(
                $term->get(BoardCertifications::BOARD_CERTIFICATION_QUERY_FIELD),
                $term->get(BoardCertifications::CREDENTIAL_KIND_FIELD)
            );

            // Check whether the term is the kind of certification this rule enforces
            if ($isBoardCertification !== $this->requiresBoardCertificationQueryTrue()) {
                // Record a board-query mismatch against this rule variant
                $fail(__(
                    'statamic::validation.'.$this->translationKeyPrefix().'.wrong_query_flag'
                ));

                // Stop validating further ids after a flag mismatch
                return;
            }
        }
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
