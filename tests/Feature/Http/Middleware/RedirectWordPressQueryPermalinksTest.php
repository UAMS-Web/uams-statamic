<?php

declare(strict_types=1);

use App\Exceptions\SiteHttpException;
use App\Http\Middleware\RedirectWordPressQueryPermalinks;
use App\Support\Redirects\RedirectStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Feature coverage for {@see RedirectWordPressQueryPermalinks} (UAMS-Web/uams-statamic-archive#1657).
 *
 * WordPress ugly permalinks (`/?p=123`) are the one migration redirect the 404
 * template cannot serve: their *path* is the site root, so they render the home
 * page with a `200` and `{{ find_redirect_rule }}` never runs. This middleware is
 * the substitute, and these tests pin both halves of its contract — that it serves
 * a generated rule for `?p=` and `?page_id=` alike, and that it leaves every other
 * request completely alone, including one carrying a rule that is not currently
 * allowed to serve.
 *
 * @command  php artisan test --compact tests/Feature/Http/Middleware/RedirectWordPressQueryPermalinksTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->save();

    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();
    /** @var Variables $vars */
    $vars = $set->makeLocalization($defaultSite->handle());
    $vars->data(['redirects' => []]);
    $vars->save();
});

/**
 * Seed the shared redirect list with the given rows.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function seedQueryPermalinkRows(array $rows): void
{
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');
    /** @var Statamic\Sites\Site $site */
    $site = Site::default();
    /** @var Variables $vars */
    $vars = $set->in($site->handle());
    $vars->set('redirects', $rows);
    $vars->save();
}

/**
 * A generated ugly-permalink row, overridable per case.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function queryPermalinkRow(array $overrides = []): array
{
    return [
        'id' => 'generated',
        'type' => 'redirect',
        'enabled' => true,
        'status' => RedirectStatus::Approved->value,
        'source' => '/?p=4207',
        'use_regex' => false,
        'target_type' => 'url',
        'target_url' => '/news/a-post',
        'response' => '301',
        'order' => 40,
        'notes' => 'WordPress ugly permalink ?p=4207.',
        ...$overrides,
    ];
}

/**
 * Run the middleware over a GET request, returning its response.
 *
 * The downstream response is a distinguishable `200` so a pass-through is
 * provable, not merely "not a redirect".
 */
function runQueryPermalinkMiddleware(string $uri, string $method = 'GET'): Symfony\Component\HttpFoundation\Response
{
    return app(RedirectWordPressQueryPermalinks::class)->handle(
        Request::create($uri, $method),
        static fn (Request $request): Response => new Response('rendered', 200),
    );
}

it('redirects an ugly permalink to the migrated entry', function (): void {
    seedQueryPermalinkRows([queryPermalinkRow()]);

    $response = runQueryPermalinkMiddleware('/?p=4207');

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe(url('/news/a-post'));
});

it('resolves ?page_id= through the same rule as ?p=', function (): void {
    // WordPress post ids are unique across post types, so one stored rule covers
    // both spellings rather than a near-duplicate row for each.
    seedQueryPermalinkRows([queryPermalinkRow()]);

    $response = runQueryPermalinkMiddleware('/?page_id=4207');

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe(url('/news/a-post'));
});

it('serves an ugly permalink from any path, not just the site root', function (): void {
    // WordPress honors `?p=` wherever it appears; so does the stored rule.
    seedQueryPermalinkRows([queryPermalinkRow()]);

    expect(runQueryPermalinkMiddleware('/some/page?p=4207')->getStatusCode())->toBe(301);
});

it('renders normally when no rule covers the id', function (): void {
    seedQueryPermalinkRows([queryPermalinkRow()]);

    $response = runQueryPermalinkMiddleware('/?p=9999');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('rendered');
});

it('renders normally for a request that is not an ugly permalink', function (string $uri): void {
    seedQueryPermalinkRows([queryPermalinkRow()]);

    expect(runQueryPermalinkMiddleware($uri)->getStatusCode())->toBe(200);
})->with([
    'no query string' => ['/'],
    'unrelated parameter' => ['/?page=2'],
    'non-numeric id' => ['/?p=a-post'],
    'zero id' => ['/?p=0'],
    'signed id' => ['/?p=-4207'],
    'array id' => ['/?p[]=4207'],
    'padded id' => ['/?p=%204207'],
]);

it('leaves a non-GET request alone', function (): void {
    seedQueryPermalinkRows([queryPermalinkRow()]);

    expect(runQueryPermalinkMiddleware('/?p=4207', 'POST')->getStatusCode())->toBe(200);
});

