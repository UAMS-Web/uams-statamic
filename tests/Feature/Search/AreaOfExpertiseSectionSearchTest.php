<?php

declare(strict_types=1);

use App\Search\AreaOfExpertiseSectionDocument;
use App\Search\AreaOfExpertiseSectionProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;
use Statamic\Search\Index;
use Statamic\Search\Searchables;
use Statamic\Search\Searchables\Providers;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for surfacing Area-of-Expertise routed section subpages (`/{entry}/{section}`,
 * UAMS-Web/uams-statamic-archive#1058) as their own interactive search results (UAMS-Web/uams-statamic-archive#1527): the custom
 * {@see AreaOfExpertiseSectionProvider} + {@see AreaOfExpertiseSectionDocument}, their
 * registration into the `default` index, and the results-listing dispatch.
 *
 * `provide()`'s membership is asserted through `Search::index('default')->searchables()`,
 * which drives the provider without writing an index file (tests keep that JSON in a
 * per-process directory since UAMS-Web/uams-statamic-archive#2536, but none is needed here). The reindex-on-save listener is
 * covered separately in SyncAreaOfExpertiseSectionSearchIndexTest.
 *
 * @command  php artisan test --compact tests/Feature/Search/AreaOfExpertiseSectionSearchTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $expertise = CollectionFacade::make('clinical_expertise');
    $expertise->title('Areas of Expertise');
    $expertise->routes('{slug}');
    $expertise->structureContents(['root' => false]);
    $expertise->save();

    $locations = CollectionFacade::make('clinical_locations');
    $locations->title('Clinical Locations');
    $locations->save();

    $expertiseBlueprint = Blueprint::make('clinical_expertise');
    $expertiseBlueprint->setNamespace('collections.clinical_expertise');
    $expertiseBlueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'clinical_locations', 'field' => ['type' => 'entries', 'collections' => ['clinical_locations']]],
        ]]]]],
    ]);
    $expertiseBlueprint->save();

    $locationBlueprint = Blueprint::make('location');
    $locationBlueprint->setNamespace('collections.clinical_locations');
    $locationBlueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]],
    ]);
    $locationBlueprint->save();
});

/**
 * Create a published Area of Expertise; saveQuietly so no global save listener mutates it.
 *
 * @param  array<string, mixed>  $data
 */
function makeAoe(string $slug, array $data): Statamic\Entries\Entry
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
    $entry = $entry->data($data);
    $entry->saveQuietly();

    return $entry;
}

/** A published clinical location to populate the AoE `locations` section relationship. */
function makeLocation(): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    /** @var Statamic\Entries\Entry $location */
    $location = $location->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->blueprint('location');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->slug('main-campus');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->published(true);
    /** @var Statamic\Entries\Entry $location */
    $location = $location->data(['title' => 'Main Campus']);
    $location->saveQuietly();

    return $location;
}

it('registers the AoE-section provider under its reference prefix', function (): void {
    expect(app(Providers::class)->getByPrefix('clinical_expertise_section'))
        ->toBeInstanceOf(AreaOfExpertiseSectionProvider::class);
});

