<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryObject;
use Statamic\Facades\Entry;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Query scope narrowing a `people` provider listing to the providers in a chosen
 * recognition list (or category) on the Find-a-Doc provider block (UAMS-Web/uams-statamic-archive#1176).
 *
 * Membership is a top-level `entries` relationship after the UAMS-Web/uams-statamic-archive#1546 re-model
 * (`people.recognition_lists ⇄ recognition_lists.members`), so this is a
 * `whereJsonOverlaps` over the person's `recognition_lists` field — the sibling of
 * the `ontology_overlap_expertise` scope, over recognition membership instead of
 * expertise, and composes with it in the block's `query_scope` pipe list.
 *
 * "In list L" resolves to L's id OR any of its descendant category ids: a person is
 * related to whichever node applies (the whole list, or a category within it), and
 * the categories are L's structure children. So each selected LIST id is expanded
 * to itself plus its category children before the overlap; a selected CATEGORY id
 * (a leaf) expands to just itself, keeping "in category C" appropriately narrow.
 *
 * Example usage in a template:
 * ```
 * {{ collection:people
 *      query_scope="ontology_overlap_expertise|recognition_list_membership"
 *      recognition_lists="{{ recognition_lists | pluck:'id' | implode:',' }}"
 * }}
 * ```
 */
class RecognitionListMembership extends Scope
{
    /**
     * Apply the scope to the query.
     *
     * @param  Builder  $query  The query builder instance.
     * @param  array<string, mixed>  $values  Parameters passed from the collection tag, including 'recognition_lists'.
     * @return Builder The modified query.
     */
    public function apply(
        $query,
        $values
    ) {
        // Resolve the block-selected recognition list / category ids, dropping blanks.
        $selectedIds = $this->extractIds($values['recognition_lists'] ?? []);

        // No recognition filter selected → leave the listing unfiltered (pass-through).
        if ($selectedIds === []) {
            return $query;
        }

        // Expand each selected list to include its descendant category ids, so
        // filtering "in list L" also matches providers filed under L's categories.
        /** @var EntryQueryBuilder $descendantQuery */
        $descendantQuery = Entry::query();
        $descendantQuery->where('collection', 'recognition_lists');
        $descendantQuery->whereIn('parent', $selectedIds);

        /** @var Collection<int, EntryObject> $descendants */
        $descendants = $descendantQuery->get();

        $membershipIds = $selectedIds;
        foreach ($descendants as $descendant) {
            $descendantId = $descendant->id();
            if (\is_string($descendantId)) {
                $membershipIds[] = $descendantId;
            }
        }

        $membershipIds = array_values(array_unique($membershipIds));

        // Match a provider whose recognition_lists overlaps the selected list(s) /
        // category(ies) or any descendant category thereof.
        $query->whereJsonOverlaps('recognition_lists', $membershipIds);

        return $query;
    }

    /**
     * Normalize the block filter parameter to a list of non-blank id strings.
     *
     * The value arrives either already as an array (a raw Entries value) or as a
     * comma-separated string (from `implode` in the template). An unfiltered block
     * passes an empty string, which `explode` turns into `['']`; those blanks are
     * dropped so an empty filter leaves the listing unfiltered instead of matching
     * nothing.
     *
     * @return list<string>
     */
    private function extractIds(
        mixed $value
    ): array {
        if (\is_string($value)) {
            $value = \explode(
                ',',
                $value
            );
        }

        if (! \is_array($value)) {
            return [];
        }

        return \array_values(\array_filter(
            $value,
            static fn (mixed $id): bool => \is_string($id) && $id !== ''
        ));
    }
}
