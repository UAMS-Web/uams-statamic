<?php

declare(strict_types=1);

use App\Jobs\SyncHealthTalkEpisodes;
use App\Services\Podcasts\HealthTalk\HealthTalkEpisodeSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The queued UAMS Health Talk sync job runs the sync it is handed (#255 coverage batch 12).
 *
 * The sync caches its report and takes a lock in the default cache store, which is a file
 * store shared by every test process, so these tests switch to the array store: they cannot
 * then take the lock from, or leave a report for, `HealthTalkEpisodeSyncTest`.
 *
 * @command  php artisan test --compact tests/Feature/Jobs/SyncHealthTalkEpisodesJobTest.php
 */
beforeEach(function (): void {
    Cache::setDefaultDriver('array');
});

it('runs the sync it is given, which aborts and reports when the feed cannot be fetched', function (): void {
    // Every request fails, so the sync aborts before it changes any entry
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response('unavailable', 503));

    $logger = new class extends AbstractLogger
    {
        /** @var list<array{level: mixed, message: string}> */
        public array $records = [];

        public function log($level, Stringable|string $message, array $context = []): void
        {
            $this->records[] = ['level' => $level, 'message' => (string) $message];
        }
    };
    app()->instance(LoggerInterface::class, $logger);

    // A limit the job must lift: a full sync can outrun any execution limit
    set_time_limit(300);

    try {
        (new SyncHealthTalkEpisodes)->handle(app(HealthTalkEpisodeSync::class));

        $limitDuringRun = ini_get('max_execution_time');
    } finally {
        set_time_limit(0);
    }

    $report = Cache::get(HealthTalkEpisodeSync::LAST_REPORT_CACHE_KEY);

    expect($limitDuringRun)->toBe('0')
        ->and($logger->records)->toBe([['level' => 'error', 'message' => 'UAMS Health Talk sync aborted; no entries were changed.']])
        ->and($report)->toBeArray()
        ->and(mustBeArray($report, 'the cached report')['aborted'] ?? null)->toBeString()->not->toBe('');
});
