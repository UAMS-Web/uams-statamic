<?php

declare(strict_types=1);

// cspell:ignore xceterms

use App\Fieldtypes\CtdlClass;
use App\Services\CredentialRegistry\CtdlClassHierarchyBuilder;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Feature tests for the CtdlClass fieldtype: process() normalization of the
 * stored CTDL class chain (single-root coercion, invalid-root clearing, and
 * recomputed `value`). The hierarchy comes from the bundled
 * App\Enums\CredentialRegistry enum, so these tests are not content-dependent.
 *
 * Mutation: 100% on App\Fieldtypes\CtdlClass (110 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/CtdlClassFieldtypeTest.php
 */
it('forces ceterms type when only one root is allowed', function () {
    // Build fieldtype with a single allowed top-level class.
    $fieldtype = new CtdlClass;
    $field = new Field('ctdl', [
        'type' => 'ctdl_class',
        'ceterms_type_filter' => ['Credential'],
    ]);
    $fieldtype->setField($field);

    // Process a payload that used a different root before config changed.
    $out = $fieldtype->process([
        'ceterms_type' => 'Agent',
        'ceterms_credential_type' => 'Badge',
        'value' => 'Badge',
    ]);

    // Assert the root is coerced to Credential and subclasses were cleared.
    expect($out['ceterms_type'])->toBe('Credential');
    expect($out)->not->toHaveKey('ceterms_credential_type');
    expect($out['value'])->toBe('Credential');
});

it('clears an invalid root when multiple roots are allowed', function () {
    // Build fieldtype with two allowed roots (not including LearningOpportunity).
    $fieldtype = new CtdlClass;
    $field = new Field('ctdl', [
        'type' => 'ctdl_class',
        'ceterms_type_filter' => ['Credential', 'Agent'],
    ]);
    $fieldtype->setField($field);

    // Process a payload whose root is outside the allowlist.
    $out = $fieldtype->process([
        'ceterms_type' => 'LearningOpportunity',
        'value' => 'LearningOpportunity',
    ]);

    // Assert invalid root and value were removed.
    expect($out)->not->toHaveKey('ceterms_type');
    expect($out)->not->toHaveKey('value');
});

it('recomputes value for a nested selection', function () {
    // Build fieldtype with full list (no filter).
    $fieldtype = new CtdlClass;
    $field = new Field('ctdl', [
        'type' => 'ctdl_class',
    ]);
    $fieldtype->setField($field);

    // Process a nested chain without an explicit value key.
    $out = $fieldtype->process([
        'ceterms_type' => 'Credential',
        'ceterms_credential_type' => 'Badge',
        'ceterms_badge_type' => 'DigitalBadge',
    ]);

    // Assert resolved slug is written to `value`.
    expect($out['value'])->toBe('DigitalBadge');
});

/**
 * Build a CtdlClass fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function ctdlClassFieldtype(array $config = []): CtdlClass
{
    // Bind a Field so config() resolves the top-level filter
    $fieldtype = new CtdlClass;
    $fieldtype->setField(new Field('ctdl', array_merge(['type' => 'ctdl_class'], $config)));

    return $fieldtype;
}

/**
 * Call a private CtdlClass method via reflection.
 *
 * @param  array<int, mixed>  $args  Arguments forwarded to the method
 */
function ctdlClassCall(string $method, array $args = []): mixed
{
    return (new ReflectionMethod(CtdlClass::class, $method))->invoke(ctdlClassFieldtype(), ...$args);
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = new CtdlClass;

    expect(CtdlClass::title())->toBe('Credential Transparency Description Language Class')
        ->and($fieldtype->component())->toBe('ctdl_class')
        ->and($fieldtype->icon())->toBe('array')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->localizable())->toBeFalse()
        ->and($fieldtype->categories())->toBe(['special', 'structured'])
        ->and($fieldtype->keywords())->toBe([
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
        ]);
});

it('preloads the class hierarchy and defaults to an empty selection', function (): void {
    $fieldtype = new CtdlClass;

    expect($fieldtype->preload())->toBe(CtdlClassHierarchyBuilder::preload())
        ->and($fieldtype->defaultValue())->toBe([]);
});

it('offers every top-level class as an allowed root, all selected by default', function (): void {
    $topLevel = CtdlClassHierarchyBuilder::preload()['top_level'];

    expect((new ReflectionMethod(CtdlClass::class, 'configFieldItems'))->invoke(new CtdlClass))->toBe([
        [
            'display' => 'CTDL class',
            'fields' => [
                'ceterms_type_filter' => [
                    'display' => 'Allowed top-level classes',
                    'instructions' => 'Editors only see these root CTDL classes. Leave all selected to show the full list. At least one must stay selected.',
                    'type' => 'checkboxes',
                    'options' => array_combine(array_column($topLevel, 'key'), array_column($topLevel, 'value')),
                    'default' => array_column($topLevel, 'key'),
                    'validate' => [
                        'required',
                        'array',
                        'min:1',
                    ],
                ],
            ],
        ],
    ])->and($topLevel)->toContain(['key' => 'Credential', 'value' => 'Credential']);
});

