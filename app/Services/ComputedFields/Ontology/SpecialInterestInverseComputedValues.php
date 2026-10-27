<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Ontology;

use App\Support\Collections\PeopleCollection;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * The special-interest inverse computeds for `clinical_conditions` and
 * `clinical_services` entries (UAMS-Web/uams-statamic-archive#1064).
 *
 * Special Interest is authored on ONE side only — the Provider (`people`) or
 * Location (`clinical_locations`) — as two typed fields (`special_interest_clinical_conditions`,
 * `special_interest_clinical_services`), so the Condition/Service inverse is a pure derivation
 * of that single authored side with no second authoring side to keep in sync (the
 * recorded decision over stored `stillat` sync — UAMS-Web/uams-statamic-archive#1064, docs/DECISIONS.md 2026-07-09).
 * This resolves every PUBLISHED Provider / Location whose matching typed field (chosen
 * by the C/T entry's own collection) contains the C/T entry's id, so a Condition/Service
 * can list who has a special interest in it without a second authored edge.
 *
 * Each set is `Blink`-memoized per entry per request, so a C/T profile that renders
 * both inverse sides at several sites issues one query per source collection, not
 * one per render.
 */
class SpecialInterestInverseComputedValues
{
    /**
     * The authored special-interest field to match, keyed by the C/T entry's own
     * collection — a Condition entry is referenced from `special_interest_clinical_conditions`,
     * a Service entry from `special_interest_clinical_services`.
     *
     * @var array<string, string>
     */
    private const array OVERRIDE_FIELD_BY_COLLECTION = [
        'clinical_conditions' => 'special_interest_clinical_conditions',
        'clinical_services' => 'special_interest_clinical_services',
    ];

    /**
     * The Provider collection authoring the special-interest fields.
     */
    private static function providersCollection(): string
    {
        return PeopleCollection::handle();
    }

    /**
     * The Location collection authoring the special-interest fields.
     */
    private const string LOCATIONS_COLLECTION = 'clinical_locations';

    /**
     * Published Providers (`people`) with a special interest in the C/T entry.
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveSpecialInterestProviders(
        Entry $entry
    ): EntryCollection {
        return $this->resolveInverse($entry, self::providersCollection());
    }

    /**
     * Published Locations (`clinical_locations`) with a special interest in the C/T entry.
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveSpecialInterestLocations(
        Entry $entry
    ): EntryCollection {
        return $this->resolveInverse($entry, self::LOCATIONS_COLLECTION);
    }

    /**
     * Resolve one inverse side for the C/T entry, `Blink`-memoized per entry.
     *
     * @param  string  $collection  The source collection (people or clinical_locations)
     * @return EntryCollection<int, Entry>
     */
    private function resolveInverse(
        Entry $entry,
        string $collection
    ): EntryCollection {
        $entryId = $entry->id();

        // An unsaved C/T entry has no stable id to be referenced by; compute
        // (to an empty set) without memoizing under an unstable key.
        if (! is_string($entryId)) {
            return $this->computeInverse($entry, $collection);
        }

        /** @var EntryCollection<int, Entry> $inverse */
        $inverse = Blink::once(
            sprintf('computed_special_interest_%s_%s', $collection, $entryId),
            fn (): EntryCollection => $this->computeInverse($entry, $collection)
        );

        return $inverse;
    }

    /**
     * Every published entry in $collection whose matching typed special interest
     * field contains the C/T entry's id — `special_interest_clinical_conditions` when $entry
     * is a Condition, `special_interest_clinical_services` when it is a Service. An entry
     * without a stable id (or in an unmapped collection) has no inverse.
     *
     * @param  string  $collection  The source collection (people or clinical_locations)
     * @return EntryCollection<int, Entry>
     */
    private function computeInverse(
        Entry $entry,
        string $collection
    ): EntryCollection {
        $ctId = $entry->id();

        // No stable id → nothing can reference it yet.
        if (! is_string($ctId) || $ctId === '') {
            return EntryCollection::make();
        }

        // The typed field to match is chosen by the C/T entry's own collection.
        $ctCollection = $entry->collectionHandle();
        $overrideField = is_string($ctCollection) ? (self::OVERRIDE_FIELD_BY_COLLECTION[$ctCollection] ?? null) : null;
        if ($overrideField === null) {
            return EntryCollection::make();
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', $collection);
        $query->whereJsonContains($overrideField, $ctId);
        $query->whereStatus('published');

        /** @var EntryCollection<int, Entry> $results */
        $results = $query->get();

        return $results;
    }
}
