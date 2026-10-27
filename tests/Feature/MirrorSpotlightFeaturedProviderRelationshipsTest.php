<?php

declare(strict_types=1);

use App\Listeners\MirrorSpotlightFeaturedProviderRelationships;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * The Provider Spotlight featured-provider relationship mirror (UAMS-Web/uams-statamic-archive#1425 §7): on
 * save, the featured provider is unioned into `clinical_providers` (never
 * clobbering editor-entered ones) and the provider's own ontology (locations,
 * areas of expertise, conditions, treatments) is copied — overwrite, not union —
 * onto the spotlight's matching relationships, so those ontology pages list the
 * spotlight. The other resource types are untouched.
 *
 * @command php artisan test --compact tests/Feature/MirrorSpotlightFeaturedProviderRelationshipsTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Run the mirror listener over an in-memory clinical-resource entry and return it.
 *
 * @param  array<string, mixed>  $data
 */
function mirrorSpotlight(string $blueprint, array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_resources');
    $entry->blueprint($blueprint);
    $entry->locale('default');
    $entry->id('spotlight-mirror');
    $entry->slug('spotlight-mirror');
    $entry->data($data);

    (new MirrorSpotlightFeaturedProviderRelationships)->handle(new EntrySaving($entry));

    return $entry;
}

/**
 * An in-memory `people` provider fixture carrying its ontology relationships.
 *
 * @param  array<string, mixed>  $data
 */
function providerEntry(string $id, array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale('default');
    $entry->id($id);
    $entry->slug($id);
    $entry->data($data);

    return $entry;
}

/** Resolve any featured provider to null unless a test overrides it. */
function stubUnresolvableProviders(): void
{
    Entry::partialMock()->shouldReceive('find')->andReturnNull()->byDefault();
}

it('unions the featured provider into clinical_providers', function (): void {
    stubUnresolvableProviders();

    $entry = mirrorSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-mccoy'],
        'clinical_providers' => ['prov-spock'],
    ]);

    /** @var list<string> $providers */
    $providers = $entry->get('clinical_providers');
    expect($providers)->toBe(['prov-spock', 'prov-mccoy']);
});

it('preserves editor-entered related providers rather than overwriting them', function (): void {
    stubUnresolvableProviders();

    $entry = mirrorSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-mccoy'],
        'clinical_providers' => ['prov-spock', 'prov-uhura'],
    ]);

    /** @var list<string> $providers */
    $providers = $entry->get('clinical_providers');
    expect($providers)->toBe(['prov-spock', 'prov-uhura', 'prov-mccoy']);
});

it('is idempotent when the featured provider is already related', function (): void {
    stubUnresolvableProviders();

    $entry = mirrorSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-mccoy'],
        'clinical_providers' => ['prov-mccoy'],
    ]);

    /** @var list<string> $providers */
    $providers = $entry->get('clinical_providers');
    expect($providers)->toBe(['prov-mccoy']);
});

it('seeds clinical_providers from the featured provider when none are entered', function (): void {
    stubUnresolvableProviders();

    $entry = mirrorSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-mccoy'],
    ]);

    /** @var list<string> $providers */
    $providers = $entry->get('clinical_providers');
    expect($providers)->toBe(['prov-mccoy']);
});

it('does nothing when there is no featured provider', function (): void {
    $entry = mirrorSpotlight('provider_spotlight', [
        'clinical_providers' => ['prov-spock'],
    ]);

    /** @var list<string> $providers */
    $providers = $entry->get('clinical_providers');
    expect($providers)->toBe(['prov-spock']);
});

it('leaves the other clinical-resource types untouched', function (): void {
    $entry = mirrorSpotlight('article', [
        'featured_provider' => ['prov-mccoy'],
        'clinical_providers' => ['prov-spock'],
    ]);

    /** @var list<string> $providers */
    $providers = $entry->get('clinical_providers');
    expect($providers)->toBe(['prov-spock']);
});

it('overwrites the ontology relationships with the featured provider’s own set', function (): void {
    $provider = providerEntry('prov-mccoy', [
        'clinical_locations' => ['loc-1', 'loc-2'],
        'expertise' => ['exp-1'],
        'conditions' => ['cond-1'],
        'treatments' => ['treat-1', 'treat-2'],
    ]);
    Entry::partialMock()->shouldReceive('find')->with('prov-mccoy')->andReturn($provider);

    $entry = mirrorSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-mccoy'],
        // Stale values that must be replaced by the provider's set, not unioned.
        'clinical_locations' => ['stale-loc'],
        'clinical_expertise' => ['stale-exp'],
    ]);

    expect($entry->get('clinical_locations'))->toBe(['loc-1', 'loc-2']);
    expect($entry->get('clinical_expertise'))->toBe(['exp-1']);
    expect($entry->get('clinical_conditions'))->toBe(['cond-1']);
    expect($entry->get('clinical_services'))->toBe(['treat-1', 'treat-2']);
    // The featured provider is still unioned into clinical_providers.
    expect($entry->get('clinical_providers'))->toBe(['prov-mccoy']);
});

it('clears the derived ontology when there is no featured provider', function (): void {
    $entry = mirrorSpotlight('provider_spotlight', [
        'clinical_locations' => ['loc-1'],
        'clinical_expertise' => ['exp-1'],
        'clinical_conditions' => ['cond-1'],
        'clinical_services' => ['treat-1'],
    ]);

    expect($entry->get('clinical_locations'))->toBe([]);
    expect($entry->get('clinical_expertise'))->toBe([]);
    expect($entry->get('clinical_conditions'))->toBe([]);
    expect($entry->get('clinical_services'))->toBe([]);
});

it('leaves the ontology untouched when the featured provider is unresolvable', function (): void {
    Entry::partialMock()->shouldReceive('find')->with('prov-missing')->andReturnNull();

    $entry = mirrorSpotlight('provider_spotlight', [
        'featured_provider' => ['prov-missing'],
        'clinical_locations' => ['loc-keep'],
    ]);

    // The featured provider is still unioned...
    expect($entry->get('clinical_providers'))->toBe(['prov-missing']);
    // ...but a transient resolve failure does not clobber a good mirror.
    expect($entry->get('clinical_locations'))->toBe(['loc-keep']);
});
