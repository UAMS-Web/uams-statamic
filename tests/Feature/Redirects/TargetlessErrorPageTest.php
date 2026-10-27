<?php

declare(strict_types=1);

use App\Exceptions\SiteHttpException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Statamic\View\Cascade;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

use function Pest\Laravel\withoutVite;

/**
 * Coverage for the targetless error pages — 403, 410 and 451 (UAMS-Web/uams-statamic-archive#1942).
 *
 * ## Why this file exists at all
 *
 * The redirect suites render `errors/_redirects` **directly**, which proves the
 * partial aborts with the right status and stops there. What Laravel then did
 * with that abort is exactly where UAMS-Web/uams-statamic-archive#1942 lived: the message reached no visitor,
 * and the page was the framework's fallback rather than the site's. No test in
 * the suite could see that, because none of them let the exception reach the
 * handler.
 *
 * These do. Each hands the exception to the real exception handler — the exact
 * link that was missing — and asserts against the bytes it produces: the site's
 * layout, rendered through Statamic's `View`, with the message in it.
 *
 * ## Why the handler rather than a full HTTP request
 *
 * Neither harness that drives a real request can see this, and both fail
 * silently rather than loudly:
 *
 *   - **`tests/Browser`**, which the ticket proposed on the reasoning that it
 *     drives a real HTTP server, deliberately omits the `content/` submodule for
 *     cost, so every real front-end route 500s there — the same constraint that
 *     makes `FrontendAccessibilityTest` scan rendered fragments instead of live
 *     pages.
 *   - **An in-process `$this->get()`** reaches the 404 template but never
 *     matches a rule, because Statamic binds its `Cascade` as a singleton around
 *     the request present when the app booted. In a test that is the empty
 *     bootstrap request, so `current_uri` is `URL::tidy($request->path())` = `/`
 *     for every request the test makes, whatever URI it asks for. Instrumented
 *     and measured: the tag ran twice per request and saw `uri: "/"` both times.
 *     That is the concrete mechanism behind the ticket's report that "Antlers
 *     binds none of the tag's row values" — and a rule seeded for the URI under
 *     test simply never matches, so every assertion passes for the wrong reason.
 *
 * Handing the exception to `ExceptionHandler::render()` sidesteps both. It is
 * the same call `Kernel::handle()`'s catch makes, so everything downstream of
 * the throw — `method_exists($e, 'render')`, `SiteHttpException::render()`,
 * `RendersHttpExceptions::contents()`, Statamic's `View` and the site layout —
 * runs exactly as it does in production. What it does not cover is the framework
 * glue that reaches it, which is stated here rather than left implied.
 *
 * The link *before* the throw is covered elsewhere: `RedirectServingTest` drives
 * the real partial to the tag with a stored rule's code, and
 * `RespondWithStatusTest` pins which exception the tag then raises. Raising it
 * directly here also needs no write to the `redirects` global, which lives in
 * the `content/` submodule where a test fixture must never land (UAMS-Web/uams-statamic-archive#1873 / UAMS-Web/uams-statamic-archive#1920).
 *
 * All of the above was cross-checked against the running site, where a seeded
 * rule for each code was served over real HTTP with `APP_DEBUG` both on and off.
 *
 * @command  php artisan test --compact tests/Feature/Redirects/TargetlessErrorPageTest.php
 */
uses(TestCase::class);

covers(SiteHttpException::class);

beforeEach(function (): void {
    // These pages render the full site layout, which resolves its assets through
    // the Vite manifest at `public/build/manifest.json`. That directory is
    // gitignored and only written by `npm run build`, which CI runs in the
    // separate `build` job — the `phpunit` job never has it. Without this, every
    // case here dies on `ViteManifestNotFoundException` in CI while passing on any
    // developer machine that has built once, which is the worst of both.
    withoutVite();
});

