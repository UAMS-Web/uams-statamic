<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\ContentSubmodule;
use Tests\Support\EntriesTermsAndTreesIsolatedTestCase;
use Tests\TestCase;

// Entries, terms and trees isolated (#557): each case writes a collection's structure
// tree, and a tree write needs a root no sibling worker shares.
uses(EntriesTermsAndTreesIsolatedTestCase::class);

/*
|--------------------------------------------------------------------------
| Clinical content pages render (#1235)
|--------------------------------------------------------------------------
|
| The `basic_page` and `landing_page` blueprints in `clinical_expertise` and
| `clinical_locations` store their template default on every entry saved in the
| control panel. Each default named a view under the collection's own folder that
| did not exist, so every such page returned 500. These cases save a parent and
| one content page per blueprint on `health`, give the page the template its
| blueprint defaults to, place both in the tree, and request each URL. A default
| that names a missing view fails here with the view's name.
|
| @command php artisan test --compact tests/Feature/ClinicalContentPageRenderTest.php
*/

/** Slug prefix for self-provisioned entries, so teardown is bulletproof. */
const CLINICAL_CONTENT_PAGE_TEST_PREFIX = 'zz-clinical-content-page-test-';

/**
 * Marker text in a basic page's body, to show its `content` reaches the page. One
 * word, because the widow filter joins a paragraph's last two words with `&nbsp;`.
 */
const CLINICAL_CONTENT_PAGE_BODY_MARKER = 'ClinicalContentPageBodyMarker';

/**
 * Each clinical collection, with the blueprint its content pages nest under and
 * the collection template that parent renders through.
 *
 * @var array<string, array{parent: string, template: string}>
 */
const CLINICAL_CONTENT_PAGE_COLLECTIONS = [
    'clinical_expertise' => ['parent' => 'clinical_expertise', 'template' => 'clinical_expertise/show'],
    'clinical_locations' => ['parent' => 'location', 'template' => 'clinical_locations/show'],
];

/**
 * Remove every self-provisioned test entry.
 *
 * Through the repository rather than `$entry->delete()`: Statamic removes an entry
 * from its tree and saves the tree before it deletes the entry, so the structure
 * still finds the entry, re-adds it at the top level, and the tree validator
 * refuses a top-level content page. Each test's tree is isolated, so nothing
 * outlives the test, and no event fires, so no search index is updated.
 */
