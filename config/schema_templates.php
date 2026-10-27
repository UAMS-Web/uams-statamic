<?php

declare(strict_types=1);
use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;

/**
 * Schema Templates tooling (CP defaults, JSON-LD output cache).
 *
 * JSON-LD cache TTL: With `invalidate_entry_collections` and optional global
 * set bumps, a default of one hour (3600) is a reasonable safety net if an
 * event is missed. For mostly static sites you may raise TTL (e.g., 86400);
 * keep invalidation lists accurate when adding collections that affect schema.
 * Asset saves deliberately do NOT bump the bust counter (issue UAMS-Web/uams-statamic-archive#937: one
 * import or upload used to cold-bust the whole site); asset-driven staleness
 * is bounded by `ttl_seconds` instead.
 *
 * Cold composition: After a bust (or first visit), `SchemaOutputService` may spend
 * tens of seconds rebuilding JSON-LD for heavy entries; `Cache::remember` then
 * serves cached HTML quickly. Run `schema-templates:warm-json-ld-cache` (or CP warm)
 * after deploy or bulk bust so editors rarely hit that cold path.
 *
 * Profiling: set `SCHEMA_LD_PROFILE=true` (via `.env` or deployment env) to log
 * `compose_wall_ms` and uncached blueprint augment totals once per JSON-LD compose
 * (see `laravel.log`). Optional `SCHEMA_LD_PROFILE_TOP_N` caps per-request
 * profile samples.
 */
