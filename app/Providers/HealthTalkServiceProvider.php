<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\CP\Podcasts\HealthTalkSyncController;
use App\Services\Podcasts\HealthTalk\HealthTalkClient;
use App\Services\Podcasts\HealthTalk\HealthTalkEpisodeSync;
use App\Services\Podcasts\HealthTalk\HealthTalkFeedParser;
use App\Services\Podcasts\HealthTalk\HealthTalkItemPageParser;
use App\Services\Podcasts\HealthTalk\HealthTalkSpeakerMappings;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Statamic\Facades\Permission;
use Statamic\Statamic;

/**
 * Wires up the UAMS Health Talk sync (UAMS-Web/uams-statamic-archive#1803): the services built from
 * `config/uams_health_talk.php`, the permission to run the sync on demand, and
 * the Control Panel route the dashboard widget's "Run sync now" button posts to.
 */
class HealthTalkServiceProvider extends ServiceProvider
{
    /**
     * The permission required to run the sync from the Control Panel.
     */
    public const string PERMISSION = 'run uams health talk sync';

    /**
     * Bind the sync and its collaborators to their configuration.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->bind(HealthTalkClient::class, static fn (Application $app): HealthTalkClient => new HealthTalkClient(
            $app->make(HttpFactory::class),
            self::string('source.feed_url'),
            self::string('source.item_url'),
            self::int('source.timeout')
        ));

        $this->app->bind(HealthTalkFeedParser::class, static fn (): HealthTalkFeedParser => new HealthTalkFeedParser(
            self::string('source.audio_url_prefix')
        ));

        $this->app->bind(HealthTalkSpeakerMappings::class, static fn (): HealthTalkSpeakerMappings => new HealthTalkSpeakerMappings(
            self::string('speaker_mappings.global'),
            self::string('speaker_mappings.site')
        ));

        $this->app->bind(HealthTalkEpisodeSync::class, static fn (Application $app): HealthTalkEpisodeSync => new HealthTalkEpisodeSync(
            $app->make(HealthTalkClient::class),
            $app->make(HealthTalkFeedParser::class),
            $app->make(HealthTalkItemPageParser::class),
            $app->make(HealthTalkSpeakerMappings::class),
            $app->make(LoggerInterface::class),
            [
                'collection' => self::string('destination.collection'),
                'blueprint' => self::string('destination.blueprint'),
                'site' => self::string('destination.site'),
                'series' => self::string('destination.series'),
            ],
            self::int('max_unpublish_per_run'),
            self::int('source.pause_ms')
        ));
    }

    /**
     * Register the permission and the Control Panel route.
     */
    public function boot(): void
    {
        Permission::group('podcasts', 'Podcasts', function (): void {
            Permission::register(self::PERMISSION)
                ->label(__('Run the UAMS Health Talk sync'));
        });

        Statamic::pushCpRoutes(function (): void {
            Route::post('podcasts/health-talk/sync', HealthTalkSyncController::class)->name('health-talk.sync');
        });
    }

    /**
     * A string value from `config/uams_health_talk.php`.
     */
    private static function string(
        string $key
    ): string {
        return config()->string('uams_health_talk.'.$key);
    }

    /**
     * An integer value from `config/uams_health_talk.php`.
     */
    private static function int(
        string $key
    ): int {
        return config()->integer('uams_health_talk.'.$key);
    }
}
