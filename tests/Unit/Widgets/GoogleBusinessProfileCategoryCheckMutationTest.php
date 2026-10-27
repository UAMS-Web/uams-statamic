<?php

declare(strict_types=1);

use App\Services\GoogleBusinessProfile\Categories\CategoryCheckLog;
use App\Services\GoogleBusinessProfile\Categories\CategoryDiff;
use App\Widgets\GoogleBusinessProfileCategoryCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Auth\User;
use Statamic\Facades\User as UserFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(GoogleBusinessProfileCategoryCheck::class);

/**
 * Narrow mutate coverage for GoogleBusinessProfileCategoryCheck.
 *
 * Uses a real CategoryCheckLog (final) backed by the cache.
 *
 * @command  php artisan test --compact tests/Unit/Widgets/GoogleBusinessProfileCategoryCheckMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if (! $padded) {
        if (runningUnderMutate()) {
            usleep(20_000_000);
            $padded = true;
        }
    }

    Cache::forget(CategoryCheckLog::CACHE_KEY);
    Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00', 'UTC'));
});

/**
 * @param  array{checked_at: string, source: string, counts: array<string, int>, report: array<string, mixed>}|null  $latest
 */
function gbpCategorySeedLatest(?array $latest): void
{
    if ($latest === null) {
        Cache::forget(CategoryCheckLog::CACHE_KEY);

        return;
    }

    Cache::forever(CategoryCheckLog::CACHE_KEY, $latest);
}

it('returns an empty string when no user is signed in', function (): void {
    UserFacade::shouldReceive('current')->once()->andReturn(null);

    expect((new GoogleBusinessProfileCategoryCheck)->html())->toBe('');
});

it('returns an empty string when the user cannot view the taxonomy', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')
        ->with('view '.CategoryDiff::TAXONOMY.' terms')
        ->andReturn(false);
    UserFacade::shouldReceive('current')->once()->andReturn($user);

    expect((new GoogleBusinessProfileCategoryCheck)->html())->toBe('');
});

it('renders the widget view with latest null and overdue false when never checked', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')
        ->with('view '.CategoryDiff::TAXONOMY.' terms')
        ->andReturn(true);
    UserFacade::shouldReceive('current')->once()->andReturn($user);
    gbpCategorySeedLatest(null);

    $html = mustBe(View::class, (new GoogleBusinessProfileCategoryCheck)->html(), 'the GoogleBusinessProfileCategoryCheck widget view');

    expect($html->name())->toBe('widgets.google_business_profile_category_check')
        ->and($html->getData()['latest'])->toBeNull()
        ->and($html->getData()['overdue'])->toBeFalse()
        ->and($html->getData()['overdue_after_days'])->toBe(CategoryCheckLog::OVERDUE_AFTER_DAYS)
        ->and($html->getData()['title'])->toBe(__('Google Business Profile categories'));
});

it('flags overdue when the latest check is older than thirty days', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')
        ->with('view '.CategoryDiff::TAXONOMY.' terms')
        ->andReturn(true);
    UserFacade::shouldReceive('current')->once()->andReturn($user);

    $latest = [
        'checked_at' => '2026-09-01T12:00:00+00:00',
        'source' => 'file: categories.tsv',
        'counts' => ['new' => 1, 'renamed' => 0, 'removed' => 0, 'without_gcid' => 0, 'malformed' => 0],
        'report' => [],
    ];
    gbpCategorySeedLatest($latest);

    $html = mustBe(View::class, (new GoogleBusinessProfileCategoryCheck)->html(), 'the GoogleBusinessProfileCategoryCheck widget view');

    expect($html->getData()['latest'])->toBe($latest)
        ->and($html->getData()['overdue'])->toBeTrue();
});

it('keeps overdue false when the latest check is still within thirty days', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')
        ->with('view '.CategoryDiff::TAXONOMY.' terms')
        ->andReturn(true);
    UserFacade::shouldReceive('current')->once()->andReturn($user);

    $latest = [
        'checked_at' => '2026-10-20T12:00:00+00:00',
        'source' => 'file: categories.tsv',
        'counts' => ['new' => 0, 'renamed' => 0, 'removed' => 0, 'without_gcid' => 0, 'malformed' => 0],
        'report' => [],
    ];
    gbpCategorySeedLatest($latest);

    $html = mustBe(View::class, (new GoogleBusinessProfileCategoryCheck)->html(), 'the GoogleBusinessProfileCategoryCheck widget view');

    expect($html->getData()['latest'])->toBe($latest)
        ->and($html->getData()['overdue'])->toBeFalse();
});
