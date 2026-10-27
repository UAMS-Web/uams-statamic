<?php

declare(strict_types=1);

use App\Http\Middleware\ProtectEntry;
use App\Listeners\HashVisibilityPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User as UserFacade;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\StacheIsolatedTestCase;

/**
 * End-to-end coverage that {@see ProtectEntry}'s private/password gate holds for a
 * secondary-site localization (UAMS-Web/uams-statamic-archive#223).
 *
 * `status_and_visibility` / `password_protected` are `localizable: false`, so a
 * localization carries no own copy — the value lives only on the origin and is
 * reachable via `value()` (origin fallback), not `get()`. The mock-based
 * ProtectEntryTest cannot catch a `get()` vs `value()` regression because it stubs
 * the accessor directly; this drives a REAL multi-site origin + localization so
 * the origin fallback is actually exercised — the gate must not fail open on the
 * secondary-site copy.
 *
 * @covers \App\Http\Middleware\ProtectEntry
 *
 * @command  php artisan test --compact tests/Feature/Http/Middleware/ProtectEntryLocalizationTest.php
 */
uses(StacheIsolatedTestCase::class, MockeryPHPUnitIntegration::class);

beforeEach(function (): void {
    Queue::fake();

    // The `password_prompt` Blade view references `$errors` (normally shared by
    // the web group's ShareErrorsFromSession middleware).
    View::share('errors', new ViewErrorBag);

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make('loc_probe');
    $collection->sites(['default', 'health']);
    $collection->routes('{slug}');
    $collection->propagate(false);
    $collection->save();
});

/**
 * A request whose matched route binds the given entry as its `entry` parameter
 * (the middleware then gates that exact entry, no URL resolution needed).
 */
function protectLocalizationRequest(Entry $entry): Request
{
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('parameter')->with('entry')->andReturn($entry);

    $request = Request::create('http://localhost/health/secret');
    $request->setRouteResolver(fn () => $route);

    return $request;
}

/**
 * Build a private origin in `default` and a published `health` localization of it.
 */
function protectLocalizationPrivateLocalization(): Entry
{
    /** @var Entry $origin */
    $origin = EntryFacade::make();
    $origin->collection('loc_probe');
    $origin->locale('default');
    $origin->slug('secret');
    $origin->published(true);
    $origin->data(['title' => 'Secret', 'status_and_visibility' => 'private']);
    $origin->save();

    /** @var Entry $localization */
    $localization = $origin->makeLocalization('health');
    $localization->published(true);
    $localization->save();

    return $localization;
}

/**
 * Build a password-protected origin in `default` and a published `health`
 * localization of it. Saving the origin fires {@see HashVisibilityPassword},
 * so the stored password is a hash with `is_password_hashed` set — the shape UAMS-Web/uams-statamic-archive#1862
 * made the norm.
 */
function protectLocalizationPasswordProtectedLocalization(string $password): Entry
{
    /** @var Entry $origin */
    $origin = EntryFacade::make();
    $origin->collection('loc_probe');
    $origin->locale('default');
    $origin->slug('vault');
    $origin->published(true);
    $origin->data([
        'title' => 'Vault',
        'status_and_visibility' => 'published',
        'password_protected' => true,
        'visibility_password' => $password,
    ]);
    $origin->save();

    /** @var Entry $localization */
    $localization = $origin->makeLocalization('health');
    $localization->published(true);
    $localization->save();

    return $localization;
}

/**
 * A password submission bound to the given entry as the route's `entry` parameter.
 */
