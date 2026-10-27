<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Schema Object Fieldtype & Schema Slots
    |--------------------------------------------------------------------------
    |
    | Configuration for the Schema Object fieldtype (schema_objects collection
    | and any blueprint using type: schema_object). Also drives type options
    | for the four schema slots (subject, website, webpage, main content).
    |
    */

    // ---
    // Slot type roots: limit which Schema.org types are offered per slot
    // ---

    // Root type(s) per slot. Used by SchemaTypeOptions (subjectTypes, websiteTypes,
    // webpageTypes, mainContentTypes). Omit or set to null to use PHP fallbacks.
    // main_content: null or empty = all types; list = restrict to those roots + descendants.
    'slot_type_roots' => [
        'subject' => [
            'Person',
            'Organization',
        ],
        'website' => [
            'WebSite',
        ],
        'webpage' => [
            'WebPage',
        ],
        'main_content' => null,
    ],

    // Collection for Thing/entries replicator set (schema object references).
    'entries_collection' => 'schema_objects',

    // ---
    // Property blacklist: hide specific Schema.org properties in the CP
    // ---

    // general: property names hidden for all schema types.
    // specific: property names hidden per type (key = Schema.org type, value = list of names).
    // Only affects which property fields are shown in the CP; validation unchanged.
    'property_blacklist' => [
        'general' => [
            'duns', // handled by SchemaClassificationsIdentifiersMerger
            'geoContains', // This is a highly technical GIS property used for mapping software; it is generally overkill for organizational SEO.
            'geoCoveredBy', // This is a highly technical GIS property used for mapping software; it is generally overkill for organizational SEO.
            'geoCovers', // This is a highly technical GIS property used for mapping software; it is generally overkill for organizational SEO.
            'geoCrosses', // This is a highly technical GIS property used for mapping software; it is generally overkill for organizational SEO.
            'geoDisjoint', // This is a highly technical GIS property used for mapping software; it is generally overkill for organizational SEO.
            'geoEquals', // This is a highly technical GIS property used for mapping software; it is generally overkill for organizational SEO.
            'geoIntersects', // This is a highly technical GIS property used for mapping software; it is generally overkill for organizational SEO.
            'geoOverlaps', // This is a highly technical GIS property used for mapping software; it is generally overkill for organizational SEO.
            'geoTouches', // This is a highly technical GIS property used for mapping software; it is generally overkill for organizational SEO.
            'globalLocationNumber', // handled by SchemaClassificationsIdentifiersMerger
            'hasGS1DigitalLink', // This is primarily for consumer packaged goods (QR codes on products).
            'isicV4', // handled by SchemaClassificationsIdentifiersMerger
            'iso6523Code', // handled by SchemaClassificationsIdentifiersMerger
            'leiCode', // handled by SchemaClassificationsIdentifiersMerger
            'naics', // handled by SchemaClassificationsIdentifiersMerger
            'rxcui', // handled by SchemaClassificationsIdentifiersMerger
            'taxID', // handled by SchemaClassificationsIdentifiersMerger
            'tickerSymbol', // handled by SchemaClassificationsIdentifiersMerger
            'usNPI', // handled by SchemaClassificationsIdentifiersMerger
            'vatID', // not relevant to U.S. entities
        ],

        'specific' => [
            // Example: hide mainEntityOfPage on WebPage so editors don't set it in the CP.
            // 'WebPage' => ['mainEntityOfPage'],
            //
            // Example: hide Person relationship properties from the CP.
            // 'Person' => ['knows', 'follows'],

            'CollegeOrUniversity' => [
                'acceptedPaymentMethod',
                'actionableFeedbackPolicy',
                'agentInteractionStatistic', // This is usually for "CreativeWorks" (like videos) or AI agents; it isn't standard for high-level organization schema.
                'aggregateRating',
                'amenityFeature',
                'branchCode',
                'correctionsPolicy',
                'currenciesAccepted',
                'dissolutionDate',
                'diversityPolicy',
                'diversityStaffingReport',
                'hasDriveThroughService',
                'hasMemberProgram',
                'hasMerchantReturnPolicy',
                'hasPOS',
                'hasProductReturnPolicy',
                'hasShippingService',
                'interactionStatistic', // This is usually for "CreativeWorks" (like videos) or AI agents; it isn't standard for high-level organization schema.
                'isAccessibleForFree',
                'leiCode', // UAMS has no LEI code.
                'maximumAttendeeCapacity',
                'member',
                'nonprofitStatus', // UAMS is a government entity, not a private nonprofit. The nonprofitStatus options are for private organizations, whereas UAMS is a state instrumentality.
                'openingHours',
                'openingHoursSpecification',
                'paymentAccepted',
                'priceRange',
                'publishingPrinciples',
                'review',
                'seeks',
                'specialOpeningHoursSpecification',
                'unnamedSourcesPolicy',
            ],

            'GeoCoordinates' => [
                'additionalType',
                'address',
                'addressCountry',
                'alternateName',
                'disambiguatingDescription',
                'identifier',
                'image',
                'mainEntityOfPage',
                'postalCode',
                'potentialAction',
                'sameAs',
                'subjectOf',
                'url',
            ],

            'Organization' => [
                'companyRegistration', // As a state-owned academic entity, UAMS isn't a "registered company" in the traditional sense
            ],

            'WebPage' => [
                'acquireLicensePage',
                'aggregateRating',
                'assesses',
                'associatedMedia',
                'audio',
                'award',
                'awards',
                'character',
                'comment',
                'commentCount',
                'conditionsOfAccess',
                'contentLocation',
                'contentRating',
                'contentReferenceTime',
                'discussionUrl',
                'displayLocation',
                'editEIDR',
                'educationalAlignment',
                'encoding',
                'encodingFormat',
                'encodings',
                'exampleOfWork',
                'expires',
                'fileFormat',
                'funder',
                'funding',
                'genre',
                'identifier',
                'interactivityType',
                'interpretedAsClaim',
                'license',
                'locationCreated',
                'mainContentOfPage',
                'material',
                'materialExtent',
                'offers',
                'pattern',
                'position',
                'producer',
                'provider',
                'publication',
                'publisherImprint',
                'recordedAt',
                'releasedEvent',
                'review',
                'sameAs',
                'size',
                'spatial',
                'spatialCoverage',
                'sponsor',
                'subjectOf',
                'teaches',
                'temporal',
                'temporalCoverage',
                'text',
                'thumbnail', // `thumbnailUrl` is preferred over `thumbnail` (which requires `ImageObject`)
                'timeRequired',
                'translationOfWork',
                'typicalAgeRange',
                'usageInfo',
                'version',
                'video',
                'wordCount',
                'workExample',
                'workTranslation',
                'xpath',
            ],

            'WebSite' => [
                'acquireLicensePage',
                'aggregateRating',
                'assesses',
                'associatedMedia',
                'audio',
                'award',
                'awards',
                'character',
                'comment',
                'commentCount',
                'conditionsOfAccess',
                'conditionsOfAccess',
                'contentLocation',
                'contentRating',
                'contentRating',
                'contentReferenceTime',
                'contentReferenceTime',
                'discussionUrl',
                'displayLocation',
                'editEIDR',
                'educationalAlignment',
                'encoding',
                'encodingFormat',
                'encodings',
                'exampleOfWork',
                'expires',
                'fileFormat',
                'funder',
                'funding',
                'genre',
                'identifier',
                'interactivityType',
                'interpretedAsClaim',
                'license',
                'locationCreated',
                'mainEntityOfPage',
                'material',
                'material',
                'materialExtent',
                'offers',
                'pattern',
                'pattern',
                'position',
                'producer',
                'provider',
                'publication',
                'publisherImprint',
                'recordedAt',
                'releasedEvent',
                'review',
                'sameAs',
                'size',
                'spatial',
                'spatialCoverage',
                'sponsor',
                'subjectOf',
                'teaches',
                'temporal',
                'temporalCoverage',
                'text',
                'thumbnail', // `thumbnailUrl` is preferred over `thumbnail` (which requires `ImageObject`)
                'timeRequired',
                'translationOfWork',
                'typicalAgeRange',
                'usageInfo',
                'version',
                'video',
                'wordCount',
                'workExample',
                'workTranslation',
                'xpath',
            ],
        ],
    ],
];
