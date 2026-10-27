<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\ComputedFields\Ontology\ImpliedOntologyComputedValues;
use App\Support\Collections\PeopleCollection;
use Exception;
use Illuminate\Support\Facades\Log;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry as EntryFacade;

/**
 * Enforces the specialty scope of the special-interest override on Provider
 * (`people`) and Location (`clinical_locations`) entries (UAMS-Web/uams-statamic-archive#1064).
 *
 * Special Interest is authored as two typed fields — `special_interest_clinical_conditions`
 * (Conditions) and `special_interest_clinical_services` (Services) — the retained explicit
 * exception over the transitive C/T inference (UAMS-Web/uams-statamic-archive#1063): a Provider/Location may
 * highlight specific Conditions/Services it focuses on. That selection MUST stay
 * within the entity's own specialties — every picked Condition/Service must be one
 * whose `clinical_expertise` overlaps the entity's `expertise`, i.e. a member of the
 * same specialty-overlap set the implied inference draws from (via
 * `ImpliedOntologyComputedValues::candidateOverrideIds`, which reuses that exact
 * primitive and spans both C/T collections). An entity with no specialties has an
 * EMPTY candidate set, so it can hold no special interests. Both fields are checked
 * against the same candidate set; a Condition id can only match a Condition
 * candidate and a Service id only a Service candidate, so the union is exact.
 *
 * Only **newly-added** selections are scope-checked. An association that was in
 * scope when it was picked must not be retroactively invalidated — e.g. by the
 * entity later narrowing its own `expertise` — and then block an unrelated save; a
 * removal is likewise never re-checked. (Publish status is not part of association,
 * so the candidate set is publish-independent — see `candidateOverrideIds`.)
 *
 * This runs on `EntrySaving` — not as a field `validate:` rule — so the check fires
 * on every event-firing save path (Control Panel, REST API, programmatic import), so
 * an out-of-scope selection cannot be *added* by any of those routes. (Like every
 * `EntrySaving` listener, a deliberate `saveQuietly()` / `withoutEvents()` write
 * bypasses it — that is the framework's documented event-suppression escape hatch,
 * not a scope-specific gap.) On a violation the save is aborted with a Control Panel
 * error and the offending ids are logged.
 */
class ValidateSpecialInterestsScope
{
    /**
     * Collection handles that author the special-interest fields.
     *
     * @return list<string>
     */
    private static function collectionHandles(): array
    {
        return [PeopleCollection::handle(), 'clinical_locations'];
    }

    /**
     * The authored special-interest override fields this listener scope-checks —
     * the typed Condition and Service pickers. A profile's total special interests
     * are the union of both.
     *
     * @var list<string>
     */
    private const array OVERRIDE_FIELDS = ['special_interest_clinical_conditions', 'special_interest_clinical_services'];

    public function __construct(
        protected ImpliedOntologyComputedValues $impliedValues
    ) {}

    /**
     * Reject a save that ADDS a special-interest selection outside the entity's
     * specialty-derived candidate set.
     *
     * @param  EntrySaving  $event  The entry save event
     *
     * @throws Exception If a newly-added Condition/Service is outside the specialty scope
     */
    public function handle(
        EntrySaving $event
    ): void {
        /** @var Entry $entry */
        $entry = $event->entry;

        // Only Provider/Location entries author the fields.
        if (! in_array($entry->collectionHandle(), self::collectionHandles(), true)) {
            return;
        }

        // The entity's currently-selected special interests, across both typed fields.
        $selectedIds = $this->specialInterestIds($entry);

        // Nothing authored → nothing to scope-check (do not run the overlap query).
        if ($selectedIds === []) {
            return;
        }

        // Scope-check only the ids being ADDED this save; already-persisted selections
        // are grandfathered so a later expertise change cannot retroactively block an
        // unrelated save.
        $newlyAdded = array_values(array_diff($selectedIds, $this->previouslySavedIds($entry)));
        if ($newlyAdded === []) {
            return;
        }

        // Every newly-added selection must sit in the entity's specialty-derived candidate set.
        $candidateIds = $this->impliedValues->candidateOverrideIds($entry);
        $outOfScope = array_values(array_diff($newlyAdded, $candidateIds));

        if ($outOfScope !== []) {
            Log::error(
                'ValidateSpecialInterestsScope::handle: Throwing exception: Special interest outside specialty scope',
                [
                    'entry_id' => $entry->id(),
                    'collection' => $entry->collectionHandle(),
                    'out_of_scope' => $outOfScope,
                ]
            );

            throw new Exception("A Special Interest must be a condition or service associated with one of this profile's Areas of Expertise. Remove the out-of-scope selection, or add the matching Area of Expertise first.");
        }
    }

    /**
     * The special-interest ids already persisted on the entry before this save,
     * unioned across both typed fields.
     *
     * A new (not-yet-persisted) entry has no prior selections, so every id counts as
     * newly added and is scope-checked.
     *
     * @return list<string>
     */
    private function previouslySavedIds(
        Entry $entry
    ): array {
        $id = $entry->id();
        if (! is_string($id)) {
            return [];
        }

        $existing = EntryFacade::find($id);
        if (! $existing instanceof Entry) {
            return [];
        }

        return $this->specialInterestIds($existing);
    }

    /**
     * The union of the entry's `special_interest_clinical_conditions` + `special_interest_clinical_services`
     * ids, deduped. Any malformed non-string entry is ignored (Statamic's entries field
     * drops those anyway).
     *
     * @return list<string>
     */
    private function specialInterestIds(
        Entry $entry
    ): array {
        $ids = [];
        foreach (self::OVERRIDE_FIELDS as $field) {
            $selected = $entry->get($field, []);
            if (! is_array($selected)) {
                continue;
            }

            foreach ($selected as $value) {
                if (is_string($value)) {
                    $ids[] = $value;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
