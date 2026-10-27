<?php

declare(strict_types=1);

use App\Fieldtypes\Identifier;
use App\Rules\IdentifierRegex;
use App\Rules\Npi;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Behavior of the Identifier fieldtype's validation-rule resolution
 * (app/Fieldtypes/Identifier.php): preset regex rules become IdentifierRegex
 * instances, non-regex preset rules become Laravel string rules, and the rule set
 * falls back to the Input trait without a preset. Also covers IdentifierRegex's
 * array-value handling.
 *
 * Mutation: IdentifierRegex is ~64% with every killable mutant dead; the survivors are
 * equivalents (the array->string coercion via array_map + (string) casts is redundant
 * with implode's own coercion, and the non-scalar `: ''` fallbacks still yield
 * non-matching strings). The Identifier fieldtype's preset-definition map, config
 * overrides, hidden fields and keywords are pinned exactly, so its config data is
 * covered along with the validation-rule resolution logic.
 *
 * @command  php artisan test --compact tests/Unit/Fieldtypes/IdentifierFieldtypeTest.php
 */
uses(TestCase::class);

it('returns validation rules for a regex preset', function () {
    $rules = (new Identifier)->getValidationRules('test', ['preset' => 'ahaid']);

    expect($rules)->not->toBeEmpty();
});

it('formats non-regex preset rules as Laravel string rules', function () {
    // Override the protected preset definitions with a non-regex preset (no PHPUnit mock).
    $fieldtype = new class extends Identifier
    {
        protected function getPresetDefinitions(): array
        {
            return [
                'test_preset' => [
                    'keywords' => [],
                    'label' => 'Test',
                    'config_defaults' => [],
                    'validation_rules' => [
                        ['rule' => 'min', 'arguments' => ['3'], 'message' => 'The :attribute must be at least 3 characters.'],
                        ['rule' => 'max', 'arguments' => ['10'], 'message' => 'The :attribute must not exceed 10 characters.'],
                    ],
                ],
            ];
        }
    };

    $rules = $fieldtype->getValidationRules('test', ['preset' => 'test_preset']);

    expect($rules)->toContain('min:3', 'max:10');
});

it('falls back to the Input trait rules without a preset', function () {
    // No preset selected → no preset-derived rules are contributed at all.
    expect((new Identifier)->getValidationRules('test', []))->toBe([]);
});

it('migrates every mask preset to declarative config — no JS-snippet eval keys remain', function () {
    // Expose the protected real preset definitions (no override).
    $fieldtype = new class extends Identifier
    {
        /**
         * @return array<string, array{keywords: array<string>, label: string, config_defaults: array<string, mixed>, validation_rules: array<array<string, mixed>>}>
         */
        public function presetsForTest(): array
        {
            /** @var array<string, array{keywords: array<string>, label: string, config_defaults: array<string, mixed>, validation_rules: array<array<string, mixed>>}> $presets */
            $presets = $this->getPresetDefinitions();

            return $presets;
        }
    };

    foreach ($fieldtype->presetsForTest() as $handle => $preset) {
        $config = $preset['config_defaults'];

        // The eval'd `prepareChar` JS-snippet key is gone everywhere (replaced by prepare_case).
        expect($config)->not->toHaveKey('input_mask_prepareChar');

        // `definitions`, where present, must be valid JSON (parsed, never eval'd).
        if (array_key_exists('input_mask_definitions', $config)) {
            /** @var string $definitions */
            $definitions = $config['input_mask_definitions'];
            expect(json_decode((string) $definitions, true))->toBeArray();
        }

        // `prepare_case`, where present, must be a known declarative value.
        if (array_key_exists('input_mask_prepare_case', $config)) {
            expect(['uppercase', 'lowercase'])->toContain($config['input_mask_prepare_case']);
        }
    }
});