function protectLocalizationPasswordPost(Entry $entry, string $password): Request
{
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('parameter')->with('entry')->andReturn($entry);

    $request = Request::create('http://localhost/health/vault', 'POST', ['password' => $password]);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

it('resolves the localizable:false status from the origin via value(), not get()', function (): void {
    $localization = protectLocalizationPrivateLocalization();

    // The mechanism the fix depends on: the localization has no own status value,
    // but value() falls back to the origin's 'private'. get() (the old code) does not.
    expect($localization->get('status_and_visibility'))->toBeNull()
        ->and($localization->value('status_and_visibility'))->toBe('private');
});

it('404s a guest on a secondary-site localization of a private origin entry', function (): void {
    $localization = protectLocalizationPrivateLocalization();

    UserFacade::shouldReceive('current')->andReturn(null);

    $request = protectLocalizationRequest($localization);

    expect(fn () => app(ProtectEntry::class)->handle($request, fn () => response('PRIVATE BODY')))
        ->toThrow(NotFoundHttpException::class);
});

it('password-gates a guest on a localization of a password-protected origin entry', function (): void {
    /** @var Entry $origin */
    $origin = EntryFacade::make();
    $origin->collection('loc_probe');
    $origin->locale('default');
    $origin->slug('locked');
    $origin->published(true);
    $origin->data(['title' => 'Locked', 'status_and_visibility' => 'published', 'password_protected' => true]);
    $origin->save();

    /** @var Entry $localization */
    $localization = $origin->makeLocalization('health');
    $localization->published(true);
    $localization->save();

    // password_protected is localizable:false → only value() sees it on the localization.
    expect($localization->value('password_protected'))->toBeTruthy();

    UserFacade::shouldReceive('current')->andReturn(null);

    $request = protectLocalizationRequest($localization);

    $response = app(ProtectEntry::class)->handle($request, fn () => response('LOCKED BODY'));

    $body = $response->getContent();
    $bodyString = is_string($body) ? $body : '';
    expect($bodyString)
        ->toContain('Password Required')
        ->and($bodyString)->not->toContain('LOCKED BODY');
});

/*
 * UAMS-Web/uams-statamic-archive#1884 — the prompt appears on a localization (handle() gates on `value()`), but
 * verification read the stored password with `get()`, which is own-data-only. Both
 * `visibility_password` and `is_password_hashed` are `localizable: false`, so a
 * localization holds neither, the verifier's `is_string()` guard tripped, and every
 * submitted password was rejected — including the correct one.
 */

it('holds no own copy of either password field on a localization', function (): void {
    $localization = protectLocalizationPasswordProtectedLocalization('picard-four-seven-alpha');

    // The mechanism the bug rests on: get() sees nothing, value() falls back.
    expect($localization->get('visibility_password'))->toBeNull()
        ->and($localization->get('is_password_hashed'))->toBeNull()
        ->and($localization->value('is_password_hashed'))->toBeTrue()
        ->and($localization->value('visibility_password'))->toBeString();
});

it('unlocks a secondary-site localization with the correct password', function (): void {
    $localization = protectLocalizationPasswordProtectedLocalization('picard-four-seven-alpha');

    $request = protectLocalizationPasswordPost($localization, 'picard-four-seven-alpha');

    $response = app(ProtectEntry::class)->handle($request, fn () => response('LOCKED BODY'));

    $redirect = mustBe(RedirectResponse::class, $response, 'the unlock response');
    $sessionKey = 'entry_password_verified_'.mustBeString($localization->id(), 'the localization id');

    expect($redirect->getTargetUrl())->toBe('http://localhost/health/vault')
        ->and(Session::get($sessionKey))->toBeTrue();
});

it('still rejects an incorrect password on a secondary-site localization', function (): void {
    $localization = protectLocalizationPasswordProtectedLocalization('picard-four-seven-alpha');

    $request = protectLocalizationPasswordPost($localization, 'wrong-password');

    app(ProtectEntry::class)->handle($request, fn () => response('LOCKED BODY'));

    $sessionKey = 'entry_password_verified_'.mustBeString($localization->id(), 'the localization id');

    expect(Session::get($sessionKey))->toBeNull();
});

it('leaves the origin-site unlock path unchanged', function (): void {
    $localization = protectLocalizationPasswordProtectedLocalization('picard-four-seven-alpha');

    $origin = mustBe(Entry::class, $localization->origin(), 'the origin entry');

    $request = protectLocalizationPasswordPost($origin, 'picard-four-seven-alpha');

    $response = app(ProtectEntry::class)->handle($request, fn () => response('LOCKED BODY'));

    $sessionKey = 'entry_password_verified_'.mustBeString($origin->id(), 'the origin id');

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and(Session::get($sessionKey))->toBeTrue();
});
