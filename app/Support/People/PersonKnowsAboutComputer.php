<?php

declare(strict_types=1);

namespace App\Support\People;

use App\Support\RequestAttributeMemo;
use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Term;
use Statamic\Fields\Value;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Computes `knows_about_terms`, `knows_about_entries`, and `knows_about_strings` for
 * people collection entries by walking taxonomy relationships defined in product rules.
 *
 * `knows_about_terms` values are canonical Statamic term ids (`taxonomy::slug`) for the
 * taxonomy terms explicitly listed in Sections B and E. Clinical specializations (Section A)
 * are `clinical_specializations` collection entries since UAMS-Web/uams-statamic-archive#2596, so they are listed in
 * `knows_about_entries`, while the `training_specialty` terms each one names are still
 * expanded into `knows_about_terms`. Subject matter (Section G) is a `subject_matter`
 * collection since UAMS-Web/uams-statamic-archive#2822, so the subjects the walk reaches are listed in
 * `knows_about_entries` too, directly after the clinical specializations. Since UAMS-Web/uams-statamic-archive#2823 a
 * `subject_matter` field may also point at a `clinical_specializations` entry, the home of
 * every clinical concept, and that entry is listed the same way.
 *
 * `knows_about_strings` merges free-text research interest lines with `keywords` from each
 * `subject_matter` entry listed in `knows_about_entries`.
 */
final class PersonKnowsAboutComputer
{
    /**
     * Request attribute holding the memo of full bundles keyed by people entry id.
     *
     * A process-wide static would outlive the request, so a long-lived worker (or a
     * second test file) would read a bundle computed from an earlier entry state.
     */
    private const string REQUEST_BUNDLE_MEMO = '_app_person_knows_about_bundle_memo';

    /**
     * Collection handle for subject matter references (Section G, UAMS-Web/uams-statamic-archive#2822).
     */
    private const string COLLECTION_SUBJECT_MATTER = 'subject_matter';

    /**
     * Collection a `subject_matter` field may also point at since UAMS-Web/uams-statamic-archive#2823: a clinical concept
     * is chosen from the clinical specializations, never from subject matter.
     */
    private const string COLLECTION_CLINICAL_SPECIALIZATIONS = 'clinical_specializations';

    /**
     * Taxonomy handles whose terms follow Section E.1 (medical specialties).
     *
     * @var list<string>
     */
    private const array TAXONOMIES_MEDICAL_SPECIALTIES = [
        'specialties_aoa',
        'specialties_acgme',
        'specialties_non_accredited',
    ];

    /**
     * Taxonomy handles whose terms follow Section E.2 (research specialties).
     *
     * @var list<string>
     */
    private const array TAXONOMIES_RESEARCH_SPECIALTIES = [
        'research_specialties_acgme',
        'research_specialties_non_accredited',
    ];

    /**
     * Taxonomy handle for Section E.3 (other specialties).
     */
    private const string TAXONOMY_OTHER_SPECIALTIES = 'other_specialties';

    /**
     * Certification blueprint field handles that map to Section E.1 queues.
     *
     * @var list<string>
     */
    private const array CERTIFICATION_FIELD_HANDLES_E1 = [
        'medical_specialty',
        'medical_subspecialty',
    ];

    /**
     * Certification blueprint field handles that map to Section E.2 queues.
     *
     * @var list<string>
     */
    private const array CERTIFICATION_FIELD_HANDLES_E2 = [
        'non_medical_research_specialty',
        'non_medical_research_subspecialty',
        'medical_research_specialty',
        'medical_research_subspecialty',
    ];

    /**
     * Certification blueprint field handles that map to Section E.3 queues.
     *
     * @var list<string>
     */
    private const array CERTIFICATION_FIELD_HANDLES_E3 = [
        'other_specialty',
        'other_subspecialty',
    ];

    /**
     * Allowed collection handles for `knows_about_entries` (Section I).
     *
     * @var array<string, string> people field handle => collection handle
     */
    private const array ENTRIES_FIELD_TO_COLLECTION = [
        'clinical_specializations' => 'clinical_specializations',
        'expertise' => 'clinical_expertise',
        'conditions' => 'clinical_conditions',
        'treatments' => 'clinical_services',
    ];

    /**
     * Compute deduplicated canonical term ids for `knows_about_terms`.
     *
     * @param  Entry  $person  People collection entry
     * @return list<string> Deduped `taxonomy::slug` term ids
     */
    public function computeTerms(Entry $person): array
    {
        // Reuse bundled computation so graph expansion runs at most once per entry
        return $this->computeBundle($person)['terms'];
    }

    /**
     * Compute deduplicated related entry ids for `knows_about_entries`.
     *
     * @param  Entry  $person  People collection entry
     * @return list<string> Entry id strings
     */
    public function computeEntries(Entry $person): array
    {
        // Reuse bundled computation so graph expansion runs at most once per entry
        return $this->computeBundle($person)['entries'];
    }

    /**
     * Compute strings for `knows_about_strings` (free-text research interests plus
     * `keywords` from each `subject_matter` entry listed in `knows_about_entries`).
     *
     * @param  Entry  $person  People collection entry
     * @return list<string> Non-empty trimmed strings, deduped in first-seen order
     */
    public function computeStrings(Entry $person): array
    {
        // Reuse bundled computation so graph expansion runs at most once per entry
        return $this->computeBundle($person)['strings'];
    }

