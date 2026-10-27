<?php

declare(strict_types=1);

use App\Rules\CredentialTypeCertification as CredentialTypeCertificationRule;
use App\Scopes\CredentialTypeCertification as CredentialTypeCertificationScope;
use Illuminate\Support\Facades\Validator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Behavior of the App\Rules\CredentialTypeCertification validation rule: each
 * selected term id must resolve to a term in the credentials taxonomy whose CTDL
 * payload classifies it as a Credential root with a Certification credential type.
 * Empty values skip; not-found, wrong-taxonomy, wrong-CTDL-root, and wrong-
 * credential-type each fail with a distinct message. Given a kind field, the
 * rule also accepts a Certificate on an assessment-based certificate (UAMS-Web/uams-statamic-archive#2972).
 *
 * @command  php artisan test --compact tests/Feature/Rules/CredentialTypeCertificationRuleTest.php
 */

/**
 * Run the rule against a value and return the fail messages it emitted.
 *
 * @return list<string>
 */
function credentialTypeCertificationMessages(mixed $value): array
{
    $messages = [];

    (new CredentialTypeCertificationRule)->validate('field', $value, function (string $message) use (&$messages) {
        $messages[] = $message;
    });

    return $messages;
}

it('skips when the value is empty', function () {
    mockTermRepository()->shouldReceive('find')->never();

    expect(credentialTypeCertificationMessages(''))->toBeEmpty();
});

it('fails when the term is not found', function () {
    mockTermRepository()->shouldReceive('find')->with('credentials::missing')->once()->andReturn(null);

    $messages = credentialTypeCertificationMessages('credentials::missing');

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain('could not be found');
});

it('fails when the term is in the wrong taxonomy', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');

    mockTermRepository()->shouldReceive('find')->with('certifications::slug')->once()->andReturn($term);

    $messages = credentialTypeCertificationMessages('certifications::slug');

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain('must belong to the credentials taxonomy');
});

it('fails when the CTDL credential type is not Certification', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(CredentialTypeCertificationScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with('ctdl')->andReturn([
        'ceterms_type' => CredentialTypeCertificationScope::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
        'ceterms_credential_type' => 'Degree',
    ]);

    mockTermRepository()->shouldReceive('find')->with('credentials::md')->once()->andReturn($term);

    $messages = credentialTypeCertificationMessages('credentials::md');

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain(CredentialTypeCertificationScope::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATION);
});

it('passes when the CTDL credential type is Certification', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(CredentialTypeCertificationScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with('ctdl')->andReturn([
        'ceterms_type' => CredentialTypeCertificationScope::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
        'ceterms_credential_type' => CredentialTypeCertificationScope::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATION,
    ]);

    mockTermRepository()->shouldReceive('find')->with('credentials::atc')->once()->andReturn($term);

    expect(credentialTypeCertificationMessages('credentials::atc'))->toBeEmpty();
});

it('fails when the CTDL ceterms type is not Credential', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(CredentialTypeCertificationScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with('ctdl')->andReturn([
        'ceterms_type' => 'LearningOpportunity',
    ]);

    mockTermRepository()->shouldReceive('find')->with('credentials::cnp')->once()->andReturn($term);

    $messages = credentialTypeCertificationMessages('credentials::cnp');

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain(CredentialTypeCertificationScope::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL);
});

it('normalizes an array value, skipping non-string and empty entries', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(CredentialTypeCertificationScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with('ctdl')->andReturn([
        'ceterms_type' => CredentialTypeCertificationScope::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
        'ceterms_credential_type' => CredentialTypeCertificationScope::CTDL_CREDENTIAL_TYPE_VALUE_CERTIFICATION,
    ]);

    // The non-string (123) and empty-string entries are dropped before lookup, so
    // find() is invoked exactly once with the only usable id.
    mockTermRepository()->shouldReceive('find')->with('credentials::atc')->once()->andReturn($term);

    expect(credentialTypeCertificationMessages(['credentials::atc', 123, '']))->toBeEmpty();
});

/**
 * Run the rule, reading the credential kind from a sibling field, and return its
 * fail messages.
 *
 * @param  array<string, mixed>  $data  The data the validator holds.
 * @return list<string>
 */
