<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Globals\Variables;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\StacheIsolatedTestCase;
use UAMSWeb\Aether\Redirects\Exceptions\SiteHttpException;
use UAMSWeb\Aether\Redirects\Services\NotFoundRedirectResolver;

/**
 * End-to-end coverage for serving a stored redirect rule (UAMS-Web/uams-statamic-archive#1933 / UAMS-Web/uams-statamic-archive#1934).
 *
 * A rule is served by the `uams-web/aether-redirects` add-on's
 * {@see NotFoundRedirectResolver}, which answers a request that is about to render
 * a 404 (#398). This file drives it against this site's own rules, error views and
 * layout, so a rule the site stores but the add-on serves wrongly still fails here.
 *
 * The resolver is called rather than requested over HTTP because the isolated
 * Stache base fakes the item filesystem, which a full kernel pass discards.
 *
 * @command  php artisan test --compact tests/Feature/Redirects/RedirectServingTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->save();

    /** @var Statamic\Sites\Site $site */
    $site = Site::default();
    /** @var Variables $vars */
    $vars = $set->makeLocalization($site->handle());
    $vars->data(['redirects' => []]);
    $vars->save();
});

/**
 * Store the given redirect rows on the default site's `redirects` global.
 *
 * @param  list<array<string, mixed>>  $rows  The redirect rows to store
 */
function seedRedirectRules(array $rows): void
{
    /** @var Statamic\Globals\GlobalSet $global */
    $global = GlobalSet::findByHandle('redirects');

    /** @var Statamic\Sites\Site $site */
    $site = Site::default();
    $handle = $site->handle();

    /** @var Variables $vars */
    $vars = $global->in(\is_string($handle) ? $handle : 'default');
    $vars->data(['redirects' => $rows]);
    $vars->save();
}

/**
 * Build a stored redirect row with overridable defaults.
 *
 * @param  string  $source  The stored source path or pattern
 * @param  array<string, mixed>  $overrides  Values to override the defaults
 * @return array<string, mixed> The redirect row
 */
function storedRedirectRule(string $source, array $overrides = []): array
{
    return array_merge([
        'id' => 'rule-1',
        'type' => 'redirect',
        'source' => $source,
        'target_type' => 'url',
        'target_url' => '/destination',
        'response' => '301',
        'order' => 30,
        'use_regex' => false,
        'enabled' => true,
    ], $overrides);
}

/**
 * Ask the add-on's 404 resolver what it answers a request URI with.
 *
 * @param  string  $requestUri  The request URI, query string included
 * @return array{status: int, location: string|null} The status and Location it answered with, or 200 when it left the 404 to render
 */
function serveRedirectFor(string $requestUri): array
{
    // Bind the request the resolver reads its path, query string and site from.
    $request = Request::create($requestUri);
    app()->instance('request', $request);

    $response = app(NotFoundRedirectResolver::class)->responseFor($request);

    // No answer, so the 404 render carries on.
    if ($response === null) {
        return [
            'status' => 200,
            'location' => null,
        ];
    }

    // A move carries its target; a targetless error page carries none.
    return [
        'status' => $response->getStatusCode(),
        'location' => $response->isRedirection() ? $response->headers->get('Location') : null,
    ];
}

/**
 * Render the body-side message partial for `$requestUri` and return its output.
 *
 * The 404 template includes this inside `layout/entry-content`, where a `404`
 * rule's message is actually visible; the resolver leaves a `404` rule alone.
 * Rendered directly for the same reason the resolver is called directly; see this
 * file's header.
 *
 * @param  string  $requestUri  The request URI, query string included
 * @return string The partial's rendered output
 */
function renderRedirectMessageFor(string $requestUri): string
{
    // Bind the request the tag reads its path and query string from.
    $request = Request::create($requestUri);
    app()->instance('request', $request);

    // Derive current_uri exactly as the Statamic cascade does.
    $currentUri = URL::tidy($request->path());

    return (string) view('errors/_redirect_message', ['current_uri' => $currentUri])->render();
}

it('serves a rule whose stored source carries a trailing slash', function (): void {
    // 1,637 rules in the export corpus are stored this way and matched nothing.
    seedRedirectRules([
        storedRedirectRule('/course/', ['target_url' => '/courses']),
    ]);

    expect(serveRedirectFor('/course'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/courses',
    ]);
});

it('serves a rule when the request itself carries a trailing slash', function (): void {
    seedRedirectRules([
        storedRedirectRule('/course', ['target_url' => '/courses']),
    ]);

    expect(serveRedirectFor('/course/'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/courses',
    ]);
});

it('serves a rule without regard to the case of the request', function (): void {
    seedRedirectRules([
        storedRedirectRule('/hotdog', ['target_url' => '/food/hot-dog']),
    ]);

    expect(serveRedirectFor('/HotDog'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/food/hot-dog',
    ]);
});