it('migrates the orcid / iso_6523_code / researcherid presets to RegExp-source + prepare_case', function () {
    $fieldtype = new class extends Identifier
    {
        /**
         * @return array<string, array{keywords: array<string>, label: string, config_defaults: array<string, mixed>, validation_rules: array<array<string, mixed>>}>
         */
        public function presetsForTest(): array
        {
            /** @var array<string, array{keywords: array<string>, label: string, config_defaults: array<string, mixed>, validation_rules: array<array<string, mixed>>}> $presets */
            $presets = $this->getPresetDefinitions();

            return $presets;
        }
    };
    $presets = $fieldtype->presetsForTest();

    expect($presets['orcid']['config_defaults']['input_mask_definitions'])->toBe('{"#": "[0-9X]"}');
    expect($presets['orcid']['config_defaults']['input_mask_prepare_case'])->toBe('uppercase');
    expect($presets['iso_6523_code']['config_defaults']['input_mask_definitions'])->toBe('{"#": "[A-Za-z0-9X]"}');
    expect($presets['iso_6523_code']['config_defaults']['input_mask_prepare_case'])->toBe('uppercase');
    expect($presets['researcherid']['config_defaults']['input_mask_prepare_case'])->toBe('uppercase');
});

it('exposes a selectable epic_dep_id preset backed by the epic_dep_id rule (#960)', function () {
    // Expose the protected real preset definitions (no override).
    $fieldtype = new class extends Identifier
    {
        /**
         * @return array<string, array{keywords: array<string>, label: string, config_defaults: array<string, mixed>, validation_rules: array<array<string, mixed>>}>
         */
        public function presetsForTest(): array
        {
            /** @var array<string, array{keywords: array<string>, label: string, config_defaults: array<string, mixed>, validation_rules: array<array<string, mixed>>}> $presets */
            $presets = $this->getPresetDefinitions();

            return $presets;
        }
    };
    $presets = $fieldtype->presetsForTest();

    // The preset is selectable and labelled for the DEP identifier.
    expect($presets)->toHaveKey('epic_dep_id');
    expect($presets['epic_dep_id']['label'])->toContain('DEP');

    // It is wired to the registered `epic_dep_id` rule alias (App\Rules\EpicDepId, UAMS-Web/uams-statamic-archive#959).
    expect($presets['epic_dep_id']['validation_rules'][0]['rule'])->toBe('epic_dep_id');

    // Selecting the preset resolves that rule into the fieldtype's validation rules.
    expect((new Identifier)->getValidationRules('dep', ['preset' => 'epic_dep_id']))
        ->toContain('epic_dep_id');
});

it('validates string and array values via IdentifierRegex', function (mixed $value, bool $shouldPass) {
    $rule = new IdentifierRegex('/^\d{3}$/', 'Must be exactly 3 digits');

    $failed = false;
    $rule->validate('test', $value, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBe(! $shouldPass);
})->with([
    'a matching string passes' => ['123', true],
    'an array joining to a match passes' => [['1', '2', '3'], true],
    'an array not matching fails' => [['1', '2'], false],
]);

/**
 * Call a protected Identifier method via reflection.
 */
function identifierCall(string $method): mixed
{
    return (new ReflectionMethod(Identifier::class, $method))->invoke(new Identifier);
}

/**
 * An Identifier whose only preset carries the given validation rules.
 *
 * @param  list<array<string, mixed>>  $rules
 */
function identifierWithRules(array $rules): Identifier
{
    return new class($rules) extends Identifier
    {
        /**
         * @param  list<array<string, mixed>>  $presetRules
         */
        public function __construct(private array $presetRules) {}

        protected function getPresetDefinitions(): array
        {
            return ['probe' => ['keywords' => [], 'label' => 'Probe', 'config_defaults' => [], 'validation_rules' => $this->presetRules]];
        }
    };
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = new Identifier;

    expect(Identifier::title())->toBe('Identifier')
        ->and($fieldtype->component())->toBe('identifier')
        ->and($fieldtype->icon())->toBe('form-text-field')
        ->and($fieldtype->categories())->toBe(['text', 'number', 'special']);
});

it('pins every preset definition exactly', function (): void {
    expect(identifierCall('getPresetDefinitions'))->toBe([
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+$/',
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
                'input_mask_mask_MaskedRegExp' => '/^\\d+/',
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
    ]);
});

