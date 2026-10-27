<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Identifiers\ResolvesIdentifierValues;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that every identifier in a replicator list is unique -- both within
 * the list itself and across the collection (UAMS-Web/uams-statamic-archive#2152).
 *
 * Used as `unique_identifier_list:{collection},{id},<row_key>` on the
 * replicator field. `<row_key>` is the handle of the subfield each row carries
 * the identifier in: `item` for a location's `npi` rows, `dep` for its
 * `epic_dep_ids` rows.
 *
 * Two collisions are reported, and the within-list one is the case a
 * cross-entry check alone never sees:
 *
 *   - **Within the list** -- the same identifier is entered on two rows of this
 *     entry. Reported first, because it is the one an editor can fix without
 *     knowing anything about other entries.
 *   - **Across entries** -- a row's identifier is already held by another entry
 *     in the collection.
 *
 * Blank rows never collide with each other, and a row that normalizes to
 * nothing is skipped rather than compared -- presence is the `required` rule's
 * job, exactly as in the sibling `DepInDeclaredList`. Comparison is
 * case-insensitive with surrounding whitespace trimmed, per the decision
 * recorded on UAMS-Web/uams-statamic-archive#2221.
 *
 * Deliberately NOT applied to the MyChart scheduling widget's `dep` field. That
 * field is already validated `dep_in_declared_list:epic_dep_ids`, which
 * *requires* it to repeat values from the location's declared list -- so a
 * uniqueness rule there would reject correct data on every location using the
 * widget. It is the case a broad rule breaks first, and UAMS-Web/uams-statamic-archive#2152 carries a
 * regression guard for it.
 */
class UniqueIdentifierList implements ValidationRule
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
     * Replicator row subfield carrying the identifier.
     */
    private readonly string $rowKey;

    /**
     * Create a new rule instance.
     *
     * @param  string  $collection  Collection handle to scan
     * @param  string|null  $except  Entry id to exclude, or the `NULL` placeholder on a create
     * @param  string  $rowKey  Replicator row subfield handle carrying the identifier
     */
    public function __construct(
        string $collection,
        ?string $except = null,
        string $rowKey = 'item'
    ) {
        $trimmedCollection = trim($collection);

        if ($trimmedCollection === '') {
            throw new \InvalidArgumentException(
                'UniqueIdentifierList requires a non-empty collection handle.'
            );
        }

        $trimmedRowKey = trim($rowKey);

        if ($trimmedRowKey === '') {
            throw new \InvalidArgumentException(
                'UniqueIdentifierList requires a non-empty row subfield handle.'
            );
        }

        $this->collection = $trimmedCollection;
        $this->exceptId = $this->exceptEntryId($except);
        $this->rowKey = $trimmedRowKey;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  Handle of the replicator field being validated
     * @param  mixed  $value  The submitted rows
     * @param  Closure  $fail  Failure callback
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // A field holding no rows has nothing to compare
        if (! \is_array($value)) {
            // Nothing to check
            return;
        }

        // Track each comparison form already seen on this entry
        $seen = [];

        // Accumulate the comparison forms this entry submits
        $submitted = [];

        // Walk each submitted row in editor order
        foreach ($value as $row) {
            // A row carries its identifier under the named subfield
            $candidate = \is_array($row) ? ($row[$this->rowKey] ?? null) : $row;

            // Reduce the row value to its comparison and display forms
            $forms = $this->identifierForms($candidate);

            // A blank row is absent rather than a value that can collide
            if ($forms === null) {
                // Move to the next row
                continue;
            }

            // Report a repeat within this entry's own list
            if (isset($seen[$forms['compare']])) {
                // Fail naming the repeated value
                $fail(__('statamic::validation.unique_identifier_list.within_entry', [
                    'value' => $forms['display'],
                ]));

                return;
            }

            // Record the value so a later row can be seen as a repeat
            $seen[$forms['compare']] = true;

            // Keep the original spelling for the cross-entry message
            $submitted[$forms['compare']] = $forms['display'];
        }

        // An entry submitting no usable rows collides with nothing
        if ($submitted === []) {
            // Nothing to check
            return;
        }

        // Compare against every other entry in the collection
        foreach ($this->otherEntriesIn($this->collection, $this->exceptId) as $entry) {
            // Read that entry's comparable values for the same list field
            $held = $this->identifierValuesOn($entry, $attribute, $this->rowKey);

            // Find the first value both entries claim
            foreach ($submitted as $normalized => $original) {
                // Report the collision, naming the value and the entry
                if (isset($held[$normalized])) {
                    // Fail with the detail an editor needs to resolve it
                    $fail(__('statamic::validation.unique_identifier_list.collision', [
                        'value' => $original,
                        'entry' => $this->identifierEntryLabel($entry),
                    ]));

                    return;
                }
            }
        }
    }
}
