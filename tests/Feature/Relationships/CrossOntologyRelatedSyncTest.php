<?php

declare(strict_types=1);

use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\Store;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\CollectionTreeIsolatedTestCase;
use Tests\Support\ContentSubmodule;

uses(CollectionTreeIsolatedTestCase::class)->group('serial');

/*
|--------------------------------------------------------------------------
| Family 2 (UAMS-Web/uams-statamic-archive#1360 / UAMS-Web/uams-statamic-archive#1362) — cross-ontology "related" stored bidirectional sync
|--------------------------------------------------------------------------
|
| stillat/relationships keeps both sides of each "Related X" edge in sync: adding
| a related entry on one side writes the inverse on the other, clearing it removes
| the inverse, and a symmetric self-relationship propagates both ways.
|
| These assert the CORRECTED clinical_expertise-side handles from the UAMS-Web/uams-statamic-archive#1360 table
| (the post-renormalization `clinical_*`-prefixed handles — NOT the fieldset's bare
| `conditions`/`treatments`/`related_locations`/`associated`, which do not exist on
| that blueprint and would silently no-op). A stale mapping passes `relate:list` but
| never syncs, so exercising each corrected handle is the load-bearing verification —
| all four (`clinical_conditions`, `clinical_services`, `clinical_locations`, and the
| `clinical_expertise_related` self) have a write test here.
|
| clinical_expertise and clinical_locations are structured, health-scoped collections,
| so these exercise the real Stache: serial + self-cleaning, skipped when content/ is
| absent. The knowledge_bases.related_articles self-relationship uses the same
| manyToMany(same, same) shape but on a large multi-site, multi-blueprint collection
| (related_articles lives only on the `article` blueprint); its dedicated fixture is
| deferred/tracked, not asserted here.
*/

const OG1362_PREFIX = 'zz-og1362-';

function og1362TreePath(string $collection = 'clinical_expertise'): string
{
    /** @var Store $store */
    $store = Stache::store('collection-trees');

    return $store->directory()."health/{$collection}.yaml";
}

function og1362TearDown(): void
{
    foreach (['clinical_expertise', 'clinical_conditions', 'clinical_services', 'clinical_locations'] as $handle) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $handle);

        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();
        $entries->filter(function ($entry): bool {
            /** @var string|null $slug */
            $slug = $entry->slug();

            return str_starts_with((string) $slug, OG1362_PREFIX);
        })->each(fn ($entry) => $entry->delete());
    }

    foreach (['clinical_expertise', 'clinical_locations'] as $structured) {
        if (file_exists(og1362TreePath($structured))) {
            @unlink(og1362TreePath($structured));
        }
    }
}

function og1362MakeCondition(string $suffix, string $title): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_conditions');
    $entry->locale('health');
    $entry->slug(OG1362_PREFIX.$suffix);
    $entry->data(['title' => $title]);
    $entry->save();

    return $entry;
}

/**
 * @param  array<string, mixed>  $data
 */
function og1362MakeSpecialty(string $suffix, string $title, array $data = []): Statamic\Entries\Entry
{
    // A published Area of Expertise must carry a controlled certifying-org anchor
    // (UAMS-Web/uams-statamic-archive#1066 ValidateClinicalExpertiseEntry); these fixtures publish by default, so
    // seed one — the value is inert to the relationship sync under test.
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_expertise');
    $entry->blueprint('clinical_expertise');
    $entry->locale('health');
    $entry->slug(OG1362_PREFIX.$suffix);
    $entry->data(array_merge(['title' => $title, 'clinical_specializations' => ['207RC0000X']], $data));
    $entry->save();

    return $entry;
}

function og1362MakeService(string $suffix, string $title): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_services');
    $entry->locale('health');
    $entry->slug(OG1362_PREFIX.$suffix);
    $entry->data(['title' => $title]);
    $entry->save();

    return $entry;
}

function og1362MakeLocation(string $suffix, string $title): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_locations');
    $entry->blueprint('location');
    $entry->locale('health');
    $entry->slug(OG1362_PREFIX.$suffix);
    $entry->data(['title' => $title]);
    $entry->save();

    return $entry;
}

beforeEach(function () {
    foreach (['clinical_expertise', 'clinical_conditions', 'clinical_services', 'clinical_locations'] as $handle) {
        if (! Collection::find($handle)) {
            ContentSubmodule::reportMissing('clinical ontology collections');
        }
    }

    og1362TearDown();

    // A non-null health tree must exist before saving these structured collections' entries.
    foreach (['clinical_expertise', 'clinical_locations'] as $structured) {
        /** @var Statamic\Entries\Collection $collection */
        $collection = Collection::findByHandle($structured);
        /** @var CollectionStructure $structure */
        $structure = $collection->structure();
        /** @var Tree $tree */
        $tree = $structure->in('health');
        $tree->tree([]);
        $tree->save();
    }
});