    /**
     * Build or return memoized `terms`, `entries`, and `strings` for one person entry.
     *
     * Bundle `terms` values are deduped canonical Statamic term ids (`taxonomy::slug`).
     * Bundle `entries` lists clinical specializations, then the subject matter entries the
     * term walk reached, then the other related entries.
     * Bundle `strings` merges research free text with subject matter `keywords` list values.
     *
     * @param  Entry  $person  People collection entry
     * @return array{terms: list<string>, entries: list<string>, strings: list<string>}
     */
    private function computeBundle(Entry $person): array
    {
        // Resolve stable entry id for memoization
        $personId = $person->id();
        // A non-scalar id only needs one fixed memo key, whatever its text
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $entryId = \is_scalar($personId) ? (string) $personId : '';

        // Return the bundle memoized for this entry in this request, or compute it
        $bundle = RequestAttributeMemo::remember(
            self::REQUEST_BUNDLE_MEMO,
            $entryId,
            function () use ($person): array {
                // Walk the graph first so entries and strings can use the subjects it reached
                $graph = $this->computeGraphUncached($person);

                // Compute all three outputs once per entry id
                return [
                    'terms' => $graph['terms'],
                    'entries' => $this->computeEntriesUncached(
                        $person,
                        $graph['subjects']
                    ),
                    'strings' => $this->computeStringsUncached(
                        $person,
                        $graph['subjects']
                    ),
                ];
            }
        );

        /** @var array{terms: list<string>, entries: list<string>, strings: list<string>} $bundle */
        return $bundle;
    }

    /**
     * Walk the person's taxonomy graph without bundle memo.
     *
     * @param  Entry  $person  People collection entry
     * @return array{terms: list<string>, subjects: list<string>} Deduped `taxonomy::slug`
     *                                                            term ids, and deduped
     *                                                            entry ids from `subject_matter` fields,
     *                                                            each in first-seen order
     */
    private function computeGraphUncached(Entry $person): array
    {
        // Initialize ordered unique canonical term ids for output
        $termIds = [];

        // Track term ids already appended to preserve first-seen order
        $termSeen = [];

        // Initialize ordered unique subject matter entry ids for output
        $subjectIds = [];

        // Track subject matter entry ids already appended to preserve first-seen order
        $subjectSeen = [];

        // Initialize BFS queue of canonical term ids to expand
        $queue = [];

        // Track expanded term ids to avoid cycles
        $expanded = [];

        // Seed queue and direct subject matter from the person entry
        $this->seedKnowsAboutGraphFromPerson(
            $person,
            $queue,
            $subjectIds,
            $subjectSeen
        );

        // Drain the queue until all reachable taxonomy terms are expanded
        while ($queue !== []) {
            // Dequeue the next canonical term id
            $termId = \array_shift($queue);

            // Skip duplicate expansion attempts
            if (isset($expanded[$termId])) {
                continue;
            }

            // Mark this term id as expanded
            // Membership is read with isset(), which is also true for false
            // @pest-mutate-ignore: TrueToFalse
            $expanded[$termId] = true;

            // Resolve the concrete taxonomy term model
            $term = $this->resolveTaxonomyTerm($termId);

            // Skip missing or non-term resolutions
            if (! $term instanceof TaxonomyTerm) {
                continue;
            }

            // Read taxonomy handle for dispatch
            $taxonomy = $term->taxonomyHandle();

            // Dispatch expansion by taxonomy handle
            if ($taxonomy === 'fields_of_study') {
                $this->expandFieldOfStudyTerm(
                    $term,
                    $termIds,
                    $termSeen,
                    $subjectIds,
                    $subjectSeen
                );
            } elseif ($taxonomy === 'credentials') {
                $this->expandCredentialTerm(
                    $term,
                    $queue,
                    $subjectIds,
                    $subjectSeen
                );
            } elseif ($taxonomy === 'certifications') {
                $this->expandCertificationTerm(
                    $term,
                    $queue
                );
            } elseif (\in_array(
                $taxonomy,
                self::TAXONOMIES_MEDICAL_SPECIALTIES,
                // The handles compared are non-numeric strings, so a loose match is the same
                // @pest-mutate-ignore: TrueToFalse
                true
            )) {
                $this->expandMedicalSpecialtyTerm(
                    $term,
                    $termIds,
                    $termSeen,
                    $subjectIds,
                    $subjectSeen
                );
            } elseif (\in_array(
                $taxonomy,
                self::TAXONOMIES_RESEARCH_SPECIALTIES,
                // The handles compared are non-numeric strings, so a loose match is the same
                // @pest-mutate-ignore: TrueToFalse
                true
            )) {
                $this->expandResearchSpecialtyTerm(
                    $term,
                    $termIds,
                    $termSeen,
                    $subjectIds,
                    $subjectSeen
                );
            } elseif ($taxonomy === self::TAXONOMY_OTHER_SPECIALTIES) {
                $this->expandOtherSpecialtyTerm(
                    $term,
                    $termIds,
                    $termSeen,
                    $subjectIds,
                    $subjectSeen
                );
            } elseif ($taxonomy === 'professional_and_leadership_development_activities') {
                $this->expandProfessionalLeadershipActivityTerm(
                    $term,
                    $subjectIds,
                    $subjectSeen
                );
            }
        }

        return [
            'terms' => $termIds,
            'subjects' => $subjectIds,
        ];
    }

