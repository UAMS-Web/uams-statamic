<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Services\CredentialRegistry\CtdlClassHierarchyBuilder;
use Statamic\Fields\Fieldtype;

/**
 * Chained select for Credential Transparency Description Language (CTDL) class
 * terms from CredentialRegistry: top-level ceterms roots, then successive child
 * classes. Stores intermediate choices under dynamic `ceterms_*_type` keys and
 * the resolved class slug in `value`.
 *
 * Config `ceterms_type_filter` (checkboxes) restricts which root classes appear;
 * at least one must remain selected. A single selection hides the top control in
 * the CP and fixes `ceterms_type` to that slug.
 */
class CtdlClass extends Fieldtype
{
    /**
     * Display title in the fieldtype picker (translation key optional).
     *
     * @var string
     */
    protected static $title = 'Credential Transparency Description Language Class';

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'CTDL',
        'CredentialRegistry',
        'Credential Registry',
        'CredentialEngine',
        'Credential Engine',
        'AccreditAction',
        'AdvancedStandingAction',
        'Agent',
        'AggregateDataProfile',
        'AlignmentMap',
        'ApprenticeshipCertificate',
        'ApproveAction',
        'Assessment',
        'AssessmentComponent',
        'AssessmentProfile',
        'AssociateDegree',
        'AssociateOfAppliedArtsDegree',
        'AssociateOfAppliedScienceDegree',
        'AssociateOfArtsDegree',
        'AssociateOfScienceDegree',
        'BachelorDegree',
        'BachelorOfArtsDegree',
        'BachelorOfScienceDegree',
        'Badge',
        'BasicComponent',
        'Certificate',
        'CertificateOfCompletion',
        'Certification',
        'CocurricularComponent',
        'Collection',
        'CollectionMember',
        'CompetencyComponent',
        'ComponentCondition',
        'ConditionManifest',
        'ConditionProfile',
        'ContactPoint',
        'CostManifest',
        'CostProfile',
        'Course',
        'CourseComponent',
        'Credential',
        'CredentialAlignmentObject',
        'CredentialAssertion',
        'CredentialComponent',
        'CredentialFramework',
        'CredentialOrganization',
        'CredentialPerson',
        'CredentialingAction',
        'Degree',
        'DigitalBadge',
        'Diploma',
        'DoctoralDegree',
        'DurationProfile',
        'EarningsProfile',
        'EmploymentOutcomeProfile',
        'ExtracurricularComponent',
        'FinancialAssistanceProfile',
        'GeneralEducationDevelopment',
        'GeoCoordinates',
        'HoldersProfile',
        'IdentifierValue',
        'IndustryClassification',
        'InstructionalProgramClassification',
        'Job',
        'JobComponent',
        'JourneymanCertificate',
        'JurisdictionProfile',
        'LearningOpportunity',
        'LearningOpportunityProfile',
        'LearningProgram',
        'LearningResource',
        'License',
        'MasterCertificate',
        'MasterDegree',
        'MasterOfArtsDegree',
        'MasterOfScienceDegree',
        'MicroCredential',
        'Occupation',
        'OccupationClassification',
        'OfferAction',
        'OpenBadge',
        'Organization',
        'Pathway',
        'PathwayComponent',
        'PathwaySet',
        'Place',
        'PostalAddress',
        'ProcessProfile',
        'ProfessionalDoctorate',
        'QACredentialOrganization',
        'QualityAssuranceCredential',
        'RecognizeAction',
        'RegulateAction',
        'RenewAction',
        'ResearchDoctorate',
        'RevocationProfile',
        'RevokeAction',
        'RightsAction',
        'RuleSet',
        'RuleSetProfile',
        'ScheduledOffering',
        'SecondarySchoolDiploma',
        'SelectionComponent',
        'SpecialistDegree',
        'Task',
        'TransferIntermediary',
        'TransferValueProfile',
        'ValueProfile',
        'VerificationServiceProfile',
        'WorkExperienceComponent',
        'WorkRole',
        'WorkforceDemandAction',
    ];

    /**
     * Categories in the Statamic fieldtype selector.
     *
     * @var array<int, string>
     */
    protected $categories = [
        'special',
        'structured',
    ];

    /**
     * Whether the fieldtype is selectable in blueprints.
     *
     * @var bool
     */
    protected $selectable = true;

    /**
     * Whether the value can be localized.
     *
     * @var bool
     */
    protected $localizable = false;

    /**
     * Vue component handle for the CP (see cp.js registration).
     *
     * @var string
     */
    protected $component = 'ctdl_class';

    /**
     * Icon for the fieldtype picker.
     */
    #[\Override]
    public function icon(): string
    {
        return 'array';
    }

    /**
     * Blueprint config: checkboxes of all top-level ceterms classes (default all).
     *
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Load top-level class rows for checkbox options and defaults
        $topLevel = CtdlClassHierarchyBuilder::preload()['top_level'];

        // Build associative options slug => label for the checkboxes fieldtype
        $checkboxOptions = [];

        // Fill options from each top-level row
        foreach ($topLevel as $row) {
            $checkboxOptions[$row['key']] = $row['value'];
        }

        // Default: every top-level slug selected
        $defaultSlugs = array_column($topLevel, 'key');

        return [
            [
                'display' => __('CTDL class'),
                'fields' => [
                    'ceterms_type_filter' => [
                        'display' => __('Allowed top-level classes'),
                        'instructions' => __(
                            'Editors only see these root CTDL classes. Leave all selected to show the full list. At least one must stay selected.'
                        ),
                        'type' => 'checkboxes',
                        'options' => $checkboxOptions,
                        'default' => $defaultSlugs,
                        'validate' => [
                            'required',
                            'array',
                            'min:1',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Preload hierarchy options and labels for the Vue fieldtype.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function preload(): array
    {
        // Build hierarchy maps from CredentialRegistry
        return CtdlClassHierarchyBuilder::preload();
    }

    /**
     * Default stored value when the field is empty.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function defaultValue(): array
    {
        return [];
    }

    /**
     * Normalize entry data before the publish form loads.
     *
     * @param  mixed  $data  Raw stored value
     * @return array<string, mixed>
     */
    #[\Override]
    public function preProcess(mixed $data): array
    {
        // Coerce null or non-array values to an empty array for the CP
        if (! \is_array($data)) {
            $data = [];
        }

        /** @var array<string, mixed> $data */

        // Apply allowlist rules and recompute `value` when data was normalized
        return $this->normalizeStoredArray($data);
    }

    /**
     * Normalize the publish payload before storage.
     *
     * @param  mixed  $data  Incoming value from the CP
     * @return array<string, mixed>
     */
    #[\Override]
    public function process(mixed $data): array
    {
        // Coerce non-array input to an empty array
        if (! \is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $data */

        // Enforce configured top-level allowlist and resolved `value`
        return $this->normalizeStoredArray($data);
    }

    /**
     * Intersect configured filter with known top-level slugs; fallback if empty.
     *
     * @return list<string>
     */
    private function resolvedAllowedCetermsTypeSlugs(): array
    {
        // Load authoritative top-level slug list
        $all = array_column(
            CtdlClassHierarchyBuilder::preload()['top_level'],
            'key'
        );

        // Read blueprint checkboxes config
        /** @var mixed $filter */
        $filter = $this->config('ceterms_type_filter');

        // Treat missing or empty filter as "all roots allowed"
        if (! \is_array($filter) || $filter === []) {
            return $all;
        }

        /** @var array<int, string> $filter */

        // Keep only slugs that still exist on the top-level list
        $intersect = array_values(array_intersect($all, $filter));

        // Fall back to full list if nothing intersects (stale config)
        return $intersect !== [] ? $intersect : $all;
    }

    /**
     * Enforce allowlist, optional fixed top-level slug, and recomputed `value`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeStoredArray(array $data): array
    {
        // Resolve allowed root class slugs from field config
        $allowed = $this->resolvedAllowedCetermsTypeSlugs();

        // Check if the editor may only use a single root class
        if (\count($allowed) === 1) {
            // Read the sole allowed top-level slug
            $only = $allowed[0];

            // Check if stored top-level differs from the forced slug
            if (($data['ceterms_type'] ?? null) !== $only) {
                // Strip subclass tiers and resolved value before re-applying top
                $data = $this->stripCetermsDescendants($data);
                // Fix hidden top-level field to the only allowed class
                $data['ceterms_type'] = $only;
            }
        } elseif (isset($data['ceterms_type']) && ! \in_array($data['ceterms_type'], $allowed, true)) {
            // Drop an invalid root selection and its subtree
            $data = $this->stripCetermsDescendants($data);
            unset($data['ceterms_type']);
        }

        // Recompute canonical `value` from the tier chain
        return $this->withRecomputedValue($data);
    }

    /**
     * Remove every `ceterms_*_type` key and `value` (keep `ceterms_type` if set).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stripCetermsDescendants(array $data): array
    {
        // Walk keys and drop subclass tiers and resolved value
        foreach (array_keys($data) as $key) {
            if (
                $key !== 'ceterms_type'
                && preg_match('/^ceterms_.*_type$/', (string) $key)
            ) {
                unset($data[$key]);
            }
        }

        unset($data['value']);

        return $data;
    }

    /**
     * Ordered tier keys for the stored chain (matches CP logic).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function chainKeyOrderForData(array $data): array
    {
        // Load children map for walking the chain
        $childrenBy = CtdlClassHierarchyBuilder::preload()['children_by_shorthand'];

        // Always start with the root tier key
        $keys = ['ceterms_type'];

        // Stop when no root slug is chosen
        if (empty($data['ceterms_type']) || ! \is_string($data['ceterms_type'])) {
            return $keys;
        }

        // Walk parent shorthand URIs using successive selections
        $parentShorthand = 'ceterms:'.$data['ceterms_type'];

        while (true) {
            // Read direct subclasses for this parent
            $kids = $childrenBy[$parentShorthand] ?? [];

            // Stop when the class has no further subclasses in the registry
            if ($kids === []) {
                break;
            }

            // Derive storage key for the next tier from the parent local name
            $parentSlug = $this->shorthandUriToSlug($parentShorthand);
            $tierKey = 'ceterms_'.$this->pascalSlugToSnake($parentSlug).'_type';

            $keys[] = $tierKey;

            // Read the selection for this tier
            $selected = $data[$tierKey] ?? null;

            // Stop on explicit "none" (false) or not yet chosen (null). An empty slug
            // names a parent with no subclasses, so the next pass stops on it.
            if (! \is_string($selected)) {
                break;
            }

            $parentShorthand = 'ceterms:'.$selected;
        }

        return $keys;
    }

    /**
     * Recompute `value` from tier keys (same semantics as the Vue fieldtype).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withRecomputedValue(array $data): array
    {
        // Build key order for walking selections
        $order = $this->chainKeyOrderForData($data);

        // Track last string slug before a false tier
        $previous = null;

        // Walk tiers to find resolved slug or early exit on "none"
        foreach ($order as $key) {
            $part = $data[$key] ?? null;

            // The chain stops at a false tier, so no tier follows it and finishing the loop gives the same result
            // @pest-mutate-ignore: FalseToTrue, RemoveEarlyReturn
            if ($part === false) {
                unset($data['value']);

                if ($previous !== null) {
                    $data['value'] = $previous;
                }

                return $data;
            }

            if (\is_string($part) && $part !== '') {
                $previous = $part;
            }
        }

        unset($data['value']);

        if ($previous !== null) {
            $data['value'] = $previous;
        }

        return $data;
    }

    /**
     * Strip `prefix:LocalName` to `LocalName`.
     */
    private function shorthandUriToSlug(string $shorthand): string
    {
        $pos = strpos($shorthand, ':');

        if ($pos === false) {
            return $shorthand;
        }

        return substr($shorthand, $pos + 1);
    }

    /**
     * PascalCase slug to snake_case for dynamic tier keys.
     */
    private function pascalSlugToSnake(string $slug): string
    {
        $snake = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $slug);

        if (! \is_string($snake)) {
            return strtolower($slug);
        }

        $snake = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $snake);

        if (! \is_string($snake)) {
            return strtolower($slug);
        }

        return strtolower($snake);
    }
}
