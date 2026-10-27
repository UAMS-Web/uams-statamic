<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

/**
 * Validates that every UAMS Health Epic Department (DEP) ID on the attribute is
 * drawn from the location's declared `epic_dep_ids` list (a sibling field on the
 * same payload root, identified by handle).
 *
 * Both the attribute value and the parent field are the shared
 * `uamsweb_common_fad.epic_dep_ids` replicator — rows of an integer `dep`
 * subfield — so the rule extracts each row's `dep` value before comparing. Used
 * as `dep_in_declared_list:epic_dep_ids` on each MyChart scheduling widget's
 * `dep` field within the `mychart_scheduling_options` replicator, so a widget
 * cannot reference a DEP ID the location has not declared (a typo or stale copy).
 *
 * Modeled on {@see TermsSubsetOf}: it implements ValidatorAwareRule to read the
 * sibling field via `$validator->getData()`, which returns the full entry data
 * (top-level handles) even when this rule runs on a nested replicator subfield.
 */
class DepInDeclaredList implements ValidationRule, ValidatorAwareRule
{
    /**
     * Root field handle that declares the canonical DEP ID list.
     */
    private readonly string $parentFieldHandle;

    /**
     * Active Laravel validator (for display names and full entry data).
     */
    private ?Validator $validator = null;

    /**
     * Create a new rule instance.
     *
     * @param  string  $parentFieldHandle  Blueprint field handle for the declared-list field
     */
    public function __construct(
        string $parentFieldHandle
    ) {
        $trimmed = trim($parentFieldHandle);

        if ($trimmed === '') {
            throw new \InvalidArgumentException(
                'DepInDeclaredList requires a non-empty parent field handle.'
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
     * @param  string  $attribute  Attribute being validated (the widget `dep` replicator)
     * @param  mixed  $value  Field value (rows of `['dep' => '…']`)
     * @param  Closure  $fail  Failure callback
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        $widgetDeps = $this->extractDepValues($value);

        // Nothing selected on the widget — presence is the `required` rule's job.
        if ($widgetDeps === []) {
            return;
        }

        $validator = $this->validator;

        if (! $validator instanceof Validator) {
            throw new \LogicException(
                'DepInDeclaredList requires ValidatorAwareRule; validator was not set.'
            );
        }

        $declaredDeps = $this->extractDepValues(
            $validator->getData()[$this->parentFieldHandle] ?? null
        );

        // The canonical list must be declared before a widget can reference it.
        if ($declaredDeps === []) {
            $fail(__('statamic::validation.dep_in_declared_list.parent_empty'));

            return;
        }

        foreach ($widgetDeps as $dep) {
            if (! \in_array($dep, $declaredDeps, true)) {
                $fail(
                    __(
                        'statamic::validation.dep_in_declared_list.not_declared',
                        [
                            'dep' => $dep,
                        ]
                    )
                );

                return;
            }
        }
    }

    /**
     * Flatten an `epic_dep_ids` replicator value to a list of its `dep` row
     * values as trimmed strings, dropping rows whose `dep` is empty,
     * whitespace-only, or non-scalar. A non-array value — which the
     * `epic_dep_ids` replicator fieldtype never produces — yields an empty list.
     *
     * @return array<int, string>
     */
    private function extractDepValues(
        mixed $value
    ): array {
        if (! \is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $row) {
            // Replicator rows are associative (`['type' => …, 'dep' => '123']`);
            // fall back to a bare scalar for defensiveness.
            $dep = \is_array($row)
                ? ($row['dep'] ?? null)
                : $row;

            if (! \is_scalar($dep)) {
                continue;
            }

            // Normalize to a trimmed string; empty / whitespace-only rows are
            // dropped so they defer to the `required` rule (like an unset DEP).
            $dep = trim((string) $dep);

            if ($dep !== '') {
                $out[] = $dep;
            }
        }

        return $out;
    }
}
