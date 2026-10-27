<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\People\ClinicalResourceProviderFilter;
use Statamic\Entries\Entry;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Names the provider the `clinical_resources` archive is filtered to (UAMS-Web/uams-statamic-archive#1692), for
 * the on-page notice above the results.
 *
 * `{{ clinical_resource_filter:provider_name provider="{{ get:provider }}" }}`
 *
 * Returns an EMPTY STRING when no provider is requested, when the slug names
 * nobody, or when that person is unpublished — the same three cases in which
 * `ClinicalResourcesForProvider` leaves the archive UNFILTERED. That shared
 * emptiness is the point: the notice appears exactly when the filter is in force,
 * so a typo'd slug shows an unfiltered archive with no notice rather than an
 * archive claiming to be narrowed to a provider who does not exist.
 *
 * A tag rather than a computed because the subject is the REQUEST (a query
 * parameter on a listing page), not a field of the entry being rendered — the
 * archive is a mount landing, and no provider entry is in scope there.
 */
final class ClinicalResourceFilter extends Tags
{
    /**
     * @param  ClinicalResourceProviderFilter  $providers  Shared `?provider=` slug lookup.
     */
    public function __construct(
        private readonly ClinicalResourceProviderFilter $providers
    ) {}

    /**
     * The display name of the provider the archive is filtered to, or ''.
     */
    public function providerName(): string
    {
        /** @var Parameters $params */
        $params = $this->params;

        $raw = $params->get('provider');
        // ClinicalResourceProviderFilter::find() also trims; dropping trim here
        // is observationally identical for every caller.
        // @pest-mutate-ignore: UnwrapTrim
        $slug = is_scalar($raw) ? trim((string) $raw) : '';

        // find('') returns null before any query, so a blank slug returns '' below
        $provider = $this->providers->find($slug);

        if (! $provider instanceof Entry) {
            return '';
        }

        return $this->providers->displayName($provider);
    }
}