return [

    /**
     * JSON-LD composition profiling (read by `SchemaOutputService` and relationship
     * resolver).
     *
     * Use `config()` in application code; values are defined here so they work with
     * `php artisan config:cache`.
     */
    'json_ld_compose_profile' => [
        'enabled' => (bool) env('SCHEMA_LD_PROFILE', false),
        'top_n' => (int) env('SCHEMA_LD_PROFILE_TOP_N', 10),
    ],

    /**
     * Program accreditor blocks emitted on a residency/fellowship
     * `EducationalOccupationalCredential`'s `about` (`EducationalOccupationalProgram`)
     * `accreditedBy` property (see issue UAMS-Web/uams-statamic-archive#637).
     *
     * `taxonomy_map` resolves a training set's specialty taxonomy handle to an
     * accreditor key; `organizations` holds the JSON-LD `Organization` block for
     * each key. Specialty taxonomies absent from `taxonomy_map` (e.g.
     * `specialties_non_accredited`) are non-accredited and emit no `accreditedBy`.
     *
     * Accreditation is derived from the specialty taxonomy, so no authoring field
     * is required. `acgme_international` and `royal_college` are defined for future
     * use even though the current specialty taxonomies cannot yet express them; a
     * later ticket may add an explicit accreditation indicator that maps onto these
     * same keys. `App\Support\People\TrainingAccreditorRegistry` reads this config.
     */
    'training_accreditors' => [

        /**
         * Specialty taxonomy handle => accreditor key in `organizations` below.
         */
        'taxonomy_map' => [
            'specialties_acgme' => 'acgme',
            'research_specialties_acgme' => 'acgme',
            'specialties_aoa' => 'aoa',
        ],

        /**
         * Accreditor key => JSON-LD `Organization` block.
         */
        'organizations' => [
            'acgme' => [
                '@type' => 'Organization',
                'name' => 'Accreditation Council for Graduate Medical Education',
                'alternateName' => 'ACGME',
                'url' => 'https://www.acgme.org/',
            ],
            'acgme_international' => [
                '@type' => 'Organization',
                'name' => 'ACGME International',
                'url' => 'https://www.acgme-i.org/',
            ],
            'royal_college' => [
                '@type' => 'Organization',
                'name' => 'Royal College of Physicians and Surgeons of Canada',
                'url' => 'https://www.royalcollege.ca/',
            ],
            'aoa' => [
                '@type' => 'Organization',
                'name' => 'American Osteopathic Association',
                'alternateName' => 'AOA',
                'url' => 'https://osteopathic.org/',
            ],
        ],
    ],

    'json_ld_cache' => [

        /**
         * Master switch for every JSON-LD cache layer. False disables both the page
         * output cache and the page-invariant fragment cache below, whatever their
         * own switches say.
         *
         * Controlled by `SCHEMA_JSON_LD_CACHE_ENABLED`; defaults to true when the variable
         * is unset. Turn an individual layer off with `output_enabled` /
         * `fragments_enabled` rather than with this flag — see UAMS-Web/uams-statamic-archive#1842 for why the
         * distinction matters when profiling.
         */
        'enabled' => (bool) env('SCHEMA_JSON_LD_CACHE_ENABLED', true),

        /**
         * Whether cached JSON-LD script payloads may be read from the store when the tag
         * renders — the per-page output cache keyed by
         * {@see SchemaJsonLdOutputCache::cacheKey()}.
         *
         * Split from `enabled` in UAMS-Web/uams-statamic-archive#1842 so the output cache can be switched off without
         * silently taking `fragments_enabled` with it. Tests that build nodes for
         * in-memory fixtures must disable *this* rather than the master switch: the
         * output key falls back to `spl_object_hash()` for an entity with no id, and PHP
         * reuses those hashes once an object is freed, so two unrelated fixtures can
         * collide on one key.
         *
         * Controlled by `SCHEMA_JSON_LD_OUTPUT_CACHE_ENABLED`; defaults to true.
         */
        'output_enabled' => (bool) env('SCHEMA_JSON_LD_OUTPUT_CACHE_ENABLED', true),

        /**
         * Time-to-live in seconds for each cached script payload. Prefer
         * invalidation via bust counter; TTL bounds staleness if a listener
         * misses a content change.
         *
         * A value of zero or less also bypasses the output cache, independently of
         * `output_enabled`.
         */
        'ttl_seconds' => (int) env('SCHEMA_JSON_LD_CACHE_TTL', 3600),

        /**
         * Whether page-invariant relationship fragments (global-set targets built
         * with `merge_source_context="false"`, e.g. `founder` / `accountable_person`
         * / `media_contact` people) are cached across requests so only the first
         * compose after a bust pays their build cost (issue UAMS-Web/uams-statamic-archive#937). Keys embed the
         * bust generation and `ttl_seconds` bounds staleness, matching page output.
         */
        'fragments_enabled' => (bool) env('SCHEMA_JSON_LD_FRAGMENT_CACHE_ENABLED', true),

        /**
         * Cache key for the monotonic bust counter (incremented on clear and
         * on configured content saves).
         */
        'bust_cache_key' => 'schema_templates:json_ld:bust',

        /**
         * ISO8601 timestamp of the last bust (for CP display).
         */
        'last_bump_meta_key' => 'schema_templates:json_ld:last_bump',

        /**
         * Summary array from the last warm command (for CP display).
         */
        'last_warm_meta_key' => 'schema_templates:json_ld:last_warm',

        /**
         * Entry collection handles whose saves bump the bust counter.
         */
        'invalidate_entry_collections' => [
            'people',
            'schema_objects',
            'pages',
        ],

        /**
         * Global set handles whose saves bump the bust counter (e.g., multisite
         * overrides used by schema cascade).
         */
        'invalidate_global_set_handles' => [
            'uams_multisite_settings',
            // System-wide clinical-location closures (UAMS-Web/uams-statamic-archive#1327) feed the JSON-LD via
            // schema_location_hours, so editing them must bust the schema cache.
            'uams_fad_settings',
        ],

        /**
         * Taxonomy handles whose term saves bump the bust counter.
         */
        'invalidate_taxonomies' => [
            // e.g., 'tags',
        ],

        /**
         * Options for the Artisan warmer and CP action that request published entry URLs
         * so front-end JSON-LD responses populate the output cache.
         *
         * Read by `schema-templates:warm-json-ld-cache` and the JSON-LD cache utility in
         * the Statamic control panel.
         */
        'warm' => [

            /**
             * Optional site handle used when `$globalSet->in($site)` has no localized
             * row for a referenced global set; null uses `Site::default()->handle()`.
             */
            'global_fallback_site_handle' => null,

            /**
             * Global fields whose values are Statamic entry ids, grouped by the entry
             * collection those ids belong to. Keys must be collection handles (e.g.
             * `schema_objects`, `people`). Values are dotted references
             * `{global_set_handle}.{field_handle}`.
             *
             * `schema_objects`: ids are recorded for the warm summary (audit); other
             * collections: each published entry’s `absoluteUrl()` is requested.
             */
            'global_entry_fields_by_collection' => [
                'schema_objects' => [
                    'uams_multisite_settings.schema_subject',
                    'uams_multisite_settings.schema_website',
                    'uams_multisite_settings.copyright_holder_schema_object',
                    'uams_multisite_settings.country_of_origin',
                    'uams_multisite_settings.producer',
                    'uams_multisite_settings.publisher',
                    'uams_multisite_settings.source_organization',
                ],
                'people' => [
                    'uams_multisite_settings.founder',
                    'uams_multisite_settings.accountable_person',
                ],
            ],

            /**
             * Globals whose values point at taxonomy terms; resolve each term then read
             * `term_entries_field` and warm entry ids found there. Use
             * `term_entries_field_collection_handle` when the linked entries collection
             * is ambiguous; omit to infer from each resolved entry.
             *
             * `schema_objects`: record ids in the warm summary; other collections:
             * request each published entry URL.
             */
            'global_taxonomy_entry_traversals' => [
                [
                    'global_set_field_handles' => [
                        'uams_multisite_settings.media_contact',
                    ],
                    'taxonomy_handle' => 'media_contact_groups',
                    'term_entries_field' => 'people',
                    'term_entries_field_collection_handle' => 'people',
                ],
            ],

            /**
             * Collection handle for the routed tree that contains the site homepage.
             */
            'homepage_collection_handle' => 'pages',

            /**
             * URIs tried in order with `Entry::findByUri($uri, $site->handle())` until
             * a published homepage in `homepage_collection_handle` is found.
             */
            'homepage_uri_candidates' => [
                '/',
            ],
        ],
    ],
];