it('indexes each populated section of a searchable AoE, and gates empty + unsearchable ones', function (): void {
    $location = makeLocation();

    $cancer = makeAoe('cancer-care', ['title' => 'Cancer Care', 'clinical_locations' => [$location->id()]]);
    $empty = makeAoe('empty-area', ['title' => 'Empty Area']);
    $hidden = makeAoe('hidden-area', ['title' => 'Hidden Area', 'searchable' => false, 'clinical_locations' => [$location->id()]]);
    // No `searchable` key at all — the missing/null default stays searchable (UAMS-Web/uams-statamic-archive#1060 rule).
    $legacy = makeAoe('legacy-area', ['title' => 'Legacy Area', 'clinical_locations' => [$location->id()]]);

    /** @var Index $index */
    $index = Search::index('default');
    /** @var Searchables $searchables */
    $searchables = $index->searchables();
    /** @var Collection<int, string> $refs */
    $refs = $searchables->all();

    /** @var string $cancerId */
    $cancerId = $cancer->id();
    /** @var string $legacyId */
    $legacyId = $legacy->id();
    /** @var string $emptyId */
    $emptyId = $empty->id();
    /** @var string $hiddenId */
    $hiddenId = $hidden->id();

    // A populated section of a searchable AoE (explicit-true default and missing-key) is indexed.
    expect($refs)->toContain('clinical_expertise_section::'.$cancerId.'::locations')
        ->toContain('clinical_expertise_section::'.$legacyId.'::locations');

    // The empty AoE and the `searchable: false` AoE contribute no section documents.
    $sectionRefs = collect($refs)->filter(fn ($ref): bool => str_starts_with((string) $ref, 'clinical_expertise_section::'));
    expect($sectionRefs->contains(fn ($ref): bool => str_contains((string) $ref, $emptyId)))->toBeFalse()
        ->and($sectionRefs->contains(fn ($ref): bool => str_contains((string) $ref, $hiddenId)))->toBeFalse();
});

it('rebuilds a document from its key with the subpage URL and title, and drops a stale key', function (): void {
    $location = makeLocation();
    $cancer = makeAoe('cancer-care', ['title' => 'Cancer Care', 'clinical_locations' => [$location->id()]]);

    $provider = app(AreaOfExpertiseSectionProvider::class);

    /** @var string $cancerId */
    $cancerId = $cancer->id();

    $docs = $provider->find([$cancerId.'::locations']);
    expect($docs)->toHaveCount(1);

    /** @var AreaOfExpertiseSectionDocument $doc */
    $doc = $docs->first();
    expect($doc->getSearchValue('title'))->toBe('Cancer Care — Related Clinical Locations')
        ->and($doc->getSearchValue('url'))->toBe('/cancer-care/locations');

    // An unpopulated section (providers) and a deleted entry both rebuild to nothing, so a
    // stale reference left in the index renders no result.
    expect($provider->find([$cancerId.'::providers']))->toBeEmpty()
        ->and($provider->find(['00000000-0000-0000-0000-000000000000::locations']))->toBeEmpty();
});

it('types the result and keeps a published, current-site subpage past the tag filters', function (): void {
    $doc = new AreaOfExpertiseSectionDocument('id-1', 'locations', 'Cancer Care — Related Clinical Locations', '/cancer-care/locations', 'default');

    // The reference-prefix type is what the results listing dispatches on.
    expect($doc->toSearchResult()->getType())->toBe('clinical_expertise_section')
        // Survives the tag's status/site `where` filters (matching PlainResult's defaults).
        ->and($doc->getQueryableValue('status'))->toBe('published')
        ->and($doc->getQueryableValue('site'))->toBe('default')
        // Any other queried field falls through to the augmented data (defensive completeness).
        ->and($doc->getQueryableValue('title'))->toBe('Cancer Care — Related Clinical Locations');
});

it('dispatches AoE-section hits by result_type to the escaping section card', function (): void {
    // The listing renders each hit through the `_result` partial, which holds the dispatch.
    $listing = File::get(resource_path('views/search/_results.antlers.html'));
    $result = File::get(resource_path('views/search/_result.antlers.html'));

    expect($listing)->toContain('partial:search/result')
        ->and($result)
        ->toContain("result_type == 'clinical_expertise_section'")
        ->toContain('partial:search/cards/clinical_expertise_section');
});

it('renders the section card as a link to the subpage URL, escaping the title', function (): void {
    // A hostile AoE title (composed into the document title) must be escaped — Antlers output
    // is raw by default.
    $doc = new AreaOfExpertiseSectionDocument('id-1', 'locations', '<b>Cancer</b> — Related Clinical Locations', '/cancer-care/locations', 'default');

    $card = File::get(resource_path('views/search/cards/_clinical_expertise_section.antlers.html'));
    $out = (string) Antlers::parse($card, $doc->augmentedArrayData(), true);

    expect($out)->toContain('href="/cancer-care/locations"')
        ->and($out)->not->toContain('<b>Cancer</b>')
        ->and($out)->toContain('Cancer');
});
