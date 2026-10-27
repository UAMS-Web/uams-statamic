<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use App\Rules\IdentifierRegex;
use Statamic\Fields\Fieldtype;

/**
 * Identifier Fieldtype
 *
 * A Statamic fieldtype for identifier input with support for various identifier
 * types including AHAID, DUNS numbers, ORCID, NPI, and many others.
 *
 * Features:
 *   - Provides configurable identifier input fields for the Control Panel
 *   - Supports preset-driven configuration for various identifier types
 *   - Includes comprehensive validation patterns for each identifier type
 *   - Offers input masking for consistent formatting
 *   - Hides irrelevant config fields for a cleaner UI
 *
 * Adding New Presets:
 * To add a new preset option, add an entry to the `getPresetDefinitions()` method
 * with the following structure:
 *
 * ```php
 * 'preset_handle' => [
 *     'keywords' => [
 *         'Keyword 1',
 *         'Keyword 2',
 *         // ... more keywords for search functionality
 *     ],
 *     'label' => 'Human-readable label for the preset',
 *     'config_defaults' => [
 *         'placeholder' => 'e.g., example format',
 *         'input_mask' => true,
 *         'input_mask_class' => 'MaskedPattern',
 *         'input_mask_mask_MaskedPattern' => '0000000000',
 *         'input_mask_store_unmasked' => true,
 *         // ... additional input mask config options as needed
 *     ],
 *     'validation_rules' => [
 *         [
 *             'rule' => 'regex',
 *             'arguments' => ['/^\d{7}$/'],
 *             'message' => 'The value must be a valid identifier with exactly seven digits.',
 *         ],
 *         [
 *             'rule' => 'min',
 *             'arguments' => ['3'],
 *             'message' => 'The value must be at least three characters.',
 *         ],
 *     ],
 * ],
 * ```
 *
 * The preset handle will be used as the option key in the preset selector,
 * and the label will be displayed to users in the Control Panel.
 */
class Identifier extends Fieldtype
{
    /**
     * Provides DRY, extensible config and preset logic for input-based fieldtypes.
     *
     *   - Centralizes config field attribute overrides (type, instructions, defaults,
     *     etc.)
     *   - Supports preset-driven config logic
     *   - Ensures maintainable, declarative fieldtype configuration
     */
    use Input;

    /**
     * The display title of the fieldtype in the Control Panel.
     *
     * @var string The display title of the fieldtype in the Control Panel.
     */
    protected static $title = 'Identifier';

