<?php

declare(strict_types=1);

use App\Http\Middleware\ProtectEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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
 * They also pin the two responses the static cache must never store (the
 * `X-Statamic-Private` / `X-Statamic-Protected` headers on every gated response)
 * and the per-client, per-entry cap on password submissions.
 *
 * Mutation: no unexplained survivors on App\Http\Middleware\ProtectEntry (--covered-only,
 * across this file, ProtectEntryLocalizationTest and ProtectEntryStaticCacheHeadersTest),
 * re-measured on 2026-11-17 with the cache headers and the password throttle in place;
 * each survivor left carries a `@pest-mutate-ignore` with its reason in the class.
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
function protectEntryFakeEntry(bool $protected, string|int|null $id = 'entry-1', ?string $password = null, ?string $status = null, string $collectionHandle = 'posts', bool $selfOrigin = false): Entry
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
    // hashes on EntrySaving (UAMS-Web/uams-statamic-archive#1862), and `wordpress-importer-archive#5` then removed the
    // plain-text comparison PasswordVerifier used to fall back to — so a plain
    // string here would be rejected, and this helper would be asserting against a
    // state no writer can produce.
    // ---
    $entry->shouldReceive('get')->with('visibility_password')
        ->andReturn($password === null ? null : Hash::make($password));
    $entry->shouldReceive('get')->with('is_password_hashed', false)->andReturn(true);
    // A root entry: passwordFieldOwner() walks the origin chain before verifying (UAMS-Web/uams-statamic-archive#1884).
    // $selfOrigin points the origin at the entry itself, as cyclic stored content can.
    $entry->shouldReceive('origin')->andReturn($selfOrigin ? $entry : null);

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

/**
 * A password post for an entry from a fixed client address, with a matched route
 * that has no `entry` parameter.
 */
function protectEntryPasswordPost(string $url, string $password, string $ip): Request
{
    $request = Request::create($url, 'POST', ['password' => $password], [], [], ['REMOTE_ADDR' => $ip]);
    protectEntryBindRouteWithoutEntryParameter($request);

    return $request;
}

it('marks a private entry served to a privileged user as uncacheable', function (): void {
    // The static cache keys on the URL alone and runs before this middleware, so
    // an unmarked 200 for a super user would be stored and served to guests.
    $entry = protectEntryFakeEntry(protected: false, status: 'private');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/secret-page');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    protectEntryActingAs(super: true);

    $response = protectEntryMiddleware()->handle($request, fn () => response('PRIVATE BODY'));

    expect($response->headers->get('X-Statamic-Private'))->toBe('true');
    expect($response->headers->has('X-Statamic-Protected'))->toBeFalse();
});

it('leaves an unprotected entry response without the cache-skip headers', function (): void {
    $entry = protectEntryFakeEntry(protected: false, status: 'published');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/about');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);

    $response = protectEntryMiddleware()->handle($request, fn () => response('PUBLIC BODY'));

    expect($response->headers->has('X-Statamic-Private'))->toBeFalse();
    expect($response->headers->has('X-Statamic-Protected'))->toBeFalse();
});

it('carries the private header on the 404 so a half-measure cache cannot pin it', function (): void {
    $entry = protectEntryFakeEntry(protected: false, status: 'private');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/secret-page');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    protectEntryActingAs(super: null);

    try {
        protectEntryMiddleware()->handle($request, fn () => response('PRIVATE BODY'));
    } catch (NotFoundHttpException $e) {
        expect($e->getHeaders())->toHaveKey('X-Statamic-Private');
        expect($e->getHeaders()['X-Statamic-Private'])->toBe('true');

        return;
    }

    throw new RuntimeException('The private entry was served to a guest.');
});

it('marks the password prompt as uncacheable', function (): void {
    $entry = protectEntryFakeEntry(protected: true);
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/2024/01/01/secret');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);

    $response = protectEntryMiddleware()->handle($request, fn () => response('PROTECTED BODY'));

    expect($response->headers->get('X-Statamic-Protected'))->toBe('true');
    expect($response->headers->has('X-Statamic-Private'))->toBeFalse();
});

