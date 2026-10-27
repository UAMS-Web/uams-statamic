<?php

declare(strict_types=1);

use App\Providers\SecurityDefaultsServiceProvider;
use Illuminate\Support\Facades\Log;
use Statamic\API\ResourceAuthorizer;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Feature tests for SecurityDefaultsServiceProvider's production-only fail-safe
 * guards (UAMS-Web/uams-statamic-archive#897): the secure-session-cookie default and the fail-closed Content
 * API.
 *
 * Production is simulated with `app()->detectEnvironment(fn () => 'production')`
 * and reset in `afterEach`. The cookie guard's "explicit choice" signal is the
 * already-resolved `session.secure` config value (the provider reads config,
 * not the env, so it stays correct under `config:cache`): `null` means the
 * operator set nothing, a bool means they chose it. Each case sets that config
 * directly and resets it after.
 *
 * The Content-API fail-closed cases assert at the **request-time** layer — that
 * Statamic's own `ResourceAuthorizer::isAllowed()` denies — not merely that the
 * `statamic.api.enabled` config value flipped. The config flip alone is inert
 * under `php artisan route:cache` (the route group is baked from build time), so
 * asserting only the flag would be a false green; the request-time gate proves
 * the clamp denies even when the route is served from the cache.
 */

/**
 * Boot the provider once against the current environment + config state.
 */
function bootSecurityDefaultsProvider(): void
{
    // Instantiate the provider against the test app and run its boot guards
    (new SecurityDefaultsServiceProvider(app()))->boot();
}

/**
 * Switch the application into the given environment for a single test.
 *
 * @param  string  $environment  The environment name to simulate
 */
function setSecurityDefaultsEnvironment(string $environment): void
{
    // Override the detected environment so app()->environment() reports it
    app()->detectEnvironment(fn (): string => $environment);
}

beforeEach(function (): void {
    // Start each test from the "operator set nothing" baseline (null resolves
    // from env('SESSION_SECURE_COOKIE') with no default)
    config()->set('session.secure', null);
});

afterEach(function (): void {
    // Restore the test environment so later tests are unaffected
    setSecurityDefaultsEnvironment('testing');
});

// ---
// Guard 1: secure session cookie default in production
// ---

it('forces the session cookie secure in production when the env is unset', function (): void {
    // Simulate production with no explicit SESSION_SECURE_COOKIE choice
    setSecurityDefaultsEnvironment('production');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // The cookie is defaulted to Secure (HTTPS-only)
    expect(config('session.secure'))->toBeTrue();
});

it('respects an explicit SESSION_SECURE_COOKIE=false in production', function (): void {
    // Operator deliberately disabled the secure cookie — env('SESSION_SECURE_COOKIE')
    // resolved to the bool false, so session.secure is non-null
    config()->set('session.secure', false);

    // Simulate production
    setSecurityDefaultsEnvironment('production');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // The explicit choice is honored — not overridden to true
    expect(config('session.secure'))->toBeFalse();
});

it('respects an explicit SESSION_SECURE_COOKIE=true in production', function (): void {
    // Operator explicitly enabled the secure cookie
    config()->set('session.secure', true);

    // Simulate production
    setSecurityDefaultsEnvironment('production');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // The explicit value stays as the operator set it
    expect(config('session.secure'))->toBeTrue();
});

it('leaves the session cookie untouched outside production', function (): void {
    // Stay in the (non-production) testing environment with the env unset
    setSecurityDefaultsEnvironment('testing');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // The guard does not fire outside production — config stays null
    expect(config('session.secure'))->toBeNull();
});

// ---
// Guard 2: fail-closed Content API in production
// ---

it('fails the content api closed in production when enabled with a resource and no token', function (): void {
    // Enable the API and a resource with no auth token configured
    config()->set('statamic.api.enabled', true);
    config()->set('statamic.api.auth_token', null);
    config()->set('statamic.api.resources.collections', true);

    // Sanity-check the misconfiguration is genuinely exposed BEFORE the guard:
    // Statamic's request-time gate would allow the resource as configured.
    expect(app(ResourceAuthorizer::class)->isAllowed('api', 'collections'))->toBeTrue();

    // Expect a critical log naming the disabled-for-safety state, with the
    // full operator-facing remediation message
    Log::shouldReceive('critical')
        ->once()
        ->withArgs(fn (string $message): bool => $message === 'Statamic Content API disabled in production: it was enabled with at least one enabled resource but no STATAMIC_API_AUTH_TOKEN, which would have served unauthenticated content publicly. Set STATAMIC_API_AUTH_TOKEN (or disable the exposed resources) and redeploy to re-enable the API.');

    // Simulate production
    setSecurityDefaultsEnvironment('production');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // Request-time proof: Statamic's own per-request gate now DENIES the
    // resource, so the API serves nothing even if the route is cached and the
    // `enabled` flip never re-runs. This is the assertion that survives
    // `route:cache`, where checking only `statamic.api.enabled` is a false green.
    expect(app(ResourceAuthorizer::class)->isAllowed('api', 'collections'))->toBeFalse();

    // The resources map is emptied (the request-time clamp) ...
    expect(config('statamic.api.resources'))->toBe([]);

    // ... and the registration-time master switch is flipped off too.
    expect(config('statamic.api.enabled'))->toBeFalse();
});

it('keeps the content api enabled in production when an auth token is set', function (): void {
    // Enable the API and a resource, but with an auth token present
    config()->set('statamic.api.enabled', true);
    config()->set('statamic.api.auth_token', 'a-real-token');
    config()->set('statamic.api.resources.collections', true);

    // No critical log should fire when the API is properly authenticated
    Log::shouldReceive('critical')->never();

    // Simulate production
    setSecurityDefaultsEnvironment('production');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // The authenticated API stays enabled, and the request-time gate still
    // allows the resource — the clamp left both layers untouched.
    expect(config('statamic.api.enabled'))->toBeTrue();
    expect(app(ResourceAuthorizer::class)->isAllowed('api', 'collections'))->toBeTrue();
});

it('treats a whitespace-only auth token as no token and fails closed', function (): void {
    // Enable the API and a resource with a blank (whitespace-only) token
    config()->set('statamic.api.enabled', true);
    config()->set('statamic.api.auth_token', '   ');
    config()->set('statamic.api.resources.collections', true);

    // Expect the fail-closed critical log
    Log::shouldReceive('critical')->once();

    // Simulate production
    setSecurityDefaultsEnvironment('production');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // A whitespace-only token cannot authenticate — fail closed at both layers.
    expect(config('statamic.api.enabled'))->toBeFalse();
    expect(app(ResourceAuthorizer::class)->isAllowed('api', 'collections'))->toBeFalse();
});

it('leaves the content api alone in production when no resource is enabled', function (): void {
    // API enabled, no token, but every resource is off — nothing is exposed
    config()->set('statamic.api.enabled', true);
    config()->set('statamic.api.auth_token', null);
    config()->set('statamic.api.resources', [
        'collections' => false,
        'navs' => false,
        'taxonomies' => false,
    ]);

    // No critical log should fire when nothing is exposed
    Log::shouldReceive('critical')->never();

    // Simulate production
    setSecurityDefaultsEnvironment('production');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // With every resource off the API exposes nothing — the trigger does not
    // fire, the master switch stays enabled, and the resources map is left as
    // the operator set it (not emptied by the clamp).
    expect(config('statamic.api.enabled'))->toBeTrue();
    expect(config('statamic.api.resources'))->toBe([
        'collections' => false,
        'navs' => false,
        'taxonomies' => false,
    ]);
});

it('counts a non-empty per-resource allow-list as an enabled resource', function (): void {
    // Statamic also accepts an array of allowed handles per resource
    config()->set('statamic.api.enabled', true);
    config()->set('statamic.api.auth_token', null);
    config()->set('statamic.api.resources', [
        'collections' => ['pages'],
    ]);

    // The allow-list exposes content publicly, so fail closed
    Log::shouldReceive('critical')->once();

    // Simulate production
    setSecurityDefaultsEnvironment('production');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // A non-empty allow-list counts as exposure — fail closed at both layers.
    expect(config('statamic.api.enabled'))->toBeFalse();
    expect(config('statamic.api.resources'))->toBe([]);
    expect(app(ResourceAuthorizer::class)->isAllowed('api', 'collections'))->toBeFalse();
});

it('fails closed for a truthy non-true resource value (Statamic !== false semantics)', function (): void {
    // Statamic's ResourceAuthorizer treats a resource as enabled whenever its
    // value is !== false — including a truthy scalar that is not literally
    // `true` (e.g. a string). The guard must catch this, not just === true.
    config()->set('statamic.api.enabled', true);
    config()->set('statamic.api.auth_token', null);
    config()->set('statamic.api.resources', [
        'collections' => 'yes',
    ]);

    // Sanity-check: Statamic would expose this resource as configured.
    expect(app(ResourceAuthorizer::class)->isAllowed('api', 'collections'))->toBeTrue();

    // The truthy value exposes content publicly, so fail closed
    Log::shouldReceive('critical')->once();

    // Simulate production
    setSecurityDefaultsEnvironment('production');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // A non-`false` value counts as exposure — fail closed at both layers.
    expect(config('statamic.api.enabled'))->toBeFalse();
    expect(config('statamic.api.resources'))->toBe([]);
    expect(app(ResourceAuthorizer::class)->isAllowed('api', 'collections'))->toBeFalse();
});

it('leaves the content api alone in production when the api is disabled', function (): void {
    // The API master switch is off, so there is nothing to guard
    config()->set('statamic.api.enabled', false);
    config()->set('statamic.api.auth_token', null);
    config()->set('statamic.api.resources.collections', true);

    // No critical log should fire when the API is disabled
    Log::shouldReceive('critical')->never();

    // Simulate production
    setSecurityDefaultsEnvironment('production');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // A disabled API stays disabled and untouched
    expect(config('statamic.api.enabled'))->toBeFalse();
});

it('leaves the content api alone outside production even when misconfigured', function (): void {
    // The exact misconfiguration that would fail closed in production
    config()->set('statamic.api.enabled', true);
    config()->set('statamic.api.auth_token', null);
    config()->set('statamic.api.resources.collections', true);

    // No critical log should fire outside production
    Log::shouldReceive('critical')->never();

    // Stay in the non-production testing environment
    setSecurityDefaultsEnvironment('testing');

    // Run the provider's guards
    bootSecurityDefaultsProvider();

    // The guard does not fire outside production — API stays enabled
    expect(config('statamic.api.enabled'))->toBeTrue();
});
