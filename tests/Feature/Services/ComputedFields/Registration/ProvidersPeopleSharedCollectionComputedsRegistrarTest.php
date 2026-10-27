<?php

declare(strict_types=1);

use App\Services\ComputedFields\ProviderPeople\AppointmentContextResolver;
use App\Services\ComputedFields\ProviderPeople\ClinicalTitleResolver;
use App\Services\ComputedFields\ProviderPeople\MedicalOntologySuppressionResolver;
use App\Services\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use App\Services\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;
use App\Services\ComputedFields\ProviderPeople\ProviderProfileBodyResolver;
use App\Services\ComputedFields\ProviderPeople\ProviderResourcesArchiveUrlResolver;
use App\Services\ComputedFields\ProviderPeople\ResearchPublicationSelectComputed;
use App\Services\ComputedFields\Registration\ProvidersPeopleSharedCollectionComputedsRegistrar;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ProvidersPeopleSharedCollectionComputedsRegistrar::class);

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

/**
 * The full set of computed handles the registrar owns, in registration order.
 *
 * @var list<string>
 */
const PP_SHARED_HANDLES = [
    'display_name_full',
    'display_name_full_with_prefix',
    'display_name_full_with_credentials',
    'display_name_short_with_prefix',
    'full_name',
    'full_name_with_credentials',
    'full_name_with_prefix',
    'nickname_list',
    'alternate_name_list',
    'legal_name_pairs',
    'brand_organizations',
    'regions',
    'portal',
    'prefix',
    'asp_filter',
    'research_publication_select',
    'separated_active_query',
    'separated_unpublish_query',
    'searchable',
    'clinical_title',
    'appointment_context',
    'provider_profile_body_rendered',
    'provider_resources_url',
    'hide_medical_ontology',
];

/**
 * @covers \App\Services\ComputedFields\Registration\ProvidersPeopleSharedCollectionComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Feature/Services/ComputedFields/Registration/ProvidersPeopleSharedCollectionComputedsRegistrarTest.php
 */
it('registers every shared provider/people computed exactly once on both collections', function (): void {
    // Resolve from the container (the ResearchPublicationSelectComputed dependency is
    // `final`, so the whole graph is easier real than mocked) and capture the surface.
    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collections, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collections;
        }
    );

    app(ProvidersPeopleSharedCollectionComputedsRegistrar::class)->register();

    // Exactly one registration per handle — a duplicate registration (same handle
    // twice) would collapse in the keyed map, so pin the raw call count too.
    expect($callCount)->toBe(count(PP_SHARED_HANDLES))
        ->and(array_keys($captured))->toEqualCanonicalizing(PP_SHARED_HANDLES);

    // Each computed targets exactly both consumer collections.
    foreach (PP_SHARED_HANDLES as $handle) {
        expect($captured[$handle])->toBe(['clinical_providers', 'people']);
    }
});

it('wires each computed handle to its owning service method', function (): void {
    // Mock the two (non-final) name/listing services so a swapped binding surfaces as
    // the wrong return value. The final ResearchPublicationSelectComputed slot is never
    // invoked here — its behavior is covered by ResearchPublicationSelectComputedTest;
    // this asserts the registrar binds handles to the right methods.
    $names = Mockery::mock(ProviderPeoplePersonNameComputedValues::class);
    $listing = Mockery::mock(ProviderPeopleListingComputedValues::class);
    $entry = Mockery::mock(Entry::class);

    // display_name_full and full_name both come from $names; distinct return values
    // catch a swap between the two sibling bindings.
    $names->shouldReceive('getDisplayNameFull')->andReturn(['text' => 'DISPLAY_FULL']);
    $names->shouldReceive('getFullName')->andReturn(['text' => 'FULL_NAME']);
    $listing->shouldReceive('computeBrandOrganizations')->andReturn(['ORG']);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collections, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    (new ProvidersPeopleSharedCollectionComputedsRegistrar(
        $names,
        $listing,
        app(ResearchPublicationSelectComputed::class),
        app(ClinicalTitleResolver::class),
        app(AppointmentContextResolver::class),
        app(ProviderProfileBodyResolver::class),
        app(ProviderResourcesArchiveUrlResolver::class),
        app(MedicalOntologySuppressionResolver::class)
    ))->register();

    expect(($captured['display_name_full'])($entry))->toBe(['text' => 'DISPLAY_FULL'])
        ->and(($captured['full_name'])($entry))->toBe(['text' => 'FULL_NAME'])
        ->and(($captured['brand_organizations'])($entry))->toBe(['ORG']);
});
