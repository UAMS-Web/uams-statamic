<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Aether Search settings
|--------------------------------------------------------------------------
|
| What the `uams-web/aether-search` add-on needs from this application: the type
| tabs, the result labels, the host frames, knowledge-base search as the scoped
| mode, the private-entry query scope and Google's results. Laravel merges this
| file over the add-on's at the top level only, so each key set here replaces
| the add-on's default whole.
|
| A tab other than `default` must be an index in `config/statamic/search.php`.
| The seams the add-on calls back into are bound in `SearchServiceProvider`, and
| the six type cards are in `resources/views/vendor/aether-search/cards/`.
|
*/

return [

    'layout' => 'layout',

    'route_data' => [
        'title' => 'Search',
        'breadcrumb_leaf' => 'Search',
        // Routeless views have no `published` in the cascade, which the shared
        // `layout/article` reads as a draft (`status-draft`); this page is live.
        'published' => true,
        // Render the standard page-header `h1`, so /search has a page title like any
        // ordinary entry.
        'title_options' => 'normal',
    ],

    'tabs' => [
        'default' => 'All results',
        'people' => 'People',
        'clinical_expertise' => 'Clinical Areas of Expertise',
        'clinical_locations' => 'Clinical Locations',
        'clinical_resources' => 'Clinical Resources',
        'posts' => 'News',
    ],

    // The collections each tab covers, so "This site" shows a tab only on a site that
    // carries one of them (#475). Each list matches the `collection:` searchables of the
    // tab's index in `config/statamic/search.php`, and a test holds the two together.
    'tab_collections' => [
        'people' => ['people'],
        'clinical_expertise' => ['clinical_expertise'],
        'clinical_locations' => ['clinical_locations'],
        'clinical_resources' => ['clinical_resources'],
        'posts' => ['posts'],
    ],

    'labels' => [
        'clinical_expertise_section' => 'Area of Expertise',
        'clinical_expertise' => 'Area of Expertise',
        'people' => 'Person',
        'posts' => 'News',
        'clinical_locations' => 'Clinical Location',
        'clinical_resources' => 'Clinical Resource',
        'knowledge_bases' => 'Knowledge Base',
        'pages' => 'Page',
    ],

    'text' => [
        'form_label' => 'Search UAMS',
        'placeholder' => 'Type to search…',
        'prompt_hint' => 'You can search people, areas of expertise, clinical locations and resources, news, and pages across the site.',
    ],

    'partials' => [
        'article' => 'layout/article',
        'entry_content' => 'layout/entry-content',
        'results_block' => 'page_builder/uamsweb_block',
        'results_row' => 'page_builder/components/container_row',
        'results_header' => 'page_builder/components/block_header',
        'pagination' => 'components/pagination',
    ],

    // Knowledge-base search: the search bar submits `kb_scope`, the id of the root or
    // topic to search under.
    'scoped' => [
        'index' => 'knowledge_bases',
        'parameter' => 'kb_scope',
        'query_scope' => 'knowledge_base_subtree_articles',
        'form_label' => 'Search this knowledge base',
        'note' => 'Searches here are limited to this knowledge base.',
        'broaden_label' => 'Search the whole site',
        'broaden_url' => '/search',
    ],

    'always_scope' => 'hide_private_search_results',

    // With no `GOOGLE_SEARCH_ENGINE_ID`, /search does not offer "All UAMS websites".
    'external' => [
        'enabled' => true,
        'provider' => 'google',
        'value' => 'google',
        'label' => 'All UAMS websites',
        'providers' => [
            'google' => [
                'engine_id' => env('GOOGLE_SEARCH_ENGINE_ID'),
            ],
        ],
    ],

    'suggestions' => [
        'per_minute' => 120,
    ],

];
