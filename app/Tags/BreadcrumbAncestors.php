<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Statamic\Tags\TagParam;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Tags\Context;
use Statamic\Tags\Tags;

/**
 * Provides a Statamic tag for resolving the existing-entry ancestor chain of a URL.
 *
 * Handles the {{ breadcrumb_ancestors }} tag in Antlers templates. Given a URL
 * (defaulting to the current request URI), it walks up the path segments and,
 * for each ancestor path, looks up an existing Statamic entry in the current
 * site. It returns the ordered chain of ancestor entries that actually exist,
 * from shallowest to deepest. This powers breadcrumb recovery on the 404 page
 * without leaking path structure for ancestors that do not resolve to entries.
 */
class BreadcrumbAncestors extends Tags
{
    /**
     * The {{ breadcrumb_ancestors }} tag.
     *
     * Resolves the chain of existing ancestor entries for the requested URL by
     * walking up its path segments. The final (requested) segment is dropped
     * because it is the missing page itself; each remaining ancestor path is
     * looked up in the current site, and only paths that resolve to an existing
     * entry are included.
     *
     * Parameters:
     *   - url (optional): The URL whose ancestors should be resolved; defaults
     *     to the current request URI.
     *   - in (optional): The site handle to query; defaults to the current site.
     *
     * Returns:
     *   - Array: An ordered list (shallowest to deepest) of ancestor entries
     *     that exist, each as an associative array with 'url' and 'title' keys.
     *   - Boolean false: When no ancestor paths resolve to entries, so the
     *     paired tag renders zero times rather than once with an empty scope.
     *
     * @return array<int, array<string, string>>|false The ordered ancestor-entry chain, or false when empty
     */
    public function index(): array|false
    {
        // Resolve the current site so its handle can seed the default below
        /** @var \Statamic\Sites\Site $currentSite */
        $currentSite = Site::current();

        // Narrow the tag parameters to their array shape before lookup
        /** @var array<string, mixed> $params */
        $params = $this->params;

        // Get the site handle to query; default to the current site's handle
        $siteHandleParam = TagParam::get(
            $params,
            'in',
            $currentSite->handle()
        );
        $siteHandle = \is_scalar($siteHandleParam) ? (string) $siteHandleParam : '';

        // Get the URL to resolve; default to the current request URI
        $uriParam = TagParam::get(
            $params,
            'url',
            $this->getCurrentUri()
        );
        $uri = \is_scalar($uriParam) ? (string) $uriParam : '';

        // ---
        // Reduce the URL to its site-relative URI so it can be matched against
        // entries in the queried site; sites mounted at a path prefix (e.g.
        // "/medicine/anesthesiology") store entry URIs relative to that mount,
        // while ancestor sites are handled separately by the breadcrumb partial
        // ---
        $uri = $this->siteRelativeUri(
            $uri,
            $siteHandle
        );

        // ---
        // Split the URI into non-empty path segments (e.g. "/department/program/missing"
        // becomes ["department", "program", "missing"])
        // ---
        $segments = \array_values(
            \array_filter(
                \explode(
                    '/',
                    \trim(
                        $uri,
                        '/'
                    )
                ),
                static fn (string $segment): bool => $segment !== ''
            )
        );

        // ---
        // Return false when there are no segments to walk (e.g. site root) so the
        // paired tag renders zero times rather than once with an empty scope
        // ---
        if ($segments === []) {
            return false;
        }

        // ---
        // Drop the final segment because it is the requested (missing) page itself;
        // only its ancestors are candidates for the breadcrumb chain
        // ---
        \array_pop($segments);

        // Initialize the ordered list of existing ancestor entries
        $ancestors = [];

        // Track the cumulative ancestor path as we walk up from the shallowest segment
        $path = '';

        // Walk each ancestor segment from shallowest to deepest, building its full path
        foreach ($segments as $segment) {
            // Append the current segment to the cumulative ancestor path
            $path .= '/'.$segment;

            // Look up an existing entry at this ancestor path in the current site
            $entry = EntryFacade::findByUri(
                $path,
                $siteHandle
            );

            // ---
            // Skip ancestor paths that do not resolve to an existing entry;
            // findByUri returns the Entry contract (a concrete Entry or, for
            // structured collections, a Page that proxies the same accessors)
            // ---
            if (! $entry) {
                continue;
            }

            // ---
            // Add the resolved entry to the chain, preferring its navigation alt
            // name over its title for the breadcrumb label; findByUri yields a
            // concrete Entry or a Page, both of which expose url() and value()
            // ---
            /** @var StatamicEntry $entry */
            // Resolve the entry's URL, narrowing the untyped accessor to a scalar
            $entryUrl = $entry->url();

            // Prefer the navigation alt name, falling back to the title
            $entryLabel = $entry->value('nav_alt_name') ?: $entry->value('title');

            $ancestors[] = [
                'url' => \is_scalar($entryUrl) ? (string) $entryUrl : '',
                'title' => \is_scalar($entryLabel) ? (string) $entryLabel : '',
            ];
        }

        // ---
        // Return false when no ancestors resolved so the paired tag renders zero
        // times; Statamic renders a paired tag once with an empty scope for []
        // ---
        if ($ancestors === []) {
            return false;
        }

        // Return the ordered chain of existing ancestor entries
        return $ancestors;
    }