function tearDownClinicalContentPageFixtures(): void
{
    foreach (array_keys(CLINICAL_CONTENT_PAGE_COLLECTIONS) as $handle) {
        // Content-less checkouts have neither collection.
        if (! CollectionFacade::find($handle)) {
            continue;
        }

        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $handle);

        /** @var Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();

        $entries
            ->filter(fn (Statamic\Entries\Entry $entry): bool => is_string($entry->slug()) && str_starts_with($entry->slug(), CLINICAL_CONTENT_PAGE_TEST_PREFIX))
            ->each(fn (Statamic\Entries\Entry $entry) => Entry::delete($entry));
    }
}

/**
 * The template a blueprint stores on a new entry.
 *
 * @param  string  $collection  The collection handle.
 * @param  string  $blueprint  The blueprint handle.
 */
function clinicalContentPageTemplateDefault(string $collection, string $blueprint): string
{
    /** @var Statamic\Fields\Blueprint $resolved */
    $resolved = Blueprint::in("collections/{$collection}")->get($blueprint);
    /** @var Field $template */
    $template = $resolved->field('template');
    $default = $template->defaultValue();

    expect($default)->toBeString()->not->toBe('');

    /** @var string $default */
    return $default;
}

/**
 * Save a published entry on `health` in the given collection and blueprint.
 *
 * @param  string  $collection  The collection handle.
 * @param  string  $blueprint  The blueprint handle.
 * @param  array<string, mixed>  $data  The entry data.
 */
function makeClinicalContentPageEntry(string $collection, string $blueprint, array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->locale('health');
    $entry->blueprint($blueprint);
    $entry->slug(CLINICAL_CONTENT_PAGE_TEST_PREFIX.$blueprint);
    $entry->data($data);
    $entry->saveQuietly();

    return $entry;
}

/**
 * The URL an entry is requested at, which a placed entry always has.
 *
 * @param  Statamic\Entries\Entry  $entry  The entry.
 */
function clinicalContentPageUrl(Statamic\Entries\Entry $entry): string
{
    $url = $entry->url();

    expect($url)->toBeString()->not->toBe('');

    /** @var string $url */
    return $url;
}

beforeEach(fn () => tearDownClinicalContentPageFixtures());
afterEach(fn () => tearDownClinicalContentPageFixtures());

it('renders each clinical content page through the template its blueprint defaults to', function (string $collection, string $blueprint): void {
    if (! CollectionFacade::find($collection)) {
        ContentSubmodule::reportMissing("{$collection} collection config");
    }

    $parentBlueprint = CLINICAL_CONTENT_PAGE_COLLECTIONS[$collection]['parent'];
    $template = clinicalContentPageTemplateDefault($collection, $blueprint);

    $parent = makeClinicalContentPageEntry($collection, $parentBlueprint, ['title' => 'ZZ Clinical Parent']);
    $page = makeClinicalContentPageEntry($collection, $blueprint, [
        'title' => 'ZZ Clinical '.$blueprint,
        'template' => $template,
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => CLINICAL_CONTENT_PAGE_BODY_MARKER]]]],
    ]);

    // Content pages cannot be top-level, so the page goes under the parent.
    /** @var Statamic\Entries\Collection $statamicCollection */
    $statamicCollection = CollectionFacade::findByHandle($collection);
    /** @var CollectionStructure $structure */
    $structure = $statamicCollection->structure();
    /** @var Tree $tree */
    $tree = $structure->in('health');
    $tree->tree([['entry' => $parent->id(), 'children' => [['entry' => $page->id()]]]]);
    $tree->save();

    /** @var Statamic\Entries\Entry $reread */
    $reread = Entry::find($page->id());

    expect($reread->template())->toBe($template);

    /** @var TestCase $test */
    $test = $this;
    $response = $test->get(clinicalContentPageUrl($reread));

    // A missing view is a 500 naming it; say so rather than only "expected 200".
    expect($response->exception?->getMessage())->toBeNull()
        ->and($response->status())->toBe(200)
        ->and((string) $response->getContent())->toContain('ZZ Clinical '.$blueprint);

    // A basic page's own body reaches the page, not only its title.
    if ($blueprint === 'basic_page') {
        expect((string) $response->getContent())->toContain(CLINICAL_CONTENT_PAGE_BODY_MARKER);
    }
})->with([
    ['clinical_expertise', 'basic_page'],
    ['clinical_expertise', 'landing_page'],
    ['clinical_locations', 'basic_page'],
    ['clinical_locations', 'landing_page'],
]);

it('renders the parent entry through its collection template', function (string $collection): void {
    if (! CollectionFacade::find($collection)) {
        ContentSubmodule::reportMissing("{$collection} collection config");
    }

    $parent = makeClinicalContentPageEntry($collection, CLINICAL_CONTENT_PAGE_COLLECTIONS[$collection]['parent'], ['title' => 'ZZ Clinical Parent']);

    /** @var Statamic\Entries\Collection $statamicCollection */
    $statamicCollection = CollectionFacade::findByHandle($collection);
    /** @var CollectionStructure $structure */
    $structure = $statamicCollection->structure();
    /** @var Tree $tree */
    $tree = $structure->in('health');
    $tree->tree([['entry' => $parent->id()]]);
    $tree->save();

    /** @var Statamic\Entries\Entry $reread */
    $reread = Entry::find($parent->id());

    expect($reread->template())->toBe(CLINICAL_CONTENT_PAGE_COLLECTIONS[$collection]['template']);

    /** @var TestCase $test */
    $test = $this;

    $test->get(clinicalContentPageUrl($reread))->assertOk()->assertSee('ZZ Clinical Parent');
})->with(array_keys(CLINICAL_CONTENT_PAGE_COLLECTIONS));
