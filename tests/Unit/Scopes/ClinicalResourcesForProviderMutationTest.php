<?php

declare(strict_types=1);

use App\Scopes\ClinicalResourcesForProvider;
use App\Support\People\ClinicalResourceProviderFilter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Query\Builder;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ClinicalResourcesForProvider::class);

/**
 * Narrow mutate coverage for App\Scopes\ClinicalResourcesForProvider.
 *
 * `ClinicalResourceProviderFilter` is final, so the scope is exercised against a
 * real filter with Entry/Site facade stubs: same pattern as other final-service
 * mutate suites.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/ClinicalResourcesForProviderMutationTest.php
 */

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

it('is a no-op for a missing, blank, or non-string provider', function (array $values): void {
    /** @var array<string, mixed> $values */
    EntryFacade::shouldReceive('query')->never();

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')->never();

    (new ClinicalResourcesForProvider(new ClinicalResourceProviderFilter))->apply(
        $query,
        $values,
    );
})->with([
    'absent key' => [[]],
    'null' => [['provider' => null]],
    'empty string' => [['provider' => '']],
    'whitespace' => [['provider' => '   ']],
    'non-string int' => [['provider' => 12]],
    'non-string array' => [['provider' => ['spock']]],
]);

it('leaves the query unfiltered when the slug resolves to no entry', function (): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('default');
    Site::shouldReceive('current')->once()->andReturn($site);

    $peopleQuery = Mockery::mock();
    $peopleQuery->shouldReceive('where')->with('collection', 'people')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('site', 'default')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('slug', 'nobody')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('get')->once()->andReturn([]);

    EntryFacade::shouldReceive('query')->once()->andReturn($peopleQuery);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')->never();

    (new ClinicalResourcesForProvider(new ClinicalResourceProviderFilter))->apply(
        $query,
        ['provider' => 'nobody'],
    );
});

it('rejects a non-Entry find row rather than filtering on it', function (): void {
    // InstanceOfToTrue on ``$provider instanceof Entry`` would treat a stdClass
    // as a hit and call resourceIds() with the wrong type.
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('default');
    Site::shouldReceive('current')->once()->andReturn($site);

    $peopleQuery = Mockery::mock();
    $peopleQuery->shouldReceive('where')->with('collection', 'people')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('site', 'default')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('slug', 'ghost')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('get')->once()->andReturn([new stdClass]);

    EntryFacade::shouldReceive('query')->once()->andReturn($peopleQuery);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')->never();

    (new ClinicalResourcesForProvider(new ClinicalResourceProviderFilter))->apply(
        $query,
        ['provider' => 'ghost'],
    );
});

it('constrains the query to the related resource ids for a known provider', function (): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('default');
    Site::shouldReceive('current')->once()->andReturn($site);

    $provider = Mockery::mock(Entry::class);
    $provider->shouldReceive('status')->once()->andReturn('published');
    $provider->shouldReceive('value')->with('status_and_visibility')->once()->andReturn('public');
    $provider->shouldReceive('value')->with('password_protected')->once()->andReturn(false);
    $provider->shouldReceive('get')
        ->once()
        ->with('clinical_resources')
        ->andReturn(['res-1', 'res-2']);

    $peopleQuery = Mockery::mock();
    $peopleQuery->shouldReceive('where')->with('collection', 'people')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('site', 'default')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('slug', 'spock')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('get')->once()->andReturn([$provider]);

    EntryFacade::shouldReceive('query')->once()->andReturn($peopleQuery);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with(
            'id',
            ['res-1', 'res-2'],
        )
        ->andReturnSelf();

    (new ClinicalResourcesForProvider(new ClinicalResourceProviderFilter))->apply(
        $query,
        ['provider' => '  spock  '],
    );
});

it('constrains to an empty id list when a known provider relates nothing', function (): void {
    // Distinct from the unknown-slug case: a real empty answer must still
    // call whereIn so the archive shows empty rather than browse-all.
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('default');
    Site::shouldReceive('current')->once()->andReturn($site);

    $provider = Mockery::mock(Entry::class);
    $provider->shouldReceive('status')->once()->andReturn('published');
    $provider->shouldReceive('value')->with('status_and_visibility')->once()->andReturn('public');
    $provider->shouldReceive('value')->with('password_protected')->once()->andReturn(false);
    $provider->shouldReceive('get')
        ->once()
        ->with('clinical_resources')
        ->andReturn([]);

    $peopleQuery = Mockery::mock();
    $peopleQuery->shouldReceive('where')->with('collection', 'people')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('site', 'default')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('where')->with('slug', 'uhura')->once()->andReturnSelf();
    $peopleQuery->shouldReceive('get')->once()->andReturn([$provider]);

    EntryFacade::shouldReceive('query')->once()->andReturn($peopleQuery);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with(
            'id',
            [],
        )
        ->andReturnSelf();

    (new ClinicalResourcesForProvider(new ClinicalResourceProviderFilter))->apply(
        $query,
        ['provider' => 'uhura'],
    );
});
