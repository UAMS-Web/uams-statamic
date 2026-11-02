<?php

declare(strict_types=1);

use App\Jobs\RefreshProviderEligibilityForDatedLocations;
use App\Jobs\UnpublishExpiredClassifieds;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests that a unique job's lock holds during a run and expires after one that never finished (#488).
 *
 * A faked queue never runs the job, so nothing releases the lock, which is the state a killed worker
 * leaves. The array store keeps the lock inside this process, so a parallel worker cannot see it.
 *
 * @covers \App\Jobs\RefreshProviderEligibilityForDatedLocations
 * @covers \App\Jobs\UnpublishExpiredClassifieds
 *
 * @command  php artisan test --compact tests/Feature/Jobs/UniqueJobLockExpiryTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    Config::set('cache.default', 'array');
    Cache::setDefaultDriver('array');
    Queue::fake();
});

dataset('unique jobs', [
    'expired classifieds' => [fn (): UnpublishExpiredClassifieds => new UnpublishExpiredClassifieds],
    'dated locations' => [fn (): RefreshProviderEligibilityForDatedLocations => new RefreshProviderEligibilityForDatedLocations],
]);

it('drops a second dispatch while the lock is held', function (UnpublishExpiredClassifieds|RefreshProviderEligibilityForDatedLocations $job): void {
    $job::dispatch();

    $this->travel($job->uniqueFor - 1)->seconds();

    $job::dispatch();

    Queue::assertPushed($job::class, 1);
})->with('unique jobs');

it('accepts a dispatch once the lock from a lost run has expired', function (UnpublishExpiredClassifieds|RefreshProviderEligibilityForDatedLocations $job): void {
    $job::dispatch();

    $this->travel($job->uniqueFor + 1)->seconds();

    $job::dispatch();

    Queue::assertPushed($job::class, 2);
})->with('unique jobs');

it('outlasts the time the queue gives a run', function (UnpublishExpiredClassifieds|RefreshProviderEligibilityForDatedLocations $job): void {
    expect($job->uniqueFor)->toBeGreaterThan(config()->integer('queue.connections.database.retry_after'));
})->with('unique jobs');