    /**
     * Compute related entry ids without bundle memoization.
     *
     * Clinical specializations come first, then the subject matter entries the term walk
     * reached, then the other related entries. The Person JSON-LD template emits the first
     * two groups and relies on them leading the list for its comma placement.
     *
     * @param  Entry  $person  People collection entry
     * @param  list<string>  $subjectIds  Subject matter entry ids from the term walk
     * @return list<string> Entry id strings
     */
    private function computeEntriesUncached(
        Entry $person,
        array $subjectIds
    ): array {
        // Initialize merged entry id list
        $ids = [];

        // Track ids already accepted to preserve first-seen order
        $seen = [];

        // Walk each configured entries field on the person
        foreach (self::ENTRIES_FIELD_TO_COLLECTION as $fieldHandle => $collectionHandle) {
            // Normalize raw entries field values to id strings
            $normalized = self::normalizeEntryIdsRaw(
                $person->get(
                    $fieldHandle,
                    []
                )
            );

            // Resolve each id when it belongs to the expected collection
            foreach ($normalized as $entryId) {
                // Skip ids already merged
                if (isset($seen[$entryId])) {
                    continue;
                }

                // Load the entry by id
                $related = EntryFacade::find($entryId);

                // Skip non-concrete entries and wrong collections
                if (! $related instanceof Entry) {
                    continue;
                }

                // Skip entries that do not belong to the expected collection
                if ($related->collectionHandle() !== $collectionHandle) {
                    continue;
                }

                // Record the accepted id
                // Membership is read with isset(), which is also true for false
                // @pest-mutate-ignore: TrueToFalse
                $seen[$entryId] = true;

                // Append the id to the merged list
                $ids[] = $entryId;
            }

            // Place the subject matter entries directly after the clinical specializations
            if ($fieldHandle === 'clinical_specializations') {
                foreach ($subjectIds as $subjectId) {
                    // Skip ids already merged
                    if (isset($seen[$subjectId])) {
                        continue;
                    }

                    // Record and append the subject entry id
                    // Membership is read with isset(), which is also true for false
                    // @pest-mutate-ignore: TrueToFalse
                    $seen[$subjectId] = true;
                    $ids[] = $subjectId;
                }
            }
        }

        return $ids;
    }

    /**
     * Compute free-text research strings and subject matter keyword strings.
     *
     * @param  Entry  $person  People collection entry
     * @param  list<string>  $subjectIds  Subject matter entry ids from `computeGraphUncached`
     * @return list<string> Non-empty trimmed strings
     */
    private function computeStringsUncached(
        Entry $person,
        array $subjectIds
    ): array {
        // Initialize ordered unique strings
        $out = [];

        // Track strings already appended
        $seen = [];

        // Normalize replicator rows for research interests
        $rows = $this->normalizeReplicatorRows(
            $person->get(
                'research_interests',
                []
            )
        );

        // Walk each research interest row
        foreach ($rows as $row) {
            // Check whether this row is free-text research interest
            if (($row['type'] ?? null) !== 'free_text') {
                continue;
            }

            // Read the text value when present
            $text = $row['text'] ?? null;

            // Skip non-string values
            if (! \is_string($text)) {
                continue;
            }

            // Trim whitespace from the text
            $trimmed = \trim($text);

            // Skip empty strings after trimming
            if ($trimmed === '') {
                continue;
            }

            // Skip duplicates
            if (isset($seen[$trimmed])) {
                continue;
            }

            // Mark the string as seen
            // Membership is read with isset(), which is also true for false
            // @pest-mutate-ignore: TrueToFalse
            $seen[$trimmed] = true;

            // Append the trimmed string
            $out[] = $trimmed;
        }

        // Merge `keywords` list values from each subject matter entry the walk reached
        $this->mergeSubjectMatterKeywordsIntoStrings(
            $subjectIds,
            $out,
            $seen
        );

        return $out;
    }

