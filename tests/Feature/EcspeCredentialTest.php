<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\ContentSubmodule;
use Tests\Support\TermSaveValidation;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Locks the UAMS-Web/uams-statamic#346 outcome: no Arkansas Department of
 * Education source uses "ECSPE", so the `ecspe` credential carries the license's
 * full name in every field that a reader sees. `abbreviation` is required on a
 * `credentials` term, so it holds the full name rather than being empty.
 */

// cspell:ignore ecds ecspe

beforeEach(function (): void {
    $term = Term::find('credentials::ecspe');

    // Skip without the content submodule, and fail when it is present
    if (! $term instanceof LocalizedTerm) {
        ContentSubmodule::reportMissing('the credentials::ecspe term');
    }

    $this->term = $term;
});

it('records the license by the full name the Arkansas rule uses', function (): void {
    $term = mustBe(LocalizedTerm::class, $this->term, 'the ecspe term');
    $data = mustBe(Collection::class, $term->data(), 'the term data');

    expect($term->title())->toBe('Early Childhood Special Education')
        ->and($data->get('full_name'))->toBe('Early Childhood Special Education')
        ->and($data->get('abbreviation'))->toMatchArray(['text' => 'Early Childhood Special Education']);
});

it('presents the abbreviation nowhere in the term', function (): void {
    $term = mustBe(LocalizedTerm::class, $this->term, 'the ecspe term');
    $data = mustBe(Collection::class, $term->data(), 'the term data');

    // A scan of an empty term would pass, so show it read the body text first
    expect(json_encode($data->all(), JSON_THROW_ON_ERROR))
        ->toContain('Early Childhood Special Education credential is')
        ->not->toContain('ECSPE');
});

it('asks for the website answer the term has never recorded', function (): void {
    $term = mustBe(LocalizedTerm::class, $this->term, 'the ecspe term');

    // UAMS-Web/uams-statamic#501 keeps the question required: the Control Panel refuses this save
    expect(mustBe(Collection::class, $term->data(), 'the term data')->has('official_website_query'))->toBeFalse()
        ->and(TermSaveValidation::rejectedFields($term))->toBe(['official_website_query']);
});

it('saves in the Control Panel once the website question is answered', function (bool $answer): void {
    $term = mustBe(LocalizedTerm::class, $this->term, 'the ecspe term');

    // Yes also shows the URL field, which then needs its own answer
    expect(TermSaveValidation::rejectedFields($term, [
        'official_website_query' => $answer,
        'official_website' => $answer ? 'https://example.com/' : null,
    ]))->toBe([]);
})->with(['no' => false, 'yes' => true]);

it('keeps the website question required on a credential', function (): void {
    $term = mustBe(LocalizedTerm::class, $this->term, 'the ecspe term');
    $blueprint = mustBe(Blueprint::class, $term->blueprint(), 'the term blueprint');

    expect(mustBe(Field::class, $blueprint->field('official_website_query'), 'the website question')->rules())
        ->toHaveKey('official_website_query')
        ->official_website_query->toContain('required', 'sometimes');
});

it('leaves the website question out for a credential the editor hides it on', function (): void {
    $term = Term::find('credentials::ecds');

    // An Occupation is hidden by the custom condition, so its unanswered question is never rejected
    expect(TermSaveValidation::rejectedFields(mustBe(LocalizedTerm::class, $term, 'the ecds term')))->toBe([]);
});

it('rejects an unanswered website question on a certification', function (): void {
    $term = Term::find('certifications::american-academy-of-nurse-practitioners-certification-board-adult-gerontology-primary-care-nurse-practitioner');

    // The certification field is shown unconditionally
    expect(TermSaveValidation::rejectedFields(mustBe(LocalizedTerm::class, $term, 'a certification term'), ['official_website_query' => null]))
        ->toContain('official_website_query');
});

it('reports a required field left empty, so a clean save is not a blind check', function (): void {
    $term = mustBe(LocalizedTerm::class, $this->term, 'the ecspe term');

    // A field the editor always shows, emptied, must be rejected
    expect(TermSaveValidation::rejectedFields($term, ['full_name' => null, 'official_website_query' => false]))->toBe(['full_name']);
});
