<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Support\Entries\EntryReferenceIds;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Query scope restricting a `people` query to the people filed under one or more
 * `people_groups` collection entries.
 *
 * UAMS-Web/uams-statamic-archive#1863 migrated `people_groups` from a flat taxonomy to a structured
 * collection, so a person now references their groups through the
 * `people_groups` *entries* field — an array of group entry ids — rather than a
 * taxonomy term. The People block previously filtered with
 * `:taxonomy:people_groups="…"`, which has no meaning once the taxonomy is gone;
 * this scope is its replacement, modeled on {@see PostsInPostCategory} from the
 * `post_categories` conversion (UAMS-Web/uams-statamic-archive#1433).
 *
 * `whereJsonContains` is an OR across the supplied ids — a person in ANY of the
 * selected groups matches — which is the behavior the taxonomy filter had, since
 * Statamic's `:taxonomy:` parameter defaults to `any`.
 *
 * Invoked from the People block as
 * `{{ collection:people query_scope="people_in_groups" :groups="$group_field" }}`.
 * A missing or empty selection applies NO constraint, which is what the retired
 * taxonomy parameter did: it resolved to `void` and was skipped, so an unfiltered
 * block listed every person. Matching nothing instead would look like a safety
 * measure while silently changing behavior.
 */
class PeopleInGroups extends Scope
{
    /**
     * Constrain the query to people whose `people_groups` field contains any of
     * the given group entry ids.
     *
     * @param  Builder  $query  Entry query builder for the `people` collection
     * @param  array<string, mixed>  $values  Antlers tag parameters; `groups` carries the group entry id(s)
     */
    public function apply(
        $query,
        $values
    ): void {
        // The parameter arrives as raw ids, augmented `Entry` objects, or a
        // single value from the `max_items: 1` field — see EntryReferenceIds.
        $ids = EntryReferenceIds::from($values['groups'] ?? null);

        if ($ids === []) {
            // Apply NO constraint, preserving the behavior this scope replaces.
            // The retired `:taxonomy:people_groups="…"` parameter resolved to
            // `void` when nothing was selected, and `Collection\Entries::queryTaxonomies()`
            // skips a null/empty taxonomy value outright — so an unfiltered block
            // listed every person. Matching nothing instead would be a silent
            // behavior change dressed up as a safety measure.
            return;
        }

        $query->where(static function (Builder $builder) use ($ids): void {
            foreach ($ids as $id) {
                $builder->orWhereJsonContains('people_groups', $id);
            }
        });
    }
}
