<?php

declare(strict_types=1);

use App\SearchTransformers\AdditionalTypeTransformer;
use App\SearchTransformers\AlternateNameTransformer;
use App\SearchTransformers\NpiTransformer;
use App\SearchTransformers\SameAsTransformer;
use App\SearchTransformers\TermsToTitleTransformer;

return [

    /*
    |--------------------------------------------------------------------------
    | Default search index
    |--------------------------------------------------------------------------
    |
    | This option controls the search index that gets queried when performing
    | search functions without explicitly selecting another index.
    |
    */

    'default' => env('STATAMIC_DEFAULT_SEARCH_INDEX', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Search Indexes
    |--------------------------------------------------------------------------
    |
    | Here you can define all of the available search indexes.
    |
    */

    'indexes' => [

        'default' => [
            'driver' => 'local',
            // Collections only — taxonomy terms and assets are deliberately kept
            // out of the unified site search. The `ExcludeNonSearchableCollections`
            // scope then removes the infrastructure / non-reader-facing collections
            // (see its EXCLUDED_COLLECTIONS list) at index-build time, leaving the
            // reader-facing content types (pages, people, posts, clinical_*, …).
            // `clinical_expertise_section:*` folds in the synthetic Area-of-Expertise
            // routed-subpage documents (UAMS-Web/uams-statamic-archive#1527) so "All results" returns them too; that
            // custom provider (App\Search\AreaOfExpertiseSectionProvider) honors the same
            // per-entry `searchable` gate itself rather than through the query scope below.
            'searchables' => ['collection:*', 'clinical_expertise_section:*'],
            'query_scope' => 'exclude_non_searchable_collections',
            'fields' => [
                'title',
                'page_builder', // Added by studio1902/statamic-peak
            ],
            // Relevance weighting for the default index — tunes how strongly
            // each match type scores when ranking results. Honored by the
            // `local` driver's Comb matcher (Statamic\Search\Comb\Comb).
            'match_weights' => [
                'partial_word' => 1,
                'partial_first_word' => 2,
                'partial_word_start' => 1,
                'partial_first_word_start' => 2,
                'whole_word' => 5,
                'whole_first_word' => 5,
                'partial_whole' => 2,
                'partial_whole_start' => 2,
                'whole' => 10,
            ],
        ],

        // Collection Entries

        'clinical_providers' => [
            'driver' => 'local',
            'searchables' => 'collection:clinical_providers',
            'fields' => [
                'alternate_name_list',
                'display_name_full',
                'display_name_full_with_credentials',
                'display_name_full_with_prefix',
                'display_name_short_with_prefix',
                'full_name',
                'full_name_with_credentials',
                'full_name_with_prefix',
                'nickname_list',
                'npi',
                'pid',
                'title',
            ],
        ],

        'people' => [
            'driver' => 'local',
            'searchables' => 'collection:people',
            'fields' => [
                'alternate_name_list',
                'display_name_full',
                'display_name_full_with_credentials',
                'display_name_full_with_prefix',
                'display_name_short_with_prefix',
                'full_name',
                'full_name_with_credentials',
                'full_name_with_prefix',
                'nickname_list',
                'title',
            ],
        ],

        'clinical_locations' => [
            'driver' => 'local',
            'searchables' => 'collection:clinical_locations',
            'fields' => [
                'title',
                'alternate_name',
                'npi',
                'va_station_substation_id',
                'ahaid',
                'hospital_aha_name',
                'hospital_ccn_id',
                'hospital_ccn_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
                // `npi` is a replicator (a location can have several NPIs); flatten
                // its per-row `item` identifier values into searchable text (UAMS-Web/uams-statamic-archive#1417).
                'npi' => NpiTransformer::class,
            ],
        ],

        'clinical_conditions' => [
            'driver' => 'local',
            'searchables' => 'collection:clinical_conditions',
            'fields' => [
                'title',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'clinical_expertise' => [
            'driver' => 'local',
            'searchables' => 'collection:clinical_expertise',
            // Drop Areas of Expertise an editor has flagged out of interactive
            // search (UAMS-Web/uams-statamic-archive#1060/UAMS-Web/uams-statamic-archive#1438) while they stay published, routable, and in the
            // sitemap. The unified `default` index applies the same gate via
            // ExcludeNonSearchableCollections.
            'query_scope' => 'exclude_unsearchable_entries',
            'fields' => [
                'title',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'clinical_services' => [
            'driver' => 'local',
            'searchables' => 'collection:clinical_services',
            'fields' => [
                'title',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'clinical_resources' => [
            'driver' => 'local',
            'searchables' => 'collection:clinical_resources',
            'fields' => [
                'title',
                'intro',
                'article',
            ],
        ],

        'posts' => [
            'driver' => 'local',
            'searchables' => 'collection:posts',
            'fields' => [
                'title',
                'intro',
                'content',
            ],
        ],

        // Knowledge base articles/topics/roots (UAMS-Web/uams-statamic-archive#506). Powers the in-context KB
        // search bar, scoped to a KB or topic subtree at query time by the
        // `knowledge_base_subtree_articles` query scope. Indexes the article
        // body/summary fields so KB search matches on content, not just titles;
        // topic/root entries carry only `title` here and are filtered out of the
        // results (the scope restricts hits to published `article` entries).
        'knowledge_bases' => [
            'driver' => 'local',
            'searchables' => 'collection:knowledge_bases',
            'fields' => [
                'title',
                'title_short',
                'excerpt',
                'content',
            ],
        ],

        'calculators' => [
            'driver' => 'local',
            'searchables' => 'collection:calculators',
            'fields' => [
                'title',
                'note',
            ],
        ],

        // Taxonomy Terms

        'audiences' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:audiences',
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'drugs' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:drugs',
            'fields' => [
                'title',
                'name',
                'additional_type',
                'description',
                'disambiguating_description',
                'non_proprietary_name',
                'proprietary_name',
                'rxcui',
                'same_as',
            ],
            'transformers' => [
                'additional_type' => AdditionalTypeTransformer::class,
                'same_as' => SameAsTransformer::class,
            ],
        ],

        'membership_organizations' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:membership_organizations',
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'certification_organizations' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:certification_organizations',
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'certifications' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:certifications',
            'fields' => [
                'title',
                'name',
                'organization',
                'alternate_name',
            ],
            'transformers' => [
                'organization' => TermsToTitleTransformer::class,
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'clinical_specializations' => [
            'driver' => 'local',
            'searchables' => 'collection:clinical_specializations',
            'fields' => [
                'title',
                'slug',
                'code',
                'code_name',
                'code_name_display',
                'title_occupation',
                'title_occupation_alt',
                'title_specialization_patient',
                'title_specialization_patient_alt',
                // A clinical concept carries the keywords its subject had since UAMS-Web/uams-statamic-archive#2823
                'keywords',
            ],
        ],

        'credentials' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:credentials',
            'fields' => [
                'title',
                'full_name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'education_organizations' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:education_organizations',
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'education_types' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:education_types',
            'fields' => [
                'title',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'facilities' => [
            'driver' => 'local',
            'searchables' => 'collection:facilities',
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'fields_of_study' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:fields_of_study',
            // `subject_matter` was a `terms_search_index` relationship indexed raw
            // (term references, not text). It is the field-of-study's association,
            // not its own searchable identity, so it is dropped rather than
            // transformed — title + alternate_name carry the identity (UAMS-Web/uams-statamic-archive#1417).
            'fields' => [
                'title',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'patient_types' => [
            'driver' => 'local',
            'searchables' => 'collection:patient_types',
            'fields' => [
                'title',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'regions' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:regions',
            'fields' => [
                'title',
                'counties_covered',
            ],
        ],

        // `people_groups` migrated from a flat taxonomy to a structured
        // collection (UAMS-Web/uams-statamic-archive#1863), so this index sources from the collection now that
        // the `taxonomy:people_groups` source no longer exists.
        'people_groups' => [
            'driver' => 'local',
            'searchables' => 'collection:people_groups',
            'fields' => [
                'title',
            ],
        ],

        'affiliations_hospital' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:affiliations_hospital',
            'fields' => [
                'title',
                'name',
            ],
        ],

        'audio_series' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:audio_series',
            'fields' => [
                'title',
            ],
        ],

        'organizations' => [
            'driver' => 'local',
            'searchables' => 'collection:organizations',
            // The non-computed inputs to the generated title, so an editor can find an
            // entry by its Workday name, its Workday ID, or its public label - not only
            // by the composed string (UAMS-Web/uams-statamic-archive#2125).
            'fields' => [
                'title',
                'name',
                'alternate_name',
                'public_label',
                'workday_supervisory_organization_name',
                'workday_supervisory_organization_id',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'geographic_locations' => [
            'driver' => 'local',
            'searchables' => 'collection:geographic_locations',
            'fields' => [
                'title',
                'alternate_name',
                'same_as',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
                'same_as' => SameAsTransformer::class,
            ],
        ],

        'google_business_profile_categories' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:google_business_profile_categories',
            'fields' => [
                'title',
            ],
        ],

        'location_types' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:location_types',
            'fields' => [
                'title',
            ],
        ],

        // `logos_item_categories` migrated from a flat taxonomy to a structured
        // collection (UAMS-Web/uams-statamic-archive#1863), alongside `people_groups`.
        'logos_item_categories' => [
            'driver' => 'local',
            'searchables' => 'collection:logos_item_categories',
            'fields' => [
                'title',
            ],
        ],

        // `logos_item_labels` followed as a site-scoped structured collection
        // (UAMS-Web/uams-statamic-archive#2533), so this index sources from the collection.
        'logos_item_labels' => [
            'driver' => 'local',
            'searchables' => 'collection:logos_item_labels',
            'fields' => [
                'title',
            ],
        ],

        'other_specialties_all' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:other_specialties',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'other_specialties' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:other_specialties',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'portals' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:portals',
            'fields' => [
                'title',
            ],
        ],

        // `post_categories` migrated from a flat taxonomy to a structured
        // collection (UAMS-Web/uams-statamic-archive#1433), so this index sources from the collection now that
        // the `taxonomy:post_categories` source no longer exists (UAMS-Web/uams-statamic-archive#1483).
        'post_categories' => [
            'driver' => 'local',
            'searchables' => 'collection:post_categories',
            'fields' => [
                'title',
            ],
        ],

        // `post_tags` became a site-scoped structured collection (UAMS-Web/uams-statamic-archive#2532), so this
        // index sources from the collection now that the taxonomy no longer exists.
        'post_tags' => [
            'driver' => 'local',
            'searchables' => 'collection:post_tags',
            'fields' => [
                'title',
            ],
        ],

        'professional_and_leadership_development_activities' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:professional_and_leadership_development_activities',
            ],
            'fields' => [
                'title',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'professional_and_leadership_development_organizations' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:professional_and_leadership_development_organizations',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'professional_and_leadership_development_organizations_all' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:professional_and_leadership_development_organizations',
                'taxonomy:certification_organizations',
                'taxonomy:membership_organizations',
                'taxonomy:education_organizations',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        // `recognition_lists` is a structured collection (UAMS-Web/uams-statamic-archive#1546): a list is a
        // top-level entry, its categories are child entries. The `series` /
        // `data_source` / `list_date` fields live on the list blueprint only, so
        // category entries index by `title` alone (the missing fields are skipped).
        'recognition_lists' => [
            'driver' => 'local',
            'searchables' => 'collection:recognition_lists',
            'fields' => [
                'title',
                'series',
                'data_source',
                'list_date',
            ],
            'transformers' => [
                'series' => TermsToTitleTransformer::class,
                'data_source' => TermsToTitleTransformer::class,
            ],
        ],

        'recognition_lists_publications' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:recognition_lists_publications',
            // `schema_object` is an `entries` pointer to the schema_objects
            // collection (entry references, not text) and is not a publication's
            // searchable identity, so it is dropped rather than indexed raw (UAMS-Web/uams-statamic-archive#1417).
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'recognition_lists_series' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:recognition_lists_series',
            'fields' => [
                'title',
                'name',
                'publication',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
                'publication' => TermsToTitleTransformer::class,
            ],
        ],

        'recognition_lists_data_sources' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:recognition_lists_data_sources',
            // `schema_object` is an `entries` pointer to the schema_objects
            // collection (entry references, not text) and is not a data source's
            // searchable identity, so it is dropped rather than indexed raw (UAMS-Web/uams-statamic-archive#1417).
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'research_specialties_all' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:research_specialties_acgme',
                'taxonomy:research_specialties_non_accredited',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'research_specialties_acgme' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:research_specialties_acgme',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'research_specialties_non_accredited' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:research_specialties_non_accredited',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'residency_years' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:residency_years',
            'fields' => [
                'title',
            ],
        ],

        'specialties_all' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:specialties_acgme',
                'taxonomy:specialties_aoa',
                'taxonomy:specialties_non_accredited',
                'taxonomy:research_specialties_acgme',
                'taxonomy:research_specialties_non_accredited',
                'taxonomy:other_specialties',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'medical_specialties_all' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:specialties_acgme',
                'taxonomy:specialties_aoa',
                'taxonomy:specialties_non_accredited',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'specialties_acgme' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:specialties_acgme',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'specialties_aoa' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:specialties_aoa',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        'specialties_non_accredited' => [
            'driver' => 'local',
            'searchables' => [
                'taxonomy:specialties_non_accredited',
            ],
            'fields' => [
                'title',
                'name',
                'alternate_name',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
            ],
        ],

        // The pickers that offer both concept vocabularies search one index, since an
        // entries field with two collections and two indexes falls back to a title
        // query (UAMS-Web/uams-statamic-archive#2823). Its fields are the union of the `subject_matter` index below and
        // the `clinical_specializations` index above.
        'subject_concepts' => [
            'driver' => 'local',
            'searchables' => ['collection:subject_matter', 'collection:clinical_specializations'],
            'fields' => [
                'title',
                'slug',
                'notes',
                'description',
                'disambiguating_description',
                'alternate_name',
                'same_as',
                'keywords',
                'code',
                'code_name',
                'code_name_display',
                'title_occupation',
                'title_occupation_alt',
                'title_specialization_patient',
                'title_specialization_patient_alt',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
                'same_as' => SameAsTransformer::class,
            ],
        ],

        'subject_matter' => [
            'driver' => 'local',
            'searchables' => 'collection:subject_matter',
            'fields' => [
                'title',
                'notes',
                'description',
                'disambiguating_description',
                'alternate_name',
                'same_as',
                'keywords',
            ],
            'transformers' => [
                'alternate_name' => AlternateNameTransformer::class,
                'same_as' => SameAsTransformer::class,
            ],
        ],

        'video_series' => [
            'driver' => 'local',
            'searchables' => 'taxonomy:video_series',
            'fields' => [
                'title',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Defaults
    |--------------------------------------------------------------------------
    |
    | Here you can specify default configuration to be applied to all indexes
    | that use the corresponding driver. For instance, if you have two
    | indexes that use the "local" driver, both of them can have the
    | same base configuration. You may override for each index.
    |
    */

    'drivers' => [

        'local' => [
            'path' => storage_path('statamic/search'),
        ],

        'algolia' => [
            'credentials' => [
                'id' => env('ALGOLIA_APP_ID', ''),
                'secret' => env('ALGOLIA_SECRET', ''),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Search Defaults
    |--------------------------------------------------------------------------
    |
    | Here you can specify default configuration to be applied to all indexes
    | regardless of the driver. You can override these per driver or per index.
    |
    */

    'defaults' => [
        'fields' => ['title'],
    ],

];
