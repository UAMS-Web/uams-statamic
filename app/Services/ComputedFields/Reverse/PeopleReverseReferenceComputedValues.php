<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Reverse;

use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;

/**
 * The reverse-reference computeds for the `people` collection (UAMS-Web/uams-statamic-archive#1374).
 *
 * Directional `entries` pointers aim at a person but expose no reverse, so a person
 * cannot answer "what credits me?" or "which provider record wraps me?":
 *
 * - **Credited content** — content credits a person through the `creator` group, whose
 *   per-role sub-fields (`writer`, `editor`, `host`, `guest`, `videographer`, …) are each
 *   an `entries` pointer to `people`. The group is authored on the content-bearing
 *   collections `posts`, `clinical_resources`, and `knowledge_bases`, at TWO stored
 *   locations: a **top-level** `creator` group (text posts, KB / clinical-resource
 *   articles) and, on video posts, a `creator` group nested **inside each `segments`
 *   replicator set** (a per-segment videographer / host / guest / … credit). The reverse
 *   is the content this person is credited on in any creator role, matched by deep-collecting
 *   the person ids nested in BOTH fields (hence {@see ReverseEntryReferenceResolver::resolveNested()}).
 *   Scanning the whole `segments` value is safe: segments reference assets and URLs, never a
 *   `people` entry except through their creator group, so no non-credit id can false-match.
 * - **Clinical-provider entry** — `person` (`max_items: 1`) on a `clinical_providers`
 *   provider record names the person it represents. The reverse is effectively one-to-one:
 *   the provider entry that wraps this person (a zero- or one-element set).
 *
 * Both are derived on read; nothing is stored back on the person.
 *
 * ## Why "credited content", not "authored posts"
 *
 * The relationship audit (UAMS-Web/uams-statamic-archive#1374) framed this as "authored posts" via a single top-level
 * `creator_person` pointer, but the actual model is a multi-role `creator` **group** shared
 * across three content collections. The reverse therefore spans all three collections and
 * counts every creator role, not just authorship of a `posts` entry — so the computed is
 * named for what it is.
 */
class PeopleReverseReferenceComputedValues
{
    /**
     * Content collections whose entries credit people through the `creator` group.
     *
     * @var list<string>
     */
    private const array CREDITED_CONTENT_SOURCE_COLLECTIONS = [
        'posts',
        'clinical_resources',
        'knowledge_bases',
    ];

    /**
     * The fields whose nested per-role `entries` sub-fields point at people: the
     * top-level `creator` group, and the `segments` replicator that carries a per-segment
     * `creator` group on video posts. Deep-collected together (see the class docblock).
     *
     * @var list<string>
     */
    private const array CREDITED_CONTENT_FIELDS = [
        'creator',
        'segments',
    ];

    /**
     * The collection whose entries wrap a person as a clinical-provider record.
     */
    private const string PROVIDER_SOURCE_COLLECTION = 'clinical_providers';

    /**
     * The provider-to-person pointer matched for the provider-entry reverse.
     */
    private const string PROVIDER_PERSON_FIELD = 'person';

    /**
     * @param  ReverseEntryReferenceResolver  $resolver  Resolves each reverse pointer.
     */
    public function __construct(
        private ReverseEntryReferenceResolver $resolver
    ) {}

    /**
     * Published `posts` / `clinical_resources` / `knowledge_bases` entries that credit
     * this person in any `creator` role (writer, editor, host, guest, videographer, …),
     * whether the credit is on the top-level `creator` group or nested per-segment.
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveCreditedContent(
        Entry $entry
    ): EntryCollection {
        return $this->resolver->resolveNested(
            self::CREDITED_CONTENT_SOURCE_COLLECTIONS,
            self::CREDITED_CONTENT_FIELDS,
            $entry->id()
        );
    }

    /**
     * The published `clinical_providers` entry that wraps this person (its `person`
     * pointer names this entry) — a zero- or one-element set, as the link is 1:1.
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveClinicalProviderEntry(
        Entry $entry
    ): EntryCollection {
        return $this->resolver->resolve(
            self::PROVIDER_SOURCE_COLLECTION,
            self::PROVIDER_PERSON_FIELD,
            $entry->id()
        );
    }
}