    /**
     * Reduce a request URI to the path relative to the queried site's mount point.
     *
     * Multisite sites can be mounted at a path prefix (e.g. a site whose URL is
     * "http://example.test/medicine/anesthesiology"). Statamic stores that site's
     * entry URIs relative to the mount, so the prefix must be stripped from the
     * full request URI before looking entries up. Sites mounted at the root (or a
     * URI that does not carry the prefix) are returned unchanged.
     *
     * @param  string  $uri  The full request URI (e.g. "/medicine/anesthesiology/test/missing")
     * @param  string  $siteHandle  The handle of the site whose mount prefix should be stripped
     * @return string The site-relative URI (e.g. "/test/missing")
     */
    private function siteRelativeUri(
        string $uri,
        string $siteHandle
    ): string {
        // Resolve the queried site so its mount path can be inspected
        /** @var \Statamic\Sites\Site|null $site */
        $site = Site::get($siteHandle);

        // Return the URI unchanged when the site cannot be resolved
        if (! $site) {
            return $uri;
        }

        // ---
        // Derive the site's mount path from its URL (e.g. "/medicine/anesthesiology"),
        // normalizing the root mount to a single forward slash
        // ---
        // Narrow the untyped site URL accessor to a string before parsing
        $siteUrl = $site->url();
        $siteUrlString = \is_scalar($siteUrl) ? (string) $siteUrl : '';

        $mountPath = '/'.\trim(
            (string) (\parse_url(
                $siteUrlString,
                PHP_URL_PATH
            ) ?? ''),
            '/'
        );

        // Return the URI unchanged for root-mounted sites, which carry no prefix
        if ($mountPath === '/') {
            return $uri;
        }

        // Return the URI unchanged when it does not begin with the mount prefix
        if (! \str_starts_with($uri, $mountPath)) {
            return $uri;
        }

        // ---
        // Strip the mount prefix and ensure the remaining site-relative URI begins
        // with a forward slash
        // ---
        return '/'.\ltrim(
            \substr($uri, \strlen($mountPath)),
            '/'
        );
    }

    /**
     * Get the current request URI.
     *
     * Retrieves the current request path, ensuring it starts with a forward
     * slash and is relative to the site root. Uses the context's current_uri
     * if available, otherwise falls back to the request path.
     *
     * @return string The current request URI path
     */
    private function getCurrentUri(): string
    {
        // Try to get current_uri from the context first (matches template variable)
        /** @var Context $context */
        $context = $this->context;
        $currentUri = $context->get('current_uri');

        // Return the URI from context when it is available
        if ($currentUri) {
            return \is_scalar($currentUri) ? (string) $currentUri : '';
        }

        // ---
        // Fall back to the request path, ensuring it starts with a forward slash;
        // the leading slash means the result is always at least the site root
        // ---
        return '/'.\ltrim(
            request()->path(),
            '/'
        );
    }
}
