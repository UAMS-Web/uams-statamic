<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Term;
use Statamic\Fields\Field;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\ContentSubmodule;
use Tests\Support\TermSaveValidation;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Locks the UAMS-Web/uams-statamic#303 First Connections terms: the developmental therapist and
 * developmental therapy assistant certifications under 20 CAR § 1002-616, their
 * certifying body, and the corrected `ecds` credential. Neither certification has
 * an abbreviation, so neither may point at a `credentials` term, and every term
 * must save in the Control Panel without validation errors.
 *
 * @command  php artisan test --compact tests/Feature/FirstConnectionsCertificationsTest.php
 */

// cspell:ignore ecds

/**
 * The two First Connections certifications, as `certifications` term slugs.
 */
const FIRST_CONNECTIONS_CERTIFICATIONS = [
    'first-connections-developmental-therapist',
    'first-connections-developmental-therapy-assistant',
];

/**
 * Resolve a term by its `taxonomy::slug` id, skipping when `content/` is absent
 * and failing when it is present but the term does not resolve.
 */
function firstConnectionsTerm(string $id): LocalizedTerm
{
    $term = Term::find($id);

    // Skip without the content submodule, and fail when it is present
    if (! $term instanceof LocalizedTerm) {
        ContentSubmodule::reportMissing("the {$id} term");
    }

    return $term;
}

it('gives each First Connections certification its certifying body and no credentials term', function (string $slug): void {
    $term = firstConnectionsTerm("certifications::{$slug}");

    $data = mustBe(Collection::class, $term->data(), 'the term data');

    expect($data->get('organization'))->toBe('first-connections')
        ->and($data->get('credential_kind'))->toBe('certification')
        ->and($data->has('credentials_term'))->toBeFalse();
})->with(FIRST_CONNECTIONS_CERTIFICATIONS);

it('saves each changed term in the Control Panel without validation errors', function (string $id): void {
    expect(TermSaveValidation::rejectedFields(firstConnectionsTerm($id)))->toBe([]);
})->with([
    'certifying body' => 'certification_organizations::first-connections',
    'developmental therapist' => 'certifications::first-connections-developmental-therapist',
    'developmental therapy assistant' => 'certifications::first-connections-developmental-therapy-assistant',
    'ecds' => 'credentials::ecds',
]);

it('reports a required field left empty, so a clean save is not a blind check', function (): void {
    $term = firstConnectionsTerm('certifications::first-connections-developmental-therapist');

    // A field the editor always shows, emptied, must be rejected
    expect(TermSaveValidation::rejectedFields($term, ['name' => null]))->toBe(['name']);
});
