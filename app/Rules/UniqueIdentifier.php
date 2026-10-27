<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Identifiers\ResolvesIdentifierValues;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * Validates that a single-value identifier is unique across a collection, and
 * across every field sharing its uniqueness namespace (UAMS-Web/uams-statamic-archive#2152).
 *
 * Used as `unique_identifier:{collection},{id},<handle>[,<handle>...]` on the
 * field itself. The trailing handles are the **pool**: the set of fields whose
 * values share one namespace. A pool of one is the ordinary case -- `npi`
 * against other `npi` values. A pool of several is the Workday case, where
 * `workday_universal_id`, `workday_employee_id`, `workday_contingent_worker_id`
 * and `workday_student_id` are issued from four different id spaces, so a value
 * appearing in two of them means one was typed into the wrong box. That is the
 * error a per-field check passes, which is why the pool is the unit rather than
 * the field.
 *
 * Two collisions are reported, and the second is the one a same-named check
 * cannot see:
 *
 *   - **Across entries** -- the value is held by another entry in the
 *     collection, in any pooled field.
 *   - **Within this entry** -- the value is also typed into another of this
 *     entry's own pooled fields. Reported only when the pool has more than one
 *     member, since a pool of one cannot collide with itself.
 *
 * Blank never collides with blank: a value that normalizes to nothing is absent
 * rather than a value, so any number of entries may leave an identifier empty.
 * Comparison is case-insensitive with surrounding whitespace trimmed, per the
 * decision recorded on UAMS-Web/uams-statamic-archive#2221 and stated in the field instructions.
 *
 * On a CREATE Statamic has no id to substitute for `{id}` and the parameter
 * arrives as the literal string `NULL`, which the trait reads as "exclude
 * nothing" -- correct, because on a create there is no self to collide with.
 *
 * WHAT THIS RULE DOES NOT COVER, because the docblock would otherwise read as a
 * general guarantee: blueprint validation runs only in the Control Panel, and
 * the WordPress importer saves entries directly, so this rule NEVER sees
 * imported content. Duplicates arriving by import are UAMS-Web/uams-statamic-archive#2395's audit command to
 * find, and the two cover disjoint populations rather than overlapping ones --
 * which makes the audit the load-bearing half rather than a deferred extra.
 *
 * Statamic's own `Statamic\Rules\UniqueEntryValue` is deliberately not reused.
 * It compares one field against the same-named field, case-sensitively and
 * without trimming, treats a blank as a value, and fails with a message naming
 * neither the value nor the conflicting entry -- so it satisfies none of the
 * pooled, blank-handling, comparison or message requirements here.
 */
class UniqueIdentifier implements ValidationRule, ValidatorAwareRule
{
    use ResolvesIdentifierValues;

    /**
     * Collection handle scanned for collisions.
     */
    private readonly string $collection;

    /**
     * Entry id excluded from the scan, or null on a create.
     */
    private readonly ?string $exceptId;

    /**
     * Field handles sharing one uniqueness namespace.
     *
     * @var list<string>
     */
    private readonly array $pool;

    /**
     * Active Laravel validator, for reading this entry's other pooled fields.
     */
    private ?Validator $validator = null;

    /**
     * Create a new rule instance.
     *
     * @param  string  $collection  Collection handle to scan
     * @param  string|null  $except  Entry id to exclude, or the `NULL` placeholder on a create
     * @param  string  ...$pool  Field handles sharing one uniqueness namespace
     */
    public function __construct(
        string $collection,
        ?string $except = null,
        string ...$pool
    ) {
        $trimmedCollection = trim($collection);

        if ($trimmedCollection === '') {
            throw new \InvalidArgumentException(
                'UniqueIdentifier requires a non-empty collection handle.'
            );
        }

        $handles = array_values(array_filter(array_map(trim(...), $pool), static fn (string $h): bool => $h !== ''));

        if ($handles === []) {
            throw new \InvalidArgumentException(
                'UniqueIdentifier requires at least one field handle in the uniqueness pool.'
            );
        }

        $this->collection = $trimmedCollection;
        $this->exceptId = $this->exceptEntryId($except);
        $this->pool = $handles;
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
     * @param  string  $attribute  Handle of the field being validated
     * @param  mixed  $value  The submitted identifier
     * @param  Closure  $fail  Failure callback
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Reduce the submitted value to its comparison and display forms
        $forms = $this->identifierForms($value);

        // A blank identifier is absent and collides with nothing
        if ($forms === null) {
            // Nothing to check
            return;
        }

        $candidate = $forms['compare'];

        // Check this entry's own other pooled fields before scanning the store
        if ($this->failsWithinThisEntry($attribute, $candidate, $forms['display'], $fail)) {
            // A within-entry collision is reported on its own
            return;
        }

        // Compare against every other entry in the collection
        foreach ($this->otherEntriesIn($this->collection, $this->exceptId) as $entry) {
            // Check each field in the uniqueness pool on that entry
            foreach ($this->pool as $handle) {
                // Read that entry's comparable values for this field
                $held = $this->identifierValuesOn($entry, $handle, null);

                // Report the first collision found, naming both fields and the entry
                if (isset($held[$candidate])) {
                    // Fail with the detail an editor needs to resolve it
                    $fail(__('statamic::validation.unique_identifier.collision', [
                        'value' => $forms['display'],
                        'other' => $handle,
                        'entry' => $this->identifierEntryLabel($entry),
                    ]));

                    return;
                }
            }
        }
    }

    /**
     * Whether the value is already typed into another of this entry's own
     * pooled fields, reporting it if so.
     *
     * A pool of one cannot collide with itself, so this is a no-op there. The
     * attribute under validation is skipped for the obvious reason, and so is
     * any field the validator cannot see.
     *
     * @param  string  $attribute  Handle of the field being validated
     * @param  string  $candidate  Comparison form of the submitted value
     * @param  string  $display  Submitted value as typed, for the message
     * @param  Closure  $fail  Failure callback
     * @return bool Whether a collision was reported
     */
    private function failsWithinThisEntry(
        string $attribute,
        string $candidate,
        string $display,
        Closure $fail
    ): bool {
        // A single-field pool has no sibling to collide with
        if (\count($this->pool) < 2) {
            // Report no collision
            return false;
        }

        $validator = $this->validator;

        // The sibling values are only readable through the validator
        if (! $validator instanceof Validator) {
            throw new \LogicException(
                'UniqueIdentifier requires ValidatorAwareRule; validator was not set.'
            );
        }

        // Read the whole submitted payload, which carries the sibling fields
        $data = $validator->getData();

        // Compare against each other field in the pool
        foreach ($this->pool as $handle) {
            // The field being validated is not its own sibling
            if ($handle === $attribute) {
                // Move to the next pooled field
                continue;
            }

            // Reduce the sibling's submitted value to its comparison form,
            // resolving a dotted path so a grouped field is found where it lives
            $sibling = $this->normalizeIdentifier(Arr::get($data, $handle));

            // Report a collision when the two share one value
            if ($sibling === $candidate) {
                // Fail naming both fields and the value
                $fail(__('statamic::validation.unique_identifier.within_entry', [
                    'value' => $display,
                    'other' => $handle,
                ]));

                return true;
            }
        }

        // Report no collision
        return false;
    }
}
