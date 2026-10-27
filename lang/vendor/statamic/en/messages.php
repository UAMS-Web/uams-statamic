<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Collection-specific "Create Entry" label overrides
    |--------------------------------------------------------------------------
    |
    | Format: "{collection_handle}_collection_create_entry" => "Your Label"
    | Blueprint-specific: "{collection_handle}_collection_create_entry.{blueprint}"
    |
    | Keys must match the live collection handle. Several collections were renamed
    | to the `clinical_*` family for consistency (#973 and neighbors); the keys
    | below track those handles so the override does not silently fall back to the
    | generic "Create Entry" label.
    |
    */

    // Clinical Conditions collection
    'clinical_conditions_collection_create_entry' => 'Create Clinical Condition',

    // Clinical Areas of Expertise collection
    'clinical_expertise_collection_create_entry' => 'Create Clinical Area of Expertise',

    // Clinical Locations collection
    'clinical_locations_collection_create_entry' => 'Create Clinical Location',

    // Clinical Providers collection
    'clinical_providers_collection_create_entry' => 'Create Clinical Provider',

    // Clinical Resources collection
    'clinical_resources_collection_create_entry' => 'Create Clinical Resource',
    'clinical_resources_collection_create_entry.article' => 'Create Article Clinical Resource',
    'clinical_resources_collection_create_entry.infographic' => 'Create Infographic Clinical Resource',
    'clinical_resources_collection_create_entry.document' => 'Create Document Clinical Resource',
    'clinical_resources_collection_create_entry.video' => 'Create Video Clinical Resource',

    // Clinical Services collection
    'clinical_services_collection_create_entry' => 'Create Clinical Service',

    // Knowledge Bases collection
    'knowledge_bases_collection_create_entry' => 'Create Knowledge Base Entry',
    'knowledge_bases_collection_create_entry.knowledge_base' => 'Create Knowledge Base',
    'knowledge_bases_collection_create_entry.topic' => 'Create Knowledge Base Topic',
    'knowledge_bases_collection_create_entry.article' => 'Create Knowledge Base Article',

    // Logo List Items collection
    'logo_list_items_collection_create_entry' => 'Create Logo List Item',

    // Pages collection
    'pages_collection_create_entry' => 'Create Page',
    'pages_collection_create_entry.basic_page' => 'Create Basic Page',
    'pages_collection_create_entry.landing_page' => 'Create Landing Page',

    // People collection
    'people_collection_create_entry' => 'Create Person',

    // Posts collection
    'posts_collection_create_entry' => 'Create Post',
    'posts_collection_create_entry.audio' => 'Create Audio Post',
    'posts_collection_create_entry.news_release' => 'Create News Release Post',
    'posts_collection_create_entry.post' => 'Create Standard Post',
    'posts_collection_create_entry.video' => 'Create Video Post',

    // Reusable Content collection
    'reusable_content_collection_create_entry' => 'Create Reusable Content',

    // Schema Objects collection
    'schema_objects_collection_create_entry' => 'Create Schema Object',

    // Tables collection
    'tables_collection_create_entry' => 'Create Table',
];