it('marks unlocked protected content as uncacheable', function (): void {
    // One visitor's unlock must not warm a cache every later visitor reads.
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-unlocked');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/2024/01/01/secret');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    Session::put('entry_password_verified_entry-unlocked', true);

    $response = protectEntryMiddleware()->handle($request, fn () => response('PROTECTED BODY'));

    expect($response->headers->get('X-Statamic-Protected'))->toBe('true');
    expect($response->headers->has('X-Statamic-Private'))->toBeFalse();
});

it('marks a private, password-protected entry with both headers once unlocked', function (): void {
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-both', status: 'private');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/secret-page');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    protectEntryActingAs(super: true);
    Session::put('entry_password_verified_entry-both', true);

    $response = protectEntryMiddleware()->handle($request, fn () => response('PRIVATE PROTECTED BODY'));

    expect($response->headers->get('X-Statamic-Private'))->toBe('true');
    expect($response->headers->get('X-Statamic-Protected'))->toBe('true');
});

it('refuses the eleventh password post in a minute with the prompt, a 429 and Retry-After', function (): void {
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-throttled', password: 'audit-secret-pw');
    $url = 'http://localhost/2024/01/01/secret';
    $ip = '203.0.113.10';

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);

    // The suite's file cache outlives a run, so start this client's count at zero.
    RateLimiter::clear(ProtectEntry::passwordAttemptKey('entry-throttled', $ip));

    // Ten wrong passwords are each answered as a wrong password, the tenth included.
    foreach (range(1, 10) as $attempt) {
        $response = protectEntryMiddleware()->handle(protectEntryPasswordPost($url, 'wrong-'.$attempt, $ip), fn () => response('PROTECTED BODY'));

        expect($response)->toBeInstanceOf(RedirectResponse::class, 'Attempt '.$attempt.' was not answered as a wrong password.');
    }

    // The eleventh is refused before the password is compared: the correct one is
    // refused too, and nothing is recorded in the session.
    $response = protectEntryMiddleware()->handle(protectEntryPasswordPost($url, 'audit-secret-pw', $ip), fn () => response('PROTECTED BODY'));

    expect($response->getStatusCode())->toBe(429);
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
    expect($response->headers->get('X-Statamic-Protected'))->toBe('true');
    expect(Session::get('entry_password_verified_entry-throttled'))->toBeNull();

    $body = $response->getContent();
    $body = is_string($body) ? $body : '';
    expect($body)->toContain('Password Required');
    expect($body)->toContain('Too many password attempts');
    expect($body)->not->toContain('PROTECTED BODY');
});

it('counts password posts per client address and entry', function (): void {
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-counted', password: 'audit-secret-pw');
    $url = 'http://localhost/2024/01/01/secret';

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);

    RateLimiter::clear(ProtectEntry::passwordAttemptKey('entry-counted', '203.0.113.20'));
    RateLimiter::clear(ProtectEntry::passwordAttemptKey('entry-counted', '203.0.113.21'));

    foreach (range(1, 10) as $attempt) {
        protectEntryMiddleware()->handle(protectEntryPasswordPost($url, 'wrong-'.$attempt, '203.0.113.20'), fn () => response('PROTECTED BODY'));
    }

    // The exhausted client is refused; another client at the same entry is not.
    $refused = protectEntryMiddleware()->handle(protectEntryPasswordPost($url, 'audit-secret-pw', '203.0.113.20'), fn () => response('PROTECTED BODY'));
    expect($refused->getStatusCode())->toBe(429);

    $allowed = protectEntryMiddleware()->handle(protectEntryPasswordPost($url, 'audit-secret-pw', '203.0.113.21'), fn () => response('PROTECTED BODY'));
    expect($allowed)->toBeInstanceOf(RedirectResponse::class);
    expect(Session::get('entry_password_verified_entry-counted'))->toBeTrue();
});

