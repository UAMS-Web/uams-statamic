<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | 'single' emits one <urlset> per site at {site}/sitemap.xml. The app-owned
    | cross-site index (/sitemaps.xml, App\Http\Controllers\Seo\SitemapIndexController)
    | links every site's sitemap, so per-site splitting is not needed here.
    |
    */

    'mode' => 'single',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Sitemapamic caches its rendered output forever and invalidates it whenever
    | an entry, term, taxonomy or collection is saved or deleted — so a 254-site
    | multisite is never regenerated on a cold request unless content changed.
    |
    */

    'cache' => 'sitemapamic',
    'ttl' => 'forever',

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Only collections listed here are emitted (Sitemapamic iterates this array).
    | Every routable, front-end-facing collection is listed: `pages`, `posts`,
    | `people`, `clinical_expertise` (UAMS-Web/uams-statamic-archive#1402), `knowledge_bases` (UAMS-Web/uams-statamic-archive#1403), and the
    | `post_categories` (UAMS-Web/uams-statamic-archive#1433) and `post_tags` (UAMS-Web/uams-statamic-archive#2532) structured collections.
    | Each entry's visibility is gated by the `meta_include_in_xml_sitemap`
    | computed (see the mappings block and App\Services\ComputedFields\
    | Registration\SitemapInclusionComputedsRegistrar). What it checks differs
    | per collection, and each comment below names it. `frequency` and
    | `priority` are intentionally omitted — Google ignores both and no content sets
    | per-entry overrides — so no <changefreq>/<priority> tags are rendered.
    | `includeTaxonomies` is off on every collection: it would emit terms of a
    | taxonomy *attached to that collection*, duplicating the global-taxonomies path
    | below (UAMS-Web/uams-statamic-archive#1346).
    |
    */

    'defaults' => [
        'pages' => [
            'include' => true,
            'includeTaxonomies' => false,
        ],
        // Routable content collections whose entries have public front-end URLs
        // (UAMS-Web/uams-statamic-archive#1402). Each gates per-entry visibility through the shared inclusion
        // computed: private / password-protected entries (posts, clinical_expertise)
        // and non-routable people (`profile_page_query = false`) are excluded.
        'posts' => [
            'include' => true,
            'includeTaxonomies' => false,
        ],
        'people' => [
            'include' => true,
            'includeTaxonomies' => false,
        ],
        'clinical_expertise' => [
            'include' => true,
            'includeTaxonomies' => false,
        ],
        // The `knowledge_bases` collection (UAMS-Web/uams-statamic-archive#1403): routable but gated on the
        // orphaned-ancestor rule rather than status/password — a published
        // descendant under an unpublished ancestor is redirected away (UAMS-Web/uams-statamic-archive#217), so its
        // inclusion computed drops it (see KnowledgeBaseCollectionComputedsRegistrar).
        'knowledge_bases' => [
            'include' => true,
            'includeTaxonomies' => false,
        ],
        // The `post_categories` structured collection (UAMS-Web/uams-statamic-archive#1433): its hierarchical
        // `/category/…` archives are emitted here so the migrated category URLs stay
        // in the sitemap — they moved off the flat-taxonomy globals path below when
        // the taxonomy was retired (UAMS-Web/uams-statamic-archive#1434 owns that move). Gated on routability
        // alone: an entry the router cannot resolve is dropped (UAMS-Web/uams-statamic-archive#2542), which is
        // how an entry saved without a place in the tree stays out.
        'post_categories' => [
            'include' => true,
            'includeTaxonomies' => false,
        ],
        // The `post_tags` site-scoped collection (UAMS-Web/uams-statamic-archive#2532): its `/post-tags/{slug}`
        // archives moved here from the globals path below when the taxonomy was
        // retired, keeping the same URLs in the sitemap. Gated on routability
        // alone, like `post_categories`.
        'post_tags' => [
            'include' => true,
            'includeTaxonomies' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global taxonomies
    |--------------------------------------------------------------------------
    |
    | Taxonomy term-archive URLs to emit, keyed by taxonomy handle (UAMS-Web/uams-statamic-archive#1346, once
    | UAMS-Web/uams-statamic-archive#1145 shipped the public archives via UAMS-Web/uams-statamic-archive#1410). Only taxonomies with a public,
    | routable `{handle}/show` term-archive view belong here. Sitemapamic
    | self-guards on that view existing, so a taxonomy without a public archive
    | emits nothing even if listed — but only routable ones are listed here to keep
    | intent explicit. `frequency` / `priority` are omitted (empty config) to match
    | the pages scope.
    |
    | The list is EMPTY: both taxonomies it used to hold are now collections and
    | are emitted via the `defaults` collection path above. `post_categories`
    | migrated with hierarchical `/category/…` URLs (UAMS-Web/uams-statamic-archive#1433/UAMS-Web/uams-statamic-archive#1434), and `post_tags`
    | became a site-scoped collection at `/post-tags/{slug}` (UAMS-Web/uams-statamic-archive#2532). Leaving either
    | in this list would fatal `/sitemap.xml` — Sitemapamic's loader dereferences
    | `Taxonomy::find(...)->sites()` with no null guard.
    |
    */

    'globals' => [
        'taxonomies' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field mappings
    |--------------------------------------------------------------------------
    |
    | Per-entry sitemap controls map to these blueprint field handles. The
    | `include` handle is a computed on every sitemapped collection (App\Services\
    | ComputedFields\Registration\SitemapInclusionComputedsRegistrar, plus the
    | knowledge_bases branch in KnowledgeBaseCollectionComputedsRegistrar) that
    | excludes non-public / non-routable / orphaned entries. Where a blueprint
    | carries the editor's `include_in_xml_sitemap` toggle (UAMS-Web/uams-statamic-archive#1345) it honors that
    | too; `post_categories` and `post_tags` have none — Sitemapamic reads
    | `get() ?? getComputed()`, so this handle is intentionally *not* a stored field.
    | `change_frequency` / `priority` have no fields; those columns are omitted from
    | the output by design.
    |
    */

    'mappings' => [
        'include' => 'meta_include_in_xml_sitemap',
        'change_frequency' => 'meta_change_frequency',
        'priority' => 'meta_priority',
    ],
];
