<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider that hardens production security posture with fail-safe
 * runtime guards for configuration that would otherwise silently weaken the
 * site when a production env var is forgotten (UAMS-Web/uams-statamic-archive#897).
 *
 * Two guards apply **only in the production environment**, so local and
 * testing are unaffected:
 *
 *   1. **Secure session cookie default.** `config/session.php` reads
 *      `env('SESSION_SECURE_COOKIE')` with no default, so the session cookie
 *      is not forced `Secure` in production unless an operator sets the env.
 *      When the operator has made no explicit choice, this provider defaults
 *      the cookie to `Secure` (HTTPS-only). An explicit env value — including a
 *      deliberate `false` — is always respected.
 *
 *   2. **Fail-closed Content API.** The Statamic Content API `auth_token`
 *      defaults to null. Every resource defaults to `false`, so enabling the
 *      API alone exposes nothing — but enabling a resource with no `auth_token`
 *      makes that resource **public**. When the API is enabled, at least one
 *      resource is enabled, and no `auth_token` is set, this provider disables
 *      the Content API outright (a scoped fail-closed that serves nothing,
 *      rather than serving unauthenticated data) and logs a `critical` message
 *      naming the misconfiguration. Disabling only the API — rather than
 *      throwing at boot — keeps unrelated requests serving normally on a
 *      health-system site.
 *
 *      The clamp is applied at **two layers** so it holds even under
 *      `php artisan route:cache` (standard production), where the API route
 *      group is baked at build time from `statamic.api.enabled` and a runtime
 *      flip of that flag unregisters nothing:
 *
 *        - `statamic.api.enabled` → `false` (unregisters the routes when they
 *          are NOT cached, e.g. local or an uncached deploy); and
 *        - `statamic.api.resources` → `[]` (the request-time clamp). Statamic's
 *          `ResourceAuthorizer::isAllowed()` re-reads
 *          `config("statamic.api.resources.<r>", false) !== false` on every
 *          request, so an empty resources map makes every resource abort as
 *          disabled even when the route itself is served from the cache.
 *
 *      **Custom API auth middleware caveat:** this guard treats `auth_token` as
 *      the only authentication signal. An operator who deliberately exposes the
 *      API publicly behind a *custom* auth layer via `statamic.api.middleware`
 *      (instead of the built-in `auth_token`) is clamped too — fail-safe by
 *      design. To opt out, set a `STATAMIC_API_AUTH_TOKEN` sentinel (which the
 *      custom middleware can ignore) so this guard sees the API as authenticated
 *      and leaves it enabled.
 *
 * HSTS is intentionally out of scope: it is already documented in
 * `.env.example` and covered by `tests/Feature/Http/SecurityHeadersMiddlewareTest.php`.
 */
class SecurityDefaultsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * Both guards run in `boot()` so they mutate the already-loaded config
     * repository the session manager and Statamic API read from at request
     * time.
     */
    public function boot(): void
    {
        // Only harden production; local and testing keep their permissive
        // defaults so HTTP dev servers and the test runner are unaffected.
        if (! $this->app->environment('production')) {
            return;
        }

        // Apply each production-only fail-safe guard.
        $this->forceSecureSessionCookieDefault();
        $this->failContentApiClosedWhenUnauthenticated();
    }

    /**
     * Force the session cookie to `Secure` in production when the operator has
     * made no explicit choice.
     *
     * "Explicit choice" is detected from the already-resolved
     * `session.secure` config value rather than re-reading the env. The config
     * key sources `env('SESSION_SECURE_COOKIE')` with no default, so it
     * resolves to `null` only when the operator has set nothing — an explicit
     * `true` or `false` resolves to that bool. Reading the resolved config (not
     * the env) keeps the detection correct under `config:cache`, where the
     * `.env` file is no longer loaded at runtime but the baked-in config value
     * still reflects what the operator set when the cache was built.
     *
     * We do NOT override an explicit value: an operator who sets
     * `SESSION_SECURE_COOKIE=false` (e.g. terminating TLS upstream in a way
     * that needs it off) keeps that decision.
     */
    private function forceSecureSessionCookieDefault(): void
    {
        // Respect an explicit operator choice — any non-null resolved value,
        // even a deliberate `false`.
        if (config('session.secure') !== null) {
            return;
        }

        // No explicit choice: default the production session cookie to
        // HTTPS-only so it is never transmitted over plain HTTP.
        config(['session.secure' => true]);
    }

    /**
     * Fail the Content API closed in production when it is enabled with an
     * enabled resource but no `auth_token`.
     *
     * Without an `auth_token`, any enabled resource is served publicly. Rather
     * than expose unauthenticated content, clamp the whole Content API so it
     * serves nothing, and log a `critical` so the misconfiguration is loud. The
     * rest of the site keeps serving normally.
     *
     * The clamp is two-layered so it survives `route:cache`: flipping
     * `statamic.api.enabled` unregisters the routes only when they are NOT
     * cached, while emptying `statamic.api.resources` denies every resource at
     * request time via `ResourceAuthorizer::isAllowed()` even when the route is
     * served from the cache.
     */
    private function failContentApiClosedWhenUnauthenticated(): void
    {
        // Nothing to guard when the Content API is disabled.
        if (! config('statamic.api.enabled')) {
            return;
        }

        // An auth_token makes every enabled resource authenticated — safe.
        if (! $this->isBlank(config('statamic.api.auth_token'))) {
            return;
        }

        // With no token, exposure requires at least one enabled resource;
        // when all resources are off the API serves nothing already.
        if (! $this->hasEnabledApiResource()) {
            return;
        }

        // Misconfigured: enabled API + an enabled resource + no auth_token.
        // Registration-time clamp: flip the master switch off so the route
        // group never registers when routes are NOT cached.
        config(['statamic.api.enabled' => false]);

        // Request-time clamp: empty the resources map so Statamic's per-request
        // ResourceAuthorizer::isAllowed() denies every resource even when the
        // baked route is served from a `route:cache`d build.
        config(['statamic.api.resources' => []]);

        // Surface the disabled-for-safety state loudly for operators.
        Log::critical('Statamic Content API disabled in production: it was enabled with at least one enabled resource but no STATAMIC_API_AUTH_TOKEN, which would have served unauthenticated content publicly. Set STATAMIC_API_AUTH_TOKEN (or disable the exposed resources) and redeploy to re-enable the API.');
    }

    /**
     * Determine whether any Content API resource is enabled.
     *
     * Detection mirrors Statamic's own request-time semantics:
     * `ResourceAuthorizer::isAllowed()` treats a resource as enabled whenever
     * its configured value is `!== false`. So ANY non-`false` entry — `true`, a
     * string, an allow-list array (including the empty `[]` Statamic still
     * exposes), or any other truthy value — counts as enabled here, so the
     * guard fails closed in exactly the configurations Statamic would expose.
     */
    private function hasEnabledApiResource(): bool
    {
        // Read the per-resource toggles; treat a missing block as none enabled.
        $resources = config('statamic.api.resources');

        // A non-array (or absent) resources config exposes nothing.
        if (! is_array($resources)) {
            return false;
        }

        // Mirror ResourceAuthorizer::isAllowed(): any value !== false is exposed.
        return \array_any($resources, static fn ($resource): bool => $resource !== false);
    }

    /**
     * Determine whether a configured value is effectively unset.
     *
     * Treats null and the empty string as "no token". A whitespace-only token
     * is also rejected, since it cannot authenticate a real request.
     */
    private function isBlank(mixed $value): bool
    {
        // Non-strings (null included) carry no usable token.
        if (! is_string($value)) {
            return true;
        }

        // An empty or whitespace-only string is not a usable token.
        return trim($value) === '';
    }
}
