<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Render coverage for the Areas of Expertise index faceted search (UAMS-Web/uams-statamic-archive#1440):
 * resources/views/clinical_expertise/_listing.antlers.html. The listing renders a
 * `<form method="get">` search box and passes the `q` query param through the
 * `expertise_by_associated_ontology_search` scope so the grid narrows to the Areas
 * of Expertise whose associated conditions/treatments match the term.
 *
 * Built in an isolated Stache so the `collection:clinical_expertise` tag + the
 * scope run against real entries. The `q` term reaches the template through the
 * cascade `get` variable (populated from `request()->query()` on a live request);
 * here it is passed explicitly to `Antlers::parse`. Scope-logic coverage (title vs
 * alternate-name matching, no-match, case) lives in
 * ExpertiseByAssociatedOntologySearchScopeTest.
 *
 * @command php artisan test --compact tests/Feature/Views/ExpertiseListingSearchTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $expertiseCollection = CollectionFacade::make('clinical_expertise');
    $expertiseCollection->title('Areas of Expertise');
    $expertiseCollection->save();
    $conditionsCollection = CollectionFacade::make('clinical_conditions');
    $conditionsCollection->title('Clinical Conditions');
    $conditionsCollection->save();
    $servicesCollection = CollectionFacade::make('clinical_services');
    $servicesCollection->title('Clinical Services');
    $servicesCollection->save();

    // Minimal AoE blueprint keeping the scope's ontology fields but OMITTING the real
    // `featured_image_16_9` assets field, so the enriched card (UAMS-Web/uams-statamic-archive#1467) renders its
    // fallback rather than augmenting an assets field whose `images` container is unset.
    $expertiseBlueprint = Blueprint::make('clinical_expertise');
    $expertiseBlueprint->setNamespace('collections.clinical_expertise');
    $expertiseBlueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'excerpt', 'field' => ['type' => 'textarea']],
            ['handle' => 'clinical_conditions', 'field' => ['type' => 'entries', 'collections' => ['clinical_conditions']]],
            ['handle' => 'clinical_services', 'field' => ['type' => 'entries', 'collections' => ['clinical_services']]],
        ]]]]],
    ]);
    $expertiseBlueprint->save();

    /** @var Statamic\Entries\Entry $diabetesEntry */
    $diabetesEntry = Entry::make();
    $diabetesEntry->collection('clinical_conditions');
    $diabetesEntry->slug('type-2-diabetes');
    $diabetesEntry->published(true);
    $diabetesEntry->data([
        'title' => 'Type 2 Diabetes',
        'alternate_name' => [['type' => 'new_set', 'enabled' => true, 'text' => 'T2D']],
    ]);
    $diabetes = tap($diabetesEntry)->saveQuietly();

    /** @var Statamic\Entries\Entry $endocrinologyEntry */
    $endocrinologyEntry = Entry::make();
    $endocrinologyEntry->collection('clinical_expertise');
    $endocrinologyEntry->blueprint('clinical_expertise');
    $endocrinologyEntry->slug('endocrinology');
    $endocrinologyEntry->published(true);
    $endocrinologyEntry->data(['title' => 'Endocrinology', 'clinical_conditions' => [$diabetes->id()]]);
    tap($endocrinologyEntry)->saveQuietly();

    /** @var Statamic\Entries\Entry $cardiologyEntry */
    $cardiologyEntry = Entry::make();
    $cardiologyEntry->collection('clinical_expertise');
    $cardiologyEntry->blueprint('clinical_expertise');
    $cardiologyEntry->slug('cardiology');
    $cardiologyEntry->published(true);
    $cardiologyEntry->data(['title' => 'Cardiology']);
    tap($cardiologyEntry)->saveQuietly();
});

/**
 * Render the AoE listing partial with the given `get` query params.
 *
 * @param  array<string, string>  $get
 */
function renderExpertiseListing(array $get = []): string
{
    return (string) Antlers::parse('{{ partial:clinical_expertise/listing }}', ['get' => $get], true);
}

it('renders the search form and the full grid with no active search', function (): void {
    $out = renderExpertiseListing();

    expect($out)->toContain('class="expertise-search"')
        ->and($out)->toContain('name="q"')
        ->and($out)->toContain('Endocrinology')
        ->and($out)->toContain('Cardiology')
        // No search term → no "Clear" control and no search-specific empty state.
        ->and($out)->not->toContain('expertise-search-clear')
        ->and($out)->not->toContain('No areas of expertise match');
});

it('narrows the grid to Areas of Expertise matching the term by an associated condition', function (): void {
    $out = renderExpertiseListing(['q' => 'diabetes']);

    // Endocrinology is associated with the matching condition; Cardiology is not.
    expect($out)->toContain('Endocrinology')
        ->and($out)->not->toContain('Cardiology')
        // The active term is reflected into the input and a Clear control appears.
        ->and($out)->toContain('value="diabetes"')
        ->and($out)->toContain('expertise-search-clear');
});

it('narrows by an alternate name of the associated condition', function (): void {
    $out = renderExpertiseListing(['q' => 't2d']);

    expect($out)->toContain('Endocrinology')
        ->and($out)->not->toContain('Cardiology');
});

it('shows the search-aware empty state when nothing matches', function (): void {
    $out = renderExpertiseListing(['q' => 'nonexistent-term']);

    expect($out)->toContain('No areas of expertise match')
        ->and($out)->toContain('nonexistent-term')
        ->and($out)->not->toContain('Endocrinology')
        ->and($out)->not->toContain('Cardiology');
});

it('escapes the reflected search term in the input and the empty state (no XSS)', function (): void {
    // A term that matches nothing (so the empty state also reflects it) and carries
    // an HTML/JS payload: it must be reflected ESCAPED, never as live markup.
    $out = renderExpertiseListing(['q' => '<script>alert(1)</script>']);

    expect($out)->not->toContain('<script>alert(1)</script>')
        ->and($out)->toContain('&lt;script&gt;');
});
