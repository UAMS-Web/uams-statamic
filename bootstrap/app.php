<?php

declare(strict_types=1);

use App\Cache\ScopeAliasCache;
use App\Http\Middleware\ProtectEntry;
use App\Http\Middleware\RedirectWordPressQueryPermalinks;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ServeRedirectDomainRequests;
use App\Http\Middleware\TrustHosts;
use App\Support\ContentMirror;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustHosts as BaseTrustHosts;
use Statamic\Yaml\ParseException;

// Configure the application
$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Register listeners only through `EventServiceProvider::$listen`, so each
    // runs once and in the order listed there (UAMS-Web/uams-statamic-archive#3225, UAMS-Web/uams-statamic-archive#3240)
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware) {
        // Refuse a request for an address the site does not serve (UAMS-Web/uams-statamic-archive#3014), with the
        // app's own guard in place of Laravel's so the allowed list includes the
        // published redirect domains and the refusal happens before anything else
        $middleware->trustHosts();
        $middleware->replace(BaseTrustHosts::class, TrustHosts::class);

        // Add security headers middleware
        $middleware->append(SecurityHeaders::class);

        // Answer the vanity redirect domains' requests the edge passes on (UAMS-Web/uams-statamic-archive#2813).
        // Global rather than in a route group, so a redirect domain's request is
        // answered before routing and never reaches Statamic's catch-all route.
        $middleware->append(ServeRedirectDomainRequests::class);

        // Serve the WordPress ugly-permalink redirects (`?p=` / `?page_id=`) the
        // migration generator synthesizes (UAMS-Web/uams-statamic-archive#1657). Every other migration redirect is
        // served by the 404 template's {{ find_redirect_rule }}, which cannot reach
        // these: `/?p=123` has the site root as its *path*, so it renders the home
        // page with a 200 and the 404 template never runs. Registered first in the
        // group so a moved WordPress URL redirects before any other frontend
        // middleware — notably `ProtectEntry` — can act on the page it landed on.
        $middleware->appendToGroup('statamic.web', RedirectWordPressQueryPermalinks::class);

        // Enforce entry password protection on Statamic's frontend routes. The
        // importer addon ships the `ProtectEntry` middleware but never actually
        // attaches it to a live route group, so protected entries render to
        // anyone at their canonical permalink. Append it to `statamic.web` (the
        // group FrontendController runs under) so it gates every frontend
        // request, after Statamic's session middleware so the unlock flag works.
        $middleware->appendToGroup('statamic.web', ProtectEntry::class);

        // Exempt the knowledge base engagement beacons (UAMS-Web/uams-statamic-archive#286 ratings, UAMS-Web/uams-statamic-archive#287 views)
        // from CSRF. They are posted from JavaScript on article pages that may be
        // served from the static cache, so no fresh per-session token is available
        // to embed; the endpoints change only a public engagement counter, and are
        // guarded instead by per-session repeat prevention, bot filtering, and the
        // `kb-engagement` rate limiter (see routes/web.php).
        $middleware->validateCsrfTokens(except: [
            '!/kb/articles/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Name a torn test content mirror as the cause when a request fails on a YAML parse
        // error (UAMS-Web/uams-statamic-archive#2762). Outside the test env the mirror is content/ itself, so this never fires.
        $exceptions->map(function (ParseException $exception): Throwable {
            $diagnosis = ContentMirror::parseFailureDiagnosis($exception);

            return $diagnosis === null ? $exception : new RuntimeException($diagnosis, 0, $exception);
        });
    })->create();

// ---
// Bind scope alias cache before any bootstrapper runs so it is available
// when Statamic discovers query scopes (Laravel 12 hook).
// ---
$app->beforeBootstrapping(
    LoadEnvironmentVariables::class,
    function ($app) {
        $app->singleton(
            ScopeAliasCache::class,
            fn () => new ScopeAliasCache
        );
    }
);

// Return the application
return $app;