it('offers each preset by its label and config defaults', function (): void {
    /** @var array<string, array{label: string, config_defaults: array<string, mixed>}> $definitions */
    $definitions = identifierCall('getPresetDefinitions');

    expect(identifierCall('getPresets'))->toBe([
        'display' => 'Identifier Type',
        'instructions' => 'Choose the type of identifier for this field.',
        'options' => array_map(
            fn (array $preset): array => ['label' => $preset['label'], 'config' => $preset['config_defaults']],
            $definitions
        ),
    ]);
});

it('pins the config overrides, hidden fields and autocomplete keys exactly', function (): void {
    expect(identifierCall('getConfigFieldOverrides'))->toBe([
        'input_type' => [
            'default' => 'text',
        ],
        'input_mask' => [
            'default' => true,
        ],
        'input_mask_class' => [
            'default' => 'MaskedPattern',
        ],
        'calculation_type' => [
            'default' => 'text',
        ],
    ])
        ->and(identifierCall('getHiddenConfigFields'))->toBe([
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
            'debounce',
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
        ])
        ->and(identifierCall('getAllowedAutocompleteKeys'))->toBe([]);
});

it('exposes its own and every preset keyword, once each', function (): void {
    expect((new Identifier)->keywords())->toBe([
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
        'AHA',
        'AHAID',
        'AHAIDs',
        'American Hospital Association',
        'Hospital Identifier',
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
        'DEP',
        'DEP ID',
        'Department',
        'Department ID',
        'Epic',
        'Epic Department',
        'MyChart',
        'UAMS Health',
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
        'GLN',
        'GLNs',
        'Global Location Number',
        'globalLocationNumber',
        'CAGE',
        'CAGE code',
        'Commercial and Government Entity',
        'Federal CAGE',
        'NCAGE',
        'SAM',
        'IPEDS',
        'IPEDS ID',
        'Integrated Postsecondary Education Data System',
        'Unit ID',
        'UnitID',
        'FICE',
        'OPE ID',
        'OPEID',
        'Office of Postsecondary Education',
        'Federal School Code',
        'SAM.gov',
        'UEI',
        'Unique Entity ID',
        'Unique Entity Identifier',
        'System for Award Management',
        'Google CID',
        'Google CIDs',
        'Google customer ID',
        'Google',
        'hasMap',
        'Google Scholar Author ID',
        'Google Scholar Author Identifier',
        'Google Scholar Author',
        'Google Scholar Authors',
        'Google Scholar',
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
        'provider grouping',
        'specialization',
        'specializations',
        'sub-specialties',
        'sub-specialty',
        'subspecialties',
        'subspecialty',
        'taxonomies',
        'taxonomy',
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
        'ISCO 08',
        'ISCO',
        'ISCO-08',
        'ISCO08',
        'International Standard Classification of Occupations',
        'occupation',
        'occupations',
        'ISO 6523',
        'ISO',
        'ISO-6523',
        'ISO6523',
        'International Organization for Standardization 6523',
        'International Organization for Standardization',
        'iso6523Code',
        'Legal Entity Identifier',
        'LEI',
        'LEIs',
        'North American Industry Classification System',
        'NAICS',
        'NPI',
        'NPIs',
        'NPPES',
        'National Plan and Provider Enumeration System',
        'National Provider Identifier',
        'usNPI',
        'O*NET',
        'O*Net Standard Occupational Classification',
        'O*Net-SOC',
        'ONET Standard Occupational Classification',
        'ONET',
        'ONET-SOC',
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
        'ORCID',
        'ORCIDs',
        'ORCID iD',
        'Open Researcher and Contributor ID',
        'Open Researcher and Contributor identifier',
        'PubMed',
        'PubMed ID',
        'PubMed Identifier',
        'PMIDs',
        'PMID',
        'PubMed Author Identifier',
        'PubMed Author ID',
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
        'National Library of Medicine RxNorm',
        'RxCUI',
        'RxCUIs',
        'RxNorm',
        'Scopus Author ID',
        'Scopus Author Identifier',
        'Scopus Author',
        'Scopus Authors',
        'Scopus ID',
        'Scopus',
        'EIN',
        'EINs',
        'Employer Identification Number',
        'TIN',
        'TINs',
        'Taxpayer Identification Number',
        'fiscal',
        'tax',
        'taxID',
        'NASDAQ',
        'NYSE',
        'Stock Exchange',
        'Stock Ticker',
        'Stock',
        'Stocks',
        'Ticker',
        'Tickers',
        'tickerSymbol',
        'STA3N',
        'Station',
        'U.S. Department of Veterans Affairs Station',
        'U.S. V.A. Station',
        'U.S. V.A.',
        'U.S. VA Station',
        'U.S. VA',
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
        'STA6A',
        'Substation',
        'U.S. Department of Veterans Affairs Substation',
        'U.S. V.A. Substation',
        'U.S. VA Substation',
        'U.S. Veterans Affairs Substation',
        'US Department of Veterans Affairs Substation',
        'US V.A. Substation',
        'US VA Substation',
        'US Veterans Affairs Substation',
        'United States Department of Veterans Affairs Substation',
        'United States V.A. Substation',
        'United States VA Substation',
        'V.A. Substation',
        'VA Substation',
        'Veterans Affairs Substation',
        'U.S. Department of Veterans Affairs',
        'U.S. Veterans Affairs',
        'US Department of Veterans Affairs',
        'United States Department of Veterans Affairs',
    ]);
});

