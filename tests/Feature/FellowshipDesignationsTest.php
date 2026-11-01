<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\ContentSubmodule;
use Tests\Support\TermSaveValidation;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Locks the UAMS-Web/uams-statamic#305 move: a fellowship designation is not part of
 * a `people` display name, so none may sit in the `credentials` taxonomy, whose
 * every selected term is appended to the name. The four fellowships earned by
 * examination or training live in `certifications`, and the guard is keyed on what
 * makes a term a fellowship so a request to add one fails here rather than relying
 * on anyone remembering the decision in #304.
 *
 * @command  php artisan test --compact tests/Feature/FellowshipDesignationsTest.php
 */

// cspell:ignore AAOMPT FAAOMPT FAAOS FACOG FACS FASCRS FCCM FRACS FRCS FRCSC facog fascrs fccm fracs frcs frcsc faaompt

/**
 * The eight `credentials` terms that left the taxonomy.
 */
const RETIRED_FELLOWSHIP_CREDENTIALS = ['facog', 'facs', 'fascrs', 'fccm', 'fracs', 'frcs', 'frcsc', 'faaompt'];

/**
 * The four moved `certifications` terms, keyed by slug, with their certifying body
 * and the abbreviation kept as an alternate name.
 */
const MOVED_FELLOWSHIP_CERTIFICATIONS = [
    'royal-colleges-of-surgeons-fellowship-of-the-royal-colleges-of-surgeons' => ['royal-colleges-of-surgeons', 'FRCS'],
    'royal-college-of-physicians-and-surgeons-of-canada-fellow-of-the-royal-college-of-surgeons-of-canada' => ['royal-college-of-physicians-and-surgeons-of-canada', 'FRCSC'],
    'royal-australasian-college-of-surgeons-fellow-of-the-royal-australasian-college-of-surgeons' => ['royal-australasian-college-of-surgeons', 'FRACS'],
    'american-academy-of-orthopaedic-manual-physical-therapists-fellow-of-the-american-academy-of-orthopaedic-manual-physical-therapists' => ['american-academy-of-orthopaedic-manual-physical-therapists', 'FAAOMPT'],
];

/**
 * Decide whether a credential is a fellowship designation: a full name beginning
 * "fellow of" or "fellowship of", or the letter F followed by at least three
 * initials of an organization. Three is what keeps FNP, a nurse practitioner
 * credential, outside the pattern.
 */
function isFellowshipDesignation(string $abbreviation, string $fullName): bool
{
    return preg_match('/^fellow(ship)?\s+of\b/i', trim($fullName)) === 1
        || preg_match('/^F[A-Z]{3,}$/', trim($abbreviation)) === 1;
}

/**
 * Resolve a `certifications` term, skipping when `content/` is absent and failing
 * when it is present but the term does not resolve.
 */
function fellowshipCertification(string $slug): LocalizedTerm
{
    $term = Term::find("certifications::{$slug}");

    // Skip without the content submodule, and fail when it is present
    if (! $term instanceof LocalizedTerm) {
        ContentSubmodule::reportMissing("the {$slug} term");
    }

    return $term;
}

/**
 * Read a taxonomy's terms, skipping when `content/` is absent and failing when it
 * is present but the taxonomy holds nothing.
 *
 * @return list<LocalizedTerm>
 */
function fellowshipTaxonomyTerms(string $taxonomy): array
{
    $terms = [];

    foreach (mustBe(Collection::class, Term::whereTaxonomy($taxonomy), "the {$taxonomy} terms") as $term) {
        $terms[] = mustBe(LocalizedTerm::class, $term, "a {$taxonomy} term");
    }

    // Skip without the content submodule, and fail when it is present
    if ($terms === []) {
        ContentSubmodule::reportMissing("the {$taxonomy} taxonomy");
    }

    return $terms;
}

it('recognizes a fellowship designation and nothing else', function (string $abbreviation, string $fullName, bool $expected): void {
    expect(isFellowshipDesignation($abbreviation, $fullName))->toBe($expected);
})->with([
    'the abbreviation a request would add' => ['FAAOS', '', true],
    'a full name beginning fellow of' => ['AB', 'Fellow of the American Academy of Orthopaedic Surgeons', true],
    'a full name beginning fellowship of' => ['AB', 'fellowship of the Royal Colleges of Surgeons', true],
    'a nurse practitioner credential' => ['FNP', 'Family Nurse Practitioner', false],
    'a hyphenated nurse practitioner credential' => ['FNP-BC', 'Family Nurse Practitioner', false],
    'a degree' => ['MD', 'Doctor of Medicine', false],
    'a name merely containing the word' => ['ABC', 'Graduate Fellow in Surgery', false],
]);

it('keeps every fellowship designation out of the credentials taxonomy', function (): void {
    $fellowships = [];

    foreach (fellowshipTaxonomyTerms('credentials') as $term) {
        $abbreviation = $term->get('abbreviation');
        $fullName = $term->get('full_name');

        $isFellowship = isFellowshipDesignation(
            is_array($abbreviation) && is_string($abbreviation['text'] ?? null) ? $abbreviation['text'] : '',
            is_string($fullName) ? $fullName : '',
        );

        // Name the offending term so a failure says which one to remove
        if ($isFellowship) {
            $fellowships[] = $term->id();
        }
    }

    expect($fellowships)->toBe([]);
});

it('removes the eight fellowship credentials and every certification link to them', function (): void {
    $linked = [];

    foreach (fellowshipTaxonomyTerms('certifications') as $term) {
        $credential = $term->get('credentials_term');

        // Most certifications have no related credentials term
        if ($credential !== null) {
            $linked[] = $credential;
        }
    }

    // A read that found no links at all could not have found one of these
    expect($linked)->not->toBeEmpty();

    foreach (RETIRED_FELLOWSHIP_CREDENTIALS as $slug) {
        expect(Term::find("credentials::{$slug}"))->toBeNull()
            ->and($linked)->not->toContain($slug);
    }
});

it('carries each moved fellowship into certifications with its certifying body', function (string $slug, string $organization, string $abbreviation): void {
    $data = mustBe(Collection::class, fellowshipCertification($slug)->data(), 'the term data');

    $alternateNames = collect(mustBeArray($data->get('alternate_name'), 'the alternate names'))->pluck('text')->all();

    expect($data->get('organization'))->toBe($organization)
        ->and($data->get('credential_kind'))->toBe('certification')
        ->and($data->has('credentials_term'))->toBeFalse()
        ->and($alternateNames)->toContain($abbreviation)
        ->and(Term::find("certification_organizations::{$organization}"))->toBeInstanceOf(LocalizedTerm::class);
})->with(array_map(
    static fn (string $slug, array $row): array => [$slug, $row[0], $row[1]],
    array_keys(MOVED_FELLOWSHIP_CERTIFICATIONS),
    MOVED_FELLOWSHIP_CERTIFICATIONS,
));

it('saves each moved fellowship in the Control Panel without validation errors', function (string $slug): void {
    expect(TermSaveValidation::rejectedFields(fellowshipCertification($slug)))->toBe([]);
})->with(array_keys(MOVED_FELLOWSHIP_CERTIFICATIONS));

it('keeps the save check able to reject a moved fellowship', function (): void {
    $slug = array_key_first(MOVED_FELLOWSHIP_CERTIFICATIONS);

    expect(TermSaveValidation::rejectedFields(fellowshipCertification($slug), ['organization' => null]))
        ->toBe(['organization']);
});
