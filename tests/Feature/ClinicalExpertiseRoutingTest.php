<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Facades\YAML;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\Store;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\CollectionTreeIsolatedTestCase;
use Tests\Support\ContentSubmodule;

uses(CollectionTreeIsolatedTestCase::class);

/*
|--------------------------------------------------------------------------
| clinical_expertise routing foundation (UAMS-Web/uams-statamic-archive#1054, D1 + D5)
|--------------------------------------------------------------------------
|
| The whole collection mounts to one dedicated `pages` landing entry
| (/health/expertise) and uses the depth-gated native-mount route
| `{{ depth == 1 ?= mount }}/{{ parent_uri }}/{{ slug }}` so the mount path
| appears exactly once at every depth. This is the build's first native-{mount}
| consumer, so the route shape is pinned here. Because the mount is native, an
| entry's URI derives live from the landing's URI via the `{{ mount }}` route
| token, so renaming the landing propagates to the whole subtree automatically —
| no refresh listener is needed (unlike the knowledge_bases custom mount). That
| live derivation is what the depth-gate case proves.
|
| The depth-gate cannot be proven without real structure, so the URL cases
| create clinical_expertise entries + a tree against the real Stache and tear
| them down (serial, self-cleaning). They never re-save the committed landing
| entry — saving it would rewrite a committed content file.
| Config/search cases are hermetic.
*/

const EXPERTISE_MOUNT_LANDING_ID = '79295ce3-cdca-4463-8dac-74d04571f104';
const EXPERTISE_TEST_SLUG_PREFIX = 'zz-expertise-test-';

/** Absolute path to the clinical_expertise health structure tree (test-authored, torn down). */
function expertiseHealthTreePath(): string
{
    /** @var Store $store */
    $store = Stache::store('collection-trees');
    /** @var string $directory */
    $directory = $store->directory();

    return $directory.'health/clinical_expertise.yaml';
}

/** Remove every test expertise entry and the test-authored structure tree. */
function tearDownExpertiseFixtures(): void
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_expertise');

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();
    $entries
        ->filter(function (Statamic\Entries\Entry $e): bool {
            /** @var string|null $slug */
            $slug = $e->slug();

            return str_starts_with((string) $slug, EXPERTISE_TEST_SLUG_PREFIX);
        })
        ->each(fn (Statamic\Entries\Entry $e) => $e->delete());

    if (file_exists(expertiseHealthTreePath())) {
        @unlink(expertiseHealthTreePath());
    }
}

/** Make (and persist) an expertise entry so it is Stache-findable by id. */
function makeExpertiseEntry(string $slugSuffix, string $title): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_expertise');
    $entry->blueprint('clinical_expertise');
    $entry->locale('health');
    $entry->slug(EXPERTISE_TEST_SLUG_PREFIX.$slugSuffix);
    // Published AoE entries need a controlled certifying-org anchor (UAMS-Web/uams-statamic-archive#1066); inert to routing.
    $entry->data(['title' => $title, 'clinical_specializations' => ['207RC0000X']]);
    $entry->save();

    return $entry;
}

/**
 * Set the clinical_expertise health structure tree.
 *
 * @param  array<int, array<string, mixed>>  $tree
 */
function setExpertiseTree(array $tree): void
{
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('clinical_expertise');
    /** @var CollectionStructure $structure */
    $structure = $collection->structure();
    /** @var Tree $structureTree */
    $structureTree = $structure->in('health');
    $structureTree->tree($tree);
    $structureTree->save();
}

beforeEach(function () {
    if (! Entry::find(EXPERTISE_MOUNT_LANDING_ID)) {
        ContentSubmodule::reportMissing('the expertise mount landing entry');
    }
    tearDownExpertiseFixtures();
});

afterEach(fn () => tearDownExpertiseFixtures());

it('declares the depth-gated native-mount route and mount on the collection', function () {
    /** @var Store $collectionsStore */
    $collectionsStore = Stache::store('collections');
    /** @var string $collectionsDir */
    $collectionsDir = $collectionsStore->directory();

    $config = YAML::parse(
        File::get(
            $collectionsDir.'clinical_expertise.yaml'
        )
    );

    expect($config['route'])->toBe('{{ depth == 1 ?= mount }}/{{ parent_uri }}/{{ slug }}');
    expect($config['mount'])->toBe(EXPERTISE_MOUNT_LANDING_ID);
    expect($config['structure'])->toBe(['root' => false]); // max_depth removed
});

it('resolves the mount landing entry at /health/expertise', function () {
    /** @var Statamic\Entries\Entry $landing */
    $landing = Entry::find(EXPERTISE_MOUNT_LANDING_ID);

    // uri() is site-relative — the depth-gate route builds on this; the health
    // site prefixes /health only in url()/absoluteUrl().
    expect($landing->uri())->toBe('/expertise');
    expect($landing->url())->toBe('/health/expertise');
});

it('emits the mount path exactly once across depths (root, child, grandchild)', function () {
    $root = makeExpertiseEntry('cardiology', 'Cardiology');
    $child = makeExpertiseEntry('interventional', 'Interventional Cardiology');
    $grand = makeExpertiseEntry('structural', 'Structural Heart');

    setExpertiseTree([
        [
            'entry' => $root->id(),
            'children' => [
                [
                    'entry' => $child->id(),
                    'children' => [
                        ['entry' => $grand->id()],
                    ],
                ],
            ],
        ],
    ]);

    // Assert the public url() (site prefix + the depth-gated route output).
    $base = '/health/expertise';

    /** @var Statamic\Entries\Entry $foundRoot */
    $foundRoot = Entry::find($root->id());
    /** @var Statamic\Entries\Entry $foundChild */
    $foundChild = Entry::find($child->id());
    /** @var Statamic\Entries\Entry $foundGrand */
    $foundGrand = Entry::find($grand->id());

    expect($foundRoot->url())->toBe("{$base}/".EXPERTISE_TEST_SLUG_PREFIX.'cardiology');
    expect($foundChild->url())->toBe("{$base}/".EXPERTISE_TEST_SLUG_PREFIX.'cardiology/'.EXPERTISE_TEST_SLUG_PREFIX.'interventional');
    expect($foundGrand->url())->toBe("{$base}/".EXPERTISE_TEST_SLUG_PREFIX.'cardiology/'.EXPERTISE_TEST_SLUG_PREFIX.'interventional/'.EXPERTISE_TEST_SLUG_PREFIX.'structural');

    // The mount path segment (`expertise`) appears exactly once even at depth 3.
    /** @var string $grandUrl */
    $grandUrl = $foundGrand->url();
    expect(substr_count($grandUrl, '/expertise/'))->toBe(1);
})->group('serial');

it('indexes clinical_expertise by the singular alternate_name field, aligned with the blueprint handle', function () {
    /** @var array{fields: array<int, string>, transformers: array<string, mixed>} $index */
    $index = config('statamic.search.indexes.clinical_expertise');

    expect($index['fields'])->toContain('alternate_name');
    expect($index['fields'])->not->toContain('alternate_names');
    expect(array_keys($index['transformers']))->toContain('alternate_name');

    // Guard the alignment the index depends on: the blueprint's field handle is singular too.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/clinical_expertise')->get('clinical_expertise');
    expect($blueprint->hasField('alternate_name'))->toBeTrue();
    expect($blueprint->hasField('alternate_names'))->toBeFalse();
});
