<?php

declare(strict_types=1);

use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class)->group('serial');

/*
|--------------------------------------------------------------------------
| Family 3 (UAMS-Web/uams-statamic-archive#1360 / UAMS-Web/uams-statamic-archive#1363) — schema-semantic Condition ↔ Service stored sync
|--------------------------------------------------------------------------
|
| Each pair is a Schema.org `inverseOf` claim (verified against the field
| semantics before wiring). Authoring one direction populates the other:
|
|  - condition.schema_possible_treatment ↔ service.schema_treated_medical_condition
|  - condition.schema_has_sign_or_symptom ↔ condition.schema_is_sign_or_symptom_of (directed self)
|  - service.schema_duplicate_therapy (symmetric self)
|
| clinical_conditions + clinical_services are flat health-scoped collections, so
| no tree setup is needed; serial + self-cleaning, skipped when content/ is absent.
| `clinical_services.schema_subtest` is intentionally excluded (one-directional).
*/

const OG1363_PREFIX = 'zz-og1363-';

function og1363TearDown(): void
{
    foreach (['clinical_conditions', 'clinical_services'] as $handle) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $handle);

        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();

        $entries
            ->filter(function (Statamic\Entries\Entry $entry): bool {
                /** @var string|null $slug */
                $slug = $entry->slug();

                return str_starts_with((string) $slug, OG1363_PREFIX);
            })
            ->each(fn (Statamic\Entries\Entry $entry) => $entry->delete());
    }
}

/**
 * @param  array<string, mixed>  $data
 */
function og1363MakeCondition(string $suffix, array $data = []): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_conditions');
    $entry->locale('health');
    $entry->slug(OG1363_PREFIX.$suffix);
    $entry->data(array_merge(['title' => 'Condition '.$suffix], $data));
    $entry->save();

    return $entry;
}

/**
 * @param  array<string, mixed>  $data
 */
function og1363MakeService(string $suffix, array $data = []): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_services');
    $entry->locale('health');
    $entry->slug(OG1363_PREFIX.$suffix);
    $entry->data(array_merge(['title' => 'Service '.$suffix], $data));
    $entry->save();

    return $entry;
}

beforeEach(function () {
    if (! Collection::find('clinical_conditions') || ! Collection::find('clinical_services')) {
        ContentSubmodule::reportMissing('clinical ontology collections');
    }

    og1363TearDown();
});

afterEach(fn () => og1363TearDown());

describe('schema-semantic condition/service sync (#1363)', function () {
    it('mirrors the possible_treatment ↔ treated_medical_condition inverse', function () {
        $service = og1363MakeService('ablation');
        $condition = og1363MakeCondition('afib', [
            'schema_possible_treatment' => [$service->id()],
        ]);

        /** @var Statamic\Entries\Entry $found */
        $found = Entry::find($service->id());

        /** @var array<int, string> $treated */
        $treated = $found->get('schema_treated_medical_condition');

        expect($treated)->toContain($condition->id());
    });

    it('mirrors the directed has_sign_or_symptom / is_sign_or_symptom_of self-inverse', function () {
        $symptom = og1363MakeCondition('palpitations');
        $disease = og1363MakeCondition('afib', [
            'schema_has_sign_or_symptom' => [$symptom->id()],
        ]);

        /** @var Statamic\Entries\Entry $found */
        $found = Entry::find($symptom->id());

        /** @var array<int, string> $signOf */
        $signOf = $found->get('schema_is_sign_or_symptom_of');

        expect($signOf)->toContain($disease->id());
    });

    it('mirrors the symmetric duplicate_therapy self-relationship', function () {
        $a = og1363MakeService('therapy-a');
        $b = og1363MakeService('therapy-b');

        $a->set('schema_duplicate_therapy', [$b->id()]);
        $a->save();

        /** @var Statamic\Entries\Entry $found */
        $found = Entry::find($b->id());

        /** @var array<int, string> $duplicate */
        $duplicate = $found->get('schema_duplicate_therapy');

        expect($duplicate)->toContain($a->id());
    });

    it('removes the semantic reciprocal when the edge is cleared', function () {
        $service = og1363MakeService('ablation');
        $condition = og1363MakeCondition('afib', [
            'schema_possible_treatment' => [$service->id()],
        ]);

        /** @var Statamic\Entries\Entry $found */
        $found = Entry::find($service->id());

        /** @var array<int, string> $treated */
        $treated = $found->get('schema_treated_medical_condition');

        expect($treated)->toContain($condition->id());

        $condition->set('schema_possible_treatment', []);
        $condition->save();

        /** @var Statamic\Entries\Entry $reloaded */
        $reloaded = Entry::find($service->id());

        /** @var array<int, string>|null $treatedAfter */
        $treatedAfter = $reloaded->get('schema_treated_medical_condition');

        expect($treatedAfter ?? [])->not->toContain($condition->id());
    });
});
