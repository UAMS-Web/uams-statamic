<?php

declare(strict_types=1);

use App\Http\Middleware\ProtectEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Auth\User;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Data;
use Statamic\Facades\User as UserFacade;
use Statamic\Structures\Page;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Regression tests for {@see ProtectEntry}.
 *
 * The middleware gates entry access two ways (UAMS-Web/uams-statamic-archive#223): a `private` status (404 for
 * anyone who cannot publish the collection) and `password_protected`. It previously
 * read the entry only from the route `entry` parameter, which the data-driven
 * frontend catch-all (parameter `segments`) never sets — so a protected entry
 * served at its canonical permalink fell straight through unprotected. These tests
 * pin that URL-resolution fallback, the private/publish-permission gate, and the
 * unaffected pass-through cases. The multisite origin-fallback (`value()` vs
 * `get()`) is covered end-to-end by {@see ProtectEntryLocalizationTest}.
 *
 * Mutation: 100% on App\Http\Middleware\ProtectEntry (57 mutants, --covered-only,
 * across this file + ProtectEntryLocalizationTest), re-measured on 2026-08-04 with
 * passwordFieldOwner() in place (UAMS-Web/uams-statamic-archive#1884).
 *
 * @command  php artisan test --compact tests/Feature/Http/Middleware/ProtectEntryTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

beforeEach(function (): void {
    // The `password_prompt` Blade view references `$errors`, normally shared
    // by the web group's ShareErrorsFromSession middleware. Share an empty
    // bag so the view renders when the middleware short-circuits to it.
    View::share('errors', new ViewErrorBag);
});

/**
 * Resolve a fresh middleware instance from the container.
 */
function protectEntryMiddleware(): ProtectEntry
{
    return app(ProtectEntry::class);
}

/**
 * Build a Mockery entry double mirroring the original `fakeEntry` helper.
 */
function protectEntryFakeEntry(bool $protected, string $id = 'entry-1', ?string $password = null, ?string $status = null, string $collectionHandle = 'posts'): Entry
{
    $entry = Mockery::mock(Entry::class);
    // The middleware reads the gate fields via value() (origin fallback for the
    // localizable:false status/password fields); PasswordVerifier reads the
    // password fields via get().
    $entry->shouldReceive('value')->with('status_and_visibility')->andReturn($status);
    $entry->shouldReceive('value')->with('password_protected')->andReturn($protected);
    $entry->shouldReceive('id')->andReturn($id);
    // ---
    // Store the password the way it is actually stored now. HashVisibilityPassword
    // hashes on EntrySaving (UAMS-Web/uams-statamic-archive#1862), and `wordpress-importer#5` then removed the
    // plain-text comparison PasswordVerifier used to fall back to — so a plain
    // string here would be rejected, and this helper would be asserting against a
    // state no writer can produce.
    // ---
    $entry->shouldReceive('get')->with('visibility_password')
        ->andReturn($password === null ? null : Hash::make($password));
    $entry->shouldReceive('get')->with('is_password_hashed', false)->andReturn(true);
    // A root entry: passwordFieldOwner() walks the origin chain before verifying (UAMS-Web/uams-statamic-archive#1884).
    $entry->shouldReceive('origin')->andReturn(null);

    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn($collectionHandle);
    $entry->shouldReceive('collection')->andReturn($collection);

    return $entry;
}

/**
 * Fake the current CP user (null = guest) for the private-visibility check.
 *
 * @param  array<int, string>  $permissions
 */
function protectEntryActingAs(?bool $super, array $permissions = []): void
{
    if ($super === null) {
        UserFacade::shouldReceive('current')->andReturn(null);

        return;
    }

    $user = Mockery::mock(User::class);
    $user->shouldReceive('isSuper')->andReturn($super);
    $user->shouldReceive('hasPermission')->andReturnUsing(fn (string $p): bool => in_array($p, $permissions, true));

    UserFacade::shouldReceive('current')->andReturn($user);
}

/**
 * Bind a matched route on the request whose `entry` parameter is absent,
 * mirroring the data-driven catch-all (it binds `segments` instead).
 */
function protectEntryBindRouteWithoutEntryParameter(Request $request): void
{
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('parameter')->with('entry')->andReturn(null);
    $request->setRouteResolver(fn () => $route);
}

/**
 * Create a request for the given URL with a matched route that has no `entry`.
 */
function protectEntryRequestWithoutEntryParameter(string $url): Request
{
    $request = Request::create($url);
    protectEntryBindRouteWithoutEntryParameter($request);

    return $request;
}

it('gates a protected entry resolved from the request url', function (): void {
    $entry = protectEntryFakeEntry(protected: true);

    // The catch-all route binds `segments`, never `entry`.
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/2024/01/01/secret');

    Data::shouldReceive('findByRequestUrl')
        ->once()
        ->with('http://localhost/2024/01/01/secret')
        ->andReturn($entry);

    $nextCalled = false;
    $response = protectEntryMiddleware()->handle($request, function () use (&$nextCalled) {
        $nextCalled = true;

        return response('PROTECTED BODY');
    });

    expect($nextCalled)->toBeFalse('Protected content must not be served before unlock.');
    expect($response->getStatusCode())->toBe(200);

    $body = $response->getContent();
    $body = is_string($body) ? $body : '';
    expect($body)->toContain('Password Required');
    expect($body)->not->toContain('PROTECTED BODY');
});

it('unwraps a protected page wrapper and gates it', function (): void {
    $entry = protectEntryFakeEntry(protected: true);

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('entry')->andReturn($entry);

    $request = protectEntryRequestWithoutEntryParameter('http://localhost/about-uams');

    Data::shouldReceive('findByRequestUrl')->andReturn($page);

    $nextCalled = false;
    $response = protectEntryMiddleware()->handle($request, function () use (&$nextCalled) {
        $nextCalled = true;

        return response('PROTECTED BODY');
    });

    expect($nextCalled)->toBeFalse();

    $body = $response->getContent();
    $body = is_string($body) ? $body : '';
    expect($body)->toContain('Password Required');
});

it('serves protected content when the session records an unlock', function (): void {
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-123');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/2024/01/01/secret');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    Session::put('entry_password_verified_entry-123', true);

    $nextCalled = false;
    $response = protectEntryMiddleware()->handle($request, function () use (&$nextCalled) {
        $nextCalled = true;

        return response('PROTECTED BODY');
    });

    expect($nextCalled)->toBeTrue();

    $body = $response->getContent();
    $body = is_string($body) ? $body : '';
    expect($body)->toContain('PROTECTED BODY');
});