afterEach(fn () => og1362TearDown());

describe('cross-ontology related sync (#1362)', function () {
    it('writes the specialty onto a condition when the specialty authors the edge', function () {
        $condition = og1362MakeCondition('afib', 'Atrial Fibrillation');
        $specialty = og1362MakeSpecialty('cardiology', 'Cardiology', [
            'clinical_conditions' => [$condition->id()],
        ]);

        // The addon writes the reciprocal onto the condition's clinical_expertise field.
        /** @var Statamic\Entries\Entry $reloaded */
        $reloaded = Entry::find($condition->id());
        /** @var array<int, string>|null $reciprocal */
        $reciprocal = $reloaded->get('clinical_expertise');
        expect($reciprocal)->toContain($specialty->id());
    });

    it('removes the reciprocal when the authored edge is cleared', function () {
        $condition = og1362MakeCondition('afib', 'Atrial Fibrillation');
        $specialty = og1362MakeSpecialty('cardiology', 'Cardiology', [
            'clinical_conditions' => [$condition->id()],
        ]);
        /** @var Statamic\Entries\Entry $reloaded */
        $reloaded = Entry::find($condition->id());
        /** @var array<int, string>|null $reciprocal */
        $reciprocal = $reloaded->get('clinical_expertise');
        expect($reciprocal)->toContain($specialty->id());

        $specialty->set('clinical_conditions', []);
        $specialty->save();

        /** @var Statamic\Entries\Entry $reloadedAfterClear */
        $reloadedAfterClear = Entry::find($condition->id());
        /** @var array<int, string>|null $reciprocalAfterClear */
        $reciprocalAfterClear = $reloadedAfterClear->get('clinical_expertise');
        expect($reciprocalAfterClear ?? [])->not->toContain($specialty->id());
    });

    it('propagates the symmetric clinical_expertise_related self-relationship both ways', function () {
        $a = og1362MakeSpecialty('a', 'Specialty A');
        $b = og1362MakeSpecialty('b', 'Specialty B');

        $a->set('clinical_expertise_related', [$b->id()]);
        $a->save();

        /** @var Statamic\Entries\Entry $reloaded */
        $reloaded = Entry::find($b->id());
        /** @var array<int, string>|null $reciprocal */
        $reciprocal = $reloaded->get('clinical_expertise_related');
        expect($reciprocal)->toContain($a->id());
    });

    it('writes the specialty onto a service via the corrected clinical_services handle', function () {
        $service = og1362MakeService('ablation', 'Cardiac Ablation');
        $specialty = og1362MakeSpecialty('cardiology', 'Cardiology', [
            'clinical_services' => [$service->id()],
        ]);

        /** @var Statamic\Entries\Entry $reloaded */
        $reloaded = Entry::find($service->id());
        /** @var array<int, string>|null $reciprocal */
        $reciprocal = $reloaded->get('clinical_expertise');
        expect($reciprocal)->toContain($specialty->id());
    });

    it('writes the specialty onto a location via the corrected clinical_locations handle', function () {
        $location = og1362MakeLocation('clinic', 'Heart Clinic');
        $specialty = og1362MakeSpecialty('cardiology', 'Cardiology', [
            'clinical_locations' => [$location->id()],
        ]);

        // The location-side reciprocal handle is `expertise` (not clinical_*-prefixed).
        /** @var Statamic\Entries\Entry $reloaded */
        $reloaded = Entry::find($location->id());
        /** @var array<int, string>|null $reciprocal */
        $reciprocal = $reloaded->get('expertise');
        expect($reciprocal)->toContain($specialty->id());
    });

    it('strips the reciprocal when an authoring entry is deleted (addon delete-cleanup)', function () {
        $condition = og1362MakeCondition('afib', 'Atrial Fibrillation');
        $specialty = og1362MakeSpecialty('cardiology', 'Cardiology', [
            'clinical_conditions' => [$condition->id()],
        ]);
        /** @var Statamic\Entries\Entry $reloaded */
        $reloaded = Entry::find($condition->id());
        /** @var array<int, string>|null $reciprocal */
        $reciprocal = $reloaded->get('clinical_expertise');
        expect($reciprocal)->toContain($specialty->id());

        $specialty->delete();

        /** @var Statamic\Entries\Entry $reloadedAfterDelete */
        $reloadedAfterDelete = Entry::find($condition->id());
        /** @var array<int, string>|null $reciprocalAfterDelete */
        $reciprocalAfterDelete = $reloadedAfterDelete->get('clinical_expertise');
        expect($reciprocalAfterDelete ?? [])->not->toContain($specialty->id());
    });
});
