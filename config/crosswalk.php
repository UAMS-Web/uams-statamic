<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Classification crosswalks
    |--------------------------------------------------------------------------
    |
    | Maps identifiers from one system to another (for example, Credential
    | Registry credential types to UNESCO ISCED A/P/F codes). Empty strings are
    | placeholders until mappings are defined. Add further top-level keys here
    | for other source systems as needed.
    |
    | Access paths use this config file name plus dot notation for each nested
    | array key (left to right matches outer array to inner):
    |
    |   crosswalk.mappings.{source}.{credential_type}.{dimension}
    |
    | Examples (Credential Registry → ISCED dimension value):
    |
    |   config('crosswalk.mappings.CredentialRegistry.AssociateDegree.IscedA2011')
    |   config('crosswalk.mappings.CredentialRegistry.Certificate.IscedP2011')
    |   config('crosswalk.mappings.CredentialRegistry.BachelorDegree.IscedF2013')
    |
    | Whole subtree for one credential type (three dimensions):
    |
    |   config('crosswalk.mappings.CredentialRegistry.AssociateDegree')
    |
    | All credential types for one source:
    |
    |   config('crosswalk.mappings.CredentialRegistry')
    |
    | Entire crosswalk table:
    |
    |   config('crosswalk.mappings')
    |
    */

    'mappings' => [
        /**
         * Credential Registry types; array keys match enum cases in
         * app\Enums\CredentialRegistry.php (\App\Enums\CredentialRegistry).
         */
        'CredentialRegistry' => [
            'ApprenticeshipCertificate' => [],
            'AssociateDegree' => [
                /**
                 * ISCED-A 2011; mapped values are enum cases in
                 * app\Enums\IscedA2011.php (\App\Enums\IscedA2011).
                 */
                'IscedA2011' => '5',
                /**
                 * ISCED-P 2011; mapped values are enum cases in
                 * app\Enums\IscedP2011.php (\App\Enums\IscedP2011).
                 */
                'IscedP2011' => '5',
            ],
            'AssociateOfAppliedArtsDegree' => [
                'IscedA2011' => '5',
                'IscedP2011' => '5',
            ],
            'AssociateOfAppliedScienceDegree' => [
                'IscedA2011' => '5',
                'IscedP2011' => '5',
            ],
            'AssociateOfArtsDegree' => [
                'IscedA2011' => '5',
                'IscedP2011' => '5',
            ],
            'AssociateOfScienceDegree' => [
                'IscedA2011' => '5',
                'IscedP2011' => '5',
            ],
            'BachelorDegree' => [
                'IscedA2011' => '6',
                'IscedP2011' => '6',
            ],
            'BachelorOfArtsDegree' => [
                'IscedA2011' => '6',
                'IscedP2011' => '6',
            ],
            'BachelorOfScienceDegree' => [
                'IscedA2011' => '6',
                'IscedP2011' => '6',
            ],
            'Badge' => [],
            'Certificate' => [],
            'CertificateOfCompletion' => [],
            'Certification' => [],
            'Degree' => [],
            'DigitalBadge' => [],
            'Diploma' => [],
            'DoctoralDegree' => [
                'IscedA2011' => '8',
                'IscedP2011' => '8',
            ],
            'GeneralEducationDevelopment' => [
                'IscedA2011' => '3',
                'IscedP2011' => '3',
            ],
            'JourneymanCertificate' => [],
            'License' => [],
            'MasterCertificate' => [],
            'MasterDegree' => [
                'IscedA2011' => '7',
                'IscedP2011' => '7',
            ],
            'MasterOfArtsDegree' => [
                'IscedA2011' => '7',
                'IscedP2011' => '7',
            ],
            'MasterOfScienceDegree' => [
                'IscedA2011' => '7',
                'IscedP2011' => '7',
            ],
            'MicroCredential' => [],
            'OpenBadge' => [],
            'ProfessionalDoctorate' => [
                'IscedA2011' => '85',
                'IscedP2011' => '85',
            ],
            'QualityAssuranceCredential' => [],
            'ResearchDoctorate' => [
                'IscedA2011' => '84',
                'IscedP2011' => '84',
            ],
            'SecondarySchoolDiploma' => [
                'IscedA2011' => '3',
                'IscedP2011' => '3',
            ],
            'SpecialistDegree' => [],
        ],
    ],
];