    /**
     * Append trimmed `keywords` list entries from subject matter entries into string output.
     *
     * @param  list<string>  $subjectIds  Deduped entry ids from `subject_matter` fields
     * @param  list<string>  $out  Outbound ordered string list
     * @param  array<string, true>  $seen  Outbound seen map for dedupe
     */
    private function mergeSubjectMatterKeywordsIntoStrings(
        array $subjectIds,
        array &$out,
        array &$seen
    ): void {
        // Walk each subject matter entry id the term walk reached
        foreach ($subjectIds as $subjectId) {
            // Resolve the subject matter entry
            $subject = $this->resolveSubjectMatterEntry($subjectId);

            // Skip missing entries
            // Every subject id here was resolved by the graph walk earlier in this request
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $subject instanceof Entry) {
                continue;
            }

            // Read the stored `keywords` list field
            $rawKeywords = $subject->get('keywords');

            // Flatten list storage into trimmed string tokens
            foreach (
                self::flattenKeywordsListRaw($rawKeywords) as $keyword
            ) {
                // Skip empty keywords after trimming
                // flattenKeywordsListRaw() never returns an empty keyword
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                if ($keyword === '') {
                    continue;
                }

                // Skip duplicate keywords across terms and free-text strings
                if (isset($seen[$keyword])) {
                    continue;
                }

                // Mark the keyword as seen
                // Membership is read with isset(), which is also true for false
                // @pest-mutate-ignore: TrueToFalse
                $seen[$keyword] = true;

                // Append the keyword string
                $out[] = $keyword;
            }
        }
    }

    /**
     * Flatten a Statamic `list` field raw value into trimmed non-empty strings.
     *
     * @param  mixed  $raw  Raw `keywords` field value from a term
     * @return list<string> Trimmed keyword strings
     */
    public static function flattenKeywordsListRaw(mixed $raw): array
    {
        // Unwrap Statamic Value objects when present
        if ($raw instanceof Value) {
            $raw = $raw->value();
        }

        // Unwrap Laravel collections to plain arrays
        if ($raw instanceof IlluminateCollection) {
            $raw = $raw->all();
        }

        // Treat null and empty string as empty lists
        // null and '' fall through to the same empty list
        // @pest-mutate-ignore: BooleanOrToBooleanAnd, EmptyStringToNotEmpty, RemoveEarlyReturn
        if ($raw === null || $raw === '') {
            return [];
        }

        // Accept a single string as a one-item list
        if (\is_string($raw)) {
            $trimmed = \trim($raw);

            return $trimmed === ''
                ? []
                : [$trimmed];
        }

        // Reject non-list shapes
        if (! \is_array($raw)) {
            return [];
        }

        // Collect trimmed string entries from list rows
        $out = [];

        // Walk each list element
        foreach ($raw as $item) {
            // Unwrap nested Value objects
            if ($item instanceof Value) {
                $item = $item->value();
            }

            // Accept plain string list items
            if (\is_string($item)) {
                $trimmed = \trim($item);

                // Skip empty strings after trimming
                if ($trimmed === '') {
                    continue;
                }

                // Append the trimmed string
                $out[] = $trimmed;

                continue;
            }

            // Accept associative list rows with a string `value` key
            if (
                \is_array($item)
                && isset($item['value'])
                && \is_string($item['value'])
            ) {
                $trimmed = \trim($item['value']);

                // Skip empty strings after trimming
                if ($trimmed === '') {
                    continue;
                }

                // Append the trimmed string
                $out[] = $trimmed;
            }
        }

        return $out;
    }

    /**
     * Normalize a raw `terms` / `terms_search_index` value to canonical term ids.
     *
     * @param  mixed  $raw  Raw field value from entry or term data
     * @param  ?string  $defaultTaxonomyHandle  Taxonomy handle for bare slugs, or null
     * @return list<string> Deduped `taxonomy::slug` strings
     */
    public static function normalizeTermFieldRaw(
        mixed $raw,
        ?string $defaultTaxonomyHandle
    ): array {
        // Unwrap Statamic Value objects when present
        if ($raw instanceof Value) {
            $raw = $raw->value();
        }

        // Flatten scalars and nested arrays to string tokens
        $tokens = self::flattenTermishScalars($raw);

        // Build canonical ids with dedupe
        $ids = [];

        // Track ids already accepted
        $seen = [];

        // Normalize each token to a canonical id
        foreach ($tokens as $token) {
            // Skip empty tokens after trimming
            // flattenTermishScalars() never returns an empty token
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($token === '') {
                continue;
            }

            // Build canonical id from token and default taxonomy
            $id = self::canonicalizeTermId(
                $token,
                $defaultTaxonomyHandle
            );

            // Skip tokens that cannot be canonicalized
            if ($id === null) {
                continue;
            }

            // Skip duplicate ids
            if (isset($seen[$id])) {
                continue;
            }

            // Mark id as seen
            // Membership is read with isset(), which is also true for false
            // @pest-mutate-ignore: TrueToFalse
            $seen[$id] = true;

            // Append canonical id
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Normalize a raw `entries` field value to deduped id strings.
     *
     * @param  mixed  $raw  Raw entries field value
     * @return list<string> Entry id strings
     */
    public static function normalizeEntryIdsRaw(mixed $raw): array
    {
        // Unwrap Statamic Value objects when present
        if ($raw instanceof Value) {
            $raw = $raw->value();
        }

        // Unwrap Laravel collections to plain arrays
        if ($raw instanceof IlluminateCollection) {
            $raw = $raw->all();
        }

        // Treat null and empty string as empty lists
        // null falls through to the non-list check, and '' becomes one empty id that is skipped
        // @pest-mutate-ignore: BooleanOrToBooleanAnd, EmptyStringToNotEmpty, RemoveEarlyReturn
        if ($raw === null || $raw === '') {
            return [];
        }

        // Wrap a single string id as a one-element list
        if (\is_string($raw)) {
            $raw = [$raw];
        }

        // Reject non-list shapes after coercion
        if (! \is_array($raw)) {
            return [];
        }

        // Collect deduped non-empty string ids
        $ids = [];

        // Track ids already accepted
        $seen = [];

        // Walk each raw element
        foreach ($raw as $item) {
            // Unwrap nested Value objects
            if ($item instanceof Value) {
                $item = $item->value();
            }

            // Skip non-string ids
            if (! \is_string($item)) {
                continue;
            }

            // Trim whitespace from the id
            $id = \trim($item);

            // Skip empty ids after trimming
            if ($id === '') {
                continue;
            }

            // Skip duplicate ids
            if (isset($seen[$id])) {
                continue;
            }

            // Mark id as seen
            // Membership is read with isset(), which is also true for false
            // @pest-mutate-ignore: TrueToFalse
            $seen[$id] = true;

            // Append id
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Seed the expansion queue and direct subject matter from person-level fields.
     *
     * @param  Entry  $person  People collection entry
     * @param  list<string>  $queue  Outbound BFS queue of canonical term ids
     * @param  list<string>  $subjectIds  Outbound entry ids from `subject_matter` fields
     * @param  array<string, true>  $subjectSeen  Outbound seen map for subject entry ids
     */
    private function seedKnowsAboutGraphFromPerson(
        Entry $person,
        array &$queue,
        array &$subjectIds,
        array &$subjectSeen
    ): void {
        // Enqueue root credentials taxonomy terms (Section C)
        foreach (
            self::normalizeTermFieldRaw(
                $person->get(
                    'credentials',
                    []
                ),
                'credentials'
            ) as $id
        ) {
            $this->enqueueTermId(
                $queue,
                $id
            );
        }

        // Enqueue each clinical specialization entry's training specialty terms (Section A)
        foreach (
            self::normalizeEntryIdsRaw(
                $person->get(
                    'clinical_specializations',
                    []
                )
            ) as $entryId
        ) {
            $this->enqueueClinicalSpecializationTrainingSpecialties(
                $entryId,
                $queue
            );
        }

        // Walk education replicator rows for post-secondary seeds
        foreach (
            $this->normalizeReplicatorRows(
                $person->get(
                    'education',
                    []
                )
            ) as $row
        ) {
            // Restrict to post-secondary education sets
            if (($row['type'] ?? null) !== 'post_secondary') {
                continue;
            }

            // Enqueue credential degree terms (Section C)
            foreach (
                self::normalizeTermFieldRaw(
                    $row['activity'] ?? null,
                    'credentials'
                ) as $id
            ) {
                $this->enqueueTermId(
                    $queue,
                    $id
                );
            }

            // Enqueue field of study terms (Section B)
            foreach (
                self::normalizeTermFieldRaw(
                    $row['specialty'] ?? null,
                    'fields_of_study'
                ) as $id
            ) {
                $this->enqueueTermId(
                    $queue,
                    $id
                );
            }
        }

        // Walk certifications rows and enqueue activity terms (Section D)
        foreach (
            $this->normalizeReplicatorRows(
                $person->get(
                    'certifications',
                    []
                )
            ) as $row
        ) {
            // Enqueue certification activity terms when present
            foreach (
                self::normalizeTermFieldRaw(
                    $row['activity'] ?? null,
                    'certifications'
                ) as $id
            ) {
                $this->enqueueTermId(
                    $queue,
                    $id
                );
            }
        }

        // Walk professional and leadership development rows (Section F seeds)
        foreach (
            $this->normalizeReplicatorRows(
                $person->get(
                    'professional_and_leadership_development_activities',
                    []
                )
            ) as $row
        ) {
            // Enqueue PLD activity taxonomy terms
            foreach (
                self::normalizeTermFieldRaw(
                    $row['activity'] ?? null,
                    'professional_and_leadership_development_activities'
                ) as $id
            ) {
                $this->enqueueTermId(
                    $queue,
                    $id
                );
            }
        }

        // Append controlled vocabulary subject matter directly (Section G)
        foreach (
            $this->normalizeReplicatorRows(
                $person->get(
                    'research_interests',
                    []
                )
            ) as $row
        ) {
            // Restrict to controlled vocabulary sets
            if (($row['type'] ?? null) !== 'controlled_vocabulary') {
                continue;
            }

            // Merge subject matter entry ids from the row (Section G)
            $this->appendSubjectMatterEntryIdsFromRaw(
                $row['subject_matter'] ?? null,
                $subjectIds,
                $subjectSeen
            );
        }
    }

    /**
     * Section A: enqueue the `training_specialty` terms a clinical specialization names.
     *
     * The specialization itself is listed in `knows_about_entries` by
     * {@see self::computeEntriesUncached()}; only its training specialty terms join
     * the term walk.
     *
     * @param  string  $entryId  Stored `clinical_specializations` entry id
     * @param  list<string>  $queue  Outbound BFS queue
     */
    private function enqueueClinicalSpecializationTrainingSpecialties(
        string $entryId,
        array &$queue
    ): void {
        // Load the specialization entry by id
        $specialization = EntryFacade::find($entryId);

        // Skip ids that are not clinical specialization entries
        if (
            ! $specialization instanceof Entry
            || $specialization->collectionHandle() !== 'clinical_specializations'
        ) {
            return;
        }

        // Enqueue each training specialty taxonomy term (Section E.1 targets)
        foreach (
            self::normalizeTermFieldRaw(
                $specialization->get('training_specialty'),
                null
            ) as $id
        ) {
            $this->enqueueTermId(
                $queue,
                $id
            );
        }
    }

    /**
     * Section B: append entry ids from `subject_matter` fields, then add this field of study term.
     *
     * @param  TaxonomyTerm  $term  Field of study term
     * @param  list<string>  $termIds  Outbound canonical term ids
     * @param  array<string, true>  $termSeen  Outbound seen map
     * @param  list<string>  $subjectIds  Outbound entry ids from `subject_matter` fields
     * @param  array<string, true>  $subjectSeen  Outbound seen map for subject entry ids
     */
    private function expandFieldOfStudyTerm(
        TaxonomyTerm $term,
        array &$termIds,
        array &$termSeen,
        array &$subjectIds,
        array &$subjectSeen
    ): void {
        // Read subject matter references from the default locale
        $raw = $this->readTermRawField(
            $term,
            'subject_matter'
        );

        // Append the subject matter entry ids (Section G)
        $this->appendSubjectMatterEntryIdsFromRaw(
            $raw,
            $subjectIds,
            $subjectSeen
        );

        // Resolve the term id, matching PHP scalar coercion (see computeBundle)
        $termId = $term->id();

        // Add this fields_of_study taxonomy term to the output list
        $this->appendKnowsAboutCanonicalTermId(
            // A term id is always a taxonomy::slug string
            // @pest-mutate-ignore: RemoveStringCast, EmptyStringToNotEmpty
            \is_scalar($termId) ? (string) $termId : '',
            $termIds,
            $termSeen
        );
    }

    /**
     * Section C: append credential `subject_matter` and enqueue `certifications_term`.
     *
     * @param  TaxonomyTerm  $term  Credential term
     * @param  list<string>  $queue  Outbound BFS queue
     * @param  list<string>  $subjectIds  Outbound entry ids from `subject_matter` fields
     * @param  array<string, true>  $subjectSeen  Outbound seen map for subject entry ids
     */
    private function expandCredentialTerm(
        TaxonomyTerm $term,
        array &$queue,
        array &$subjectIds,
        array &$subjectSeen
    ): void {
        // Append subject matter entry references from the credential
        $this->appendSubjectMatterEntryIdsFromRaw(
            $this->readTermRawField(
                $term,
                'subject_matter'
            ),
            $subjectIds,
            $subjectSeen
        );

        // Enqueue related certifications taxonomy terms when present
        foreach (
            self::normalizeTermFieldRaw(
                $this->readTermRawField(
                    $term,
                    'certifications_term'
                ),
                'certifications'
            ) as $id
        ) {
            $this->enqueueTermId(
                $queue,
                $id
            );
        }
    }

    /**
     * Section D: enqueue specialty taxonomy terms from a certification term.
     *
     * @param  TaxonomyTerm  $term  Certification taxonomy term
     * @param  list<string>  $queue  Outbound BFS queue
     */
    private function expandCertificationTerm(
        TaxonomyTerm $term,
        array &$queue
    ): void {
        // Enqueue medical specialty fields (Section E.1)
        foreach (self::CERTIFICATION_FIELD_HANDLES_E1 as $handle) {
            foreach (
                self::normalizeTermFieldRaw(
                    $this->readTermRawField(
                        $term,
                        $handle
                    ),
                    null
                ) as $id
            ) {
                $this->enqueueTermId(
                    $queue,
                    $id
                );
            }
        }

        // Enqueue research specialty fields (Section E.2)
        foreach (self::CERTIFICATION_FIELD_HANDLES_E2 as $handle) {
            foreach (
                self::normalizeTermFieldRaw(
                    $this->readTermRawField(
                        $term,
                        $handle
                    ),
                    null
                ) as $id
            ) {
                $this->enqueueTermId(
                    $queue,
                    $id
                );
            }
        }

        // Enqueue other specialty fields (Section E.3)
        foreach (self::CERTIFICATION_FIELD_HANDLES_E3 as $handle) {
            foreach (
                self::normalizeTermFieldRaw(
                    $this->readTermRawField(
                        $term,
                        $handle
                    ),
                    null
                ) as $id
            ) {
                $this->enqueueTermId(
                    $queue,
                    $id
                );
            }
        }
    }

    /**
     * Section E.1: append entry ids from `subject_matter` fields, then add this medical specialty term.
     *
     * @param  TaxonomyTerm  $term  Medical specialty term
     * @param  list<string>  $termIds  Outbound canonical term ids
     * @param  array<string, true>  $termSeen  Outbound seen map
     * @param  list<string>  $subjectIds  Outbound entry ids from `subject_matter` fields
     * @param  array<string, true>  $subjectSeen  Outbound seen map for subject entry ids
     */
    private function expandMedicalSpecialtyTerm(
        TaxonomyTerm $term,
        array &$termIds,
        array &$termSeen,
        array &$subjectIds,
        array &$subjectSeen
    ): void {
        // Append subject matter references from the specialty term (Section G)
        $this->appendSubjectMatterEntryIdsFromRaw(
            $this->readTermRawField(
                $term,
                'subject_matter'
            ),
            $subjectIds,
            $subjectSeen
        );

        // Resolve the term id, matching PHP scalar coercion (see computeBundle)
        $termId = $term->id();

        // Add this medical specialty taxonomy term to the output list
        $this->appendKnowsAboutCanonicalTermId(
            // A term id is always a taxonomy::slug string
            // @pest-mutate-ignore: RemoveStringCast, EmptyStringToNotEmpty
            \is_scalar($termId) ? (string) $termId : '',
            $termIds,
            $termSeen
        );
    }

    /**
     * Section E.2: append entry ids from `subject_matter` fields, then add this research specialty term.
     *
     * @param  TaxonomyTerm  $term  Research specialty term
     * @param  list<string>  $termIds  Outbound canonical term ids
     * @param  array<string, true>  $termSeen  Outbound seen map
     * @param  list<string>  $subjectIds  Outbound entry ids from `subject_matter` fields
     * @param  array<string, true>  $subjectSeen  Outbound seen map for subject entry ids
     */
    private function expandResearchSpecialtyTerm(
        TaxonomyTerm $term,
        array &$termIds,
        array &$termSeen,
        array &$subjectIds,
        array &$subjectSeen
    ): void {
        // Append subject matter references from the research specialty term (Section G)
        $this->appendSubjectMatterEntryIdsFromRaw(
            $this->readTermRawField(
                $term,
                'subject_matter'
            ),
            $subjectIds,
            $subjectSeen
        );

        // Resolve the term id, matching PHP scalar coercion (see computeBundle)
        $termId = $term->id();

        // Add this research specialty taxonomy term to the output list
        $this->appendKnowsAboutCanonicalTermId(
            // A term id is always a taxonomy::slug string
            // @pest-mutate-ignore: RemoveStringCast, EmptyStringToNotEmpty
            \is_scalar($termId) ? (string) $termId : '',
            $termIds,
            $termSeen
        );
    }

    /**
     * Section E.3: append entry ids from `subject_matter` fields, then add this other-specialty term.
     *
     * @param  TaxonomyTerm  $term  Other specialty term
     * @param  list<string>  $termIds  Outbound canonical term ids
     * @param  array<string, true>  $termSeen  Outbound seen map
     * @param  list<string>  $subjectIds  Outbound entry ids from `subject_matter` fields
     * @param  array<string, true>  $subjectSeen  Outbound seen map for subject entry ids
     */
    private function expandOtherSpecialtyTerm(
        TaxonomyTerm $term,
        array &$termIds,
        array &$termSeen,
        array &$subjectIds,
        array &$subjectSeen
    ): void {
        // Append subject matter references from the other specialty term (Section G)
        $this->appendSubjectMatterEntryIdsFromRaw(
            $this->readTermRawField(
                $term,
                'subject_matter'
            ),
            $subjectIds,
            $subjectSeen
        );

        // Resolve the term id, matching PHP scalar coercion (see computeBundle)
        $termId = $term->id();

        // Add this other_specialties taxonomy term to the output list
        $this->appendKnowsAboutCanonicalTermId(
            // A term id is always a taxonomy::slug string
            // @pest-mutate-ignore: RemoveStringCast, EmptyStringToNotEmpty
            \is_scalar($termId) ? (string) $termId : '',
            $termIds,
            $termSeen
        );
    }

    /**
     * Section F: append `subject_matter` from a PLD activity taxonomy term.
     *
     * @param  TaxonomyTerm  $term  PLD activity term
     * @param  list<string>  $subjectIds  Outbound entry ids from `subject_matter` fields
     * @param  array<string, true>  $subjectSeen  Outbound seen map for subject entry ids
     */
    private function expandProfessionalLeadershipActivityTerm(
        TaxonomyTerm $term,
        array &$subjectIds,
        array &$subjectSeen
    ): void {
        // Append subject matter references from the PLD activity term (Section G)
        $this->appendSubjectMatterEntryIdsFromRaw(
            $this->readTermRawField(
                $term,
                'subject_matter'
            ),
            $subjectIds,
            $subjectSeen
        );
    }

    /**
     * Append deduped entry ids from `subject_matter` fields from a raw subject matter field value (Section G).
     *
     * Only ids that resolve to an entry in the `subject_matter` or `clinical_specializations`
     * collection are kept, so a stale id or a legacy `subject_matter::slug` term id left in
     * stored data is dropped. A clinical concept a field points at lives in the second since
     * UAMS-Web/uams-statamic-archive#2823, and dropping it would drop the concept from the person's knowsAbout.
     *
     * @param  mixed  $raw  Raw subject matter field value
     * @param  list<string>  $subjectIds  Outbound entry ids from `subject_matter` fields
     * @param  array<string, true>  $subjectSeen  Outbound seen map for subject entry ids
     */
    private function appendSubjectMatterEntryIdsFromRaw(
        mixed $raw,
        array &$subjectIds,
        array &$subjectSeen
    ): void {
        // Walk each stored entry id
        foreach (self::normalizeEntryIdsRaw($raw) as $id) {
            // Skip ids already appended
            if (isset($subjectSeen[$id])) {
                continue;
            }

            // Skip ids that are not subject matter entries
            if (! $this->resolveSubjectMatterEntry($id) instanceof Entry) {
                continue;
            }

            // Record and append the subject matter entry id
            // Membership is read with isset(), which is also true for false
            // @pest-mutate-ignore: TrueToFalse
            $subjectSeen[$id] = true;
            $subjectIds[] = $id;
        }
    }

    /**
     * Resolve an id to a published entry in the `subject_matter` or `clinical_specializations`
     * collection, the two a `subject_matter` field can point at (UAMS-Web/uams-statamic-archive#2823).
     *
     * A draft subject is skipped, as the Person JSON-LD skips it: `knows_about_entries`
     * augments through Statamic's published-only entries query, so a draft's keywords
     * would otherwise reach `knows_about_strings` without its DefinedTerm.
     *
     * @param  string  $id  Stored entry id
     * @return Entry|null The subject matter or clinical specialization entry, or null when the id names anything else
     */
    private function resolveSubjectMatterEntry(string $id): ?Entry
    {
        // Load the entry by id
        $entry = EntryFacade::find($id);

        // Reject non-entries, entries in other collections, and drafts
        if (
            ! $entry instanceof Entry
            || ! \in_array(
                $entry->collectionHandle(),
                [self::COLLECTION_SUBJECT_MATTER, self::COLLECTION_CLINICAL_SPECIALIZATIONS],
                // The handles compared are non-numeric strings, so a loose match is the same
                // @pest-mutate-ignore: TrueToFalse
                true
            )
            || ! $entry->published()
        ) {
            return null;
        }

        return $entry;
    }

    /**
     * Append one canonical `taxonomy::slug` term id when non-empty and not yet seen.
     *
     * @param  string  $canonicalId  Statamic term id (`taxonomy::slug`)
     * @param  list<string>  $termIds  Outbound canonical term ids
     * @param  array<string, true>  $termSeen  Outbound seen map
     */
    private function appendKnowsAboutCanonicalTermId(
        string $canonicalId,
        array &$termIds,
        array &$termSeen
    ): void {
        // Skip empty ids
        // Ids here come from a loaded term's id(), which is always taxonomy::slug
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if ($canonicalId === '') {
            return;
        }

        // Skip duplicate ids
        if (isset($termSeen[$canonicalId])) {
            return;
        }

        // Mark id as seen
        // Membership is read with isset(), which is also true for false
        // @pest-mutate-ignore: TrueToFalse
        $termSeen[$canonicalId] = true;

        // Append id to the ordered output list
        $termIds[] = $canonicalId;
    }

    /**
     * Enqueue a canonical term id when non-empty.
     *
     * @param  list<string>  $queue  Outbound BFS queue
     * @param  string  $termId  Canonical `taxonomy::slug` id
     */
    private function enqueueTermId(
        array &$queue,
        string $termId
    ): void {
        // Skip empty ids
        // Queued ids are never empty, because normalizeTermFieldRaw() skips empty tokens
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if ($termId === '') {
            return;
        }

        // Append id to the queue tail
        $queue[] = $termId;
    }

    /**
     * Read a raw field value from a term in the default locale.
     *
     * @param  TaxonomyTerm  $term  Taxonomy term model
     * @param  string  $handle  Blueprint field handle
     * @return mixed Raw field value or null when unreadable
     */
    private function readTermRawField(
        TaxonomyTerm $term,
        string $handle
    ): mixed {
        // Attempt to read the field from the default locale term
        try {
            /** @var LocalizedTerm $localized */
            $localized = $term->inDefaultLocale();

            return $localized->get($handle);
        } catch (\Throwable) {
            // Return null when blueprint access fails for this term
            return null;
        }
    }

    /**
     * Resolve a canonical term id to a concrete taxonomy term model.
     *
     * @param  string  $termId  Canonical `taxonomy::slug` id
     * @return TaxonomyTerm|null Concrete term or null when missing
     */
    private function resolveTaxonomyTerm(
        string $termId
    ): ?TaxonomyTerm {
        // Look up Statamic data by canonical id
        $found = Term::find($termId);

        // Return null when nothing is found
        // null falls through to the final return null
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($found === null) {
            return null;
        }

        // Unwrap localized term facades to the shared taxonomy term
        if ($found instanceof LocalizedTerm) {
            $inner = $found->term();

            return $inner instanceof TaxonomyTerm ? $inner : null;
        }

        // Return concrete taxonomy terms directly
        if ($found instanceof TaxonomyTerm) {
            return $found;
        }

        // Reject unexpected find results
        return null;
    }

    /**
     * Normalize replicator stored values to a plain list of rows.
     *
     * @param  mixed  $stored  Raw replicator field value
     * @return list<array<string, mixed>> List of row arrays
     */
    private function normalizeReplicatorRows(mixed $stored): array
    {
        // Unwrap Statamic Value objects when present
        if ($stored instanceof Value) {
            $stored = $stored->value();
        }

        // Unwrap Laravel collections to plain arrays
        if ($stored instanceof IlluminateCollection) {
            $stored = $stored->all();
        }

        // Treat non-arrays as empty replicator payloads
        if (! \is_array($stored)) {
            return [];
        }

        // Filter to array rows only
        $rows = [];

        // Walk each replicator element
        foreach ($stored as $row) {
            // Keep array rows for downstream set/type checks
            if (\is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Flatten nested list-ish term storage into trimmed string tokens.
     *
     * @param  mixed  $raw  Arbitrary nested array/scalar mix
     * @return list<string> String tokens (may include taxonomy::slug or bare slugs)
     */
    private static function flattenTermishScalars(mixed $raw): array
    {
        // Treat null and empty string as empty token lists
        // null and '' fall through to the same empty list
        // @pest-mutate-ignore: BooleanOrToBooleanAnd, EmptyStringToNotEmpty, RemoveEarlyReturn
        if ($raw === null || $raw === '') {
            return [];
        }

        // Unwrap Statamic Value objects when present
        if ($raw instanceof Value) {
            $raw = $raw->value();
        }

        // Unwrap Laravel collections to plain arrays
        if ($raw instanceof IlluminateCollection) {
            $raw = $raw->all();
        }

        // Accept string tokens directly after trimming
        if (\is_string($raw)) {
            $trimmed = \trim($raw);

            // Return empty list for whitespace-only strings
            // An empty token is skipped by normalizeTermFieldRaw() anyway
            // @pest-mutate-ignore: EmptyStringToNotEmpty, RemoveEarlyReturn
            if ($trimmed === '') {
                return [];
            }

            return [$trimmed];
        }

        // Recurse into array elements
        if (\is_array($raw)) {
            $out = [];

            // Walk each nested element
            foreach ($raw as $value) {
                // Merge nested tokens into the output list
                foreach (self::flattenTermishScalars($value) as $token) {
                    $out[] = $token;
                }
            }

            return $out;
        }

        // Ignore unsupported scalar types
        return [];
    }

    /**
     * Build a canonical term id from a flattened token and default taxonomy.
     *
     * @param  string  $token  Trimmed token from flattened storage
     * @param  ?string  $defaultTaxonomyHandle  Taxonomy handle for bare slugs
     * @return ?string Canonical `taxonomy::slug` or null when unusable
     */
    private static function canonicalizeTermId(
        string $token,
        ?string $defaultTaxonomyHandle
    ): ?string {
        // Keep already canonical ids
        if (\str_contains(
            $token,
            '::'
        )) {
            return $token;
        }

        // Require a default taxonomy for bare slugs
        if (
            $defaultTaxonomyHandle === null
            || $defaultTaxonomyHandle === ''
        ) {
            return null;
        }

        // Build canonical id for bare slug tokens
        return sprintf('%s::%s', $defaultTaxonomyHandle, $token);
    }
}
