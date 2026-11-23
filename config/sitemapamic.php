<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Collections
    |--------------------------------------------------------------------------
    |
    | Only the collections listed here are emitted. The list has no '*', so
    | Sitemapamic does not auto-discover collections, and a plain handle selects
    | that collection's entries only, never the terms of a taxonomy attached to
    | it (that would duplicate a term archive, UAMS-Web/uams-statamic-archive#1346).
    | Every routable, front-end-facing collection is listed: `pages`, `posts`,
    | `people`, `clinical_expertise` (UAMS-Web/uams-statamic-archive#1402),
    | `knowledge_bases` (UAMS-Web/uams-statamic-archive#1403), and the
    | `post_categories` (UAMS-Web/uams-statamic-archive#1433) and `post_tags`
    | (UAMS-Web/uams-statamic-archive#2532) structured collections.
    |
    | Each entry's visibility is gated by the `meta_include_in_xml_sitemap`
    | computed (see the mappings block below and App\Services\ComputedFields\
    | Registration\SitemapInclusionComputedsRegistrar), and what it checks
    | differs per collection:
    |
    | - `posts`, `clinical_expertise`: private and password-protected entries
    |   are excluded.
    | - `people`: entries that are not routable (`profile_page_query = false`)
    |   are excluded.
    | - `knowledge_bases`: a published descendant under an unpublished ancestor
    |   is redirected away (UAMS-Web/uams-statamic-archive#217), so it is
    |   excluded (KnowledgeBaseCollectionComputedsRegistrar).
    | - `post_categories`, `post_tags`: an entry the router cannot resolve is
    |   excluded (UAMS-Web/uams-statamic-archive#2542), which is how an entry
    |   saved without a place in the tree stays out. Their `/category/…` and
    |   `/post-tags/{slug}` archives moved here from the taxonomies path when
    |   both taxonomies were retired (UAMS-Web/uams-statamic-archive#1434), so
    |   the migrated URLs stay in the sitemap.
    |
    | Sitemapamic 4 also leaves out an entry with any `redirect`, an entry
    | under Statamic's `protect` scheme, an entry whose `uri()` is empty, and an
    | entry whose template view does not exist (#991, #1232). On the Stache,
    | `uri()` recomputes the URI, so it does not catch an entry the router cannot
    | resolve; the `post_categories` / `post_tags` gate above still does.
    |
    */

    'collections' => [
        'pages',
        'posts',
        'people',
        'clinical_expertise',
        'knowledge_bases',
        'post_categories',
        'post_tags',
    ],

    /*
    |--------------------------------------------------------------------------
    | Taxonomies
    |--------------------------------------------------------------------------
    |
    | Taxonomy term archives to emit site-wide. The list is EMPTY, so no term
    | archive is emitted: both taxonomies it used to hold are now collections,
    | listed above. Without this key Sitemapamic would default to '*' and emit
    | every taxonomy.
    |
    */

    'taxonomies' => [],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | No <changefreq> or <priority> is rendered: Google ignores both, and no
    | content sets per-entry values, so both stay null.
    |
    */

    'defaults' => [
        'changefreq' => null,
        'priority' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | 'single' emits one <urlset> per site at {site}/sitemap.xml. The app-owned
    | cross-site index (/sitemaps.xml, App\Http\Controllers\Seo\SitemapIndexController)
    | links every site's sitemap. A site over 50,000 URLs or 50 MB is split into
    | numbered files under an index at the same path.
    |
    */

    'mode' => 'single',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Sitemapamic stores each site's built sitemap as files on the `local` disk
    | with no expiry, and marks them stale when an entry, collection tree,
    | collection, term, taxonomy or site is saved or deleted, so a sitemap is
    | rebuilt only after its content changed. A quiet save fires no event and
    | leaves the stored sitemap in place; `php please sitemapamic:clear` clears it.
    |
    */

    'cache' => [
        'enabled' => env('SITEMAPAMIC_CACHE_ENABLED', true),

        'key' => 'sitemapamic',

        'ttl' => env('SITEMAPAMIC_CACHE_TTL', null),

        'disk' => env('SITEMAPAMIC_CACHE_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Field mappings
    |--------------------------------------------------------------------------
    |
    | The `include` handle is a computed on every sitemapped collection
    | (App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar,
    | plus the knowledge_bases branch in KnowledgeBaseCollectionComputedsRegistrar)
    | that excludes non-public, non-routable and orphaned entries. Where a
    | blueprint carries the editor's `include_in_xml_sitemap` toggle
    | (UAMS-Web/uams-statamic-archive#1345) it honors that too; `post_categories`
    | and `post_tags` have none. Sitemapamic reads the stored value, then the
    | computed, so this handle is intentionally not a stored field. The
    | `changefreq` and `priority` handles have no fields.
    |
    */

    'mappings' => [
        'include' => 'meta_include_in_xml_sitemap',

        'changefreq' => 'meta_change_frequency',

        'priority' => 'meta_priority',
    ],
];
