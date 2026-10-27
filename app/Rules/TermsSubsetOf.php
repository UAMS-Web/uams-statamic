<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

/**
 * Validates that taxonomy term selections on the attribute are a subset of those
 * on another entry field (same payload root), identified by handle.
 *
 * Used as `terms_subset_of:work_location` on nested fields whose allowed
 * terms must come from a parent multi-select (e.g. role-specific locations vs
 * general work locations).
 */
class TermsSubsetOf implements ValidationRule, ValidatorAwareRule
{
    /**
     * Entry/root field handle that defines the allowed term set.
     */
    private readonly string $parentFieldHandle;

    /**
     * Active Laravel validator (for display names and full entry data).
     */
    private ?Validator $validator = null;

    /**
     * Create a new rule instance.
     *
     * @param  string  $parentFieldHandle  Blueprint field handle for the superset field
     */
    public function __construct(
        string $parentFieldHandle
    ) {
        $trimmed = trim($parentFieldHandle);

        if ($trimmed === '') {
            throw new \InvalidArgumentException(
                'TermsSubsetOf requires a non-empty parent field handle.'
            );
        }

        $this->parentFieldHandle = $trimmed;
    }

    /**
     * Receive the validator instance from Laravel.
     *
     * @param  Validator  $validator  Current validator
     */
    public function setValidator(
        Validator $validator
    ): static {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  Attribute being validated
     * @param  mixed  $value  Field value (term id strings or array of them)
     * @param  Closure  $fail  Failure callback
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        $childStrings = $this->extractTermStrings($value);

        if ($childStrings === []) {
            return;
        }

        $validator = $this->validator;

        if (! $validator instanceof Validator) {
            throw new \LogicException(
                'TermsSubsetOf requires ValidatorAwareRule; validator was not set.'
            );
        }

        $parentRaw = $validator->getData()[$this->parentFieldHandle] ?? null;
        $parentStrings = $this->extractTermStrings($parentRaw);

        $taxonomyHint = $this->inferTaxonomyHint(
            array_merge(
                $parentStrings,
                $childStrings
            )
        );

        $parentNormalized = $this->normalizeTermSet(
            $parentStrings,
            $taxonomyHint
        );
        $childNormalized = $this->normalizeTermSet(
            $childStrings,
            $taxonomyHint
        );

        $parentLabel = $validator->getDisplayableAttribute($this->parentFieldHandle);
        $attributeLabel = $validator->getDisplayableAttribute($attribute);

        if ($parentNormalized === []) {
            $fail(
                __(
                    'statamic::validation.terms_subset_of.parent_empty',
                    [
                        'parent' => $parentLabel,
                    ]
                )
            );

            return;
        }

        foreach ($childNormalized as $termId) {
            if (! \in_array($termId, $parentNormalized, true)) {
                $fail(
                    __(
                        'statamic::validation.terms_subset_of.not_subset',
                        [
                            'attribute' => $attributeLabel,
                            'parent' => $parentLabel,
                        ]
                    )
                );

                return;
            }
        }
    }

    /**
     * Flatten a terms field value to a list of raw strings (slug or taxonomy::slug).
     *
     * @return array<int, string>
     */
    private function extractTermStrings(
        mixed $value
    ): array {
        if (
            $value === null
            || $value === ''
        ) {
            return [];
        }

        if (\is_array($value)) {
            $out = [];

            foreach ($value as $item) {
                if ($item === null) {
                    continue;
                }

                if ($item === '') {
                    continue;
                }

                if (\is_string($item)) {
                    $out[] = $item;
                } elseif (\is_scalar($item)) {
                    $out[] = (string) $item;
                }
            }

            return $out;
        }

        if (\is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === ''
                ? []
                : [$value];
        }

        return [];
    }

    /**
     * Infer taxonomy handle from the first value that uses the `taxonomy::slug` form.
     *
     * @param  array<int, string>  $strings
     */
    private function inferTaxonomyHint(
        array $strings
    ): ?string {
        foreach ($strings as $s) {
            if (
                $s !== ''
                && str_contains(
                    $s,
                    '::'
                )
            ) {
                return explode(
                    '::',
                    $s,
                    2
                )[0];
            }
        }

        return null;
    }

    /**
     * Normalize term references to a canonical string for set comparison.
     *
     * @param  array<int, string>  $strings
     * @return array<int, string>
     */
    private function normalizeTermSet(
        array $strings,
        ?string $taxonomyHint
    ): array {
        $set = [];

        foreach ($strings as $raw) {
            $canonical = $this->normalizeOneTerm(
                $raw,
                $taxonomyHint
            );

            if ($canonical !== '') {
                $set[] = $canonical;
            }
        }

        return array_values(array_unique($set));
    }

    /**
     * Normalize a single term reference.
     */
    private function normalizeOneTerm(
        string $raw,
        ?string $taxonomyHint
    ): string {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return '';
        }

        if (str_contains($trimmed, '::')) {
            return $trimmed;
        }

        if ($taxonomyHint !== null) {
            return $taxonomyHint.'::'.$trimmed;
        }

        return $trimmed;
    }
}
