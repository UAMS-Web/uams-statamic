<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint as BlueprintContract;
use Statamic\Fields\Field;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\Support\TermSaveValidation;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * The Classification questions of the `credentials` term blueprint (UAMS-Web/uams-statamic#343).
 *
 * The section asks whether a credential is clinical, whether it is a regulatory
 * designation in the terms of the Provider Degrees and Credentials article, a
 * justification for every clinical credential, and optional additional information. The
 * Provider Enrollment Portal and Epic SER questions are gone, from the blueprint and from
 * every stored term, and every committed term saves in the Control Panel.
 *
 * @command  php artisan test --compact tests/Feature/CredentialClassificationQuestionsTest.php
 */

/**
 * The credential term blueprint.
 */
function credentialClassificationBlueprint(): BlueprintContract
{
    return mustBe(BlueprintContract::class, Blueprint::find('taxonomies.credentials.credential'), 'the credentials term blueprint');
}

/**
 * Every committed credential term.
 *
 * @return list<LocalizedTerm>
 */
function credentialClassificationTerms(): array
{
    // Resolved by id, as the Control Panel editor loads a term, so each carries its
    // localized data rather than the taxonomy listing's bare term. The listing carries
    // one row per site, so each term is kept once.
    $terms = [];

    foreach (Term::whereTaxonomy('credentials') as $listed) {
        $id = data_get($listed, 'id');
        Assert::assertIsString($id, 'A listed credentials term has no string id.');
        $terms[$id] ??= mustBe(LocalizedTerm::class, Term::find($id), "the credentials term {$id}");
    }

    return array_values($terms);
}

/**
 * A field of the credential term blueprint.
 */
function credentialClassificationField(string $handle): Field
{
    return mustBe(Field::class, credentialClassificationBlueprint()->field($handle), "the {$handle} field");
}

/**
 * A term's slug.
 */
function credentialClassificationSlug(LocalizedTerm $term): string
{
    $slug = $term->slug();
    Assert::assertIsString($slug, 'A credentials term has no string slug.');

    return $slug;
}

/**
 * Whether a term stores a value under the key, whatever the value.
 */
function credentialClassificationStores(LocalizedTerm $term, string $key): bool
{
    return mustBe(Collection::class, $term->data(), 'the stored data of '.credentialClassificationSlug($term))->has($key);
}

it('asks the clinical, regulatory, justification and additional-information questions, and nothing about the portal or Epic', function (): void {
    $fields = credentialClassificationBlueprint()->fields()->all();

    expect($fields->keys()->all())
        ->toContain('clinical_query', 'clinical_statutory_credential_query', 'clinical_justification', 'clinical_additional_info')
        ->not->toContain('provider_enrollment_portal_query')
        ->not->toContain('epic_query');

    // The regulatory question is asked in the article's terms
    $regulatory = credentialClassificationField('clinical_statutory_credential_query');
    expect($regulatory->display())->toBe('Is this a regulatory designation?')
        ->and($regulatory->get('instructions'))->toBeString()->toContain('itself authorize the provider');

    // The justification is shown, and required, for every clinical credential
    $justification = credentialClassificationField('clinical_justification');
    expect($justification->get('if'))->toBe(['clinical_query' => 'equals true'])
        ->and($justification->get('validate'))->toContain('required');
});

it('stores no portal or Epic answer on any credential term', function (): void {
    $this->requireContentCollection('people');

    $terms = credentialClassificationTerms();
    expect($terms)->not->toBeEmpty();

    // Asked of the stored data: `LocalizedTerm::has()` reports false for a key stored as
    // false, which is the only value either question ever held
    $stale = collect($terms)
        ->filter(fn (LocalizedTerm $term): bool => credentialClassificationStores($term, 'provider_enrollment_portal_query') || credentialClassificationStores($term, 'epic_query'))
        ->map(credentialClassificationSlug(...))
        ->values()
        ->all();

    expect($stale)->toBe([]);
});

it('carries a justification on every clinical credential', function (): void {
    $this->requireContentCollection('people');

    $clinical = collect(credentialClassificationTerms())
        ->filter(fn (LocalizedTerm $term): bool => $term->get('clinical_query') === true);

    // Both answers to the regulatory question are represented, so neither half is vacuous
    expect($clinical->filter(fn (LocalizedTerm $term): bool => $term->get('clinical_statutory_credential_query') === true))->not->toBeEmpty()
        ->and($clinical->filter(fn (LocalizedTerm $term): bool => $term->get('clinical_statutory_credential_query') === false))->not->toBeEmpty();

    $missing = $clinical
        ->filter(fn (LocalizedTerm $term): bool => empty($term->get('clinical_justification')))
        ->map(credentialClassificationSlug(...))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('saves every credential term in the Control Panel with no field rejected but the website question', function (): void {
    $this->requireContentCollection('people');

    $rejected = [];
    $unansweredWebsite = 0;

    foreach (credentialClassificationTerms() as $term) {
        $fields = TermSaveValidation::rejectedFields($term);

        // `official_website_query` asks an editor the first time a term is saved, and
        // most terms have not been: decided on UAMS-Web/uams-statamic#501, with no
        // backfill. It is the one rejection allowed; any other field fails this test.
        if (in_array('official_website_query', $fields, true)) {
            $unansweredWebsite++;
        }

        $fields = array_values(array_diff($fields, ['official_website_query']));

        if ($fields !== []) {
            $rejected[credentialClassificationSlug($term)] = $fields;
        }
    }

    expect($rejected)->toBe([])
        // The allowance is exercised, so the helper is seen rejecting a real save
        ->and($unansweredWebsite)->toBeGreaterThan(0);
});

it('rejects a regulatory designation saved without a justification', function (): void {
    $this->requireContentCollection('people');

    // Before UAMS-Web/uams-statamic#343 the justification was hidden for a regulatory
    // designation, so this save went through; it now has to fail on that field alone
    $term = mustBe(LocalizedTerm::class, Term::find('credentials::md'), 'the md credential term');

    expect($term->get('clinical_statutory_credential_query'))->toBeTrue()
        ->and(TermSaveValidation::rejectedFields($term, ['clinical_justification' => null]))->toBe(['clinical_justification']);
});
