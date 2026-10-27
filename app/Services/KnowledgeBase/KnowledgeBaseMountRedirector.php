<?php

declare(strict_types=1);

namespace App\Services\KnowledgeBase;

use App\Entries\KnowledgeBaseEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Data;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Resolves 301 targets for knowledge bases that used to live at their
 * un-mounted collection URL.
 *
 * When a `knowledge_base` is mounted under a page, its entries resolve at
 * `{mount_parent_uri}/{slug}` and no longer answer at their original
 * collection-relative URL (e.g. `/pediatric-radiology-resources`). This service
 * maps such an orphaned old URL back to the current mounted URL so a 404 handler
 * can issue a permanent redirect, preserving any inbound links and SEO value.
 */
class KnowledgeBaseMountRedirector
{
    /**
     * Resolve the mounted URL a request should permanently redirect to, if the
     * request targets the old un-mounted URL of a now-mounted knowledge base.
     *
     * @param  Request  $request  The incoming request that produced a 404
     * @return ?string The mounted URL to redirect to, or null when no mapping applies
     */
    public function target(Request $request): ?string
    {
        // Only redirect safe, idempotent GET/HEAD navigations.
        if (! $request->isMethodSafe()) {
            // Leave non-safe methods to the normal 404 handling
            return null;
        }

        // Normalize the requested path to a leading-slash, no-trailing-slash URI.
        $uri = '/'.trim(rawurldecode($request->path()), '/');

        // The site root can never be an old knowledge base URL.
        if ($uri === '/') {
            // Nothing to redirect
            return null;
        }

        // Resolve the current site to scope the lookup and the redirect target.
        /** @var \Statamic\Sites\Site $currentSite */
        $currentSite = Site::current();
        /** @var string $site */
        $site = $currentSite->handle();

        // Walk the mounted knowledge base roots looking for a matching old URL.
        foreach ($this->mountedRoots($site) as $root) {
            // Compute the mounted target for this root, if the URI belongs to it.
            $target = $this->targetForRoot($root, $uri, $site);

            // Return the first matching mounted URL.
            if ($target !== null) {
                // A mapping was found
                return $target;
            }
        }

        // No mounted knowledge base claims this URL.
        return null;
    }

    /**
     * Resolve the mounted URL for a single root if the requested URI is that
     * root's (or a descendant's) old un-mounted URL.
     *
     * @param  Entry  $root  A mounted `knowledge_base` root entry
     * @param  string  $uri  The requested, normalized URI
     * @param  string  $site  The current site handle
     * @return ?string The mounted URL, or null when this root does not match
     */
    private function targetForRoot(Entry $root, string $uri, string $site): ?string
    {
        // The old un-mounted URL of the root is its slug at the site root.
        /** @var string $rootSlug */
        $rootSlug = $root->slug();
        $rootPath = '/'.$rootSlug;

        // The URI must be the root path itself or sit beneath it.
        if (
            $uri !== $rootPath
            && ! str_starts_with($uri, $rootPath.'/')
        ) {
            // This root does not own the requested URI
            return null;
        }

        // Resolve the root's mount-parent page URI to build the mounted prefix.
        $mountUri = $this->mountParentUri($root);

        // A missing mount parent means the knowledge base is not actually mounted.
        if ($mountUri === null) {
            // Nothing to redirect to
            return null;
        }

        // Compose the candidate mounted URL by prefixing the old URI.
        $candidate = URL::tidy($mountUri.$uri, withTrailingSlash: false);

        // If the prefix added nothing, the URL is unchanged and not a redirect.
        if ($candidate === $uri) {
            // Not actually mounted anywhere new
            return null;
        }

        // Do not redirect if the old URL still legitimately resolves to content.
        if (Data::findByUri($uri, $site) !== null) {
            // Something else lives here; leave it alone
            return null;
        }

        // Only redirect when the mounted URL actually resolves to an entry.
        return Data::findByUri($candidate, $site) !== null
            ? $candidate
            : null;
    }

    /**
     * Resolve a mounted root's mount-parent page URI.
     *
     * @param  Entry  $root  The `knowledge_base` root entry
     * @return ?string The mount-parent page URI, or null when unresolved
     */
    private function mountParentUri(Entry $root): ?string
    {
        // Read the raw mount reference (an entries field stores an array of ids).
        $mountId = $root->get(KnowledgeBaseEntry::MOUNT_FIELD);

        // Normalize the entries-field value to a single id.
        $mountId = \is_array($mountId)
            ? ($mountId[0] ?? null)
            : $mountId;

        // Bail when no mount parent is declared.
        if (! $mountId) {
            // Not mounted
            return null;
        }

        // Resolve the mount-parent page and return its current URI.
        /** @var Entry|null $mountEntry */
        $mountEntry = EntryFacade::find($mountId);

        /** @var string|null $uri */
        $uri = $mountEntry?->uri();

        return $uri;
    }

    /**
     * Query the mounted `knowledge_base` root entries for the given site.
     *
     * @param  string  $site  The site handle to scope the query to
     * @return Collection<int, Entry> The mounted root entries
     */
    private function mountedRoots(string $site): Collection
    {
        // Build a query for knowledge base root entries in the current site.
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();

        // Restrict to knowledge_base roots in the site and materialize them.
        $query->where('collection', 'knowledge_bases');
        $query->where('blueprint', KnowledgeBaseEntry::ROOT_BLUEPRINT);
        $query->where('site', $site);

        // Materialize the matching root entries.
        /** @var Collection<int, Entry> $roots */
        $roots = $query->get();

        // Keep only roots that declare a mount parent.
        return $roots->filter(fn (Entry $root): bool => ! empty($root->get(KnowledgeBaseEntry::MOUNT_FIELD)));
    }
}
