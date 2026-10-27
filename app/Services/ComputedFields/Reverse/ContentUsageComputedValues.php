<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Reverse;

use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;

/**
 * The content-usage ("used by") computeds for the transcludable source collections
 * `reusable_content`, `tables`, and `calculators` (UAMS-Web/uams-statamic-archive#1375).
 *
 * These entries are one-way content-includes: a page transcludes one by id through a
 * page-builder set (`reusable_content` via the reusable-content block/set's `content`
 * field, `tables` via `uamsweb_set_table.table_entry`, `calculators` via
 * `uamsweb_set_calculator.calculator_entry`), but the source entry cannot tell whether
 * — or where — it is in use. The reverse makes that visible so an editor can see usage
 * before editing or unpublishing a shared entry.
 *
 * ## Mechanism: a nested scan of the page-builder fields, not a `where` lookup
 *
 * The transclusion id is buried inside a Bard/replicator page-builder value, not stored
 * as a top-level field, so `where('field', id)` cannot find it (see the UAMS-Web/uams-statamic-archive#1360 audit note
 * on the ticket). Instead this deep-scans the page-builder fields via
 * {@see ReverseEntryReferenceResolver::resolveNested()}, which collects every entry id
 * nested at any depth in those values and matches membership in PHP. Because the target
 * id is a unique entry id and these source entries are referenced *only* through
 * transclusion, an occurrence anywhere in a page builder is a genuine use.
 *
 * Scanning is scoped to the {@see PAGE_BUILDER_FIELDS} handles — deliberately NOT the
 * whole entry — so a localization's `origin` back-pointer (this is a multi-site install)
 * is never mistaken for a transclusion. The handle list is the single maintenance point:
 * every content collection stores its page builder under one of `content` / `body` /
 * `page_builder`, so scanning across all collections needs no per-collection allow-list
 * and cannot silently miss a new content collection.
 *
 * Reverse scans are not free (UAMS-Web/uams-statamic-archive#937); both computeds share one memoized scan per request,
 * and the fields are `visibility: computed` (edit-form only), not listing columns.
 */
class ContentUsageComputedValues
{
    /**
     * The page-builder field handles whose nested Bard/replicator values can carry a
     * transclusion. Every embedding collection stores its page builder under one of
     * these; an entry lacking a given field simply contributes no ids.
     *
     * @var list<string>
     */
    private const array PAGE_BUILDER_FIELDS = [
        'content',       // the `article_with_sets` Bard on basic pages
        'body',          // the `article_with_sets` Bard on `tables` entries
        'page_builder',  // the `page_builder` replicator on landing pages and FAD profiles
    ];

    /**
     * @param  ReverseEntryReferenceResolver  $resolver  Deep-scans the page-builder fields.
     */
    public function __construct(
        private ReverseEntryReferenceResolver $resolver
    ) {}

    /**
     * The published entries that transclude this source entry anywhere in their
     * page-builder content (empty when it is unused).
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveUsedBy(
        Entry $entry
    ): EntryCollection {
        return $this->resolver->resolveNested(
            $this->embeddingCollections(),
            self::PAGE_BUILDER_FIELDS,
            $entry->id()
        );
    }

    /**
     * Whether this source entry is transcluded anywhere — the at-a-glance signal.
     * Shares the memoized scan behind {@see resolveUsedBy()}.
     */
    public function isInUse(
        Entry $entry
    ): bool {
        return $this->resolveUsedBy($entry)->isNotEmpty();
    }

    /**
     * Every collection handle — the candidate set for the scan. Restricting to the
     * page-builder field handles (not the collection) keeps this allow-list-free: a
     * collection without a page builder yields no ids, so scanning it is harmless and
     * a newly added content collection is covered automatically.
     *
     * @return list<string>
     */
    private function embeddingCollections(): array
    {
        return array_values(array_filter(
            Collection::handles()->all(),
            is_string(...)
        ));
    }
}