it('flags the session and redirects on a correct password post', function (): void {
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-123', password: 'audit-secret-pw');

    $request = Request::create('http://localhost/2024/01/01/secret', 'POST', ['password' => 'audit-secret-pw']);
    protectEntryBindRouteWithoutEntryParameter($request);

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);

    $response = protectEntryMiddleware()->handle($request, fn () => response('PROTECTED BODY'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    /** @var RedirectResponse $response */
    expect($response->getTargetUrl())->toBe('http://localhost/2024/01/01/secret');
    expect(Session::get('entry_password_verified_entry-123'))->toBeTrue();
});

it('passes an unprotected entry straight through', function (): void {
    $entry = protectEntryFakeEntry(protected: false);
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/about');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);

    $nextCalled = false;
    $response = protectEntryMiddleware()->handle($request, function () use (&$nextCalled) {
        $nextCalled = true;

        return response('PUBLIC BODY');
    });

    expect($nextCalled)->toBeTrue();

    $body = $response->getContent();
    $body = is_string($body) ? $body : '';
    expect($body)->toContain('PUBLIC BODY');
});

it('passes a non-entry url through without gating', function (): void {
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/blog');

    Data::shouldReceive('findByRequestUrl')->andReturn(null);

    $nextCalled = false;
    protectEntryMiddleware()->handle($request, function () use (&$nextCalled) {
        $nextCalled = true;

        return response('PUBLIC BODY');
    });

    expect($nextCalled)->toBeTrue();
});

it('404s a private entry for a guest', function (): void {
    $entry = protectEntryFakeEntry(protected: false, status: 'private');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/secret-page');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    protectEntryActingAs(super: null);

    expect(fn () => protectEntryMiddleware()->handle($request, fn () => response('PRIVATE BODY')))
        ->toThrow(NotFoundHttpException::class);
});

it('404s a private entry for a logged-in user without collection rights', function (): void {
    $entry = protectEntryFakeEntry(protected: false, status: 'private', collectionHandle: 'posts');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/secret-page');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    protectEntryActingAs(super: false, permissions: ['edit pages entries']); // rights on a different collection

    expect(fn () => protectEntryMiddleware()->handle($request, fn () => response('PRIVATE BODY')))
        ->toThrow(NotFoundHttpException::class);
});

it('serves a private entry to a super user', function (): void {
    $entry = protectEntryFakeEntry(protected: false, status: 'private');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/secret-page');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    protectEntryActingAs(super: true);

    $nextCalled = false;
    $response = protectEntryMiddleware()->handle($request, function () use (&$nextCalled) {
        $nextCalled = true;

        return response('PRIVATE BODY');
    });

    expect($nextCalled)->toBeTrue();
    $body = $response->getContent();
    expect(is_string($body) ? $body : '')->toContain('PRIVATE BODY');
});

it('serves a private entry to an editor who can publish the collection', function (): void {
    $entry = protectEntryFakeEntry(protected: false, status: 'private', collectionHandle: 'posts');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/secret-page');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    protectEntryActingAs(super: false, permissions: ['publish posts entries']);

    $nextCalled = false;
    protectEntryMiddleware()->handle($request, function () use (&$nextCalled) {
        $nextCalled = true;

        return response('PRIVATE BODY');
    });

    expect($nextCalled)->toBeTrue();
});

it('404s a private entry for a contributor who can edit but not publish the collection', function (): void {
    // The `contributor` role holds view/edit (to author drafts) but no publish, so
    // it must not read others' private entries — private is admins/editors only (UAMS-Web/uams-statamic-archive#223).
    $entry = protectEntryFakeEntry(protected: false, status: 'private', collectionHandle: 'posts');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/secret-page');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    protectEntryActingAs(super: false, permissions: ['view posts entries', 'edit posts entries']);

    expect(fn () => protectEntryMiddleware()->handle($request, fn () => response('PRIVATE BODY')))
        ->toThrow(NotFoundHttpException::class);
});

it('uses the route entry parameter without the url fallback', function (): void {
    $entry = protectEntryFakeEntry(protected: true);

    $route = Mockery::mock(Route::class);
    $route->shouldReceive('parameter')->with('entry')->andReturn($entry);
    $request = Request::create('http://localhost/entries/secret');
    $request->setRouteResolver(fn () => $route);

    Data::shouldReceive('findByRequestUrl')->never();

    $nextCalled = false;
    $response = protectEntryMiddleware()->handle($request, function () use (&$nextCalled) {
        $nextCalled = true;

        return response('PROTECTED BODY');
    });

    expect($nextCalled)->toBeFalse();

    $body = $response->getContent();
    $body = is_string($body) ? $body : '';
    expect($body)->toContain('Password Required');
});