/**
 * Render a {@see SiteHttpException} through the real exception handler.
 *
 * @param  int  $status  The HTTP status to raise
 * @param  string  $message  The message the rule would have carried
 * @return Response The response a visitor receives
 */
function serveTargetlessError(int $status, string $message = ''): Response
{
    /** @var ExceptionHandler $handler */
    $handler = app(ExceptionHandler::class);

    // `ExceptionHandler::render()` is contractually a Symfony response, so no
    // narrowing is needed (and an `instanceof` here reads as always-true to
    // PHPStan, which is right).
    return $handler->render(
        Request::create('/retired-page'),
        new SiteHttpException($status, $message)
    );
}

it('renders the site page for a targetless error, carrying the rule message', function (int $status, string $heading): void {
    $response = serveTargetlessError($status, 'This program closed in 2019.');

    expect($response->getStatusCode())->toBe($status);

    $body = (string) $response->getContent();

    expect($body)
        // The site's own page rather than the framework fallback: a full document
        // that reached the site layout.
        ->toContain('<!doctype html>')
        ->toContain('<h1>'.$heading.'</h1>')
        // The whole point of the ticket — the message reaches the visitor.
        ->toContain('This program closed in 2019.')
        // ...and so do the recovery aids, which the generic page has none of.
        ->toContain('error-search-heading')
        ->toContain('error-links-heading');
})->with([
    'forbidden' => [403, 'Access restricted'],
    'gone' => [410, 'This page is gone'],
    'legal reasons' => [451, 'Unavailable for legal reasons'],
]);

it('falls back to the page\'s own sentence when the rule carries no message', function (): void {
    // A rule may legitimately carry no message; the page must still explain
    // itself rather than render an empty lead paragraph under the heading.
    $body = (string) serveTargetlessError(410)->getContent();

    expect($body)
        ->toContain('<h1>This page is gone</h1>')
        ->toContain('This page was permanently removed and has no replacement.');
});

it('escapes editor markup in the message rather than rendering it', function (): void {
    // `message` is CP textarea input and Antlers is raw-by-default, so an
    // unescaped render is stored XSS. Verified against the running site before
    // the fix: `<img src=x onerror=alert(1)>` reached the 404 page as live markup.
    $body = (string) serveTargetlessError(410, 'Closed <img src=x onerror=alert(1)> here')->getContent();

    expect($body)
        ->toContain('&lt;img src=x onerror=alert(1)&gt;')
        ->not->toContain('<img src=x onerror=alert(1)>');
});

it('keeps the line breaks the message textarea allows', function (): void {
    // `nl2br` runs after `entities`, so a multi-line message stays readable
    // without the escaping being undone.
    $body = (string) serveTargetlessError(410, "First line.\nSecond line.")->getContent();

    expect($body)->toContain('First line.<br />');
});

it('does not answer a 403 from the framework minimal page', function (): void {
    // Laravel ships its own `errors::403`, which resolves ahead of the `4xx`
    // fallback and won on this app until UAMS-Web/uams-statamic-archive#1942 added an app-level override. It
    // did render the message, but on an unstyled page titled "Forbidden", with
    // no branding and none of the recovery aids.
    $body = (string) serveTargetlessError(403, 'Staff only.')->getContent();

    expect($body)
        ->toContain('<h1>Access restricted</h1>')
        ->not->toContain('<title>Forbidden</title>');
});

it('publishes the response code on the cascade, as Statamic\'s own error path does', function (): void {
    // `RendersHttpExceptions::contents()` publishes `response_code` so a template
    // can branch on it; overriding `contents()` to add the message must not drop
    // that. Asserted against the cascade rather than the rendered page because no
    // view consumes it today — which is exactly how it would be lost silently.
    serveTargetlessError(410, 'Gone.');

    expect(app(Cascade::class)->get('response_code'))->toBe(410)
        ->and(app(Cascade::class)->get('response_message'))->toBe('Gone.');
});
