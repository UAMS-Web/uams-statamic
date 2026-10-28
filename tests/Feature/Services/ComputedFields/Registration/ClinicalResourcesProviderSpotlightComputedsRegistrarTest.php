<?php

declare(strict_types=1);

use App\Services\ComputedFields\ProviderSpotlight\ProviderSpotlightComputedValues;
use App\Services\ComputedFields\Registration\ClinicalResourcesProviderSpotlightComputedsRegistrar;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Assets\Asset;
use Statamic\Entries\Entry;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

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

const SPOTLIGHT_REGISTRAR_HANDLES = [
    'spotlight_auto_introduction',
    'spotlight_auto_excerpt',
    'spotlight_featured_image',
    'spotlight_provider_name',
    'spotlight_provider_title',
    'spotlight_bookable',
    'spotlight_referral_required',
    'spotlight_appointment_phone_display',
    'spotlight_appointment_phone_href',
];

/**
 * A `clinical_resources` entry mock on the given blueprint, featuring the
 * provider `prov-kirk` and carrying an appointment phone.
 */
function spotlightRegistrarEntry(?Blueprint $blueprint): Entry
{
    $values = ['featured_provider' => ['prov-kirk'], 'spotlight_phone' => '501-555-0100'];

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('id')->andReturn('spotlight-'.($blueprint?->handle() ?? 'none'));
    $entry->shouldReceive('get')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    return $entry;
}

/**
 * A blueprint carrying only the given handle.
 */
function spotlightRegistrarBlueprint(string $handle): Blueprint
{
    $blueprint = new Blueprint;
    $blueprint->setHandle($handle);

    return $blueprint;
}

/**
 * Make `prov-kirk` resolvable: a named, credentialed, bookable provider who
 * needs a referral, with a cardiology specialization and a headshot asset.
 */
function spotlightRegistrarProvider(): Asset
{
    $finder = EntryFacade::partialMock();

    /** @var Entry $specialization */
    $specialization = EntryFacade::make();
    $specialization->collection('clinical_specializations');
    $specialization->id('spec-kirk');
    $specialization->data(['title_specialization_patient' => 'Cardiologist']);
    $finder->shouldReceive('find')->with('spec-kirk')->andReturn($specialization);

    /** @var Entry $provider */
    $provider = EntryFacade::make();
    $provider->collection('people');
    $provider->locale('default');
    $provider->id('prov-kirk');
    $provider->slug('prov-kirk');
    $provider->data([
        'credentials' => ['md'],
        'clinical_specializations' => ['spec-kirk'],
        'gender' => 'Male',
        'appointments_query' => true,
        'accepting_patients_query' => true,
        'referral_required' => true,
        'portrait' => ['portraits::kirk.jpg'],
        'person_name' => [
            'first_name' => ['text' => 'James', 'phonetic_text' => null],
            'no_middle_name' => false,
            'middle_name' => ['text' => 'Tiberius', 'phonetic_text' => null],
            'no_nickname' => true,
            'last_name' => ['text' => 'Kirk', 'phonetic_text' => null],
            'no_generational_suffix' => true,
            'generational_suffix' => null,
            'display_format' => 'full_first_middle_initial@ns',
        ],
    ]);
    $finder->shouldReceive('find')->with('prov-kirk')->andReturn($provider);

    $asset = new Asset;
    AssetFacade::shouldReceive('find')->with('portraits::kirk.jpg')->andReturn($asset);

    return $asset;
}

/**
 * Capture the registrar's computeds, keyed by handle.
 *
 * @return array<string, array{0: mixed, 1: callable}>
 */
function spotlightRegistrarComputeds(ProviderSpotlightComputedValues $service): array
{
    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = [$collection, $callback];
        }
    );

    (new ClinicalResourcesProviderSpotlightComputedsRegistrar($service))->register();

    return $captured;
}

/**
 * @covers \App\Services\ComputedFields\Registration\ClinicalResourcesProviderSpotlightComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/ClinicalResourcesProviderSpotlightComputedsRegistrarTest.php
 */
it('registers the nine spotlight computeds on clinical_resources', function (): void {
    $captured = spotlightRegistrarComputeds(app(ProviderSpotlightComputedValues::class));

    expect(array_keys($captured))->toBe(SPOTLIGHT_REGISTRAR_HANDLES)
        ->and(array_column($captured, 0))->toBe(array_fill(0, 9, 'clinical_resources'));
});

it('derives each value from its own service method on a provider spotlight', function (): void {
    $asset = spotlightRegistrarProvider();
    $service = app(ProviderSpotlightComputedValues::class);
    $captured = spotlightRegistrarComputeds($service);
    $spotlight = spotlightRegistrarEntry(spotlightRegistrarBlueprint(ProviderSpotlightComputedValues::BLUEPRINT));

    $actual = [];

    foreach (SPOTLIGHT_REGISTRAR_HANDLES as $handle) {
        $actual[$handle] = ($captured[$handle][1])($spotlight, 'stored');
    }

    // Every value is set and the string values are pairwise distinct, so a
    // computed bound to the wrong method cannot match its expectation.
    expect($actual)->toBe([
        'spotlight_auto_introduction' => $service->autoIntroduction($spotlight),
        'spotlight_auto_excerpt' => $service->autoExcerpt($spotlight),
        'spotlight_featured_image' => $asset,
        'spotlight_provider_name' => $service->providerName($spotlight),
        'spotlight_provider_title' => 'Cardiologist',
        'spotlight_bookable' => true,
        'spotlight_referral_required' => true,
        'spotlight_appointment_phone_display' => $service->appointmentPhoneDisplay($spotlight),
        'spotlight_appointment_phone_href' => $service->appointmentPhoneHref($spotlight),
    ]);

    $strings = array_filter($actual, is_string(...));

    expect($strings)->toHaveCount(6)
        ->and(array_unique($strings))->toHaveCount(6);
});

it('derives nothing for a resource that is not a provider spotlight', function (?Blueprint $blueprint): void {
    spotlightRegistrarProvider();
    $captured = spotlightRegistrarComputeds(app(ProviderSpotlightComputedValues::class));
    $resource = spotlightRegistrarEntry($blueprint);

    $actual = [];

    foreach (SPOTLIGHT_REGISTRAR_HANDLES as $handle) {
        $actual[$handle] = ($captured[$handle][1])($resource, 'stored');
    }

    expect($actual)->toBe([
        'spotlight_auto_introduction' => null,
        'spotlight_auto_excerpt' => null,
        'spotlight_featured_image' => null,
        'spotlight_provider_name' => null,
        'spotlight_provider_title' => null,
        'spotlight_bookable' => false,
        'spotlight_referral_required' => false,
        'spotlight_appointment_phone_display' => null,
        'spotlight_appointment_phone_href' => null,
    ]);
})->with([
    'another blueprint' => [fn (): Blueprint => spotlightRegistrarBlueprint('resource')],
    'no blueprint' => [null],
]);