it('withholds a rule that is not currently allowed to serve', function (string $key, mixed $value): void {
    seedQueryPermalinkRows([queryPermalinkRow([$key => $value])]);

    expect(runQueryPermalinkMiddleware('/?p=4207')->getStatusCode())->toBe(200);
})->with([
    'disabled' => ['enabled', false],
    'pending approval' => ['status', RedirectStatus::Pending->value],
    'rejected' => ['status', RedirectStatus::Rejected->value],
    'not yet valid' => ['valid_from', '2999-01-01'],
    'expired' => ['valid_until', '2000-01-01'],
    'no target' => ['target_url', null],
]);

it('aborts with the declared status when an editor marks the id gone', function (): void {
    seedQueryPermalinkRows([queryPermalinkRow(['response' => '410', 'target_url' => null])]);

    $thrown = null;

    try {
        runQueryPermalinkMiddleware('/?p=4207');
    } catch (HttpException $exception) {
        $thrown = $exception;
    }

    // Asserted positively: a middleware that simply returned a 200 would otherwise
    // leave $thrown null and slip past a "did not throw the wrong thing" check.
    //
    // The class is pinned, not just the status. A bare `abort()` here satisfies
    // `HttpException` and the right status while serving a layout-less fragment:
    // once `errors/410.antlers.html` exists, Laravel's error path resolves it
    // through `response()->view()`, which applies no layout (UAMS-Web/uams-statamic-archive#1942). Only
    // `SiteHttpException` reaches Statamic's `View`, so asserting the base class
    // alone is exactly how that regression stayed green.
    expect($thrown)->toBeInstanceOf(SiteHttpException::class)
        ->and($thrown?->getStatusCode())->toBe(410);
});

it('carries the rule message onto the error page it raises', function (): void {
    // The message is the whole point of UAMS-Web/uams-statamic-archive#1942, and this surface dropped it
    // entirely before: the middleware aborted with a status and nothing else, so
    // an editor's explanation reached no visitor on an ugly permalink.
    seedQueryPermalinkRows([queryPermalinkRow([
        'response' => '410',
        'target_url' => null,
        'message' => 'This program closed in 2019.',
    ])]);

    $thrown = null;

    try {
        runQueryPermalinkMiddleware('/?p=4207');
    } catch (HttpException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(SiteHttpException::class)
        ->and($thrown?->getMessage())->toBe('This program closed in 2019.');
});

it('raises an empty message for a gone rule that carries none', function (): void {
    // A rule may legitimately carry no message; an empty string is what makes the
    // error page fall back to its own sentence rather than render a blank lead.
    seedQueryPermalinkRows([queryPermalinkRow(['response' => '410', 'target_url' => null])]);

    $thrown = null;

    try {
        runQueryPermalinkMiddleware('/?p=4207');
    } catch (HttpException $exception) {
        $thrown = $exception;
    }

    expect($thrown?->getMessage())->toBe('');
});

it('raises the site-rendered exception for every targetless code it serves', function (string $response, int $status): void {
    // `ABORT_RESPONSES` and `RespondWithStatus::SITE_RENDERED_CODES` must stay the
    // same set: a code in one but not the other renders through the wrong path.
    seedQueryPermalinkRows([queryPermalinkRow(['response' => $response, 'target_url' => null])]);

    $thrown = null;

    try {
        runQueryPermalinkMiddleware('/?p=4207');
    } catch (HttpException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(SiteHttpException::class)
        ->and($thrown?->getStatusCode())->toBe($status);
})->with([
    'forbidden' => ['403', 403],
    'gone' => ['410', 410],
    'legal reasons' => ['451', 451],
]);

it('aborts on a numerically stored gone response', function (): void {
    // The globals editor writes a string, but a re-serialized row can carry an int;
    // the serving template's loose comparison tolerates both, so this must too.
    seedQueryPermalinkRows([queryPermalinkRow(['response' => 410, 'target_url' => null])]);

    $thrown = null;

    try {
        runQueryPermalinkMiddleware('/?p=4207');
    } catch (HttpException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(SiteHttpException::class)
        ->and($thrown?->getStatusCode())->toBe(410);
});

it('falls back to a permanent move when the stored response is not a redirect status', function (): void {
    // These rules are generated as 301s; a nonsense hand-edit must still move the
    // visitor rather than emit an invalid redirect status.
    seedQueryPermalinkRows([queryPermalinkRow(['response' => '200'])]);

    expect(runQueryPermalinkMiddleware('/?p=4207')->getStatusCode())->toBe(301);
});

it('honors an editor-retargeted rule', function (): void {
    seedQueryPermalinkRows([queryPermalinkRow(['target_url' => '/somewhere-better', 'response' => '302'])]);

    $response = runQueryPermalinkMiddleware('/?p=4207');

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(url('/somewhere-better'));
});
