<?php

declare(strict_types=1);

namespace App\Tags;

use App\Entries\KnowledgeBaseEntry;
use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Provides the {{ mounted_knowledge_bases }} tag.
 *
 * Returns the `knowledge_base` root entries mounted directly under a given page,
 * so the auto-generated subpages navigation can list a mounted knowledge base as
 * a child of its mount-parent page even though it lives in the `knowledge_bases`
 * collection rather than the `pages` structure. Each returned entry augments
 * normally, exposing `url` (the mounted URL), `title`, `nav_alt_name`, `excerpt`,
 * and `hide_from_menu` for the template to consume.
 *
 * Parameters:
 *   - under (required): The id of the mount-parent page to look beneath.
 *   - in (optional): The site handle to query; defaults to the current site.
 */
class MountedKnowledgeBases extends Tags
{
    /**
     * The {{ mounted_knowledge_bases }} tag.
     *
     * @return array<int, Entry> The mounted knowledge base root entries
     */
    public function index(): array
    {
        // The mount-parent page id is required to scope the lookup.
        /** @var Parameters $params */
        $params = $this->params;
        $under = $params->get('under');

        // Bail with nothing when no parent page id was provided.
        if (! $under) {
            // No parent to look beneath
            return [];
        }

        // Resolve the site to query, defaulting to the current site.
        /** @var \Statamic\Sites\Site $currentSite */
        $currentSite = Site::current();
        $site = $params->get('in', $currentSite->handle());

        // Build a query for knowledge base root entries in the target site.
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();

        // Return roots whose mount reference points at the given page.
        $query->where('collection', 'knowledge_bases');
        $query->where('blueprint', KnowledgeBaseEntry::ROOT_BLUEPRINT);
        $query->where('site', $site);

        // Collect the matched root entries before filtering by mount parent.
        /** @var Collection<int, Entry> $roots */
        $roots = $query->get();

        // Resolve the emptiness check once; reused per root below.
        $knowledgeBaseComputedFields = app(KnowledgeBaseComputedFieldsService::class);

        // Keep only non-empty roots mounted directly under the requested page: an
        // empty knowledge base (no published descendant article) is suppressed from
        // the auto-generated navigation (UAMS-Web/uams-statamic-archive#1012), matching the front-end page
        // suppression that already redirects an empty knowledge base to the site root.
        // Bare ``in_array`` (not ``\in_array``): Pest's TrueToFalse mutator skips the
        // strict flag, which cannot be killed when mount ids are always strings.
        return $roots
            ->filter(fn (Entry $root): bool => in_array(
                $under,
                (array) $root->get(KnowledgeBaseEntry::MOUNT_FIELD),
                true
            ))
            ->filter(fn (Entry $root): bool => $knowledgeBaseComputedFields->resolveHasDescendantArticles($root))
            ->values()
            ->all();
    }
}