it('resets the attempt count on a correct password', function (): void {
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-reset', password: 'audit-secret-pw');
    $url = 'http://localhost/2024/01/01/secret';
    $ip = '203.0.113.30';
    $key = ProtectEntry::passwordAttemptKey('entry-reset', $ip);

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    RateLimiter::clear($key);

    foreach (range(1, 3) as $attempt) {
        protectEntryMiddleware()->handle(protectEntryPasswordPost($url, 'wrong-'.$attempt, $ip), fn () => response('PROTECTED BODY'));
    }
    expect(RateLimiter::attempts($key))->toBe(3);

    $response = protectEntryMiddleware()->handle(protectEntryPasswordPost($url, 'audit-secret-pw', $ip), fn () => response('PROTECTED BODY'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect(RateLimiter::attempts($key))->toBe(0);
});

it('keys the password-attempt count by a namespaced entry id and client address', function (): void {
    // The prefix keeps the count apart from every other RateLimiter key in the
    // shared cache, and the separators keep `a` + `b|c` apart from `a|b` + `c`.
    expect(ProtectEntry::passwordAttemptKey('entry-9', '203.0.113.9'))->toBe('entry_password_attempts|entry-9|203.0.113.9');
});

it('counts one entry\'s password posts apart from another entry\'s for the same client', function (): void {
    $exhausted = protectEntryFakeEntry(protected: true, id: 'entry-scope-a', password: 'audit-secret-pw');
    $other = protectEntryFakeEntry(protected: true, id: 'entry-scope-b', password: 'audit-secret-pw');
    $url = 'http://localhost/2024/01/01/secret';
    $ip = '203.0.113.40';

    RateLimiter::clear(ProtectEntry::passwordAttemptKey('entry-scope-a', $ip));
    RateLimiter::clear(ProtectEntry::passwordAttemptKey('entry-scope-b', $ip));

    Data::shouldReceive('findByRequestUrl')->andReturn($exhausted, $exhausted, $exhausted, $exhausted, $exhausted, $exhausted, $exhausted, $exhausted, $exhausted, $exhausted, $exhausted, $other);

    foreach (range(1, 10) as $attempt) {
        protectEntryMiddleware()->handle(protectEntryPasswordPost($url, 'wrong-'.$attempt, $ip), fn () => response('PROTECTED BODY'));
    }

    // The exhausted entry refuses this client; a second entry still takes its password.
    $refused = protectEntryMiddleware()->handle(protectEntryPasswordPost($url, 'audit-secret-pw', $ip), fn () => response('PROTECTED BODY'));
    expect($refused->getStatusCode())->toBe(429);

    $allowed = protectEntryMiddleware()->handle(protectEntryPasswordPost($url, 'audit-secret-pw', $ip), fn () => response('PROTECTED BODY'));
    expect($allowed)->toBeInstanceOf(RedirectResponse::class);
    expect(Session::get('entry_password_verified_entry-scope-b'))->toBeTrue();
});

it('flashes the invalid-password error on a wrong password post', function (): void {
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-wrong', password: 'audit-secret-pw');
    $ip = '203.0.113.50';

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    RateLimiter::clear(ProtectEntry::passwordAttemptKey('entry-wrong', $ip));

    $response = protectEntryMiddleware()->handle(protectEntryPasswordPost('http://localhost/2024/01/01/secret', 'not-it', $ip), fn () => response('PROTECTED BODY'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    $errors = mustBe(ViewErrorBag::class, Session::get('errors'), 'the flashed error bag');
    expect($errors->first('password'))->toBe('Invalid password');
});

it('shows the prompt for a get request that carries a password parameter', function (): void {
    // Only a POST is a submission: a password in the query string is not compared or counted.
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-get', password: 'audit-secret-pw');
    $request = protectEntryRequestWithoutEntryParameter('http://localhost/2024/01/01/secret?password=audit-secret-pw');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);

    $response = protectEntryMiddleware()->handle($request, fn () => response('PROTECTED BODY'));

    expect($response->getStatusCode())->toBe(200);
    expect(Session::get('entry_password_verified_entry-get'))->toBeNull();
    $body = $response->getContent();
    expect(is_string($body) ? $body : '')->toContain('Password Required');
});

it('counts a password post that arrives with no client address', function (): void {
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-no-ip', password: 'audit-secret-pw');
    $request = protectEntryPasswordPost('http://localhost/2024/01/01/secret', 'not-it', '203.0.113.60');
    $request->server->remove('REMOTE_ADDR');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    RateLimiter::clear(ProtectEntry::passwordAttemptKey('entry-no-ip', ''));

    $response = protectEntryMiddleware()->handle($request, fn () => response('PROTECTED BODY'));

    expect($request->ip())->toBeNull();
    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect(RateLimiter::attempts(ProtectEntry::passwordAttemptKey('entry-no-ip', '')))->toBe(1);
});

it('unlocks an entry whose id is an integer', function (): void {
    $entry = protectEntryFakeEntry(protected: true, id: 42, password: 'audit-secret-pw');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    RateLimiter::clear(ProtectEntry::passwordAttemptKey('42', '203.0.113.70'));

    $response = protectEntryMiddleware()->handle(protectEntryPasswordPost('http://localhost/2024/01/01/secret', 'audit-secret-pw', '203.0.113.70'), fn () => response('PROTECTED BODY'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect(Session::get('entry_password_verified_42'))->toBeTrue();
});

it('keys the unlock under an empty id when the entry has none', function (): void {
    $entry = protectEntryFakeEntry(protected: true, id: null, password: 'audit-secret-pw');

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    RateLimiter::clear(ProtectEntry::passwordAttemptKey('', '203.0.113.80'));

    protectEntryMiddleware()->handle(protectEntryPasswordPost('http://localhost/2024/01/01/secret', 'audit-secret-pw', '203.0.113.80'), fn () => response('PROTECTED BODY'));

    expect(Session::get('entry_password_verified_'))->toBeTrue();
});

it('compares a password submitted as a number', function (): void {
    // A JSON body can carry the password as a number; it is compared as its digits.
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-numeric', password: '12345');
    $request = Request::create('http://localhost/2024/01/01/secret', 'POST', ['password' => 12345], [], [], ['REMOTE_ADDR' => '203.0.113.90']);
    protectEntryBindRouteWithoutEntryParameter($request);

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    RateLimiter::clear(ProtectEntry::passwordAttemptKey('entry-numeric', '203.0.113.90'));

    $response = protectEntryMiddleware()->handle($request, fn () => response('PROTECTED BODY'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect(Session::get('entry_password_verified_entry-numeric'))->toBeTrue();
});

it('passes a request with no matched route through', function (): void {
    $request = Request::create('http://localhost/no-route');

    Data::shouldReceive('findByRequestUrl')->andReturn(null);

    $response = protectEntryMiddleware()->handle($request, fn () => response('OPEN BODY'));

    expect($request->route())->toBeNull();
    expect($response->getContent())->toBe('OPEN BODY');
});

it('stops at an entry whose origin is itself when verifying a password', function (): void {
    // Stored content can point an origin at its own entry; a public password post
    // must still be answered instead of walking the chain forever.
    $entry = protectEntryFakeEntry(protected: true, id: 'entry-cycle', password: 'audit-secret-pw', selfOrigin: true);

    Data::shouldReceive('findByRequestUrl')->andReturn($entry);
    RateLimiter::clear(ProtectEntry::passwordAttemptKey('entry-cycle', '203.0.113.100'));

    $response = protectEntryMiddleware()->handle(protectEntryPasswordPost('http://localhost/2024/01/01/secret', 'audit-secret-pw', '203.0.113.100'), fn () => response('PROTECTED BODY'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect(Session::get('entry_password_verified_entry-cycle'))->toBeTrue();
});