it('resolves the field rules from the configured preset', function (mixed $preset, array $rules): void {
    $fieldtype = new Identifier;
    $fieldtype->setField(new Field('id', ['type' => 'identifier', 'preset' => $preset]));

    expect($fieldtype->rules())->toBe($rules);
})->with([
    'a preset' => ['npi', ['npi']],
    'no preset' => [null, []],
    'a non-string preset' => [['npi'], []],
    'an unknown preset' => ['nonsense', []],
]);

it('reads the preset from the passed config for upload validation', function (): void {
    expect((new Identifier)->getValidationRules('x', ['preset' => 'orcid']))->toBe(['orcid'])
        ->and((new Identifier)->getValidationRules('x', ['preset' => ['orcid']]))->toBe([]);
});

it('builds a regex rule from its pattern and message, defaulting each to empty text', function (array $rule, IdentifierRegex $expected): void {
    /** @var array<string, mixed> $typedRule */
    $typedRule = $rule;

    expect(identifierWithRules([$typedRule])->getValidationRules('x', ['preset' => 'probe']))->toEqual([$expected]);
})->with([
    'a pattern and message' => [['rule' => 'regex', 'arguments' => ['/^\\d+$/'], 'message' => 'Digits only.'], new IdentifierRegex('/^\\d+$/', 'Digits only.')],
    'non-list arguments and no message' => [['rule' => 'regex', 'arguments' => '/x/'], new IdentifierRegex('', '')],
    'a non-string pattern and message' => [['rule' => 'regex', 'arguments' => [5], 'message' => ['m']], new IdentifierRegex('', '')],
]);

it('instantiates a rule class named after new', function (): void {
    expect(identifierWithRules([['rule' => 'new '.Npi::class]])->getValidationRules('x', ['preset' => 'probe']))
        ->toEqual([new Npi]);
});

it('joins a string rule\'s arguments in Laravel form', function (array $rule, string $expected): void {
    /** @var array<string, mixed> $typedRule */
    $typedRule = $rule;

    expect(identifierWithRules([$typedRule])->getValidationRules('x', ['preset' => 'probe']))->toBe([$expected]);
})->with([
    'no arguments' => [['rule' => 'alpha'], 'alpha'],
    'empty arguments' => [['rule' => 'alpha', 'arguments' => []], 'alpha'],
    'scalar arguments' => [['rule' => 'between', 'arguments' => [3, '9']], 'between:3,9'],
    'a non-scalar argument' => [['rule' => 'in', 'arguments' => ['a', ['b']]], 'in:a,'],
    'non-list arguments' => [['rule' => 'size', 'arguments' => '4'], 'size:'],
    'a non-string rule name' => [['rule' => 5], ''],
]);
