<?php

declare(strict_types=1);

use App\Support\Relationships\BoundedRelationshipProcessor;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\Store;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Stillat\Relationships\Comparisons\ComparisonResult;
use Stillat\Relationships\EntryRelationship;
use Stillat\Relationships\Processors\RelationshipProcessor;
use Stillat\Relationships\RelationshipManager;
use Tests\Support\CollectionTreeIsolatedTestCase;
use Tests\Support\ContentSubmodule;

uses(CollectionTreeIsolatedTestCase::class);

/*
|--------------------------------------------------------------------------
| UAMS-Web/uams-statamic-archive#1406 — bound the stillat RelationshipProcessor accumulator
|--------------------------------------------------------------------------
|
| The vendor processor is a singleton that appends every effected entity to
| `$effectedEntries`/`$effectedUsers`/`$effectedTerms` via `+=` and never resets,
| so on the importer + `relate:fill` bulk-save paths the buffer grows for the whole
| run (memory) and serves a stale snapshot when an id cached early is re-touched
| later within one process. `BoundedRelationshipProcessor` resets the per-operation
| working sets before each `processRelationship()` call — bound over the vendor class
| in `StillatRelationshipsServiceProvider::register()`.
|
| The first two tests are hermetic (container + a no-op processor call). The stale-
| snapshot and bounded-buffer tests exercise the real singleton through the live
| clinical-ontology sync, so they run serial against the real Stache and skip when
| the content/ submodule is absent.
*/

it('binds the bounded processor over the vendor RelationshipProcessor', function () {
    // The container resolves the app's bounded subclass... Compared by exact class
    // because Larastan resolves the container binding statically, which makes an
    // instanceof over it an assertion that cannot fail.
    expect(app(RelationshipProcessor::class)::class)->toBe(BoundedRelationshipProcessor::class);

    // ...and the manager's singleton processor IS that bounded instance, so every
    // save + relate:fill path funnels through the reset. Guards against a future
    // vendor change constructing the processor outside the container.
    expect(app(RelationshipManager::class)->processor())->toBeInstanceOf(BoundedRelationshipProcessor::class);
});

it('clears every per-operation working set before processing a relationship', function () {
    $processor = new BoundedRelationshipProcessor;

    // Seed all four working sets as if a prior save / relate:fill item had left
    // entities accumulated on the shared singleton.
    $seed = function (string $property, array $value) use ($processor): void {
        (new ReflectionProperty(RelationshipProcessor::class, $property))->setValue($processor, $value);
    };
    $seed('effectedEntries', ['id-a' => 'entry-a', 'id-b' => 'entry-b']);
    $seed('effectedUsers', ['id-u' => 'user-u']);
    $seed('effectedTerms', ['id-t' => 'term-t']);
    $seed('dependencies', ['dep-1', 'dep-2']);

    expect($processor->workingSetSizes())
        ->toBe(['entries' => 2, 'users' => 1, 'terms' => 1, 'dependencies' => 2]);

    // A no-op relationship (empty comparison → the parent returns after our reset).
    $processor->processRelationship(new EntryRelationship, new ComparisonResult);

    expect($processor->workingSetSizes())
        ->toBe(['entries' => 0, 'users' => 0, 'terms' => 0, 'dependencies' => 0]);
});

const OG1406_PREFIX = 'zz-og1406-';

function og1406TreePath(string $collection = 'clinical_expertise'): string
{
    /** @var Store $store */
    $store = Stache::store('collection-trees');
    /** @var string $directory */
    $directory = $store->directory();

    return $directory."health/{$collection}.yaml";
}

function og1406TearDown(): void
{
    foreach (['clinical_expertise', 'clinical_conditions'] as $handle) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $handle);
        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();
        $entries
            ->filter(function ($entry): bool {
                /** @var string|null $slug */
                $slug = $entry->slug();

                return str_starts_with((string) $slug, OG1406_PREFIX);
            })
            ->each(fn ($entry) => $entry->delete());
    }

    if (file_exists(og1406TreePath())) {
        @unlink(og1406TreePath());
    }
}

