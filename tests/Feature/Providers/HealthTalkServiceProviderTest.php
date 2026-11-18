<?php

declare(strict_types=1);

use App\Http\Controllers\CP\Podcasts\HealthTalkSyncController;
use App\Providers\HealthTalkServiceProvider;
use App\Services\Podcasts\HealthTalk\HealthTalkClient;
use App\Services\Podcasts\HealthTalk\HealthTalkEpisodeSync;
use App\Services\Podcasts\HealthTalk\HealthTalkFeedParser;
use App\Services\Podcasts\HealthTalk\HealthTalkItemPageParser;
use App\Services\Podcasts\HealthTalk\HealthTalkSpeakerMappings;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Routing\Route;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Statamic\Facades\Permission;
use Tests\TestCase;

uses(TestCase::class);

covers(HealthTalkServiceProvider::class);

/**
 * `HealthTalkServiceProvider` builds the Health Talk sync's services from
 * `config/uams_health_talk.php`, registers the permission that runs the sync,
 * and adds the Control Panel route the dashboard widget posts to
 * (UAMS-Web/uams-statamic#871, UAMS-Web/uams-statamic-archive#1803).
 *
 * Every configuration value is set to a distinct marker, so a binding that reads
 * the wrong key, or passes its arguments in the wrong order, is visible.
 *
 * @command  php artisan test --compact tests/Feature/Providers/HealthTalkServiceProviderTest.php
 */

/**
 * A private property of a built service, read through reflection.
 */
function healthTalkProviderProperty(object $service, string $property): mixed
{
    return new ReflectionProperty($service, $property)->getValue($service);
}

/**
 * A collaborator property of a built service, which must hold an object.
 */
function healthTalkProviderCollaborator(object $service, string $property): object
{
    $value = healthTalkProviderProperty($service, $property);

    if (! is_object($value)) {
        throw new RuntimeException($property.' does not hold an object');
    }

    return $value;
}

/**
 * The named route, failing the test when it is not registered.
 */
function healthTalkProviderRoute(string $name): Route
{
    return app('router')->getRoutes()->getByName($name)
        ?? throw new RuntimeException('No route is named '.$name);
}

beforeEach(function (): void {
    config([
        'uams_health_talk.source.feed_url' => 'https://feed.test/marker-feed',
        'uams_health_talk.source.item_url' => 'https://item.test/%s/marker-item',
        'uams_health_talk.source.timeout' => 17,
        'uams_health_talk.source.pause_ms' => 23,
        'uams_health_talk.source.audio_url_prefix' => 'https://audio.test/marker-prefix/',
        'uams_health_talk.speaker_mappings.global' => 'marker_global',
        'uams_health_talk.speaker_mappings.site' => 'marker_site',
        'uams_health_talk.destination.collection' => 'marker_collection',
        'uams_health_talk.destination.blueprint' => 'marker_blueprint',
        'uams_health_talk.destination.site' => 'marker_destination_site',
        'uams_health_talk.destination.series' => 'marker_series',
        'uams_health_talk.max_unpublish_per_run' => 29,
    ]);
});

it('builds the client from the source configuration and the container HTTP factory', function (): void {
    $http = new HttpFactory;
    $this->app->instance(HttpFactory::class, $http);

    $client = $this->app->make(HealthTalkClient::class);

    expect(healthTalkProviderProperty($client, 'http'))->toBe($http)
        ->and(healthTalkProviderProperty($client, 'feedUrl'))->toBe('https://feed.test/marker-feed')
        ->and(healthTalkProviderProperty($client, 'itemUrlPattern'))->toBe('https://item.test/%s/marker-item')
        ->and(healthTalkProviderProperty($client, 'timeout'))->toBe(17);
});

it('builds the feed parser with the configured audio URL prefix', function (): void {
    $parser = $this->app->make(HealthTalkFeedParser::class);

    expect(healthTalkProviderProperty($parser, 'audioUrlPrefix'))->toBe('https://audio.test/marker-prefix/');
});

it('builds the speaker mappings from the configured global set and site', function (): void {
    $mappings = $this->app->make(HealthTalkSpeakerMappings::class);

    expect(healthTalkProviderProperty($mappings, 'globalHandle'))->toBe('marker_global')
        ->and(healthTalkProviderProperty($mappings, 'site'))->toBe('marker_site');
});

it('builds the sync from the bound collaborators, the destination and the run limits', function (): void {
    $logger = new NullLogger;
    $this->app->instance(LoggerInterface::class, $logger);

    $sync = $this->app->make(HealthTalkEpisodeSync::class);

    $client = healthTalkProviderCollaborator($sync, 'client');
    $feedParser = healthTalkProviderCollaborator($sync, 'feedParser');
    $mappings = healthTalkProviderCollaborator($sync, 'mappings');

    expect($client)->toBeInstanceOf(HealthTalkClient::class)
        ->and(healthTalkProviderProperty($client, 'feedUrl'))->toBe('https://feed.test/marker-feed')
        ->and($feedParser)->toBeInstanceOf(HealthTalkFeedParser::class)
        ->and(healthTalkProviderProperty($feedParser, 'audioUrlPrefix'))->toBe('https://audio.test/marker-prefix/')
        ->and(healthTalkProviderProperty($sync, 'pageParser'))->toBeInstanceOf(HealthTalkItemPageParser::class)
        ->and($mappings)->toBeInstanceOf(HealthTalkSpeakerMappings::class)
        ->and(healthTalkProviderProperty($mappings, 'globalHandle'))->toBe('marker_global')
        ->and(healthTalkProviderProperty($sync, 'log'))->toBe($logger)
        ->and(healthTalkProviderProperty($sync, 'destination'))->toBe([
            'collection' => 'marker_collection',
            'blueprint' => 'marker_blueprint',
            'site' => 'marker_destination_site',
            'series' => 'marker_series',
        ])
        ->and(healthTalkProviderProperty($sync, 'maxUnpublishPerRun'))->toBe(29)
        ->and(healthTalkProviderProperty($sync, 'pauseMs'))->toBe(23);
});

it('refuses a configuration value of the wrong type rather than casting it', function (string $key, mixed $value, string $service): void {
    config(['uams_health_talk.'.$key => $value]);

    expect(fn (): mixed => $this->app->make($service))->toThrow(InvalidArgumentException::class);
})->with([
    'a numeric-string timeout' => ['source.timeout', '17', HealthTalkClient::class],
    'an integer feed URL' => ['source.feed_url', 42, HealthTalkClient::class],
    'a numeric-string unpublish limit' => ['max_unpublish_per_run', '29', HealthTalkEpisodeSync::class],
]);

it('registers the run-sync permission, labeled, in the Podcasts group', function (): void {
    $permission = Permission::get(HealthTalkServiceProvider::PERMISSION);
    /** @var array{handle: string, label: string}|null $group */
    $group = collect(Permission::tree())->firstWhere('handle', 'podcasts');

    expect(HealthTalkServiceProvider::PERMISSION)->toBe('run uams health talk sync')
        ->and($permission)->not->toBeNull()
        ->and($permission->label())->toBe('Run the UAMS Health Talk sync')
        ->and($permission->group())->toBe('podcasts')
        ->and($group['label'] ?? null)->toBe('Podcasts');
});

it('adds the Control Panel route the widget posts to', function (): void {
    $route = healthTalkProviderRoute('statamic.cp.health-talk.sync');

    expect($route->uri())->toBe(config()->string('statamic.cp.route').'/podcasts/health-talk/sync')
        ->and($route->methods())->toBe(['POST'])
        ->and($route->getActionName())->toBe(HealthTalkSyncController::class);
});
