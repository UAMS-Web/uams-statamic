<?php

declare(strict_types=1);

use App\Http\Controllers\CP\Podcasts\HealthTalkSyncController;
use App\Jobs\SyncHealthTalkEpisodes;
use App\Providers\HealthTalkServiceProvider;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Facades\User;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;

uses(TestCase::class);

covers(HealthTalkSyncController::class);

/**
 * `HealthTalkSyncController` starts the Health Talk sync from the dashboard widget's
 * button, after the response is sent (UAMS-Web/uams-statamic#848).
 *
 * The acting user is never saved, so nothing is written to the `users` submodule.
 *
 * Mutation: 100% on App\Http\Controllers\CP\Podcasts\HealthTalkSyncController (2 mutants), measured on macOS on 2026-11-25
 * without --covered-only.
 *
 * @command  php artisan test --compact tests/Feature/Http/Controllers/CP/Podcasts/HealthTalkSyncControllerTest.php
 */
beforeEach(function (): void {
    withoutMiddleware(PreventRequestForgery::class);
    Bus::fake([SyncHealthTalkEpisodes::class]);

    $user = mustBe(FileStatamicUser::class, User::make(), 'the acting user');
    $user->id('health-talk-sync-user');
    $user->email('health-talk-sync@example.test');
    $user->makeSuper();

    actingAs($user);
});

/**
 * The endpoint the widget's button posts to.
 */
function healthTalkSyncRoute(): string
{
    return mustBeString(cp_route('health-talk.sync'), 'the sync route');
}

it('starts the sync after the response and returns to the page with a message', function (): void {
    from('/cp/dashboard')->post(healthTalkSyncRoute())
        ->assertRedirect('/cp/dashboard')
        ->assertSessionHas('success', 'The UAMS Health Talk sync has started. Reload the dashboard in a minute to see its result.');

    Bus::assertDispatchedAfterResponseTimes(SyncHealthTalkEpisodes::class, 1);
    Bus::assertDispatchedTimes(SyncHealthTalkEpisodes::class, 1);
});

it('refuses a user without the sync permission and starts nothing', function (): void {
    // A super user passes every Control Panel gate; this denies only the sync permission
    Gate::before(fn (mixed $user, string $ability): ?bool => $ability === HealthTalkServiceProvider::PERMISSION ? false : null);

    postJson(healthTalkSyncRoute())->assertForbidden();

    Bus::assertNotDispatched(SyncHealthTalkEpisodes::class);
    Bus::assertNotDispatchedAfterResponse(SyncHealthTalkEpisodes::class);
});
