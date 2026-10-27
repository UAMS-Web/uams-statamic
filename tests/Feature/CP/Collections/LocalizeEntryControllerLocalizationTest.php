<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;

uses(StacheIsolatedTestCase::class);

/**
 * `LocalizeEntryController` creating a localization, and reporting a failure to create one
 * (#2834 coverage batch 17). `LocalizeEntryControllerTest` covers the site validation
 * against real content; these tests save a localization, so they run on a throwaway Stache.
 *
 * The acting user is never saved, so nothing is written to the `users` submodule.
 *
 * @command  php artisan test --compact tests/Feature/CP/Collections/LocalizeEntryControllerLocalizationTest.php
 */
beforeEach(function (): void {
    withoutMiddleware(PreventRequestForgery::class);

    $collection = Collection::make('localize_probe');
    $collection->sites(['default', 'news']);
    $collection->save();

    $origin = mustBe(Entry::class, EntryFacade::make(), 'the origin entry');
    $origin->collection('localize_probe');
    $origin->locale('default');
    $origin->id('localize-origin');
    $origin->slug('localize-origin');
    $origin->data(['title' => 'Origin']);
    $origin->saveQuietly();
});

/**
 * An unsaved user, super or holding no permissions, with a known id.
 */
function localizeProbeUser(bool $super): FileStatamicUser
{
    $user = mustBe(FileStatamicUser::class, User::make(), 'the acting user');
    $user->id('localize-probe-user');
    $user->email('localize-probe@example.test');

    if ($super) {
        $user->makeSuper();
    }

    return $user;
}

/**
 * The localize endpoint for the probe entry.
 */
function localizeProbeRoute(): string
{
    return mustBeString(cp_route('collections.entries.localize', ['localize_probe', 'localize-origin']), 'the localize route');
}

/**
 * The probe entry's localization in a site, or null when there is none.
 */
function localizeProbeLocalization(string $site): ?Entry
{
    foreach (EntryFacade::whereCollection('localize_probe') as $entry) {
        $origin = $entry instanceof Entry ? $entry->origin() : null;

        if ($entry instanceof Entry && $entry->locale() === $site && $origin instanceof Entry && $origin->id() === 'localize-origin') {
            return $entry;
        }
    }

    return null;
}

it('creates and saves the localization, stamped with who made it and when, and answers with its edit URL', function (): void {
    $this->freezeTime();
    actingAs(localizeProbeUser(super: true));

    $response = postJson(localizeProbeRoute(), ['site' => 'news']);

    $localized = localizeProbeLocalization('news');

    expect($localized)->not->toBeNull()
        ->and($localized?->get('updated_by'))->toBe('localize-probe-user')
        ->and($localized?->get('updated_at'))->toBe(now()->timestamp);

    $response->assertOk()->assertExactJson([
        'success' => true,
        'handle' => 'news',
        'url' => $localized?->editUrl(),
    ]);
});

it('reports a failure to save the localization, with a toast, and logs it', function (): void {
    actingAs(localizeProbeUser(super: true));
    Event::listen(EntrySaving::class, function (): void {
        throw new RuntimeException('blocked by the probe');
    });
    $errors = [];
    Event::listen(MessageLogged::class, function (MessageLogged $logged) use (&$errors): void {
        if ($logged->level === 'error') {
            $errors[] = [$logged->message, $logged->context];
        }
    });

    $response = postJson(localizeProbeRoute(), ['site' => 'news']);

    expect($errors)->toBe([[
        'LocalizeEntryController::__invoke: Exception caught',
        ['entry_id' => 'localize-origin', 'message' => 'blocked by the probe'],
    ]]);

    $response->assertOk()->assertExactJson([
        'success' => false,
        'message' => 'blocked by the probe',
        'toast' => ['message' => 'blocked by the probe', 'type' => 'error'],
        // The Control Panel adds the toast raised during the request
        '_toasts' => [['message' => 'blocked by the probe', 'type' => 'error', 'duration' => null]],
    ]);

    expect(localizeProbeLocalization('news'))->toBeNull();
});

it('requires a site', function (): void {
    actingAs(localizeProbeUser(super: true));

    postJson(localizeProbeRoute(), [])->assertStatus(422)->assertJsonValidationErrors('site');

    expect(localizeProbeLocalization('news'))->toBeNull();
});

it('refuses a user who may not edit the entry', function (): void {
    actingAs(localizeProbeUser(super: false));

    postJson(localizeProbeRoute(), ['site' => 'news'])->assertForbidden();

    expect(localizeProbeLocalization('news'))->toBeNull();
});

it('refuses a user whom the entry policy will not let edit, even one who can reach the Control Panel', function (): void {
    actingAs(localizeProbeUser(super: true));

    // A super user passes every Control Panel gate; this denies only the edit check
    Gate::before(fn (mixed $user, string $ability, array $arguments): ?bool => $ability === 'edit' && ($arguments[0] ?? null) instanceof Entry ? false : null);

    postJson(localizeProbeRoute(), ['site' => 'news'])->assertForbidden();

    expect(localizeProbeLocalization('news'))->toBeNull();
});