it('forwards the request query string onto a url target', function (): void {
    // SRM forwards the incoming query so attribution parameters survive; the
    // partial dropped it entirely on a url target before UAMS-Web/uams-statamic-archive#1934.
    seedRedirectRules([
        storedRedirectRule('/old', ['target_url' => '/new']),
    ]);

    expect(serveRedirectFor('/old?utm_source=email&utm_campaign=spring'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/new?utm_source=email&utm_campaign=spring',
    ]);
});

it('emits a configured target query string on a url target', function (): void {
    seedRedirectRules([
        storedRedirectRule('/old', [
            'target_url' => '/new',
            'target_query_string' => 'ref=redirect',
        ]),
    ]);

    expect(serveRedirectFor('/old'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/new?ref=redirect',
    ]);
});

it('joins a configured target query string with the forwarded one', function (): void {
    seedRedirectRules([
        storedRedirectRule('/old', [
            'target_url' => '/new',
            'target_query_string' => 'ref=redirect',
        ]),
    ]);

    expect(serveRedirectFor('/old?utm_source=email'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/new?ref=redirect&utm_source=email',
    ]);
});

it('separates a forwarded query with an ampersand when the target already has one', function (): void {
    seedRedirectRules([
        storedRedirectRule('/old', ['target_url' => '/new?existing=1']),
    ]);

    expect(serveRedirectFor('/old?utm_source=email'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/new?existing=1&utm_source=email',
    ]);
});

it('resolves one stored rule for the path, the trailing slash, and the query', function (string $request): void {
    seedRedirectRules([
        storedRedirectRule('/foo', ['target_url' => '/bar']),
    ]);

    expect(serveRedirectFor($request)['status'])->toBe(301);
})->with([
    'bare path' => ['/foo'],
    'trailing slash' => ['/foo/'],
    'query string' => ['/foo?x=1'],
    'trailing slash and query' => ['/foo/?x=1'],
]);

it('matches a source that names a query string of its own', function (): void {
    seedRedirectRules([
        storedRedirectRule('/report?year=2024', ['target_url' => '/reports/2024']),
    ]);

    // The query is accounted for by the target, so it is not forwarded again.
    expect(serveRedirectFor('/report?year=2024'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/reports/2024',
    ]);
});

it('does not match a query-bearing source on the path alone', function (): void {
    seedRedirectRules([
        storedRedirectRule('/report?year=2024', ['target_url' => '/reports/2024']),
    ]);

    expect(serveRedirectFor('/report')['status'])->toBe(200);
});

it('serves a wildcard rule and appends the remainder', function (): void {
    seedRedirectRules([
        storedRedirectRule('/old-section/*', ['target_url' => '/new-section/*']),
    ]);

    expect(serveRedirectFor('/old-section/child/grandchild'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/new-section/child/grandchild',
    ]);
});

it('serves a wildcard rule and still forwards the query', function (): void {
    seedRedirectRules([
        storedRedirectRule('/old-section/*', ['target_url' => '/new-section/*']),
    ]);

    expect(serveRedirectFor('/old-section/child?utm_source=email'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/new-section/child?utm_source=email',
    ]);
});

it('serves a bare Safe Redirect Manager regex rule with its captures substituted', function (): void {
    seedRedirectRules([
        storedRedirectRule('/legacy/(.*)', [
            'target_url' => '/new/$1',
            'use_regex' => true,
        ]),
    ]);

    expect(serveRedirectFor('/legacy/deep/path'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/new/deep/path',
    ]);
});

it('serves the corpus regex shape that optionally captures a query', function (): void {
    // 657 corpus patterns carry an optional `(\?.*)?` group; the path matches and
    // the query arrives through forwarding rather than through the capture.
    seedRedirectRules([
        storedRedirectRule('^\/dr-robert-r-wolfe\/?(\?.*)?$', [
            'target_url' => '/people/robert-r-wolfe-ph-d/$1',
            'use_regex' => true,
        ]),
    ]);

    // The capture resolves to nothing, leaving the target's own trailing slash,
    // which the URL generator then trims ahead of the forwarded query.
    expect(serveRedirectFor('/dr-robert-r-wolfe?utm_source=email'))->toBe([
        'status' => 301,
        'location' => 'http://localhost/people/robert-r-wolfe-ph-d?utm_source=email',
    ]);
});

it('leaves a 404 rule to the 404 page', function (): void {
    // A `404` rule answers nothing: the response is already 404, and the rule's
    // message is rendered into the 404 page's body by `errors/_redirect_message`
    // (UAMS-Web/uams-statamic-archive#1934).
    seedRedirectRules([
        storedRedirectRule('/retired-page', [
            'target_url' => '',
            'response' => '404',
            'message' => 'This page has been retired.',
        ]),
    ]);

    // 200 is this helper's "no answer": the 404 page renders.
    expect(serveRedirectFor('/retired-page'))->toBe([
        'status' => 200,
        'location' => null,
    ]);
});

