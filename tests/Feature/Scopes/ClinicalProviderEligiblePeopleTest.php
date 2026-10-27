<?php

declare(strict_types=1);

use App\Scopes\ClinicalProviderEligiblePeople;
use Carbon\Carbon;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Fieldset;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ClinicalProviderFixtures;
use Tests\Support\StacheIsolatedTestCase;

covers(ClinicalProviderEligiblePeople::class);

/**
 * Coverage for the UAMS-Web/uams-statamic-archive#1405 `clinical_provider_eligible_people` query scope: the CP
 * selection-surface complement to UAMS-Web/uams-statamic-archive#919's output filter. It constrains the
 * `uamsweb_common_fad.providers` ("Related Clinical Providers") picker to people
 * eligible for a UAMS Health clinical provider profile, reusing the same
 * `ProviderPeopleListingComputedValues::computeClinicalProviderProfileEligibleQuery()`
 * predicate so the picker and the live directories agree on who is a provider.
 *
 * Real `people` entries are built in an isolated Stache so the scope resolves the
 * eligible id set against real entries exactly as it does when the CP field loads.
 *
 * Mutation: tracked under App\Scopes\ClinicalProviderEligiblePeople (--covered-only).
 *
 * @command php artisan test --compact tests/Feature/Scopes/ClinicalProviderEligiblePeopleTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make('people');
    $collection->title('People');
    $collection->save();
});

/**
 * The minimum people field set where every clinical-provider eligibility base rule
 * passes (mirrors config/clinical_provider_profile.php `base`).
 *
 * @return array<string, mixed>
 */
function s1405EligibleBaseFields(): array
{
    return [
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_employee_subtype' => 'regular',
        'resident_query' => false,
        'fellow_query' => false,
        'clinical_role_query' => true,
        'direct_patient_care_query' => true,
        'clinical_locations' => [ClinicalProviderFixtures::publishedLocationId()],
    ];
}

/**
 * Persist a people entry that is eligible by default; overrides make it ineligible.
 *
 * @param  array<string, mixed>  $fields
 */
function makeS1405Person(string $slug, string $title, array $fields = []): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    /** @var EntryInstance $entry */
    $entry = $entry->collection('people');
    /** @var EntryInstance $entry */
    $entry = $entry->slug($slug);
    /** @var EntryInstance $entry */
    $entry = $entry->published(true);
    /** @var EntryInstance $entry */
    $entry = $entry->data([
        ...s1405EligibleBaseFields(),
        'title' => $title,
        'credentials' => ['md'],
        ...$fields,
    ]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Apply the scope to a fresh people query and return the surviving entry ids.
 *
 * @return array<int, string|null>
 */
function s1405ScopedPeopleIds(): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'people');

    app(ClinicalProviderEligiblePeople::class)->apply($query, []);

    /** @var Illuminate\Support\Collection<int, EntryInstance> $results */
    $results = $query->get();

    return $results->map(function (EntryInstance $entry): ?string {
        /** @var string|null $id */
        $id = $entry->id();

        return $id;
    })->all();
}

it('offers only clinical-provider-eligible people in the picker query', function (): void {
    $mccoy = makeS1405Person('leonard-mccoy', 'Leonard McCoy');
    $chekov = makeS1405Person('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    $ids = s1405ScopedPeopleIds();
    expect($ids)->toContain($mccoy->id());
    expect($ids)->not->toContain($chekov->id());
});

it('keeps only the eligible people from a mixed population', function (): void {
    $mccoy = makeS1405Person('leonard-mccoy', 'Leonard McCoy');
    $chekov = makeS1405Person('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);
    $crusher = makeS1405Person('wesley-crusher', 'Wesley Crusher', ['fellow_query' => true]);

    $ids = s1405ScopedPeopleIds();
    expect($ids)->toContain($mccoy->id());
    expect($ids)->not->toContain($chekov->id());
    expect($ids)->not->toContain($crusher->id());
});

it('still offers an actively-separated eligible provider (eligibility only, not searchable)', function (): void {
    // UAMS-Web/uams-statamic-archive#1405 filters the PICKER on eligibility only; UAMS-Web/uams-statamic-archive#919's output filter — not this
    // scope — hides separated / hidden providers from the public render, so editors
    // can still manage associations for a temporarily-separated provider.
    $sulu = makeS1405Person('hikaru-sulu', 'Hikaru Sulu', [
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);

    expect(s1405ScopedPeopleIds())->toContain($sulu->id());
});

it('offers nothing when no person is eligible', function (): void {
    // An empty eligible set must constrain the picker to nothing, never fall open to
    // every person (the empty-`whereIn` trap).
    makeS1405Person('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);
    makeS1405Person('wesley-crusher', 'Wesley Crusher', ['fellow_query' => true]);

    expect(s1405ScopedPeopleIds())->toBe([]);
});

it('registers under its handle and is wired onto the FaD providers field', function (): void {
    // Statamic auto-discovers the scope from app/Scopes under its snake_case handle...
    expect(app('statamic.scopes')->get('clinical_provider_eligible_people'))
        ->toBe(ClinicalProviderEligiblePeople::class);

    // ...and the uamsweb_common_fad.providers field actually lists that handle.
    /** @var Statamic\Fields\Fieldset $fieldset */
    $fieldset = Fieldset::find('uamsweb_common_fad');
    /** @var array<int, array<string, mixed>> $fields */
    $fields = $fieldset->contents()['fields'];
    /** @var array{field: array<string, mixed>}|null $providers */
    $providers = collect($fields)->firstWhere('handle', 'providers');

    expect($providers['field']['query_scopes'] ?? [])->toContain('clinical_provider_eligible_people');
});
