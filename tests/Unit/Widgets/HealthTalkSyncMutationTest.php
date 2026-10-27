<?php

declare(strict_types=1);

use App\Providers\HealthTalkServiceProvider;
use App\Services\Podcasts\HealthTalk\HealthTalkEpisodeSync;
use App\Widgets\HealthTalkSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Auth\User;
use Statamic\Facades\User as UserFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(HealthTalkSync::class);

/**
 * Narrow mutate coverage for App\Widgets\HealthTalkSync.
 *
 * @command  php artisan test --compact tests/Unit/Widgets/HealthTalkSyncMutationTest.php
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

it('returns an empty string when no user is signed in', function (): void {
    UserFacade::shouldReceive('current')->once()->andReturn(null);

    expect((new HealthTalkSync)->html())->toBe('');
});

it('returns an empty string when the user cannot run the sync', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')
        ->with(HealthTalkServiceProvider::PERMISSION)
        ->andReturn(false);
    UserFacade::shouldReceive('current')->once()->andReturn($user);

    expect((new HealthTalkSync)->html())->toBe('');
});

it('renders with null completed state and zero deferred when never run', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')
        ->with(HealthTalkServiceProvider::PERMISSION)
        ->andReturn(true);
    UserFacade::shouldReceive('current')->once()->andReturn($user);
    Cache::forget(HealthTalkEpisodeSync::LAST_COMPLETED_REPORT_CACHE_KEY);
    Cache::forget(HealthTalkEpisodeSync::LAST_REPORT_CACHE_KEY);

    $html = mustBe(View::class, (new HealthTalkSync)->html(), 'the HealthTalkSync widget view');

    expect($html->name())->toBe('widgets.health_talk_sync')
        ->and($html->getData()['completed'])->toBeNull()
        ->and($html->getData()['deferred_count'])->toBe(0)
        ->and($html->getData()['failure'])->toBeNull()
        ->and($html->getData()['unmapped'])->toBeArray()
        ->and($html->getData()['mapping_url'])->not->toBeNull()
        ->and($html->getData()['sync_url'])->not->toBe('')
        ->and($html->getData()['title'])->toBe(__('UAMS Health Talk'));
});

it('counts deferred episodes and surfaces an aborted failure message', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')
        ->with(HealthTalkServiceProvider::PERMISSION)
        ->andReturn(true);
    UserFacade::shouldReceive('current')->once()->andReturn($user);
    Cache::forever(HealthTalkEpisodeSync::LAST_COMPLETED_REPORT_CACHE_KEY, [
        'deferred' => [['id' => 'a'], ['id' => 'b']],
    ]);
    Cache::forever(HealthTalkEpisodeSync::LAST_REPORT_CACHE_KEY, [
        'aborted' => 'HTTP 503',
    ]);

    $html = mustBe(View::class, (new HealthTalkSync)->html(), 'the HealthTalkSync widget view');

    expect($html->getData()['deferred_count'])->toBe(2)
        ->and($html->getData()['failure'])->toBe('HTTP 503')
        ->and($html->getData()['completed'])->toBeArray();
});

it('ignores non-array cache payloads for completed and failure', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')
        ->with(HealthTalkServiceProvider::PERMISSION)
        ->andReturn(true);
    UserFacade::shouldReceive('current')->once()->andReturn($user);
    Cache::forever(HealthTalkEpisodeSync::LAST_COMPLETED_REPORT_CACHE_KEY, 'stale');
    Cache::forever(HealthTalkEpisodeSync::LAST_REPORT_CACHE_KEY, [
        'aborted' => ['not', 'a', 'string'],
    ]);

    $html = mustBe(View::class, (new HealthTalkSync)->html(), 'the HealthTalkSync widget view');

    expect($html->getData()['completed'])->toBeNull()
        ->and($html->getData()['deferred_count'])->toBe(0)
        ->and($html->getData()['failure'])->toBeNull();
});

it('treats a completed report without a deferred array as zero deferred', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')
        ->with(HealthTalkServiceProvider::PERMISSION)
        ->andReturn(true);
    UserFacade::shouldReceive('current')->once()->andReturn($user);
    // And→Or would still enter count() when completed is an array but deferred is not.
    Cache::forever(HealthTalkEpisodeSync::LAST_COMPLETED_REPORT_CACHE_KEY, [
        'deferred' => 'not-an-array',
    ]);
    Cache::forget(HealthTalkEpisodeSync::LAST_REPORT_CACHE_KEY);

    $html = mustBe(View::class, (new HealthTalkSync)->html(), 'the HealthTalkSync widget view');

    expect($html->getData()['completed'])->toBeArray()
        ->and($html->getData()['deferred_count'])->toBe(0);
});