it('normalizes nothing selected to an empty value on process and pre-process', function (mixed $data): void {
    $fieldtype = ctdlClassFieldtype();

    expect($fieldtype->preProcess($data))->toBe([])
        ->and($fieldtype->process($data))->toBe([]);
})->with([
    'null' => [null],
    'a string' => ['Credential'],
    'an empty array' => [[]],
    'a false root' => [['ceterms_type' => false]],
]);

it('forces the only allowed root even when nothing was stored', function (mixed $data): void {
    // A single allowed root is filled in for the editor before anything is chosen
    expect(ctdlClassFieldtype(['ceterms_type_filter' => ['Credential']])->preProcess($data))
        ->toBe(['ceterms_type' => 'Credential', 'value' => 'Credential']);
})->with(['null' => [null], 'an empty array' => [[]]]);

it('keeps the subclass chain when the stored root is already the only allowed one', function (): void {
    expect(ctdlClassFieldtype(['ceterms_type_filter' => ['Credential']])->process([
        'ceterms_type' => 'Credential',
        'ceterms_credential_type' => 'Badge',
    ]))->toBe([
        'ceterms_type' => 'Credential',
        'ceterms_credential_type' => 'Badge',
        'value' => 'Badge',
    ]);
});

it('strips only subclass tier keys when it forces a new root', function (): void {
    expect(ctdlClassFieldtype(['ceterms_type_filter' => ['Credential']])->process([
        'ceterms_type' => 'Agent',
        'ceterms_agent_type' => 'CredentialOrganization',
        'other' => 'kept',
        'xceterms_badge_type' => 'kept',
        'ceterms_badge_types' => 'kept',
        0 => 'kept',
        'value' => 'CredentialOrganization',
    ]))->toBe([
        'ceterms_type' => 'Credential',
        'other' => 'kept',
        'xceterms_badge_type' => 'kept',
        'ceterms_badge_types' => 'kept',
        0 => 'kept',
        'value' => 'Credential',
    ]);
});

it('allows every root when the filter is missing, not a list, or names no current class', function (array $config): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    // Any root is kept, so an unrelated root survives processing
    expect(ctdlClassFieldtype($typedConfig)->process(['ceterms_type' => 'Agent']))
        ->toBe(['ceterms_type' => 'Agent', 'value' => 'Agent']);
})->with([
    'no filter' => [[]],
    'an empty filter' => [['ceterms_type_filter' => []]],
    'a string filter' => [['ceterms_type_filter' => 'Credential']],
    'a filter of retired classes' => [['ceterms_type_filter' => ['RetiredClass']]],
]);

it('keeps only the current classes from a partly stale filter', function (): void {
    // RetiredClass drops out, leaving Credential as the only allowed root
    expect(ctdlClassFieldtype(['ceterms_type_filter' => ['RetiredClass', 'Credential']])->process(['ceterms_type' => 'Agent']))
        ->toBe(['ceterms_type' => 'Credential', 'value' => 'Credential']);
});

it('resolves the value from the last chosen tier before an explicit none', function (array $data, ?string $value): void {
    $out = ctdlClassFieldtype()->process($data);

    expect($out['value'] ?? null)->toBe($value);
})->with([
    'a none at the second tier' => [['ceterms_type' => 'Credential', 'ceterms_credential_type' => false, 'ceterms_badge_type' => 'DigitalBadge'], 'Credential'],
    'an empty second tier' => [['ceterms_type' => 'Credential', 'ceterms_credential_type' => ''], 'Credential'],
    'a non-string second tier' => [['ceterms_type' => 'Credential', 'ceterms_credential_type' => ['Badge']], 'Credential'],
    'a stale value with an empty chain' => [['ceterms_type' => 'Credential', 'value' => 'Badge'], 'Credential'],
    'a leaf with no subclasses' => [['ceterms_type' => 'Credential', 'ceterms_credential_type' => 'Badge', 'ceterms_badge_type' => 'DigitalBadge', 'ceterms_digital_badge_type' => 'X'], 'DigitalBadge'],
]);

it('takes the local name from a shorthand URI', function (string $shorthand, string $slug): void {
    expect(ctdlClassCall('shorthandUriToSlug', [$shorthand]))->toBe($slug);
})->with([
    'a prefixed name' => ['ceterms:DigitalBadge', 'DigitalBadge'],
    'a bare name' => ['DigitalBadge', 'DigitalBadge'],
    'only the first colon splits' => ['a:b:c', 'b:c'],
]);

it('turns a PascalCase class name into a snake_case tier name', function (string $slug, string $snake): void {
    expect(ctdlClassCall('pascalSlugToSnake', [$slug]))->toBe($snake);
})->with([
    'two words' => ['DigitalBadge', 'digital_badge'],
    'a leading acronym' => ['QACredentialOrganization', 'qa_credential_organization'],
    'a digit before a capital' => ['Level2Badge', 'level2_badge'],
    'one word' => ['Badge', 'badge'],
]);

it('drops a root that only loosely matches an allowed class', function (): void {
    // true equals any non-empty string loosely, so the match must be strict
    expect(ctdlClassFieldtype(['ceterms_type_filter' => ['Credential', 'Agent']])->process(['ceterms_type' => true]))->toBe([]);
});