function og1406MakeCondition(string $suffix, string $title): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_conditions');
    $entry->locale('health');
    $entry->slug(OG1406_PREFIX.$suffix);
    $entry->data(['title' => $title]);
    $entry->save();

    return $entry;
}

/**
 * @param  array<string, mixed>  $data
 */
function og1406MakeSpecialty(string $suffix, string $title, array $data = []): Statamic\Entries\Entry
{
    // A published Area of Expertise must carry a controlled certifying-org anchor
    // (UAMS-Web/uams-statamic-archive#1066 ValidateClinicalExpertiseEntry); the value is inert to the sync here.
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_expertise');
    $entry->blueprint('clinical_expertise');
    $entry->locale('health');
    $entry->slug(OG1406_PREFIX.$suffix);
    $entry->data(array_merge(['title' => $title, 'clinical_specializations' => ['207RC0000X']], $data));
    $entry->save();

    return $entry;
}

describe('bounded accumulator over the live ontology sync (#1406)', function () {
    beforeEach(function () {
        foreach (['clinical_expertise', 'clinical_conditions'] as $handle) {
            if (! Collection::find($handle)) {
                ContentSubmodule::reportMissing('clinical ontology collections');
            }
        }

        og1406TearDown();

        // A non-null health tree must exist before saving structured clinical_expertise entries.
        /** @var Statamic\Entries\Collection $expertise */
        $expertise = Collection::findByHandle('clinical_expertise');
        /** @var CollectionStructure $structure */
        $structure = $expertise->structure();
        /** @var Tree $tree */
        $tree = $structure->in('health');
        $tree->tree([]);
        $tree->save();
    });

    afterEach(fn () => og1406TearDown());

    it('does not clobber an intervening direct edit to a shared reciprocal target', function () {
        // Two specialties relate to ONE shared condition. Between the two reciprocal
        // touches, the condition is edited directly. The buggy `+=` cache reuses the
        // first specialty's stale snapshot of the condition and re-saves it on the
        // second touch, reverting the edit; the bounded processor re-fetches fresh.
        $condition = og1406MakeCondition('shared', 'Original Title');

        $first = og1406MakeSpecialty('first', 'First Specialty', [
            'clinical_conditions' => [$condition->id()],
        ]);
        /** @var Statamic\Entries\Entry $conditionAfterFirst */
        $conditionAfterFirst = Entry::find($condition->id());
        expect($conditionAfterFirst->get('clinical_expertise'))->toContain($first->id());

        // Direct edit to a field the reciprocal sync does not touch.
        /** @var Statamic\Entries\Entry $conditionToEdit */
        $conditionToEdit = Entry::find($condition->id());
        $conditionToEdit->set('title', 'Renamed Title');
        $conditionToEdit->save();

        $second = og1406MakeSpecialty('second', 'Second Specialty', [
            'clinical_conditions' => [$condition->id()],
        ]);

        /** @var Statamic\Entries\Entry $fresh */
        $fresh = Entry::find($condition->id());
        // Both reciprocals land...
        expect($fresh->get('clinical_expertise'))
            ->toContain($first->id())
            ->toContain($second->id());
        // ...and the intervening edit survives (no stale-snapshot clobber).
        expect($fresh->get('title'))->toBe('Renamed Title');
    })->group('serial');

    it('does not accumulate effected entries across successive saves', function () {
        /** @var BoundedRelationshipProcessor $processor */
        $processor = app(RelationshipManager::class)->processor();

        // Each specialty relates to its OWN distinct condition. With the unbounded
        // vendor buffer the effected-entry set grows by one every save; bounded, it
        // never carries a prior save's condition.
        $sizes = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $suffix) {
            $condition = og1406MakeCondition($suffix, "Condition {$suffix}");
            og1406MakeSpecialty("sp-{$suffix}", "Specialty {$suffix}", [
                'clinical_conditions' => [$condition->id()],
            ]);
            $sizes[] = $processor->workingSetSizes()['entries'];
        }

        // Unbounded would climb to 5 (one per save); bounded stays at the single
        // relationship's effected set, never the running total.
        expect(max($sizes))->toBeLessThanOrEqual(2);
    })->group('serial');
});
