<?php

declare(strict_types=1);

namespace App\Rules\BaseRules;

use App\Scopes\ProfessionalAndLeadershipDevelopmentActivitiesTermType as ActivitiesScope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Validates taxonomy term IDs against the
 * `professional_and_leadership_development_activities` taxonomy and the
 * blueprint `type` field, matching the
 * `ProfessionalAndLeadershipDevelopmentActivitiesTermType` query scope handles.
 */
abstract class AbstractProfessionalAndLeadershipDevelopmentActivitiesTerm implements ValidationRule
{
    /**
     * Translation key prefix under `statamic::validation` for this rule.
     *
     * @return string Prefix such as `professional_development_activity_term`.
     */
    abstract protected function translationKeyPrefix(): string;

    /**
     * Expected stored value for the term `type` field.
     *
     * @return string One of the scope `TYPE_VALUE_*` constants.
     */
    abstract protected function expectedTypeValue(): string;

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

        // Skip further checks when there is nothing to validate. Equivalent: the
        // loop below does nothing for an empty list, so the early return only says so.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($ids === []) {
            return;
        }

        // Validate each selected term against taxonomy and blueprint type
        foreach ($ids as $id) {
            // Load the term by Statamic id (e.g. taxonomy::slug)
            $term = Term::find($id);

            // Check whether the term exists in the repository
            if ($term === null) {
                $fail(__(
                    'statamic::validation.'.$this->translationKeyPrefix().'.not_found',
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

            // Check whether the term belongs to the activities taxonomy
            if ($taxonomyHandle !== ActivitiesScope::TAXONOMY) {
                $fail(__(
                    'statamic::validation.'.$this->translationKeyPrefix().'.wrong_taxonomy',
                    [
                        'id' => $id,
                        'taxonomy' => $taxonomyHandle,
                    ]
                ));

                return;
            }

            // Narrow to the localized term that exposes data accessors
            /** @var LocalizedTerm $term */

            // Read the blueprint type field from term data
            $typeRaw = $term->get(
                ActivitiesScope::TYPE_FIELD
            );

            // Check whether the stored type matches this rule variant
            if ($typeRaw !== $this->expectedTypeValue()) {
                $fail(__(
                    'statamic::validation.'.$this->translationKeyPrefix().'.wrong_type',
                    [
                        'id' => $id,
                        // The translator stringifies scalar replacements itself
                        'type' => \is_scalar($typeRaw) ? $typeRaw : '',
                        'expected' => $this->expectedTypeValue(),
                    ]
                ));

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
