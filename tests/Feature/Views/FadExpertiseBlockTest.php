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
 * Coverage for the completed Find-a-Doc Areas of Expertise page-builder block
 * (resources/views/page_builder/_uamsweb_block_fad_expertise.antlers.html, UAMS-Web/uams-statamic-archive#62):
 * a list-only card grid of Areas of Expertise with no reader facets. It renders
 * the editor-curated `expertise` selection when set, otherwise every Area, each
 * via the shared clinical_expertise/_card partial — and, unlike the pre-#62 stub,
 * emits no `fadFilter` root or filter bar.
 *
 * Built in an isolated Stache so the `collection:clinical_expertise` query + the
 * card's computeds run against real entries. The validity gate + shared wrapper
 * are exercised the same way as FadProvidersBlockTest.
 *
 * @command php artisan test --compact tests/Feature/Views/FadExpertiseBlockTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make('clinical_expertise')->title('Areas of Expertise');
    /** @var Collection $collection */
    $collection = $collection->routes('/expertise/{slug}');
    $collection->save();

    // A minimal AoE blueprint (title + excerpt) that OMITS the real `featured_image_16_9`
    // assets field, so the card's image gate reads a bare null and renders the fallback
    // instead of augmenting an assets field whose `images` container isn't set up here.
    // Plus a content-page blueprint so the block's blueprint:is exclusion is testable.
    /** @var Statamic\Fields\Blueprint $aoeBlueprint */
    $aoeBlueprint = Blueprint::make('clinical_expertise')->setNamespace('collections.clinical_expertise');
    /** @var Statamic\Fields\Blueprint $aoeBlueprint */
    $aoeBlueprint = $aoeBlueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'excerpt', 'field' => ['type' => 'textarea']],
        ]]]]],
    ]);
    $aoeBlueprint->save();

    /** @var Statamic\Fields\Blueprint $pageBlueprint */
    $pageBlueprint = Blueprint::make('basic_page')->setNamespace('collections.clinical_expertise');
    /** @var Statamic\Fields\Blueprint $pageBlueprint */
    $pageBlueprint = $pageBlueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]],
    ]);
    $pageBlueprint->save();
});

/** Persist a published Area of Expertise (anchored to a controlled term, UAMS-Web/uams-statamic-archive#1066). */
function buildAoeEntry(string $slug, string $title): Statamic\Contracts\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('clinical_expertise');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint('clinical_expertise');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($slug);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => $title, 'clinical_specializations' => ['207RC0000X']]);

    return tap($entry)->saveQuietly();
}

/**
 * Render the Areas of Expertise block with a valid block context.
 *
 * @param  array<string, mixed>  $overrides  Block-scope overrides (e.g. `expertise`).
 */
function fadExpertiseBlockRender(array $overrides = []): string
{
    return (string) Antlers::parse('{{ partial:page_builder/uamsweb_block_fad_expertise }}', array_merge([
        'block_heading' => 'Our Areas of Expertise',
        'block_description' => 'Explore UAMS Health specialties.',
        'background_color' => 'uams-blue',
        'more_query' => true,
        'more_heading' => 'More',
        'more_button_text' => 'See all areas',
        'more_button_url' => '/expertise',
    ], $overrides), true);
}

it('renders every Area of Expertise as a card, with no filter UI', function (): void {
    buildAoeEntry('cardiology', 'Cardiology');
    buildAoeEntry('neurology', 'Neurology');

    $out = fadExpertiseBlockRender();

    // Two cards render via the shared card partial (one `expertise-card` article each).
    expect(substr_count($out, 'expertise-card'))->toBe(2);
    expect($out)
        ->toContain('Cardiology')
        ->toContain('Neurology')
        ->toContain('View Area of Expertise')
        ->toContain('expertise-list');
    // ...and no reader facet layer: no filter bar, no Alpine root.
    expect($out)->not->toContain('ajax-filter');
    expect($out)->not->toContain('x-data="fadFilter"');
    expect($out)->not->toContain('Filter the');
});

it('excludes content-page descendants (basic_page) from the card grid', function (): void {
    buildAoeEntry('cardiology', 'Cardiology');
    // A content page hung under the AoE tree — must NOT render as an Area of Expertise card.
    /** @var Statamic\Entries\Entry $page */
    $page = Entry::make();
    /** @var Statamic\Entries\Entry $page */
    $page = $page->collection('clinical_expertise');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->blueprint('basic_page');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->slug('about-cardiology');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->published(true);
    /** @var Statamic\Entries\Entry $page */
    $page = $page->data(['title' => 'About Our Cardiology Program']);
    tap($page)->saveQuietly();

    $out = fadExpertiseBlockRender();

    expect($out)->toContain('Cardiology');
    expect($out)->not->toContain('About Our Cardiology Program');
    expect(substr_count($out, 'expertise-card'))->toBe(1);
});

it('renders only the editor-curated Areas when the expertise picker is set', function (): void {
    buildAoeEntry('cardiology', 'Cardiology');
    buildAoeEntry('neurology', 'Neurology');

    // The augmented `expertise` entries reference (only Cardiology picked).
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', 'clinical_expertise');
    /** @var EntryQueryBuilder $query */
    $query = $query->where('slug', 'cardiology');
    $curated = $query->get();

    $out = fadExpertiseBlockRender(['expertise' => $curated]);

    expect($out)->toContain('Cardiology');
    expect($out)->not->toContain('Neurology');
    expect(substr_count($out, 'expertise-card'))->toBe(1);
});

it('renders the empty state when there are no Areas of Expertise', function (): void {
    $out = fadExpertiseBlockRender();

    expect($out)->toContain('expertise-empty');
    expect($out)->not->toContain('View Area of Expertise');
});

it('suppresses the block entirely when a required field (background_color) is missing', function (): void {
    buildAoeEntry('cardiology', 'Cardiology');

    $out = fadExpertiseBlockRender(['background_color' => null]);

    expect($out)->not->toContain('expertise-list');
    expect($out)->not->toContain('Cardiology');
});