    /**
     * Return the name of the Vue component for this fieldtype.
     *
     * Overrides the component() method from the Input trait.
     *
     * @return string The name of the Vue component for this fieldtype
     */
    #[\Override]
    public function component(): string
    {
        return 'identifier';
    }

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'form-text-field';
    }

    /**
     * Fieldtype Categories
     *
     * @var array<string> Categories this fieldtype belongs to in the Statamic fieldtype selector
     */
    public $categories = [
        'text',
        'number',
        'special',
    ];

    /**
     * Centralized preset configuration that defines everything in one place.
     * Each preset contains keywords, label, config defaults, and validation rules.
     * The top-level key serves as the preset option handle.
     *
     * @return array<string, array{ keywords: array<string>, label: string, config_defaults: array<string, mixed>, validation_rules?: array<array<string, mixed>> }> Array of preset definitions where:
     *                                                                                                                                                               - Key: string - The preset handle used as the option key
     *                                                                                                                                                               - keywords: array<string> - Search keywords for this identifier type
     *                                                                                                                                                               - label: string - Human-readable label displayed in the Control Panel
     *                                                                                                                                                               - config_defaults: array<string, mixed> - Input mask and validation settings
     *                                                                                                                                                               - validation_rules: array<array> - Validation rules with rule, arguments, and
     *                                                                                                                                                               message
     */
    protected function getPresetDefinitions(): array
    {
        return [
            'ahaid' => [
                'keywords' => [
                    'AHA',
                    'AHAID',
                    'AHAIDs',
                    'American Hospital Association',
                    'Hospital Identifier',
                ],
                'label' => 'American Hospital Association Hospital Identifier (AHAID)',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 1234567',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'ahaid',
                    ],
                ],
            ],
            'cms_specialty_code' => [
                'keywords' => [
                    'CMS',
                    'Center for Medicare & Medicaid Services',
                    'Center for Medicare and Medicaid Services',
                    'Centers for Medicare & Medicaid Services',
                    'Centers for Medicare and Medicaid Services',
                    'code set',
                    'code sets',
                    'codes set',
                    'codes sets',
                    'specialties',
                    'specialty',
                ],
                'label' => 'Centers for Medicare & Medicaid Services (CMS) Specialty Codes',
                'config_defaults' => [
                    'input_mask' => false,
                    'placeholder' => 'e.g., A1 or 12',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'cms_specialty_code',
                    ],
                ],
            ],
            'epic_dep_id' => [
                'keywords' => [
                    'DEP',
                    'DEP ID',
                    'Department',
                    'Department ID',
                    'Epic',
                    'Epic Department',
                    'MyChart',
                    'UAMS Health',
                ],
                'label' => 'UAMS Health Epic Department (DEP) ID',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 10089',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'epic_dep_id',
                    ],
                ],
            ],
            'duns_number' => [
                'keywords' => [
                    'D&B D-U-N-S',
                    'D&B DUNS',
                    'D&B Data Universal Numbering System',
                    'D&B',
                    'D-U-N-S',
                    'DNB D-U-N-S',
                    'DNB DUNS',
                    'DNB Data Universal Numbering System',
                    'DNB',
                    'DUNS',
                    'Data Universal Numbering System',
                    'Dun & Bradstreet D-U-N-S',
                    'Dun & Bradstreet DUNS',
                    'Dun & Bradstreet Data Universal Numbering System',
                    'Dun & Bradstreet',
                    'Dun and Bradstreet D-U-N-S',
                    'Dun and Bradstreet DUNS',
                    'Dun and Bradstreet Data Universal Numbering System',
                    'Dun and Bradstreet',
                    'schema',
                ],
                'label' => 'Dun & Bradstreet Data Universal Numbering System (D-U-N-S) Number',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 123456789',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'duns_number',
                    ],
                ],
            ],
            'global_location_number' => [
                'keywords' => [
                    'GLN',
                    'GLNs',
                    'Global Location Number',
                    'globalLocationNumber',
                    'schema',
                ],
                'label' => 'Global Location Number (GLN)',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 1234567890123',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'global_location_number',
                    ],
                ],
            ],
            'cage_code' => [
                'keywords' => [
                    'CAGE',
                    'CAGE code',
                    'Commercial and Government Entity',
                    'Federal CAGE',
                    'NCAGE',
                    'SAM',
                    'schema',
                ],
                'label' => 'Commercial and Government Entity (CAGE) Code',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^[0-9A-Z]+$/',
                    'transform_case' => 'uppercase',
                    'placeholder' => 'e.g., 1QJY4',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'cage_code',
                    ],
                ],
            ],
            'ipeds_id' => [
                'keywords' => [
                    'IPEDS',
                    'IPEDS ID',
                    'Integrated Postsecondary Education Data System',
                    'Unit ID',
                    'UnitID',
                    'schema',
                ],
                'label' => 'Integrated Postsecondary Education Data System (IPEDS) Number',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 106397',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'ipeds_id',
                    ],
                ],
            ],
            'ope_id' => [
                'keywords' => [
                    'FICE',
                    'OPE ID',
                    'OPEID',
                    'Office of Postsecondary Education',
                    'Federal School Code',
                    'schema',
                ],
                'label' => 'Office of Postsecondary Education (OPE) ID Number',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 00110900',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'ope_id',
                    ],
                ],
            ],
            'uei' => [
                'keywords' => [
                    'SAM',
                    'SAM.gov',
                    'UEI',
                    'Unique Entity ID',
                    'Unique Entity Identifier',
                    'System for Award Management',
                    'schema',
                ],
                'label' => 'Federal/SAM.gov Unique Entity ID (UEI)',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^[0-9A-Z]+$/',
                    'transform_case' => 'uppercase',
                    'placeholder' => 'e.g., J1XYZ12345A1',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'uei',
                    ],
                ],
            ],
            'google_business_profile_cid' => [
                'keywords' => [
                    'Google CID',
                    'Google CIDs',
                    'Google customer ID',
                    'Google',
                    'hasMap',
                    'schema',
                ],
                'label' => 'Google CID (Customer ID) Number',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 12345678901234 or 12345678901234567890',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'google_business_profile_cid',
                    ],
                ],
            ],
            'google_scholar_author_id' => [
                'keywords' => [
                    'Google Scholar Author ID',
                    'Google Scholar Author Identifier',
                    'Google Scholar Author',
                    'Google Scholar Authors',
                    'Google Scholar',
                    'Google',
                ],
                'label' => 'Google Scholar Author ID',
                'config_defaults' => [
                    'input_mask' => false,
                    'placeholder' => 'e.g., ABC123-def45 or xyz_789-0123',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'google_scholar_author_id',
                    ],
                ],
            ],
            'google_tag_manager_container_id' => [
                'keywords' => [
                    'Google Tag Manager Container ID',
                    'Google Tag Manager Container Identifier',
                    'Google Tag Manager Container',
                    'Google Tag Manager Containers',
                    'Google Tag Manager',
                    'GTM Container ID',
                    'GTM Container Identifier',
                    'GTM Container',
                    'GTM Containers',
                    'GTM',
                    'Google',
                ],
                'label' => 'Google Tag Manager (GTM) Container ID',
                'config_defaults' => [
                    'input_mask' => false,
                    'transform_case' => 'uppercase',
                    'placeholder' => 'e.g., GTM-ABC123, GTM-1234ABC or GTM-ABCDEFGH',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'google_tag_manager_container_id',
                    ],
                ],
            ],
            'health_care_provider_taxonomy_code' => [
                'keywords' => [
                    'Health Care Provider Taxonomy code set',
                    'Health Care Provider Taxonomy code sets',
                    'Health Care Provider Taxonomy codes set',
                    'Health Care Provider Taxonomy codes sets',
                    'Health Care Provider Taxonomy',
                    'NUCC code set',
                    'NUCC code sets',
                    'NUCC codes set',
                    'NUCC codes sets',
                    'NUCC',
                    'National Uniform Claim Committee code set',
                    'National Uniform Claim Committee code sets',
                    'National Uniform Claim Committee codes set',
                    'National Uniform Claim Committee codes sets',
                    'National Uniform Claim Committee',
                    'National Uniform Claim Committee',
                    'Provider Taxonomy',
                    'area of specialization',
                    'area of specializations',
                    'area of sub-specialization',
                    'area of sub-specializations',
                    'area of subspecialization',
                    'area of subspecializations',
                    'areas of specialization',
                    'areas of sub-specialization',
                    'areas of subspecialization',
                    'classification',
                    'classifications',
                    'provider grouping',
                    'specialization',
                    'specializations',
                    'specialties',
                    'specialty',
                    'sub-specialties',
                    'sub-specialty',
                    'subspecialties',
                    'subspecialty',
                    'taxonomies',
                    'taxonomy',
                ],
                'label' => 'Health Care Provider Taxonomy Code',
                'config_defaults' => [
                    'input_mask' => false,
                    'transform_case' => 'uppercase',
                    'placeholder' => 'e.g., 123AB4567X',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'health_care_provider_taxonomy_code',
                    ],
                ],
            ],
            'isic_rev_4_code' => [
                'keywords' => [
                    'ISIC r.4',
                    'ISIC r4',
                    'ISIC rev 4',
                    'ISIC rev four',
                    'ISIC rev. 4',
                    'ISIC rev. four',
                    'ISIC rev.4',
                    'ISIC rev.four',
                    'ISIC revision 4',
                    'ISIC revision four',
                    'ISIC',
                    'ISIC, r.4',
                    'ISIC, r4',
                    'ISIC, rev 4',
                    'ISIC, rev four',
                    'ISIC, rev. 4',
                    'ISIC, rev. four',
                    'ISIC, rev.4',
                    'ISIC, rev.four',
                    'ISIC, revision 4',
                    'ISIC, revision four',
                    'International Standard of Industrial Classification of All Economic Activities r.4',
                    'International Standard of Industrial Classification of All Economic Activities r4',
                    'International Standard of Industrial Classification of All Economic Activities rev 4',
                    'International Standard of Industrial Classification of All Economic Activities rev four',
                    'International Standard of Industrial Classification of All Economic Activities rev. 4',
                    'International Standard of Industrial Classification of All Economic Activities rev. four',
                    'International Standard of Industrial Classification of All Economic Activities rev.4',
                    'International Standard of Industrial Classification of All Economic Activities rev.four',
                    'International Standard of Industrial Classification of All Economic Activities revision 4',
                    'International Standard of Industrial Classification of All Economic Activities revision four',
                    'International Standard of Industrial Classification of All Economic Activities',
                    'International Standard of Industrial Classification of All Economic Activities, r.4',
                    'International Standard of Industrial Classification of All Economic Activities, r4',
                    'International Standard of Industrial Classification of All Economic Activities, rev 4',
                    'International Standard of Industrial Classification of All Economic Activities, rev four',
                    'International Standard of Industrial Classification of All Economic Activities, rev. 4',
                    'International Standard of Industrial Classification of All Economic Activities, rev. four',
                    'International Standard of Industrial Classification of All Economic Activities, rev.4',
                    'International Standard of Industrial Classification of All Economic Activities, rev.four',
                    'International Standard of Industrial Classification of All Economic Activities, revision 4',
                    'International Standard of Industrial Classification of All Economic Activities, revision four',
                    'isicV4',
                    'schema',
                ],
                'label' => 'International Standard of Industrial Classification of All Economic Activities (ISIC), Revision 4 Code',
                'config_defaults' => [
                    'input_mask' => false,
                    'transform_case' => 'uppercase',
                    'placeholder' => 'e.g., A or 1234',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'isic_rev4_code',
                    ],
                ],
            ],
            'isco_08_code' => [
                'keywords' => [
                    'ISCO 08',
                    'ISCO',
                    'ISCO-08',
                    'ISCO08',
                    'International Standard Classification of Occupations',
                    'occupation',
                    'occupations',
                    'schema',
                    'taxonomies',
                    'taxonomy',
                ],
                'label' => 'ISCO-08 Code',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 1234',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'isco_08_code',
                    ],
                ],
            ],
            'iso_6523_code' => [
                'keywords' => [
                    'ISO 6523',
                    'ISO',
                    'ISO-6523',
                    'ISO6523',
                    'International Organization for Standardization 6523',
                    'International Organization for Standardization',
                    'iso6523Code',
                    'schema',
                ],
                'label' => 'ISO 6523 Code',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedPattern',
                    'input_mask_mask_MaskedPattern' => '0000:#[##################################][:###################################]',
                    'input_mask_definitions' => '{"#": "[A-Za-z0-9X]"}',
                    'input_mask_eager' => true,
                    'input_mask_prepare_case' => 'uppercase',
                    'placeholder' => 'e.g., 1234:ABC123 or 5678:XYZ789:DEF456',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'iso_6523_code',
                    ],
                ],
            ],
            'legal_entity_identifier' => [
                'keywords' => [
                    'Legal Entity Identifier',
                    'LEI',
                    'LEIs',
                    'schema',
                ],
                'label' => 'Legal Entity Identifier (LEI)',
                'config_defaults' => [
                    'input_mask' => false,
                    'transform_case' => 'uppercase',
                    'placeholder' => 'e.g., 123456789ABCDEFGHI12',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'legal_entity_identifier',
                    ],
                ],
            ],
            'naics_code' => [
                'keywords' => [
                    'North American Industry Classification System',
                    'NAICS',
                    'schema',
                ],
                'label' => 'North American Industry Classification System (NAICS) Code',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 12 or 123456',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'naics_code',
                    ],
                ],
            ],
            'npi' => [
                'keywords' => [
                    'NPI',
                    'NPIs',
                    'NPPES',
                    'National Plan and Provider Enumeration System',
                    'National Provider Identifier',
                    'schema',
                    'usNPI',
                ],
                'label' => 'National Provider Identifier (NPI)',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 1234567890',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'npi',
                    ],
                ],
            ],
            'onet_soc_occupation_code' => [
                'keywords' => [
                    'O*NET',
                    'O*Net Standard Occupational Classification',
                    'O*Net-SOC',
                    'ONET Standard Occupational Classification',
                    'ONET',
                    'ONET-SOC',
                    'Occupation Code Set',
                    'Occupation Code Set',
                    'Occupation Code Sets',
                    'Occupation Code',
                    'Occupation Codes',
                    'Occupation',
                    'Occupational Information Network SOC',
                    'Occupational Information Network Standard Occupational Classification',
                    'Occupational Information Network',
                    'Occupational Information Network-SOC',
                    'Occupations',
                    'SOC',
                    'Standard Occupational Classification',
                    'taxonomies',
                    'taxonomy',
                ],
                'label' => 'O*Net-SOC Occupation Code',
                'config_defaults' => [
                    'input_mask' => false,
                    'placeholder' => 'e.g., 11-1011.00 or 13-2011.01',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'onet_soc_occupation_code',
                    ],
                ],
            ],
            'orcid' => [
                'keywords' => [
                    'ORCID',
                    'ORCIDs',
                    'ORCID iD',
                    'Open Researcher and Contributor ID',
                    'Open Researcher and Contributor identifier',
                ],
                'label' => 'Open Researcher and Contributor ID (ORCID iD)',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedPattern',
                    'input_mask_mask_MaskedPattern' => '0000-0000-0000-000#',
                    'input_mask_definitions' => '{"#": "[0-9X]"}',
                    'input_mask_prepare_case' => 'uppercase',
                    'placeholder' => 'e.g., 1234-5678-9012-3456 or 1234-5678-9012-345X',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'orcid',
                    ],
                ],
            ],
            'pmid' => [
                'keywords' => [
                    'PubMed',
                    'PubMed ID',
                    'PubMed Identifier',
                    'PMIDs',
                    'PMID',
                ],
                'label' => 'PubMed Identifier',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 90123456',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'pmid',
                    ],
                ],
            ],
            'pubmed_author_id' => [
                'keywords' => [
                    'PubMed Author Identifier',
                    'PubMed Author ID',
                    'PubMed',
                ],
                'label' => 'PubMed Author Identifier',
                'config_defaults' => [
                    'input_mask' => false,
                    'transform_case' => 'lowercase',
                    'placeholder' => 'e.g., mccoy lh',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'pubmed_author_id',
                    ],
                ],
            ],
            'researcherid' => [
                'keywords' => [
                    'WOS',
                    'WoS',
                    'WOS ID',
                    'Web of Science',
                    'Web of Science ID',
                    'Web of Science Researcher ID',
                    'Web of Science ResearcherID',
                    'Web of Science ResearcherIDs',
                    'ResearcherID',
                    'ResearcherIDs',
                    'WoS RID',
                    'WoS RIDs',
                    'WoS ResearcherIDs',
                    'WoS ResearcherID',
                    'WoS Researcher ID',
                    'RID',
                    'RIDs',
                    'Researcher ID',
                ],
                'label' => 'Web of Science ResearcherID',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedPattern',
                    'input_mask_mask_MaskedPattern' => 'a[aa]-0000-0000',
                    'input_mask_prepare_case' => 'uppercase',
                    'placeholder' => 'e.g., ABC-1234-2023 or Z-5678-1990',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'researcher_id',
                    ],
                ],
            ],
            'rxcui' => [
                'keywords' => [
                    'National Library of Medicine RxNorm',
                    'RxCUI',
                    'RxCUIs',
                    'RxNorm',
                    'schema',
                ],
                'label' => 'National Library of Medicine RxNorm Concept Unique Identifier (RxCUI)',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 1, 123 or 123456789',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'rxcui',
                    ],
                ],
            ],
            'scopus_author_id' => [
                'keywords' => [
                    'Scopus Author ID',
                    'Scopus Author Identifier',
                    'Scopus Author',
                    'Scopus Authors',
                    'Scopus ID',
                    'Scopus',
                ],
                'label' => 'Scopus Author ID',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+$/',
                    'placeholder' => 'e.g., 1234567890 or 567890123456',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'scopus_author_id',
                    ],
                ],
            ],
            'taxpayer_identification_number' => [
                'keywords' => [
                    'EIN',
                    'EINs',
                    'Employer Identification Number',
                    'TIN',
                    'TINs',
                    'Taxpayer Identification Number',
                    'fiscal',
                    'schema',
                    'tax',
                    'taxID',
                ],
                'label' => 'U.S. Employer Identification Number (EIN)',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedPattern',
                    'input_mask_mask_MaskedPattern' => '00-0000000',
                    'placeholder' => 'e.g., 12-3456789',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'taxpayer_identification_number',
                    ],
                ],
            ],
            'ticker_symbol' => [
                'keywords' => [
                    'NASDAQ',
                    'NYSE',
                    'Stock Exchange',
                    'Stock Ticker',
                    'Stock',
                    'Stocks',
                    'Ticker',
                    'Ticker',
                    'Tickers',
                    'schema',
                    'tickerSymbol',
                ],
                'label' => 'Stock Ticker Symbol',
                'config_defaults' => [
                    'input_mask' => false,
                    'transform_case' => 'uppercase',
                    'placeholder' => 'e.g., AAPL, BRK.A or XYZ.PK',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'ticker_symbol',
                    ],
                ],
            ],
            'va_station_id' => [
                'keywords' => [
                    'STA3N',
                    'Station',
                    'U.S. Department of Veterans Affairs Station',
                    'U.S. V.A. Station',
                    'U.S. V.A.',
                    'U.S. VA Station',
                    'U.S. VA',
                    'U.S. Veterans Affairs Station',
                    'U.S. Veterans Affairs Station',
                    'US Department of Veterans Affairs Station',
                    'US V.A. Station',
                    'US V.A.',
                    'US VA Station',
                    'US VA',
                    'US Veterans Affairs Station',
                    'US Veterans Affairs',
                    'United States Department of Veterans Affairs Station',
                    'United States V.A. Station',
                    'United States V.A.',
                    'United States VA Station',
                    'United States VA',
                    'V.A. Station',
                    'V.A.',
                    'VA Station',
                    'VA',
                    'Veterans Affairs Station',
                    'Veterans Affairs',
                ],
                'label' => 'U.S. Department of Veterans Affairs Station Identifier (STA3N)',
                'config_defaults' => [
                    'input_mask_class' => 'MaskedRegExp',
                    'input_mask_mask_MaskedRegExp' => '/^\d+/',
                    'placeholder' => 'e.g., 123',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'va_station_id',
                    ],
                ],
            ],
            'va_substation_id' => [
                'keywords' => [
                    'STA6A',
                    'Substation',
                    'U.S. Department of Veterans Affairs Substation',
                    'U.S. V.A. Substation',
                    'U.S. V.A.',
                    'U.S. VA Substation',
                    'U.S. VA',
                    'U.S. Veterans Affairs Substation',
                    'U.S. Veterans Affairs Substation',
                    'US Department of Veterans Affairs Substation',
                    'US V.A. Substation',
                    'US V.A.',
                    'US VA Substation',
                    'US VA',
                    'US Veterans Affairs Substation',
                    'US Veterans Affairs',
                    'United States Department of Veterans Affairs Substation',
                    'United States V.A. Substation',
                    'United States V.A.',
                    'United States VA Substation',
                    'United States VA',
                    'V.A. Substation',
                    'V.A.',
                    'VA Substation',
                    'VA',
                    'Veterans Affairs Substation',
                    'Veterans Affairs',
                ],
                'label' => 'U.S. Department of Veterans Affairs Substation Identifier (STA6A)',
                'config_defaults' => [
                    'input_mask' => false,
                    'transform_case' => 'uppercase',
                    'placeholder' => 'e.g., 123ABC or 123456',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'va_substation_id',
                    ],
                ],
            ],
            'va_station_substation_id' => [
                'keywords' => [
                    'STA3N',
                    'STA6A',
                    'Station',
                    'Substation',
                    'U.S. Department of Veterans Affairs Station',
                    'U.S. Department of Veterans Affairs Substation',
                    'U.S. Department of Veterans Affairs',
                    'U.S. V.A. Station',
                    'U.S. V.A. Substation',
                    'U.S. V.A.',
                    'U.S. VA Station',
                    'U.S. VA Substation',
                    'U.S. VA',
                    'U.S. Veterans Affairs Station',
                    'U.S. Veterans Affairs Substation',
                    'U.S. Veterans Affairs',
                    'US Department of Veterans Affairs Station',
                    'US Department of Veterans Affairs Substation',
                    'US Department of Veterans Affairs',
                    'US V.A. Station',
                    'US V.A. Substation',
                    'US V.A.',
                    'US VA Station',
                    'US VA Substation',
                    'US VA',
                    'US Veterans Affairs Station',
                    'US Veterans Affairs Substation',
                    'US Veterans Affairs',
                    'United States Department of Veterans Affairs Station',
                    'United States Department of Veterans Affairs Substation',
                    'United States Department of Veterans Affairs',
                    'United States V.A. Station',
                    'United States V.A. Substation',
                    'United States V.A.',
                    'United States VA Station',
                    'United States VA Substation',
                    'United States VA',
                    'V.A. Station',
                    'V.A. Substation',
                    'V.A.',
                    'VA Station',
                    'VA Substation',
                    'VA',
                    'Veterans Affairs Station',
                    'Veterans Affairs Substation',
                    'Veterans Affairs',
                ],
                'label' => 'U.S. Department of Veterans Affairs Station Identifier (STA3N) or Substation Identifier (STA6A)',
                'config_defaults' => [
                    'input_mask' => false,
                    'transform_case' => 'uppercase',
                    'placeholder' => 'e.g., 123, 123AB or 123AB1',
                ],
                'validation_rules' => [
                    [
                        'rule' => 'va_station_substation_id',
                    ],
                ],
            ],
        ];
    }

    /**
     * Unified preset definition for identifier types.
     *
     * @return array<string, mixed> Structured array with keys:
     *                              - display: string
     *                              - instructions?: string
     *                              - default: string
     *                              - options: array<string, array{ label: string, config: array<string, mixed> }>
     */
    protected function getPresets(): array
    {
        // Initialize the options array to hold preset configurations
        $options = [];

        // Get all preset definitions from the centralized configuration
        $presetDefinitions = $this->getPresetDefinitions();

        // Loop through each preset definition
        foreach ($presetDefinitions as $handle => $preset) {
            // Build the options array
            $options[$handle] = [
                'label' => $preset['label'],
                'config' => $preset['config_defaults'],
            ];
        }

        // Return the complete preset configuration for the Control Panel
        return [
            'display' => 'Identifier Type',
            'instructions' => 'Choose the type of identifier for this field.',
            'options' => $options,
        ];
    }

    /**
     * Get the allowed autocomplete keys for this fieldtype.
     *
     * @return array<string> The allowed autocomplete keys for this fieldtype
     */
    protected function getAllowedAutocompleteKeys(): array
    {
        return [];
    }

    /**
     * Allows overriding config field attributes for this fieldtype.
     *
     * @return array<string, mixed> Config field overrides
     */
    protected function getConfigFieldOverrides(): array
    {
        return [
            'input_type' => [
                'default' => 'text',
            ],
            'input_mask' => [
                'default' => true,
            ],
            'input_mask_class' => [
                'default' => 'MaskedPattern',
            ],
            // 'input_mask_mask_MaskedDynamic' => [
            //     'default' => null,
            // ],
            // 'input_mask_mask_MaskedFunction' => [
            //     'default' => null,
            // ],
            // 'input_mask_mask_MaskedPattern' => [
            //     'default' => null,
            // ],
            // 'input_mask_mask_MaskedRegExp' => [
            //     'default' => null,
            // ],
            // 'input_mask_store_unmasked' => [
            //     'default' => null,
            // ],
            // 'input_mask_prepend_text_enabled' => [
            //     'default' => null,
            // ],
            // 'input_mask_prepend_text' => [
            //     'default' => null,
            // ],
            // 'input_mask_append_text_enabled' => [
            //     'default' => null,
            // ],
            // 'input_mask_append_text' => [
            //     'default' => null,
            // ],
            // 'input_mask_autofix' => [
            //     'default' => null,
            // ],
            // 'input_mask_blocks' => [
            //     'default' => null,
            // ],
            // 'input_mask_compiledMasks' => [
            //     'default' => null,
            // ],
            // 'input_mask_currentMask' => [
            //     'default' => null,
            // ],
            // 'input_mask_definitions' => [
            //     'default' => null,
            // ],
            // 'input_mask_displayChar' => [
            //     'default' => null,
            // ],
            // 'input_mask_eager' => [
            //     'default' => null,
            // ],
            // 'input_mask_enum' => [
            //     'default' => null,
            // ],
            // 'input_mask_exposeMask' => [
            //     'default' => null,
            // ],
            // 'input_mask_from' => [
            //     'default' => null,
            // ],
            // 'input_mask_lazy' => [
            //     'default' => null,
            // ],
            // 'input_mask_mapToRadix' => [
            //     'default' => null,
            // ],
            // 'input_mask_max' => [
            //     'default' => null,
            // ],
            // 'input_mask_maxLength' => [
            //     'default' => null,
            // ],
            // 'input_mask_min' => [
            //     'default' => null,
            // ],
            // 'input_mask_normalizeZeros' => [
            //     'default' => null,
            // ],
            // 'input_mask_overwrite' => [
            //     'default' => null,
            // ],
            // 'input_mask_padFractionalZeros' => [
            //     'default' => null,
            // ],
            // 'input_mask_parent' => [
            //     'default' => null,
            // ],
            // 'input_mask_pattern' => [
            //     'default' => null,
            // ],
            // 'input_mask_placeholderChar' => [
            //     'default' => null,
            // ],
            // 'input_mask_prepareChar' => [
            //     'default' => null,
            // ],
            // 'input_mask_radix' => [
            //     'default' => null,
            // ],
            // 'input_mask_repeat' => [
            //     'default' => null,
            // ],
            // 'input_mask_scale' => [
            //     'default' => null,
            // ],
            // 'input_mask_skipInvalid' => [
            //     'default' => null,
            // ],
            // 'input_mask_thousandsSeparator' => [
            //     'default' => null,
            // ],
            // 'input_mask_to' => [
            //     'default' => null,
            // ],
            'calculation_type' => [
                'default' => 'text',
            ],
        ];
    }

    /**
     * Get config fields that should be hidden (visibility: hidden) for this
     * fieldtype.
     *
     * Hide fields that have sensible defaults for this fieldtype.
     *
     * @return array<string> Config fields that should be hidden (visibility: hidden) for this fieldtype
     */
    protected function getHiddenConfigFields(): array
    {
        return [
            'accept',
            'accesskey',
            'alt',
            'anchor',
            'antlers',
            'append',
            'autocapitalize',
            'autocapitalize_setting',
            'autocomplete',
            'autocomplete_query',
            'autocorrect',
            'calculation_type',
            'capture',
            'checked',
            'class',
            'contenteditable',
            'debounce', // Only Text fieldtype uses debounce in Vue
            'dir',
            'dirname',
            'draggable',
            'enterkeyhint',
            'exportparts',
            'focus',
            'form',
            'formaction',
            'formenctype',
            'formmethod',
            'formnovalidate',
            'formtarget',
            'height',
            'id',
            'inert',
            'input_mask',
            'input_mask_append_text',
            'input_mask_append_text_enabled',
            'input_mask_autofix',
            'input_mask_blocks',
            'input_mask_class',
            'input_mask_compiledMasks',
            'input_mask_currentMask',
            'input_mask_definitions',
            'input_mask_displayChar',
            'input_mask_eager',
            'input_mask_enum',
            'input_mask_exposeMask',
            'input_mask_from',
            'input_mask_lazy',
            'input_mask_mapToRadix',
            'input_mask_mask',
            'input_mask_mask_MaskedDynamic',
            'input_mask_mask_MaskedPattern',
            'input_mask_mask_MaskedRegExp',
            'input_mask_max',
            'input_mask_maxLength',
            'input_mask_min',
            'input_mask_normalizeZeros',
            'input_mask_overwrite',
            'input_mask_padFractionalZeros',
            'input_mask_parent',
            'input_mask_pattern',
            'input_mask_placeholderChar',
            'input_mask_prepare_case',
            'input_mask_prepend_text',
            'input_mask_prepend_text_enabled',
            'input_mask_radix',
            'input_mask_repeat',
            'input_mask_scale',
            'input_mask_skipInvalid',
            'input_mask_store_unmasked',
            'input_mask_thousandsSeparator',
            'input_mask_to',
            'input_type',
            'inputmode',
            'is',
            'itemid',
            'itemprop',
            'itemref',
            'itemscope',
            'itemtype',
            'lang',
            'list',
            'max',
            'maxlength',
            'min',
            'minlength',
            'multiple',
            'name',
            'nonce',
            'part',
            'pattern',
            'placeholder',
            'popover',
            'popovertarget',
            'popovertargetaction',
            'prepend',
            'role',
            'size',
            'slot',
            'spellcheck',
            'src',
            'step',
            'style',
            'tabindex',
            'title',
            'transform_case',
            'translate',
            'virtualkeyboardpolicy',
            'width',
            'writingsuggestions',
        ];
    }

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'classifications',
        'classifiers',
        'classifies',
        'classify',
        'code',
        'codes',
        'id',
        'identification',
        'identifications',
        'identifier',
        'identifiers',
        'identifies',
        'ids',
        'number',
        'numbers',
        'symbol',
        'symbols',
        'uid',
        'unique id',
        'unique identifier',
        'unique identifiers',
        'unique ids',
    ];

    /**
     * Override the keywords method to dynamically add keywords from preset
     * definitions.
     *
     * This method dynamically generates keywords from all preset definitions,
     * ensuring comprehensive search functionality for the fieldtype.
     *
     * @return array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    #[\Override]
    public function keywords(): array
    {
        // Start with the base keywords defined in the property
        $keywords = $this->keywords;

        // ---
        // Dynamically add keywords from preset definitions
        // ---

        // Loop through each preset definition
        foreach ($this->getPresetDefinitions() as $preset) {
            // Merge the preset keywords with the base keywords
            $keywords = \array_merge(
                $keywords,
                $preset['keywords']
            );
        }

        // Return a clean, indexed array with unique keywords
        return \array_values(array_unique($keywords));
    }

    /**
     * Provide validation rules for this fieldtype.
     *
     * This method returns validation rules based on the selected preset. It uses
     * the same logic as rules() but works with the provided config parameter.
     *
     * @param  mixed  $value  The value to validate
     * @param  array<string, mixed>  $config  The field config
     * @return list<object|string> Array of validation rules (Laravel format)
     */
    public function getValidationRules(
        mixed $value,
        array $config = []
    ): array {
        // Get the selected preset from config
        $preset = $config['preset'] ?? null;

        // Process validation rules for the preset
        return $this->processPresetValidationRules(
            \is_string($preset) ? $preset : null
        );
    }

    /**
     * Provide validation rules for field content validation.
     *
     * This method adds validation rules based on the selected preset. It merges the
     * base input validation rules with preset-specific validation rules. Supports
     * flexible validation rule structure with special handling for regex rules.
     *
     * Validation Rule Structure:
     * Each validation rule in the preset definition should have the following
     * structure:
     *     ```php
     *     [
     *         'rule' => 'regex',           // Laravel validation rule name
     *         'arguments' => ['/^\d{7}$/'], // Array of arguments for the rule
     *         'message' => 'Custom error message',
     *     ]
     *     ```
     *
     * Special Handling:
     *   - Regex rules are converted to IdentifierRegex instances for custom
     *     validation
     *   - Class instantiation rules (e.g., 'new App\Rules\Npi') are instantiated as
     *     objects
     *   - Other Laravel rules are converted to string format (e.g., 'min:3', 'max:10')
     *   - Arguments are joined with commas for string-based rules
     *
     * @return list<object|string> Validation rules (Laravel format)
     */
    #[\Override]
    public function rules(): array
    {
        // Get the selected preset from config
        $preset = $this->config('preset');

        // Process validation rules for the preset
        return $this->processPresetValidationRules(
            \is_string($preset) ? $preset : null
        );
    }

    /**
     * Process validation rules for a given preset.
     *
     * This method extracts and processes validation rules from preset definitions,
     * handling regex rules, class instantiation rules, and string-based rules.
     *
     * @param  string|null  $preset  The preset handle, or null if no preset is selected
     * @return list<object|string> Array of validation rules (Laravel format)
     */
    private function processPresetValidationRules(
        ?string $preset
    ): array {
        // Initialize the rules array
        /** @var list<object|string> $rules */
        $rules = [];

        // ---
        // If a preset is selected, add its validation rules
        // ---

        // Check if a preset is selected
        if ($preset) {
            // Get all preset definitions
            $presetDefinitions = $this->getPresetDefinitions();

            // Check if the selected preset has validation rules
            if (isset($presetDefinitions[$preset]['validation_rules'])) {
                // Get the validation rules array
                $validationRules = $presetDefinitions[$preset]['validation_rules'];

                // Loop through each validation rule
                foreach ($validationRules as $rule) {
                    // ---
                    // Handle regex rules with custom IdentifierRegex class
                    // ---

                    // Narrow the rule name to a string for downstream operations
                    $ruleName = \is_string($rule['rule']) ? $rule['rule'] : '';

                    // Check if the rule is a regex rule
                    if ($ruleName === 'regex') {
                        // Narrow the regex arguments and message to strings
                        $regexArguments = \is_array($rule['arguments']) ? $rule['arguments'] : [];
                        $regexPattern = isset($regexArguments[0]) && \is_string($regexArguments[0])
                            ? $regexArguments[0]
                            : '';
                        $regexMessage = isset($rule['message']) && \is_string($rule['message'])
                            ? $rule['message']
                            : '';

                        // Create a new IdentifierRegex instance with the pattern and message
                        $rules[] = new IdentifierRegex(
                            $regexPattern,
                            $regexMessage
                        );
                    } elseif (
                        \str_starts_with(
                            $ruleName,
                            'new '
                        )
                    ) { // Check if the rule is a class instantiation rule
                        // ---
                        // Handle class instantiation rules (e.g., 'new App\Rules\Npi')
                        // ---

                        // Extract the class name by removing the 'new ' prefix
                        $className = \str_replace(
                            'new ',
                            '',
                            $ruleName
                        );

                        // Instantiate the class and add it to the rules array
                        $rules[] = new $className;
                    } else { // Otherwise
                        // ---
                        // Handle other Laravel validation rules
                        // ---

                        // Get the base rule string
                        $ruleString = $ruleName;

                        // Check if the rule has arguments
                        if (! empty($rule['arguments'])) {
                            // Narrow the arguments to an array of strings before joining
                            $ruleArguments = \is_array($rule['arguments'])
                                ? \array_map(static fn ($argument): string => \is_scalar($argument) ? (string) $argument : '', $rule['arguments'])
                                : [];

                            // Append arguments to the rule string in Laravel format
                            $ruleString .= ':'.\implode(
                                ',',
                                $ruleArguments
                            );
                        }

                        // Add the formatted rule string to the rules array
                        $rules[] = $ruleString;
                    }
                }

                // Clean up variables to free up memory
                unset($validationRules);
            }

            // Clean up variables to free up memory
            unset($presetDefinitions);
        }

        // Clean up variables to free up memory
        unset($preset);

        // Return the rules
        return $rules;
    }
}
