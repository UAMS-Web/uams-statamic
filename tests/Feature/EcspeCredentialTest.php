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
 * Locks the UAMS-Web/uams-statamic#346 outcome: no Arkansas Department of
 * Education source uses "ECSPE", so the `ecspe` credential carries the license's
 * full name in every field that a reader sees. `abbreviation` is required on a
 * `credentials` term, so it holds the full name rather than being empty.
 */

// cspell:ignore ecspe

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

it('saves in the Control Panel without validation errors', function (): void {
    expect(TermSaveValidation::rejectedFields(mustBe(LocalizedTerm::class, $this->term, 'the ecspe term')))->toBe([]);
});

it('reports a required field left empty, so a clean save is not a blind check', function (): void {
    $term = mustBe(LocalizedTerm::class, $this->term, 'the ecspe term');

    // A field the editor always shows, emptied, must be rejected
    expect(TermSaveValidation::rejectedFields($term, ['full_name' => null]))->toBe(['full_name']);
});
