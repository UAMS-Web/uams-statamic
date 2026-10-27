<?php

declare(strict_types=1);

namespace App\Support\Identifiers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Shared reading and comparison plumbing for the identifier-uniqueness rules
 * (UAMS-Web/uams-statamic-archive#2152).
 *
 * Both rules ask the same three questions of the entry store -- what does this
 * value normalize to, what values does an entry hold for a given field, and
 * which entries am I comparing against -- so the answers live here rather than
 * twice.
 *
 * The comparison semantics are the decision recorded on UAMS-Web/uams-statamic-archive#2221: surrounding
 * whitespace is trimmed and case is ignored. That is applied in exactly one
 * place, so the two rules cannot drift apart on what counts as the same
 * identifier, and so the field instructions describing it stay true for both.
 */
trait ResolvesIdentifierValues
{
    /**
     * Reduce a stored value to its comparison form, or null when it is blank.
     *
     * Returning null for a blank value is what keeps blank from colliding with
     * blank (UAMS-Web/uams-statamic-archive#2152). Every identifier here is optional and absent on most
     * entries, so a rule treating `''` as a value would fail on the second
     * entry ever saved.
     *
     * @param  mixed  $value  A raw stored field value
     * @return string|null The comparison form, or null when there is nothing to compare
     */
    private function normalizeIdentifier(mixed $value): ?string
    {
        // Reduce to the pair and keep only the comparison form
        return $this->identifierForms($value)['compare'] ?? null;
    }

    /**
     * Reduce a stored value to both the form used for comparison and the form
     * shown in a message, or null when it is blank.
     *
     * The two are returned together so a caller never has to cast the raw value
     * a second time: the cast is guarded by the scalar check here, and doing it
     * again at the call site would be an unguarded cast of a `mixed`.
     *
     * @param  mixed  $value  A raw stored field value
     * @return array{compare: string, display: string}|null The two forms, or null when blank
     */
    private function identifierForms(mixed $value): ?array
    {
        // Only a scalar can be an identifier
        if (! \is_scalar($value)) {
            // Report nothing to compare
            return null;
        }

        // Trim surrounding whitespace, per the recorded comparison semantics
        $trimmed = trim((string) $value);

        // A blank value is absent rather than a value that can collide
        if ($trimmed === '') {
            // Report nothing to compare
            return null;
        }

        // Fold case so two spellings of one identifier compare equal
        return ['compare' => mb_strtolower($trimmed), 'display' => $trimmed];
    }

    /**
     * Every comparable value an entry holds for one field.
     *
     * A single-value field yields at most one; a replicator list field yields
     * one per row that carries a usable value. Blank rows are dropped rather
     * than returned as empty strings, for the reason above.
     *
     * The handle is a DOT PATH rather than a bare handle, because a field inside
     * a `group` fieldtype is stored nested and is validated under the same
     * dotted attribute. `person.yaml` puts all four Workday identifiers inside a
     * `uams_workday_id` group, so `workday_universal_id` alone reads null on
     * every entry -- a check that can never fire and never says so.
     *
     * @param  ConcreteEntry  $entry  The entry to read
     * @param  string  $handle  Blueprint field path, dotted for a grouped field
     * @param  string|null  $rowKey  Replicator row subfield handle, or null for a single value
     * @return array<string, string> Comparison form => the value as stored, for messages
     */
    private function identifierValuesOn(ConcreteEntry $entry, string $handle, ?string $rowKey): array
    {
        // Read the whole stored payload so a dotted path can be resolved into a
        // group; `data()` is mixed to the analyser, so narrow before using it
        $data = $entry->data();

        /** @var array<string, mixed> $values */
        $values = $data instanceof Collection ? $data->all() : [];

        // Resolve the value, which may sit inside a group
        $raw = Arr::get($values, $handle);

        // A single-value field has at most one value to offer
        if ($rowKey === null) {
            // Reduce it to its two forms
            $forms = $this->identifierForms($raw);

            // Report the one pair, or nothing when the field is blank
            return $forms === null ? [] : [$forms['compare'] => $forms['display']];
        }

        // Report nothing when a list field holds no rows
        if (! \is_array($raw)) {
            // Return no values
            return [];
        }

        // Accumulate one pair per usable row
        $values = [];

        // Walk each replicator row
        foreach ($raw as $row) {
            // A row carries its value under the named subfield
            $candidate = \is_array($row) ? ($row[$rowKey] ?? null) : $row;

            // Reduce the row value to its two forms
            $forms = $this->identifierForms($candidate);

            // Keep the first spelling seen for a given comparison form
            if ($forms !== null && ! isset($values[$forms['compare']])) {
                // Record the pair
                $values[$forms['compare']] = $forms['display'];
            }
        }

        return $values;
    }

    /**
     * Every entry in a collection except the one being saved.
     *
     * The scan is deliberately naive -- read the collection, compare in PHP --
     * which is the approach recorded on UAMS-Web/uams-statamic-archive#2221. `people` holds 43 entries and
     * `clinical_locations` none, so the cost is measured rather than assumed
     * and the criterion that requires re-measuring it is the trigger to revisit.
     *
     * @param  string  $collection  Collection handle to scan
     * @param  string|null  $exceptId  Entry id to exclude, or null to exclude nothing
     * @return array<int, ConcreteEntry> The other entries
     */
    private function otherEntriesIn(string $collection, ?string $exceptId): array
    {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();

        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', $collection);

        // Annotated as mixed rather than as entries on purpose: the element type
        // would be this file asserting what the store returns, and the
        // `instanceof` below is the thing that actually establishes it.
        /** @var Collection<int, mixed> $entries */
        $entries = $query->get();

        // Accumulate the entries this save is compared against
        $others = [];

        // Walk each entry in the collection
        foreach ($entries as $entry) {
            // Only a concrete entry can be read
            if (! $entry instanceof ConcreteEntry) {
                // Move to the next entry
                continue;
            }

            // Never report a collision with the entry being saved
            if ($exceptId !== null && $this->entryIdAsString($entry) === $exceptId) {
                // Move to the next entry
                continue;
            }

            // Keep the entry for comparison
            $others[] = $entry;
        }

        return $others;
    }

    /**
     * Normalize the `except` rule parameter into an entry id, or null.
     *
     * Statamic substitutes `{id}` from the publish form's replacements, and on
     * a CREATE there is no id to substitute -- `Statamic\Fields\Validator`'s
     * string-rule path falls back to the literal string `NULL`. Reading that as
     * an id would be harmless today, since no entry carries it, but reading it
     * as "exclude nothing" is what it actually means and is what keeps the
     * create path honest.
     *
     * @param  string|null  $except  The raw rule parameter
     * @return string|null The entry id to exclude, or null
     */
    private function exceptEntryId(?string $except): ?string
    {
        // Nothing passed means nothing to exclude
        if ($except === null) {
            // Exclude nothing
            return null;
        }

        // Normalize the parameter for comparison
        $trimmed = trim($except);

        // An absent id arrives as a placeholder rather than as an empty string
        if ($trimmed === '' || strcasecmp($trimmed, 'NULL') === 0) {
            // Exclude nothing
            return null;
        }

        return $trimmed;
    }

    /**
     * A human label for an entry, for use in a failure message.
     *
     * @param  ConcreteEntry  $entry  The conflicting entry
     * @return string The entry title, falling back to its id
     */
    private function identifierEntryLabel(ConcreteEntry $entry): string
    {
        // Prefer the title an editor will recognize
        $forms = $this->identifierForms($entry->get('title'));

        // Use the title when it is readable
        if ($forms !== null) {
            // Return the trimmed title, not the case-folded comparison form
            return $forms['display'];
        }

        // Fall back to the id, which always exists on a saved entry
        return $this->entryIdAsString($entry);
    }

    /**
     * An entry's id as a string.
     *
     * `id()` is `mixed` to the analyser, so the narrowing happens once here
     * rather than at each of the two call sites.
     *
     * @param  ConcreteEntry  $entry  The entry to read
     * @return string The id, or an empty string when it cannot be read
     */
    private function entryIdAsString(ConcreteEntry $entry): string
    {
        // Read the identifier the store assigned
        $id = $entry->id();

        // Only a scalar id can be compared or displayed
        return \is_scalar($id) ? (string) $id : '';
    }
}
