<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Support\People\ClinicalResourceProviderFilter;
use Statamic\Entries\Entry;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Entry query scope narrowing the `clinical_resources` archive to the resources
 * related to one provider, from the `?provider=` query param (UAMS-Web/uams-statamic-archive#1692).
 *
 * The relation runs the OTHER way: `clinical_resources` is a field on `people`,
 * not a `people` field on `clinical_resources`. So the archive cannot filter on a
 * stored handle of its own — this resolves the provider entry by slug and
 * constrains to the ids that entry relates, which is why it is a scope rather
 * than an inline `where` on the collection tag.
 *
 * An absent, blank, or UNKNOWN provider leaves the query unfiltered, matching how
 * `ClinicalResourceType` treats an absent type: the archive browses everything
 * rather than rendering an empty state for a typo'd URL. A KNOWN provider who
 * relates no resources is different — that is a real, empty answer, and the
 * archive says so.
 *
 * The lookup lives in `ClinicalResourceProviderFilter` because the on-page notice
 * has to agree with it; see that class for why they are not allowed to resolve
 * the slug separately.
 *
 * Composes with `clinical_resource_type` and `exclude_suppressed_dependents` by
 * pipe on the listing tag; each only ever adds constraints.
 *
 * Example usage on the index listing tag:
 * ```
 * {{ collection:clinical_resources
 *      query_scope="clinical_resources_for_provider"
 *      provider="{{ get:provider }}" }}
 * ```
 */
class ClinicalResourcesForProvider extends Scope
{
    /**
     * @param  ClinicalResourceProviderFilter  $providers  Shared `?provider=` slug lookup.
     */
    public function __construct(private readonly ClinicalResourceProviderFilter $providers) {}

    /**
     * Apply the provider filter to the query.
     *
     * @param  Builder  $query  Entry query builder (modified in place).
     * @param  array<string, mixed>  $values  Parameters from the collection tag; reads `provider`, the requested person slug.
     */
    public function apply(
        $query,
        $values
    ): void {
        // Normalize the requested slug; a blank (or non-string) value browses all.
        $raw = $values['provider'] ?? '';
        // ClinicalResourceProviderFilter::find() also trims; dropping trim here
        // is observationally identical for every caller.
        // @pest-mutate-ignore: UnwrapTrim
        $slug = is_string($raw) ? trim($raw) : '';

        // find('') returns null before any query; falling through this return
        // still leaves the archive unfiltered.
        // @pest-mutate-ignore: EmptyStringToNotEmpty, RemoveEarlyReturn
        if ($slug === '') {
            return;
        }

        $provider = $this->providers->find($slug);

        // An unknown slug leaves the archive unfiltered rather than empty, so a
        // stale or mistyped link degrades to the browse-all view.
        if (! $provider instanceof Entry) {
            return;
        }

        // Constrain POSITIVELY to the related ids. A known provider relating
        // nothing yields an empty list, which correctly matches no entry.
        $query->whereIn('id', $this->providers->resourceIds($provider));
    }
}
