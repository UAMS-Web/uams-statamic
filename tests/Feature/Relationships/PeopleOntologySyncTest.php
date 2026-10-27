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

uses(CollectionTreeIsolatedTestCase::class);

/*
|--------------------------------------------------------------------------
| Family 1 (UAMS-Web/uams-statamic-archive#1360 / UAMS-Web/uams-statamic-archive#1361) — people ↔ clinical-ontology stored sync
|--------------------------------------------------------------------------
|
| Relating a provider (a `people` entry) to a condition / service / expertise /
| location / resource writes the inverse "Related Clinical Providers" edge onto
| that ontology entry, and clearing it removes the inverse. The provider-side
| handle is `clinical_providers` on every ontology collection except
| clinical_locations (`physician_locations`).
|
| The expertise case asserts the one CORRECTED handle from the UAMS-Web/uams-statamic-archive#1360 table:
| people.expertise ↔ clinical_expertise.clinical_providers (the table's
| `clinical_expertise.physician_expertise` does not exist and would no-op).
|
| These exercise the real Stache (clinical_expertise is structured), so they are
| serial + self-cleaning and skip when content/ is absent. The provider-eligibility
| CP-selection scope from UAMS-Web/uams-statamic-archive#1361's AC is NOT covered here — see the issue: it is
| deferred to coordinate with UAMS-Web/uams-statamic-archive#919's (still-open) searchable eligibility pattern.
*/

const OG1361_PREFIX = 'zz-og1361-';

function og1361ExpertiseTreePath(): string
{
    /** @var Store $store */
    $store = Stache::store('collection-trees');

    return $store->directory().'health/clinical_expertise.yaml';
}

function og1361TearDown(): void
{
    foreach (['people', 'clinical_conditions', 'clinical_expertise'] as $handle) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $handle);

        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();

        $entries
            ->filter(function (Statamic\Entries\Entry $entry): bool {
                /** @var string|null $slug */
                $slug = $entry->slug();

                return str_starts_with((string) $slug, OG1361_PREFIX);
            })
            ->each(fn (Statamic\Entries\Entry $entry) => $entry->delete());
    }

    if (file_exists(og1361ExpertiseTreePath())) {
        @unlink(og1361ExpertiseTreePath());
    }
}

/**
 * @param  array<string, mixed>  $data
 */
function og1361MakePerson(string $suffix, array $data = []): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->blueprint('person');
    $entry->locale('default');
    $entry->slug(OG1361_PREFIX.$suffix);
    $entry->data(array_merge(['title' => 'Person '.$suffix], $data));
    $entry->save();

    return $entry;
}

beforeEach(function () {
    if (! Collection::find('people') || ! Collection::find('clinical_conditions') || ! Collection::find('clinical_expertise')) {
        ContentSubmodule::reportMissing('people + clinical ontology collections');
    }

    og1361TearDown();

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

afterEach(fn () => og1361TearDown());

describe('people ↔ ontology sync (#1361)', function () {
    it('writes a provider onto a condition when the person authors the edge', function () {
        /** @var Statamic\Entries\Entry $condition */
        $condition = Entry::make();
        $condition->collection('clinical_conditions');
        $condition->locale('health');
        $condition->slug(OG1361_PREFIX.'afib');
        $condition->data(['title' => 'Atrial Fibrillation']);
        $condition->save();

        $person = og1361MakePerson('doc', ['conditions' => [$condition->id()]]);

        /** @var Statamic\Entries\Entry $storedCondition */
        $storedCondition = Entry::find($condition->id());
        expect($storedCondition->get('clinical_providers'))->toContain($person->id());
    })->group('serial');

    it('writes a provider onto an expertise via the corrected clinical_providers handle', function () {
        // Published AoE entries need a controlled certifying-org anchor (UAMS-Web/uams-statamic-archive#1066); inert to the sync under test.
        /** @var Statamic\Entries\Entry $specialty */
        $specialty = Entry::make();
        $specialty->collection('clinical_expertise');
        $specialty->blueprint('clinical_expertise');
        $specialty->locale('health');
        $specialty->slug(OG1361_PREFIX.'cardiology');
        $specialty->data(['title' => 'Cardiology', 'clinical_specializations' => ['207RC0000X']]);
        $specialty->save();

        $person = og1361MakePerson('doc', ['expertise' => [$specialty->id()]]);

        /** @var Statamic\Entries\Entry $storedSpecialty */
        $storedSpecialty = Entry::find($specialty->id());
        expect($storedSpecialty->get('clinical_providers'))->toContain($person->id());
    })->group('serial');

    it('removes the reciprocal when the person clears the edge', function () {
        /** @var Statamic\Entries\Entry $condition */
        $condition = Entry::make();
        $condition->collection('clinical_conditions');
        $condition->locale('health');
        $condition->slug(OG1361_PREFIX.'afib');
        $condition->data(['title' => 'Atrial Fibrillation']);
        $condition->save();

        $person = og1361MakePerson('doc', ['conditions' => [$condition->id()]]);

        /** @var Statamic\Entries\Entry $storedCondition */
        $storedCondition = Entry::find($condition->id());
        expect($storedCondition->get('clinical_providers'))->toContain($person->id());

        $person->set('conditions', []);
        $person->save();

        /** @var Statamic\Entries\Entry $refreshedCondition */
        $refreshedCondition = Entry::find($condition->id());
        expect($refreshedCondition->get('clinical_providers') ?? [])->not->toContain($person->id());
    })->group('serial');
});