function credentialTypeCertificationKindMessages(mixed $value, array $data, ?string $kindField = 'credential_kind'): array
{
    $messages = [];

    (new CredentialTypeCertificationRule($kindField))
        ->setValidator(Validator::make($data, []))
        ->validate('field', $value, function (string $message) use (&$messages) {
            $messages[] = $message;
        });

    return $messages;
}

it('accepts a CTDL type only when the certification kind allows it', function (?string $kind, string $type, bool $passes) {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(CredentialTypeCertificationScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with('ctdl')->andReturn([
        'ceterms_type' => CredentialTypeCertificationScope::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
        'ceterms_credential_type' => $type,
    ]);

    mockTermRepository()->shouldReceive('find')->with('credentials::term')->once()->andReturn($term);

    $messages = credentialTypeCertificationKindMessages('credentials::term', ['credential_kind' => $kind]);

    expect($messages === [])->toBe($passes);
})->with([
    'certification links a Certification' => ['certification', 'Certification', true],
    'certification rejects a Certificate' => ['certification', 'Certificate', false],
    'focused practice designation links a Certification' => ['focused_practice_designation', 'Certification', true],
    'focused practice designation rejects a Certificate' => ['focused_practice_designation', 'Certificate', false],
    'assessment-based certificate links a Certification' => ['assessment_based_certificate', 'Certification', true],
    'assessment-based certificate links a Certificate' => ['assessment_based_certificate', 'Certificate', true],
    'assessment-based certificate rejects a Degree' => ['assessment_based_certificate', 'Degree', false],
    'no kind rejects a Certificate' => [null, 'Certificate', false],
]);

it('names both accepted types when an assessment-based certificate links neither', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(CredentialTypeCertificationScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with('ctdl')->andReturn([
        'ceterms_type' => CredentialTypeCertificationScope::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
        'ceterms_credential_type' => 'Degree',
    ]);

    mockTermRepository()->shouldReceive('find')->with('credentials::md')->once()->andReturn($term);

    expect(credentialTypeCertificationKindMessages('credentials::md', ['credential_kind' => 'assessment_based_certificate']))
        ->toBe([__('statamic::validation.credential_type_certification.wrong_credential_type', [
            'id' => 'credentials::md',
            'type' => 'Degree',
            'expected' => 'Certification or Certificate',
        ])]);
});

it('ignores the sibling kind when no kind field is configured', function (?string $kindField) {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(CredentialTypeCertificationScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with('ctdl')->andReturn([
        'ceterms_type' => CredentialTypeCertificationScope::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
        'ceterms_credential_type' => 'Certificate',
    ]);

    mockTermRepository()->shouldReceive('find')->with('credentials::cert')->once()->andReturn($term);

    expect(credentialTypeCertificationKindMessages('credentials::cert', ['credential_kind' => 'assessment_based_certificate'], $kindField))
        ->not->toBeEmpty();
})->with([
    'null handle' => [null],
    'blank handle' => ['  '],
]);

it('ignores the sibling kind when no validator is set', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(CredentialTypeCertificationScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with('ctdl')->andReturn([
        'ceterms_type' => CredentialTypeCertificationScope::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL,
        'ceterms_credential_type' => 'Certificate',
    ]);

    mockTermRepository()->shouldReceive('find')->with('credentials::cert')->once()->andReturn($term);

    $messages = [];
    (new CredentialTypeCertificationRule('credential_kind'))->validate('field', 'credentials::cert', function (string $message) use (&$messages) {
        $messages[] = $message;
    });

    expect($messages)->not->toBeEmpty();
});

/**
 * Build a credentials term mock carrying the given CTDL payload.
 */
function credentialTermWithCtdl(mixed $ctdl): TaxonomyTerm
{
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(CredentialTypeCertificationScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with('ctdl')->andReturn($ctdl);

    return $term;
}

it('reports each failure with its full message', function (string $id, ?callable $term, Closure $expected) {
    mockTermRepository()->shouldReceive('find')->with($id)->once()->andReturn($term === null ? null : $term());

    expect(credentialTypeCertificationMessages($id))->toBe([$expected()]);
})->with([
    'not found' => ['credentials::missing', null, fn (): string => __('statamic::validation.credential_type_certification.not_found', ['id' => 'credentials::missing'])],
    'wrong taxonomy' => ['credentials::slug', function (): TaxonomyTerm {
        $term = Mockery::mock(TaxonomyTerm::class);
        $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');

        return $term;
    }, fn (): string => __('statamic::validation.credential_type_certification.wrong_taxonomy', ['id' => 'credentials::slug', 'taxonomy' => 'certifications'])],
    'wrong root' => ['credentials::cnp', fn (): TaxonomyTerm => credentialTermWithCtdl(['ceterms_type' => 'LearningOpportunity']), fn (): string => __('statamic::validation.credential_type_certification.wrong_ctdl_ceterms_type', ['id' => 'credentials::cnp', 'root' => 'LearningOpportunity', 'expected' => 'Credential'])],
    'non-scalar root' => ['credentials::cnp', fn (): TaxonomyTerm => credentialTermWithCtdl(['ceterms_type' => ['Credential']]), fn (): string => __('statamic::validation.credential_type_certification.wrong_ctdl_ceterms_type', ['id' => 'credentials::cnp', 'root' => '', 'expected' => 'Credential'])],
    'non-scalar credential type' => ['credentials::md', fn (): TaxonomyTerm => credentialTermWithCtdl(['ceterms_type' => 'Credential', 'ceterms_credential_type' => ['Certification']]), fn (): string => __('statamic::validation.credential_type_certification.wrong_credential_type', ['id' => 'credentials::md', 'type' => '', 'expected' => 'Certification'])],
]);

it('stops at the first term with the wrong CTDL root', function () {
    $repository = mockTermRepository();
    $repository->shouldReceive('find')->with('credentials::first')->once()
        ->andReturn(credentialTermWithCtdl(['ceterms_type' => 'LearningOpportunity']));
    $repository->shouldReceive('find')->with('credentials::second')->never();

    expect(credentialTypeCertificationMessages(['credentials::first', 'credentials::second']))->toHaveCount(1);
});

it('rejects a credential type that only loosely equals Certification', function () {
    mockTermRepository()->shouldReceive('find')->with('credentials::loose')->once()
        ->andReturn(credentialTermWithCtdl(['ceterms_type' => 'Credential', 'ceterms_credential_type' => true]));

    expect(credentialTypeCertificationMessages('credentials::loose'))->not->toBeEmpty();
});

it('trims the kind field handle', function () {
    mockTermRepository()->shouldReceive('find')->with('credentials::cert')->once()
        ->andReturn(credentialTermWithCtdl(['ceterms_type' => 'Credential', 'ceterms_credential_type' => 'Certificate']));

    expect(credentialTypeCertificationKindMessages('credentials::cert', ['credential_kind' => 'assessment_based_certificate'], ' credential_kind '))
        ->toBeEmpty();
});

it('validates an id that follows non-string and empty entries', function () {
    mockTermRepository()->shouldReceive('find')->with('credentials::atc')->once()
        ->andReturn(credentialTermWithCtdl(['ceterms_type' => 'Credential', 'ceterms_credential_type' => 'Degree']));

    expect(credentialTypeCertificationMessages([123, '', 'credentials::atc']))->toHaveCount(1);
});

it('asserts the repository returned a term', function () {
    mockTermRepository()->shouldReceive('find')->with('credentials::odd')->once()->andReturn(new stdClass);

    credentialTypeCertificationMessages('credentials::odd');
})->throws(AssertionError::class)->skip(ini_get('zend.assertions') !== '1', 'Requires zend.assertions=1.');

it('accepts a localized term from the repository', function () {
    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');

    mockTermRepository()->shouldReceive('find')->with('credentials::slug')->once()->andReturn($term);

    expect(credentialTypeCertificationMessages('credentials::slug'))
        ->toBe([__('statamic::validation.credential_type_certification.wrong_taxonomy', ['id' => 'credentials::slug', 'taxonomy' => 'certifications'])]);
});