it('does not let a 404 rule redirect to its target either', function (): void {
    // A rule authored to stay put must not become a redirect because it has a
    // target.
    seedRedirectRules([
        storedRedirectRule('/retired-page', [
            'target_url' => '/somewhere-else',
            'response' => '404',
            'message' => 'This page has been retired.',
        ]),
    ]);

    expect(serveRedirectFor('/retired-page'))->toBe([
        'status' => 200,
        'location' => null,
    ]);
});

it('renders a 404 rule message into the page body', function (): void {
    // The message is rendered by `errors/_redirect_message`, which the 404
    // template includes inside its body.
    seedRedirectRules([
        storedRedirectRule('/retired-page', [
            'target_url' => '',
            'response' => '404',
            'message' => 'This program moved to the College of Medicine.',
        ]),
    ]);

    expect(renderRedirectMessageFor('/retired-page'))
        ->toContain('This program moved to the College of Medicine.');
});

it('escapes editor markup in a 404 rule message rather than rendering it', function (): void {
    // `message` is CP textarea input and Antlers is raw-by-default, so an
    // unescaped render is stored XSS. Measured against the running site before
    // the fix: this payload reached the 404 page as live markup (UAMS-Web/uams-statamic-archive#1942).
    seedRedirectRules([
        storedRedirectRule('/retired-page', [
            'target_url' => '',
            'response' => '404',
            'message' => 'Retired <img src=x onerror=alert(1)> here',
        ]),
    ]);

    $rendered = renderRedirectMessageFor('/retired-page');

    expect($rendered)
        ->toContain('&lt;img src=x onerror=alert(1)&gt;')
        ->not->toContain('<img src=x onerror=alert(1)>');
});

it('keeps the line breaks a 404 rule message textarea allows', function (): void {
    // `nl2br` runs after `entities`, so a multi-line message stays readable
    // without the escaping being undone.
    seedRedirectRules([
        storedRedirectRule('/retired-page', [
            'target_url' => '',
            'response' => '404',
            'message' => "First line.\nSecond line.",
        ]),
    ]);

    expect(renderRedirectMessageFor('/retired-page'))->toContain('First line.<br />');
});

it('renders no message for a 404 rule that carries none', function (): void {
    seedRedirectRules([
        storedRedirectRule('/retired-page', ['target_url' => '', 'response' => '404']),
    ]);

    expect(trim(renderRedirectMessageFor('/retired-page')))->toBe('');
});

it('renders no message on a path no rule covers', function (): void {
    seedRedirectRules([
        storedRedirectRule('/retired-page', [
            'target_url' => '',
            'response' => '404',
            'message' => 'This page has been retired.',
        ]),
    ]);

    expect(trim(renderRedirectMessageFor('/a-different-missing-page')))->toBe('');
});

it('renders no message for a rule that redirects rather than stays put', function (): void {
    // The message partial keys on the response code, not merely on a match, so a
    // moved rule cannot leak a message onto some other page's 404.
    seedRedirectRules([
        storedRedirectRule('/retired-page', [
            'target_url' => '/current-page',
            'response' => '301',
            'message' => 'This page has been retired.',
        ]),
    ]);

    expect(trim(renderRedirectMessageFor('/retired-page')))->toBe('');
});

it('still renders the other targetless error responses', function (string $code, int $status): void {
    seedRedirectRules([
        storedRedirectRule('/blocked-page', [
            'target_url' => '',
            'response' => $code,
            'message' => 'Nope.',
        ]),
    ]);

    // The site's error page needs the full layout, which the isolated Stache base
    // cannot render; `TargetlessErrorPageTest` renders it through the real
    // handler. Here the handler only records what the resolver hands it.
    $handler = new class implements ExceptionHandler
    {
        public ?Throwable $rendered = null;

        public function report(Throwable $e): void {}

        public function shouldReport(Throwable $e): bool
        {
            return false;
        }

        public function render($request, Throwable $e): Response
        {
            $this->rendered = $e;

            return new Response('', $e instanceof SiteHttpException ? $e->getStatusCode() : 500);
        }

        public function renderForConsole($output, Throwable $e): void {}
    };
    app()->instance(ExceptionHandler::class, $handler);

    expect(serveRedirectFor('/blocked-page')['status'])->toBe($status)
        ->and($handler->rendered)->toBeInstanceOf(SiteHttpException::class)
        ->and($handler->rendered?->getMessage())->toBe('Nope.');
})->with([
    'forbidden' => ['403', 403],
    'gone' => ['410', 410],
    'legal reasons' => ['451', 451],
]);

it('serves nothing when no rule matches', function (): void {
    seedRedirectRules([
        storedRedirectRule('/somewhere-else', ['target_url' => '/target']),
    ]);

    expect(serveRedirectFor('/unmatched'))->toBe([
        'status' => 200,
        'location' => null,
    ]);
});
