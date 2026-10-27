<?php

declare(strict_types=1);

use App\Scopes\BoardCertifications;
use App\Scopes\CredentialTypeCertification as CredentialTypeCertificationScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Term;
use Statamic\Fields\Field;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Locks the UAMS-Web/uams-statamic-archive#2972 `credential_kind` field on the `certifications` term: a
 * required single select with three kinds, the Board Certification section
 * shown only for a certification, the `credentials_term` picker offering a CTDL
 * Certificate for an assessment-based certificate, and every term in content
 * carrying a kind.
 *
 * @command  php artisan test --compact tests/Feature/Blueprints/CertificationCredentialKindBlueprintTest.php
 */

/**
 * Resolve a field on the `certifications` term blueprint by handle.
 */
function certificationBlueprintField(string $handle): Field
{
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find('taxonomies.certifications.certification');

    return mustBe(Field::class, $blueprint->field($handle), "the {$handle} field");
}

it('requires a single credential kind from the three kinds', function () {
    $field = certificationBlueprintField(BoardCertifications::CREDENTIAL_KIND_FIELD);
    $options = mustBeArray($field->get('options'), 'the credential_kind options');

    expect($field->type())->toBe('select')
        ->and($field->get('max_items'))->toBe(1)
        ->and($field->get('multiple'))->toBeFalse()
        ->and($field->get('validate'))->toBe(['required'])
        ->and(array_column($options, 'key'))->toBe([
            BoardCertifications::CREDENTIAL_KIND_CERTIFICATION,
            BoardCertifications::CREDENTIAL_KIND_FOCUSED_PRACTICE_DESIGNATION,
            BoardCertifications::CREDENTIAL_KIND_ASSESSMENT_BASED_CERTIFICATE,
        ]);
});

it('shows each board certification field only for a certification', function (string $handle) {
    expect(certificationBlueprintField($handle)->get('if'))
        ->toHaveKey(BoardCertifications::CREDENTIAL_KIND_FIELD, 'equals certification');
})->with([
    'board_certification_query',
    'specialty_medical_board_physician_query',
    'specialty_medical_board_specialize_query',
    'specialty_medical_board_examination_query',
]);

it('offers certificates in the credentials picker and checks the kind on save', function () {
    $field = certificationBlueprintField('credentials_term');

    expect($field->get('query_scopes'))->toBe([CredentialTypeCertificationScope::ALIAS_CERTIFICATION_OR_CERTIFICATE])
        ->and($field->get('validate'))->toBe(['credential_type_certification:credential_kind']);
});

it('applies the registered credentials rule with the sibling kind', function (string $kind, bool $passes) {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(CredentialTypeCertificationScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with('ctdl')->andReturn([
        'ceterms_type' => CredentialTypeCertificationScope::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
        'ceterms_credential_type' => CredentialTypeCertificationScope::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATE,
    ]);

    mockTermRepository()->shouldReceive('find')->with('credentials::certificate')->once()->andReturn($term);

    $validator = Validator::make(
        [
            'credentials_term' => ['credentials::certificate'],
            'credential_kind' => $kind,
        ],
        [
            'credentials_term' => ['credential_type_certification:credential_kind'],
        ]
    );

    expect($validator->passes())->toBe($passes);
})->with([
    'assessment-based certificate' => ['assessment_based_certificate', true],
    'certification' => ['certification', false],
]);

it('gives every certifications term in content one of the three kinds', function () {
    $query = Term::query();
    $query->whereIn('taxonomies', [BoardCertifications::CERTIFICATIONS_TAXONOMY]);

    /** @var Collection<int, mixed> $terms */
    $terms = $query->get();

    // Guard against a vacuous pass over an empty taxonomy
    expect($terms)->not->toBeEmpty();

    $kinds = [
        BoardCertifications::CREDENTIAL_KIND_CERTIFICATION,
        BoardCertifications::CREDENTIAL_KIND_FOCUSED_PRACTICE_DESIGNATION,
        BoardCertifications::CREDENTIAL_KIND_ASSESSMENT_BASED_CERTIFICATE,
    ];

    foreach ($terms as $rawTerm) {
        $term = mustBe(LocalizedTerm::class, $rawTerm, 'a certifications term');
        $label = json_encode([$term->id(), $term->locale()]);

        // Read through `value()` so a localization falls back to the origin term
        expect(\in_array($term->value(BoardCertifications::CREDENTIAL_KIND_FIELD), $kinds, true))
            ->toBeTrue("Term {$label} has no valid credential_kind.");
    }
});
