<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\SchemaTemplates\SubsiteHubResolver;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers the `schema_subsite_hub` computed on the `clinical_expertise` and
 * `clinical_locations` collections (UAMS-Web/uams-statamic-archive#1414).
 *
 * A content page (`basic_page` / `landing_page`) inside either virtual subsite
 * emits page-level JSON-LD as a `WebPage` that `isPartOf` its subsite hub. The
 * hub is a structural (tree-ancestor) relationship, not a stored field, so this
 * computed surfaces the nearest hub entry id as an ordinary augmented key that
 * the content page's `WebPage` schema_object node reads via
 * `schema_thing_from_relationship`. A page outside a subsite (or a hub entry
 * itself) resolves to `null`, so `isPartOf` is simply omitted.
 */
final class SubsiteContentPageHubComputedsRegistrar
{
    /**
     * Collections whose content pages carry the subsite-hub computed.
     *
     * @var list<string>
     */
    private const array COLLECTIONS = ['clinical_expertise', 'clinical_locations'];

    /**
     * @param  SubsiteHubResolver  $hubResolver  Nearest-hub structure-tree walk shared with the AoE chrome.
     */
    public function __construct(private SubsiteHubResolver $hubResolver) {}

    /**
     * Register the `schema_subsite_hub` computed on both subsite collections.
     */
    public function register(): void
    {
        foreach (self::COLLECTIONS as $collection) {
            Collection::computed(
                $collection,
                'schema_subsite_hub',
                fn (Entry $entry, mixed $_stored): ?array => $this->hubResolver->resolveIdList($entry)
            );
        }
    }
}
