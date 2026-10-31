<?php

declare(strict_types=1);

use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\Support\TermSaveValidation;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic#302: the `rd` credential corrections and the Registered Dietitian terms.
 *
 * `atc` and `rd` stay clinical credentials (decided 2026-10-27 on UAMS-Web/uams-statamic#302); how the
 * regulatory question is asked, and each clinical credential's answer to it, is UAMS-Web/uams-statamic#343's.
 * RD is typed as the certification it is, spelled "dietitian", and linked to a
 * Registered Dietitian `certifications` term conferred by the Commission on Dietetic
 * Registration, the way `atc` is linked to the Board of Certification's athletic
 * trainer term. The Arkansas licenses the two certifications lead to (`lat` and `ld`)
 * stay clinical and statutory.
 *
 * @command  php artisan test --compact tests/Feature/CredentialClinicalClassificationTest.php
 */

/**
 * A committed term, failing when it does not resolve.
 */
function credentialClassificationTerm(string $taxonomy, string $slug): LocalizedTerm
{
    return mustBe(LocalizedTerm::class, Term::find("{$taxonomy}::{$slug}"), "the {$taxonomy} term {$slug}");
}

it('keeps ATC and RD clinical credentials', function (string $slug): void {
    $this->requireContentCollection('people');

    expect(credentialClassificationTerm('credentials', $slug)->get('clinical_query'))->toBeTrue();
})->with(['atc', 'rd']);

it('keeps the Arkansas license each certification gives way to clinical and statutory', function (string $slug): void {
    $this->requireContentCollection('people');

    $term = credentialClassificationTerm('credentials', $slug);

    expect($term->get('clinical_query'))->toBeTrue()
        ->and($term->get('clinical_statutory_credential_query'))->toBeTrue();
})->with(['lat', 'ld']);

it('types RD as a certification and spells its full name dietitian', function (): void {
    $this->requireContentCollection('people');

    $term = credentialClassificationTerm('credentials', 'rd');

    expect($term->get('ctdl'))->toMatchArray([
        'ceterms_credential_type' => 'Certification',
        'value' => 'Certification',
    ])->and($term->get('full_name'))->toBe('registered dietitian');
});

it('links RD to a Registered Dietitian certification from the Commission on Dietetic Registration', function (): void {
    $this->requireContentCollection('people');

    $credential = credentialClassificationTerm('credentials', 'rd');
    $certification = credentialClassificationTerm('certifications', 'commission-on-dietetic-registration-registered-dietitian');
    $organization = credentialClassificationTerm('certification_organizations', 'commission-on-dietetic-registration');

    expect($credential->get('certifications_term'))->toBe($certification->slug())
        ->and($certification->get('credentials_term'))->toBe('rd')
        ->and($certification->get('organization'))->toBe($organization->slug())
        ->and($certification->get('name'))->toBe('Registered Dietitian')
        ->and($certification->get('credential_kind'))->toBe('certification')
        ->and($organization->get('name'))->toBe('Commission on Dietetic Registration');
});

it('saves each changed term in the Control Panel without validation errors', function (string $taxonomy, string $slug): void {
    $this->requireContentCollection('people');

    expect(TermSaveValidation::rejectedFields(credentialClassificationTerm($taxonomy, $slug)))->toBe([]);
})->with([
    'rd' => ['credentials', 'rd'],
    'certification' => ['certifications', 'commission-on-dietetic-registration-registered-dietitian'],
    'certifying body' => ['certification_organizations', 'commission-on-dietetic-registration'],
]);

it('reports a required field left empty, so a clean save is not a blind check', function (): void {
    $this->requireContentCollection('people');

    $term = credentialClassificationTerm('certifications', 'commission-on-dietetic-registration-registered-dietitian');

    expect(TermSaveValidation::rejectedFields($term, ['name' => null]))->toBe(['name']);
});
