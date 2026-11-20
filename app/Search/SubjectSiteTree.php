<?php

declare(strict_types=1);

namespace App\Search;

use App\Support\Multisite\SiteTreeCache;
use UAMSWeb\Aether\Search\Contracts\SiteTree;

/**
 * The search tiers the add-on asks for, answered from the multisite tree and each site's
 * `subject` (#480).
 *
 * A site whose `subject` is `root` or `major_entity` and that has sites below it is a tier:
 * "This site" there searches its whole subtree. Any other site searches itself and is offered
 * "All of <nearest tier>" when that tier is not the root. The tree comes from
 * {@see SiteTreeCache}, read once a request.
 */
final readonly class SubjectSiteTree implements SiteTree
{
    /**
     * @param  SiteTreeCache  $cache  This request's multisite tree.
     */
    public function __construct(private SiteTreeCache $cache) {}

    /**
     * {@inheritDoc}
     */
    public function covered(string $siteHandle): array
    {
        return $this->cache->snapshot()->covered($siteHandle);
    }

    /**
     * {@inheritDoc}
     */
    public function tierOf(string $siteHandle): ?string
    {
        return $this->cache->snapshot()->nearestTier($siteHandle);
    }
}
