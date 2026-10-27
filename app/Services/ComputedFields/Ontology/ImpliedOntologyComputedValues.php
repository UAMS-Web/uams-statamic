<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Ontology;

use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Transitive implied-ontology computeds for `people` (Provider) and
 * `clinical_locations` entries (D9, UAMS-Web/uams-statamic-archive#1063).
 *
 * D9 severs the direct Condition/Service↔Provider and ↔Location attachments and
 * infers them over the shared specialty edge instead: every `clinical_services` /
 * `clinical_conditions` entry whose own `clinical_expertise` array overlaps the
 * subject's `expertise` specialties is implied. (Provider/Location author the edge
 * under handle `expertise`; the C/T collections store it under handle
 * `clinical_expertise` — both hold `clinical_expertise` entry ids.) The match is
 * **flat** — an exact specialty overlap with no descendant-subtree walk (Fork H).
 *
 * The set is the transitive default unioned **additively** (deduped by id) with
 * the entry's explicit special-interest override — the retained-exception path
 * authored on Provider/Location as two typed fields, `special_interest_clinical_conditions`
 * and `special_interest_clinical_services` (UAMS-Web/uams-statamic-archive#1064). Each collection's implied set merges the
 * matching typed field; reading it is inert (an empty array) until that field is
 * authored, so the union equals the transitive default today and gains the override
 * for free once UAMS-Web/uams-statamic-archive#1064 lands.
 *
 * Each set is `Blink`-memoized per entry per request, so a profile that renders it
 * at several sites issues one overlap query per collection, not one per site.
 */
class ImpliedOntologyComputedValues
{
    /**
     * Target collection of implied clinical services.
     */
    private const string SERVICES_COLLECTION = 'clinical_services';

    /**
     * Target collection of implied clinical conditions.
     */
    private const string CONDITIONS_COLLECTION = 'clinical_conditions';

    /**
     * The subject's anchor specialty-ID array — the `expertise` handle on both the
     * Provider (`people`) and Location (`clinical_locations`) blueprints. Holds
     * `clinical_expertise` collection entry ids, authored symmetrically on both.
     */
    private const string SUBJECT_SPECIALTY_FIELD = 'expertise';

    /**
     * The target C/T collections' specialty-edge handle. `clinical_services` and
     * `clinical_conditions` store the same `clinical_expertise` entry ids under the
     * handle `clinical_expertise` — the field references `uamsweb_common_fad.expertise`,
     * but the data key (the blueprint handle) is `clinical_expertise`, not `expertise`.
     * The overlap matches the subject's `expertise` ids against this target column.
     */
    private const string TARGET_SPECIALTY_FIELD = 'clinical_expertise';

    /**
     * The explicit retained-exception override authored on Provider/Location, by
     * target C/T collection — `special_interest_clinical_conditions` for Conditions and
     * `special_interest_clinical_services` for Services (UAMS-Web/uams-statamic-archive#1064). Each field holds only its own
     * collection's ids, so the union merges the field matching the collection being
     * resolved. Empty until those fields exist.
     *
     * @var array<string, string>
     */
    private const array OVERRIDE_FIELD_BY_COLLECTION = [
        self::CONDITIONS_COLLECTION => 'special_interest_clinical_conditions',
        self::SERVICES_COLLECTION => 'special_interest_clinical_services',
    ];

    /**
     * Clinical services implied for the entry (transitive default + explicit override).
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveImpliedServices(
        Entry $entry
    ): EntryCollection {
        return $this->resolveImplied($entry, self::SERVICES_COLLECTION);
    }

    /**
     * Clinical conditions implied for the entry (transitive default + explicit override).
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveImpliedConditions(
        Entry $entry
    ): EntryCollection {
        return $this->resolveImplied($entry, self::CONDITIONS_COLLECTION);
    }

    /**
     * Clinical services transitively implied for the entry **excluding** the explicit
     * special-interest override — the plain-transitive leg for JSON-LD (UAMS-Web/uams-statamic-archive#1067 slice 5).
     *
     * The provider `knowsAbout` / location `availableService` emission reads this set for
     * the *bulk* transitive edges and emits the special-interest override separately as a
     * `Role`-wrapped node; subtracting the override here keeps a Condition/Service that is
     * both overlap-implied and a hand-picked special interest from appearing twice (once
     * plainly, once in the `Role`). Distinct from {@see resolveImpliedServices}, which
     * **unions** the override for the on-profile render.
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveTransitiveServices(
        Entry $entry
    ): EntryCollection {
        return $this->resolveTransitiveExcludingOverride($entry, self::SERVICES_COLLECTION);
    }

    /**
     * Clinical conditions transitively implied for the entry **excluding** the explicit
     * special-interest override — the plain-transitive leg for JSON-LD (UAMS-Web/uams-statamic-archive#1067 slice 5).
     * See {@see resolveTransitiveServices}.
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveTransitiveConditions(
        Entry $entry
    ): EntryCollection {
        return $this->resolveTransitiveExcludingOverride($entry, self::CONDITIONS_COLLECTION);
    }

    /**
     * Whether the entry has any authored special-interest override (a service or a
     * condition). A `mode: custom` scalar computed the schema property Antlers can gate on
     * ({@see schema_template_node}) to suppress the empty `Role` wrapper — the raw
     * `special_interest_*` relationship fields are not flattened into that context, so a
     * plain `{{ if special_interest_* }}` cannot see them (UAMS-Web/uams-statamic-archive#1067 slice 5).
     */
    public function resolveHasSpecialInterest(
        Entry $entry
    ): bool {
        foreach (self::OVERRIDE_FIELD_BY_COLLECTION as $field) {
            $value = $entry->get($field, []);
            if (is_array($value) && $value !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the entry has an authored special-interest **service** override (only the
     * `special_interest_clinical_services` field). The location `Role` feeds
     * `availableService` from services alone (a location special-interest *condition* has
     * no schema.org predicate), so its wrapper must gate on **this** flag, not the combined
     * {@see resolveHasSpecialInterest} — otherwise a condition-only location fires the gate
     * and emits a bare, targetless `Role` (UAMS-Web/uams-statamic-archive#1067 slice 5 review). The provider `Role` feeds
     * from both services and conditions, so it keeps the combined flag.
     */
    public function resolveHasSpecialInterestServices(
        Entry $entry
    ): bool {
        $value = $entry->get(self::OVERRIDE_FIELD_BY_COLLECTION[self::SERVICES_COLLECTION], []);

        return is_array($value) && $value !== [];
    }

    /**
     * The in-scope candidate ids for the entry's special-interest pickers (UAMS-Web/uams-statamic-archive#1064):
     * the ids of every Condition/Service whose `clinical_expertise` overlaps the
     * entry's own `expertise` specialties — the transitive overlap across BOTH C/T
     * collections, **without** the special-interest override union (so a selection
     * is never validated against itself). An entry with no specialties yields an
     * empty candidate set, so every selection is out of scope.
     *
     * Publish status is **not** part of association: this passes `publishedOnly:
     * false`, so an in-specialty but draft/scheduled C/T is a valid candidate (the
     * rendered inference stays published-only). Reuses the exact `transitiveOverlap`
     * primitive the implied-set inference draws from, so the special-interest scope
     * and the transitive default can never drift apart. Not memoized — the scope
     * guard runs on save against the entry's being-saved specialties, where a fresh
     * read is wanted.
     *
     * @return list<string> in-scope C/T entry ids
     */
    public function candidateOverrideIds(
        Entry $entry
    ): array {
        $candidates = $this->transitiveOverlap($entry, self::SERVICES_COLLECTION, publishedOnly: false)
            ->merge($this->transitiveOverlap($entry, self::CONDITIONS_COLLECTION, publishedOnly: false));

        $ids = [];
        foreach ($candidates as $related) {
            if ($related instanceof Entry) {
                $id = $related->id();
                if (is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * Resolve the implied set for one target collection, `Blink`-memoized per entry.
     *
     * @param  string  $collection  The target collection handle (services or conditions)
     * @return EntryCollection<int, Entry>
     */
    private function resolveImplied(
        Entry $entry,
        string $collection
    ): EntryCollection {
        // Capture the entry id to key the per-request implied-set cache.
        $entryId = $entry->id();

        // A not-yet-saved entry has no stable cache key; compute without memoizing.
        if (! is_string($entryId)) {
            return $this->computeImplied($entry, $collection);
        }

        // Resolve the implied set once per request and reuse it for every render site.
        /** @var EntryCollection<int, Entry> $implied */
        $implied = Blink::once(
            sprintf('computed_implied_%s_%s', $collection, $entryId),
            fn (): EntryCollection => $this->computeImplied($entry, $collection)
        );

        return $implied;
    }

    /**
     * The transitive default unioned additively with the explicit override, deduped
     * by entry id so an item present in both is never double-counted.
     *
     * @param  string  $collection  The target collection handle (services or conditions)
     * @return EntryCollection<int, Entry>
     */
    private function computeImplied(
        Entry $entry,
        string $collection
    ): EntryCollection {
        /** @var EntryCollection<int, Entry> $implied */
        $implied = $this->transitiveOverlap($entry, $collection)
            ->merge($this->explicitOverride($entry, $collection))
            ->unique(fn (mixed $related): mixed => $related instanceof Entry ? $related->id() : null)
            ->values();

        return $implied;
    }

    /**
     * Resolve the transitive-overlap-minus-override set for one collection, `Blink`-memoized
     * per entry (mirrors {@see resolveImplied}'s memoization, keyed separately).
     *
     * @param  string  $collection  The target collection handle (services or conditions)
     * @return EntryCollection<int, Entry>
     */
    private function resolveTransitiveExcludingOverride(
        Entry $entry,
        string $collection
    ): EntryCollection {
        $entryId = $entry->id();

        // A not-yet-saved entry has no stable cache key; compute without memoizing.
        if (! is_string($entryId)) {
            return $this->computeTransitiveExcludingOverride($entry, $collection);
        }

        /** @var EntryCollection<int, Entry> $set */
        $set = Blink::once(
            sprintf('computed_transitive_excl_override_%s_%s', $collection, $entryId),
            fn (): EntryCollection => $this->computeTransitiveExcludingOverride($entry, $collection)
        );

        return $set;
    }

    /**
     * The transitive specialty-overlap set with the explicit special-interest override
     * entries **removed by id**, so a Condition/Service that is both overlap-implied and a
     * hand-picked special interest surfaces only in the `Role`-wrapped special-interest leg
     * (UAMS-Web/uams-statamic-archive#1067 slice 5), never both there and in the plain-transitive list.
     *
     * @param  string  $collection  The target collection handle (services or conditions)
     * @return EntryCollection<int, Entry>
     */
    private function computeTransitiveExcludingOverride(
        Entry $entry,
        string $collection
    ): EntryCollection {
        // Index the override ids so the transitive set can drop them.
        $overrideIds = [];
        foreach ($this->explicitOverride($entry, $collection) as $related) {
            $id = $related->id();
            if (is_string($id)) {
                $overrideIds[$id] = true;
            }
        }

        /** @var EntryCollection<int, Entry> $set */
        $set = $this->transitiveOverlap($entry, $collection)
            ->filter(function (mixed $related) use ($overrideIds): bool {
                if (! $related instanceof Entry) {
                    return true;
                }

                $id = $related->id();

                // Drop only entries whose id is in the explicit-override set.
                return ! is_string($id) || ! isset($overrideIds[$id]);
            })
            ->values();

        return $set;
    }

    /**
     * Every entry in $collection whose `clinical_expertise` array overlaps the
     * entry's own `expertise` specialty ids.
     *
     * An entry with no related specialties yields an **empty** set — the read
     * short-circuits rather than inheriting the overlap scope's "return the whole
     * collection unfiltered" behavior on an empty parameter.
     *
     * `$publishedOnly` is `true` for the rendered implied-set inference (only
     * published C/T should surface on a profile) and `false` for the
     * special-interest scope check, where *association* is a property of the
     * specialty edge alone — a matching-but-draft C/T is still associated, so
     * gating the candidate set by publish status would wrongly reject it (UAMS-Web/uams-statamic-archive#1064
     * review).
     *
     * @param  string  $collection  The target collection handle (services or conditions)
     * @param  bool  $publishedOnly  Restrict to published entries (the rendered inference); false for the scope candidate set
     * @return EntryCollection<int, Entry>
     */
    private function transitiveOverlap(
        Entry $entry,
        string $collection,
        bool $publishedOnly = true
    ): EntryCollection {
        $specialtyIds = $entry->get(self::SUBJECT_SPECIALTY_FIELD, []);

        // No anchor specialties → no implied set (do not fall through to an
        // unfiltered whole-collection query).
        if (! is_array($specialtyIds) || $specialtyIds === []) {
            return EntryCollection::make();
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', $collection);
        $query->whereJsonOverlaps(self::TARGET_SPECIALTY_FIELD, $specialtyIds);
        if ($publishedOnly) {
            $query->whereStatus('published');
        }

        /** @var EntryCollection<int, Entry> $results */
        $results = $query->get();

        return $results;
    }

    /**
     * The published entries in $collection explicitly named by the entry's matching
     * special-interest override field — `special_interest_clinical_conditions` for Conditions,
     * `special_interest_clinical_services` for Services (empty until UAMS-Web/uams-statamic-archive#1064 authors the fields).
     * Each typed field already holds only its collection's ids, so no cross-collection
     * bucketing is needed.
     *
     * @param  string  $collection  The target collection handle (services or conditions)
     * @return EntryCollection<int, Entry>
     */
    private function explicitOverride(
        Entry $entry,
        string $collection
    ): EntryCollection {
        $overrideField = self::OVERRIDE_FIELD_BY_COLLECTION[$collection] ?? null;

        // A collection with no special-interest field (never happens for C/T) → nothing to add.
        if ($overrideField === null) {
            return EntryCollection::make();
        }

        $overrideIds = $entry->get($overrideField, []);

        // No explicit override authored → nothing to add over the transitive default.
        if (! is_array($overrideIds) || $overrideIds === []) {
            return EntryCollection::make();
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', $collection);
        $query->whereIn('id', $overrideIds);
        $query->whereStatus('published');

        /** @var EntryCollection<int, Entry> $results */
        $results = $query->get();

        return $results;
    }
}
