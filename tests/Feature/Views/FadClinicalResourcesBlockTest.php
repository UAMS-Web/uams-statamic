<?php

declare(strict_types=1);

use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the completed Find-a-Doc Clinical Resources page-builder block
 * (resources/views/page_builder/_uamsweb_block_fad_clinical_resources.antlers.html,
 * UAMS-Web/uams-statamic-archive#61): a uniform card grid of clinical resources with the UAMS-Web/uams-statamic-archive#1242 client-side
 * resource-type facet. It renders the editor-curated `clinical_resources` selection
 * when set, otherwise every resource, each via the shared clinical_resources/_card
 * partial with `fad_facet` so the card emits its `data-type` blueprint slug.
 *
 * Built in an isolated Stache with per-subtype blueprints so the
 * `collection:clinical_resources` query, the card augmentation, and the real
 * `data-type` facet slugs run against real entries. The client engine itself is
 * unit-tested in resources/js/fad-filter.test.js.
 *
 * @command php artisan test --compact tests/Feature/Views/FadClinicalResourcesBlockTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make('clinical_resources');
    $collection->title('Clinical Resources');
    $collection->dated(true);
    $collection->save();

    foreach (['article', 'video'] as $handle) {
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = Blueprint::make($handle);
        $blueprint->setNamespace('collections.clinical_resources');
        $blueprint->setContents([
            'tabs' => ['main' => ['sections' => [['fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text']],
                ['handle' => 'intro', 'field' => ['type' => 'textarea']],
            ]]]]],
        ]);
        $blueprint->save();
    }
});

/** Persist a published, dated clinical resource of the given subtype blueprint. */
function buildClinicalResource(string $handle, string $slug, string $title, string $date): Statamic\Contracts\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_resources');
    $entry->blueprint($handle);
    $entry->slug($slug);
    $entry->date($date);
    $entry->published(true);
    $entry->data(['title' => $title, 'intro' => 'An intro.']);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Render the Clinical Resources block with a valid block context.
 *
 * @param  array<string, mixed>  $overrides  Block-scope overrides (e.g. `clinical_resources`, `id`).
 */
function fadResourcesBlockRender(array $overrides = []): string
{
    return (string) Antlers::parse('{{ partial:page_builder/uamsweb_block_fad_clinical_resources }}', array_merge([
        'block_heading' => 'Clinical Resources',
        'block_description' => 'Browse UAMS Health clinical resources.',
        'background_color' => 'uams-blue',
        'more_query' => true,
        'more_heading' => 'More',
        'more_button_text' => 'See all resources',
        'more_button_url' => '/resources',
    ], $overrides), true);
}

it('renders resource cards + the wired type facet with real data-type slugs (no curation)', function (): void {
    buildClinicalResource('article', 'managing-diabetes', 'Managing Diabetes', '2026-01-02');
    buildClinicalResource('video', 'surgery-overview', 'Surgery Overview', '2026-01-01');

    $out = fadResourcesBlockRender();

    // The block wrapper + the fadFilter Alpine root over the type facet.
    expect($out)
        ->toContain('clinical-resource-list')
        ->toContain('x-data="fadFilter(')
        ->toContain('data-fad-namespace="module-')
        ->toContain('aria-live="polite"')
        // The type select is populated + wired to the engine (was a stub pre-#61).
        ->toContain('data-fad-facet="type"')
        ->toContain('<option value="article">Article</option>')
        ->toContain('<option value="video">Video</option>')
        // Both resources render as cards carrying the facet contract with real slugs.
        ->toContain('Managing Diabetes')
        ->toContain('Surgery Overview')
        ->toContain('data-fad-card')
        ->toContain('data-type="article"')
        ->toContain('data-type="video"');
});

it('renders only the editor-curated resources when the picker is set', function (): void {
    buildClinicalResource('article', 'managing-diabetes', 'Managing Diabetes', '2026-01-02');
    buildClinicalResource('video', 'surgery-overview', 'Surgery Overview', '2026-01-01');

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_resources');
    $query->where('slug', 'managing-diabetes');
    $curated = $query->get();

    $out = fadResourcesBlockRender(['clinical_resources' => $curated]);

    expect($out)
        ->toContain('Managing Diabetes')
        ->and($out)->not->toContain('Surgery Overview')
        ->toContain('data-type="article"')
        ->and($out)->not->toContain('data-type="video"');
});

it('namespaces the filter control ids + query namespace per block instance (#1481)', function (): void {
    buildClinicalResource('article', 'managing-diabetes', 'Managing Diabetes', '2026-01-02');

    $blockA = fadResourcesBlockRender(['id' => 'aaa']);
    $blockB = fadResourcesBlockRender(['id' => 'bbb']);

    expect($blockA)
        ->toContain('data-fad-namespace="module-aaa"')
        ->toContain('id="module-aaa-clinical_resource_type"')
        ->and($blockA)->not->toContain('module-bbb');
    expect($blockB)
        ->toContain('data-fad-namespace="module-bbb"')
        ->toContain('id="module-bbb-clinical_resource_type"');
});

it('renders the empty state and the type control when there are no resources', function (): void {
    $out = fadResourcesBlockRender();

    expect($out)
        ->toContain('resources-empty')
        ->toContain('data-fad-facet="type"')
        ->and($out)->not->toContain('data-fad-card');
});

it('suppresses the block entirely when a required field (background_color) is missing', function (): void {
    buildClinicalResource('article', 'managing-diabetes', 'Managing Diabetes', '2026-01-02');

    $out = fadResourcesBlockRender(['background_color' => null]);

    expect($out)
        ->not->toContain('clinical-resource-list')
        ->and($out)->not->toContain('Managing Diabetes');
});
