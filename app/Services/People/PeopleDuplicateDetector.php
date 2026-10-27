<?php

declare(strict_types=1);

namespace App\Services\People;

use App\Support\Collections\PeopleCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Flags clusters of `people` entries that likely describe the same real person (UAMS-Web/uams-statamic-archive#839).
 *
 * Centralizing every WordPress profile into one `people` collection guarantees
 * duplicate clusters (the same human imported from People Showcase and Find-a-Doc).
 * This detector groups entries that share at least one matching signal and tags
 * each cluster with a confidence level so a reviewer can triage strong matches
 * before handing a cluster to the UAMS-Web/uams-statamic-archive#838 merge tool. It NEVER mutates entries — it
 * only suggests.
 *
 * No single field is reliably present on every entry, so the detector unions on
 * any shared signal and derives confidence from the strongest signal actually
 * shared within the resulting cluster:
 *
 *   - identifier (NPI / Epic SER / Workday IDs) or email → high
 *   - name + phone → high
 *   - name + slug stem, or phone alone → medium
 *   - name alone, or slug stem alone → low
 *
 * The matching strategy is intentionally tunable (see UAMS-Web/uams-statamic-archive#839); confidence keeps
 * false positives from drowning real matches rather than dropping them.
 */
class PeopleDuplicateDetector
{
    /**
     * The collection scanned for duplicates. Detecting across collections is out of scope.
     */
    public static function peopleCollection(): string
    {
        return PeopleCollection::handle();
    }

    /**
     * Exact-identifier handles read from the entry (kind => dot-path into entry data).
     *
     * Any shared identifier is near-certain evidence of the same person.
     *
     * @var array<string, string>
     */
    private const array IDENTIFIER_PATHS = [
        'npi' => 'npi',
        'epic_ser_id' => 'epic_ser_id',
        'workday_universal_id' => 'uams_workday_id.workday_universal_id',
        'workday_employee_id' => 'uams_workday_id.workday_employee_id',
    ];

    /**
     * Detect candidate duplicate clusters across the `people` collection.
     *
     * @return list<DuplicatePersonCluster> Clusters of ≥2 entries, strongest confidence first.
     */
    public function detect(): array
    {
        // Signals per entry id: ['identifier' => [...], 'email' => [...], 'name' => [...], 'phone' => [...], 'slug' => [...]]
        /** @var array<string, array<string, list<string>>> $entrySignals */
        $entrySignals = [];

        // Buckets keyed by "slot|value" → entry ids sharing that exact signal value
        /** @var array<string, list<string>> $buckets */
        $buckets = [];

        // Build the per-entry signals and the shared-value buckets in one pass
        foreach ($this->peopleEntries() as $entry) {
            $id = $entry->id();
            // Skip entries without a usable string id
            if (! \is_string($id)) {
                continue;
            }

            if ($id === '') {
                continue;
            }

            $signals = $this->signalsFor($entry);
            $entrySignals[$id] = $signals;

            // Index each non-empty signal value so entries sharing it can be unioned
            foreach ($signals as $slot => $values) {
                foreach ($values as $value) {
                    $buckets[$slot.'|'.$value][] = $id;
                }
            }
        }

        // Union every pair of entries that share at least one signal value
        $unionFind = new PeopleDuplicateUnionFind(array_keys($entrySignals));

        foreach ($buckets as $ids) {
            // A bucket with a single entry links nothing
            if (\count($ids) < 2) {
                continue;
            }

            // Union each member to the first so the whole bucket joins one set
            $first = $ids[0];
            foreach ($ids as $id) {
                $unionFind->union($first, $id);
            }
        }

        return $this->buildClusters($unionFind->groups(), $entrySignals);
    }

    /**
     * Build confidence-ranked clusters from the union-find groups.
     *
     * @param  array<string, list<string>>  $groups  Root id => member ids.
     * @param  array<string, array<string, list<string>>>  $entrySignals  Per-entry signal values.
     * @return list<DuplicatePersonCluster>
     */
    private function buildClusters(array $groups, array $entrySignals): array
    {
        $clusters = [];

        foreach ($groups as $members) {
            // A cluster needs at least two entries to be a duplicate candidate
            if (\count($members) < 2) {
                continue;
            }

            // Determine which signal types are actually shared by ≥2 members
            $sharedSignals = $this->sharedSignals($members, $entrySignals);

            // A transitive group can end up with no signal shared by ≥2 members
            // (linked only through a now-pruned value); skip those non-clusters.
            if ($sharedSignals === []) {
                continue;
            }

            $clusters[] = new DuplicatePersonCluster(
                $members,
                $this->confidenceFor($sharedSignals),
                $sharedSignals,
            );
        }

        // Strongest confidence first, then larger clusters, for review ergonomics
        usort($clusters, function (DuplicatePersonCluster $a, DuplicatePersonCluster $b): int {
            $rank = $this->confidenceRank($b->confidence) <=> $this->confidenceRank($a->confidence);

            return $rank !== 0 ? $rank : \count($b->entryIds) <=> \count($a->entryIds);
        });

        return $clusters;
    }

    /**
     * Signal types shared by at least two members of the cluster.
     *
     * @param  list<string>  $members  Cluster entry ids.
     * @param  array<string, array<string, list<string>>>  $entrySignals  Per-entry signal values.
     * @return list<string> Shared slot names (`identifier`, `email`, `name`, `phone`, `slug`).
     */
    private function sharedSignals(array $members, array $entrySignals): array
    {
        $shared = [];

        foreach (['identifier', 'email', 'name', 'phone', 'slug'] as $slot) {
            // Count how many members carry each distinct value for this slot
            $valueCounts = [];

            foreach ($members as $id) {
                // De-duplicate within an entry so one entry never self-satisfies a share
                foreach (array_unique($entrySignals[$id][$slot] ?? []) as $value) {
                    $valueCounts[$value] = ($valueCounts[$value] ?? 0) + 1;
                }
            }

            // The slot is shared when any single value occurs in ≥2 members
            foreach ($valueCounts as $count) {
                if ($count >= 2) {
                    $shared[] = $slot;

                    break;
                }
            }
        }

        return $shared;
    }

    /**
     * Map the shared signals to a confidence level.
     *
     * @param  list<string>  $shared  Shared slot names.
     */
    private function confidenceFor(array $shared): string
    {
        $hasIdentifier = \in_array('identifier', $shared, true);
        $hasEmail = \in_array('email', $shared, true);
        $hasName = \in_array('name', $shared, true);
        $hasPhone = \in_array('phone', $shared, true);
        $hasSlug = \in_array('slug', $shared, true);

        // An exact identifier or email, or a name corroborated by a phone number,
        // is near-certain.
        if ($hasIdentifier || $hasEmail || ($hasName && $hasPhone)) {
            return DuplicatePersonCluster::CONFIDENCE_HIGH;
        }

        // A name backed by a matching slug stem, or a shared phone number, is worth review.
        if (($hasName && $hasSlug) || $hasPhone) {
            return DuplicatePersonCluster::CONFIDENCE_MEDIUM;
        }

        // A name (or slug stem) alone is weak — many people share a name.
        return DuplicatePersonCluster::CONFIDENCE_LOW;
    }

    /**
     * Numeric rank for sorting confidence levels (higher is stronger).
     */
    private function confidenceRank(string $confidence): int
    {
        return match ($confidence) {
            DuplicatePersonCluster::CONFIDENCE_HIGH => 3,
            DuplicatePersonCluster::CONFIDENCE_MEDIUM => 2,
            default => 1,
        };
    }

    /**
     * Extract the normalized matching signals for one entry.
     *
     * @return array<string, list<string>> Slot name => list of normalized values (empties dropped).
     */
    private function signalsFor(Entry $entry): array
    {
        // data() is loosely typed on the concrete entry; narrow to a values collection
        /** @var Collection<string, mixed> $dataCollection */
        $dataCollection = $entry->data();

        /** @var array<string, mixed> $data */
        $data = $dataCollection->all();

        // Exact identifiers, namespaced by kind so an NPI never collides with an Epic id
        $identifiers = [];
        foreach (self::IDENTIFIER_PATHS as $kind => $path) {
            $normalized = $this->normalizeIdentifier(Arr::get($data, $path));
            if ($normalized !== null) {
                $identifiers[] = $kind.':'.$normalized;
            }
        }

        // Office and mobile numbers, normalized to digits
        $phones = [];
        foreach (['telephone', 'telephone_mobile'] as $phoneHandle) {
            $phone = $this->normalizePhone(Arr::get($data, $phoneHandle));
            if ($phone !== null) {
                $phones[] = $phone;
            }
        }

        return [
            'identifier' => $identifiers,
            'email' => $this->valueList($this->normalizeEmail(Arr::get($data, 'email'))),
            'name' => $this->valueList($this->normalizeName(Arr::get($data, 'person_name'))),
            'phone' => array_values(array_unique($phones)),
            'slug' => $this->valueList($this->normalizeSlug($entry->slug())),
        ];
    }

    /**
     * Wrap a single nullable value as a list, dropping null.
     *
     * @return list<string>
     */
    private function valueList(?string $value): array
    {
        return $value === null ? [] : [$value];
    }

    /**
     * Normalize a person_name structure to a "first last" key.
     *
     * @param  mixed  $personName  The raw `person_name` field value.
     * @return string|null Lowercased alphanumeric name, or null when both parts are empty.
     */
    private function normalizeName(mixed $personName): ?string
    {
        if (! \is_array($personName)) {
            return null;
        }

        $first = Arr::get($personName, 'first_name.text');
        $last = Arr::get($personName, 'last_name.text');

        $first = \is_string($first) ? $first : '';
        $last = \is_string($last) ? $last : '';

        // Collapse to lowercase words, dropping punctuation, so "O'Neil" == "oneil".
        // The class is Unicode-aware (\p{L}\p{N} with the /u flag) so non-Latin names
        // (CJK, Cyrillic, accented Latin) keep their letters and produce a stable key
        // instead of normalizing to null — duplicate detection must work for every name.
        $key = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($first.' '.$last)) ?? '');
        $key = trim((string) preg_replace('/\s+/', ' ', $key));

        return $key === '' ? null : $key;
    }

    /**
     * Normalize an exact identifier (trim, lowercase, strip internal whitespace).
     *
     * @param  mixed  $value  The raw identifier value.
     */
    private function normalizeIdentifier(mixed $value): ?string
    {
        if (! \is_scalar($value)) {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', mb_strtolower(trim((string) $value)));

        return ($normalized === null || $normalized === '') ? null : $normalized;
    }

    /**
     * Normalize an email (trim + lowercase).
     *
     * @param  mixed  $value  The raw email value.
     */
    private function normalizeEmail(mixed $value): ?string
    {
        if (! \is_scalar($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize a phone number to digits only, requiring enough digits to matter.
     *
     * @param  mixed  $value  The raw phone value.
     */
    private function normalizePhone(mixed $value): ?string
    {
        if (! \is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        // Require at least 7 digits so partial extensions never match coincidentally
        return \strlen($digits) >= 7 ? $digits : null;
    }

    /**
     * Normalize a slug to its stem, stripping the trailing `-N` de-duplication suffix.
     *
     * @param  mixed  $slug  The entry slug.
     */
    private function normalizeSlug(mixed $slug): ?string
    {
        if (! \is_scalar($slug)) {
            return null;
        }

        // "jane-smith-2" → "jane-smith" so a freed-then-reused slug still matches
        $stem = preg_replace('/-\d+$/', '', mb_strtolower(trim((string) $slug))) ?? '';

        return $stem === '' ? null : $stem;
    }

    /**
     * Load the people entries to scan.
     *
     * @return iterable<Entry>
     */
    private function peopleEntries(): iterable
    {
        // Cast the facade's contract builder to the concrete Stache builder
        // (its declared return type omits where()/get()).
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();

        // Filter in place (the builder is fluent) so $query keeps its concrete type
        $query->where('collection', self::peopleCollection());

        /** @var Collection<int, Entry> $entries */
        $entries = $query->get();

        return $entries;
    }
}
