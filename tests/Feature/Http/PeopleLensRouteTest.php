<?php

declare(strict_types=1);

use App\Http\Controllers\People\PeopleLensController;
use App\Support\People\ClinicalProviderProfileEligibility;
use App\Support\People\ResearcherProfileEligibility;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Http\Controllers\FrontendController;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

use function Pest\Laravel\get;
use function Pest\Laravel\withoutVite;

uses(TestCase::class);

/**
 * Route + end-to-end coverage for the people lens substrate (UAMS-Web/uams-statamic-archive#1635 / UAMS-Web/uams-statamic-archive#1636).
 *
 * The route-precedence cases prove the registry-driven `/health/provider/{slug}`
 * lens route captures the clinical lens URL (and only on the `health` site) before
 * Statamic's catch-all, without shadowing the plural `/health/providers` directory
 * mount. The render cases prove the substrate resolves the canonical `default`
 * person cross-site and renders it through the lens template, self-canonical, and
 * 404s a non-provider or ineligible slug. `withoutVite()` skips the built-asset
 * manifest so the real layout renders in CI without an `npm run build` step; the
 * render cases are gated on the `content/` submodule.
 *
 * @command php artisan test --compact tests/Feature/Http/PeopleLensRouteTest.php
 */

/** The route Laravel matches for a GET path. */
function matchedLensRoute(string $path): Route
{
    return app('router')->getRoutes()->match(Request::create($path, 'GET'));
}

/** The first published person in the canonical site matching the eligibility predicate. */
function firstProviderWhere(bool $eligible): ?Entry
{
    $eligibility = app(ClinicalProviderProfileEligibility::class);

    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();
    $query->where('collection', 'people');
    $query->where('site', 'default');

    /** @var iterable<int, mixed> $people */
    $people = $query->get();

    foreach ($people as $person) {
        if (
            $person instanceof Entry
            && $person->status() === 'published'
            && $eligibility->qualifies($person) === $eligible
        ) {
            return $person;
        }
    }

    return null;
}

/**
 * Drop the published-location base rule for the rest of the test.
 *
 * The committed content holds no `clinical_locations` entries, so since UAMS-Web/uams-statamic-archive#3123 no committed
 * person qualifies, and the render cases would skip. They test the lens render path rather
 * than the eligibility rules, which `ClinicalProviderProfileEligibilityTest` covers, so they
 * evaluate every other rule unchanged.
 */
function withoutPublishedLocationRule(): void
{
    /** @var list<array<string, mixed>> $base */
    $base = config('clinical_provider_profile.base');

    config(['clinical_provider_profile.base' => array_values(array_filter(
        $base,
        static fn (array $rule): bool => ($rule['field'] ?? null) !== 'clinical_locations',
    ))]);
}

/** The first published person in the canonical site matching the researcher predicate. */
function firstResearcherWhere(bool $isResearcher): ?Entry
{
    $eligibility = app(ResearcherProfileEligibility::class);

    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();
    $query->where('collection', 'people');
    $query->where('site', 'default');

    /** @var iterable<int, mixed> $people */
    $people = $query->get();

    foreach ($people as $person) {
        if (
            $person instanceof Entry
            && $person->status() === 'published'
            && $eligibility->qualifies($person) === $isResearcher
        ) {
            return $person;
        }
    }

    return null;
}

it('captures the clinical provider lens URL on the health site prefix', function (): void {
    $route = matchedLensRoute('/health/provider/some-slug');

    expect($route->getActionName())->toContain('PeopleLensController')
        ->and($route->defaults['lens'] ?? null)->toBe('provider');
});

it('does not register the provider lens on the canonical default site', function (): void {
    // The provider lens is health-only, so `/provider/{slug}` at the root must not
    // reach the lens controller.
    expect(matchedLensRoute('/provider/some-slug')->getActionName())
        ->not->toContain('PeopleLensController');
});

it('does not shadow the plural /health/providers directory mount', function (): void {
    // Singular `provider/{slug}` (two trailing segments) cannot match the single
    // `providers` mount segment.
    expect(matchedLensRoute('/health/providers')->getActionName())
        ->not->toContain('PeopleLensController');
});

it('captures the academic lens URL at the root and a department prefix', function (): void {
    // The academic lens is registered on every site (`['*']`); its gate is the
    // per-site filter, so the route matches at any prefix.
    $root = matchedLensRoute('/faculty/some-slug');
    expect($root->getActionName())->toContain('PeopleLensController')
        ->and($root->defaults['lens'] ?? null)->toBe('academic');

    $department = matchedLensRoute('/medicine/anesthesiology/faculty/some-slug');
    expect($department->getActionName())->toContain('PeopleLensController')
        ->and($department->defaults['lens'] ?? null)->toBe('academic');
});

it('captures the institutional lens URL', function (): void {
    $route = matchedLensRoute('/human-resources/staff/some-slug');
    expect($route->getActionName())->toContain('PeopleLensController')
        ->and($route->defaults['lens'] ?? null)->toBe('institutional');
});

it('captures the researcher lens URL at the root and a department prefix', function (): void {
    // The researcher lens is registered on every site (`['*']`); its gate is person-level
    // (a public research identity), so the route matches at any prefix and the gate — not
    // the route — filters non-researchers.
    $root = matchedLensRoute('/researcher/some-slug');
    expect($root->getActionName())->toContain('PeopleLensController')
        ->and($root->defaults['lens'] ?? null)->toBe('researcher');

    $department = matchedLensRoute('/medicine/anesthesiology/researcher/some-slug');
    expect($department->getActionName())->toContain('PeopleLensController')
        ->and($department->defaults['lens'] ?? null)->toBe('researcher');
});

it('does not register the general lens on any site by default (opt-in)', function (): void {
    // The general lens ships with an explicit empty `sites` — registered but active on no
    // site — so its `/people/{slug}` route is NOT wired to the lens controller anywhere
    // until a site opts in. `/people/{slug}` therefore falls through to the normal front end.
    expect(matchedLensRoute('/people/some-slug')->getActionName())
        ->not->toContain('PeopleLensController');
});

it('404s the researcher lens for a non-researcher', function (): void {
    if (! Collection::find('people')) {
        ContentSubmodule::reportMissing('people collection');
    }

    $person = firstResearcherWhere(isResearcher: false);

    if ($person === null) {
        ContentSubmodule::reportNoQualifyingContent('No non-researcher published person in the content fixture.');
    }

    $slug = mustBeString($person->slug(), 'slug');

    withoutVite();
    get("/researcher/{$slug}")->assertNotFound();
});

it('renders the general lens for every publicly renderable person', function (): void {
    if (! Collection::find('people')) {
        ContentSubmodule::reportMissing('people collection');
    }

    // EVERY publicly-visible person, not the first one the query happens to
    // return. Taking only the first made this a lottery decided by the Stache
    // index and worker state: UAMS-Web/uams-statamic-archive#1883 was a real 500 on four entries that `main`
    // stayed green through for months, until a PR that merely added tests
    // reshuffled paratest's distribution and landed the query on one of them.
    // Iterating removes the luck — a defect on any single person is now caught
    // by construction rather than by which person came back first.
    $controller = app(PeopleLensController::class);

    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();
    $query->where('collection', 'people');
    $query->where('site', 'default');

    /** @var iterable<int, mixed> $people */
    $people = $query->get();

    withoutVite();
    config()->set('people_lenses.lenses.general.sites', ['default']);

    $checked = 0;
    $failures = [];

    foreach ($people as $candidate) {
        if (! $candidate instanceof Entry || ! $controller->isPubliclyRenderable($candidate)) {
            continue;
        }

        $slug = mustBeString($candidate->slug(), 'slug');

        // Drive the general lens render path directly: the ungated lens carried on the route
        // defaults, resolving the canonical person and rendering the base `people/show`.
        $request = Request::create("/people/{$slug}", 'GET');
        $route = (new Route('GET', 'people/{slug}', []))->defaults('lens', 'general');
        $request->setRouteResolver(fn (): Route => $route);

        try {
            $response = $controller->__invoke($request, $slug);
        } catch (Throwable $throwable) {
            // Collect rather than throw, so one bad entry names itself instead of
            // hiding the other thirty-nine behind the first stack trace.
            $failures[] = $slug.': '.$throwable->getMessage();

            continue;
        }

        // The ungated general lens renders through the substrate, so it returns a Response
        // (not a fall-through to the front end); narrow off the `mixed` return before asserting.
        expect($response)->toBeInstanceOf(Response::class);
        $response = mustBe(Response::class, $response, 'response');

        if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->getContent(), 'person-info')) {
            $failures[] = $slug.': status '.$response->getStatusCode();
        }

        $checked++;
    }

    if ($checked === 0 && $failures === []) {
        ContentSubmodule::reportNoQualifyingContent('No publicly-renderable published person in the content fixture.');
    }

    expect($failures)->toBe([]);
});

it('404s a health provider URL with no canonical person', function (): void {
    if (! Collection::find('pages')) {
        ContentSubmodule::reportMissing('people collection');
    }

    withoutVite();
    get('/health/provider/no-such-provider-xyz-123')->assertNotFound();
});

it('renders an eligible provider through the clinical lens template', function (): void {
    if (! Collection::find('people')) {
        ContentSubmodule::reportMissing('people collection');
    }

    withoutPublishedLocationRule();

    $person = firstProviderWhere(eligible: true);

    if ($person === null) {
        ContentSubmodule::reportNoQualifyingContent('No eligible published provider in the content fixture.');
    }

    $slug = mustBeString($person->slug(), 'slug');

    withoutVite();
    $response = get("/health/provider/{$slug}");

    // 200 through the CLINICAL template, not the base `people/show` — the lens body
    // stamps `data-lens="provider"`, which the base profile never emits (UAMS-Web/uams-statamic-archive#571).
    $response->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    $html = mustBeString($response->getContent(), 'the lens page');
    expect($html)->toContain('data-lens="provider"')
        // Inside the site layout, with the person's own content in the cascade
        ->and($html)->toContain('</head>')
        ->and($html)->toContain(e(mustBeString($person->get('title'), 'the person title')))
        // Self-canonical: the lens page, not the person's own permalink
        ->and($html)->toContain('<link rel="canonical" href="'.url("/health/provider/{$slug}").'">');
});

it('hands a request with no lens on its route to the front end, even for a renderable person', function (): void {
    if (! Collection::find('people')) {
        ContentSubmodule::reportMissing('people collection');
    }

    withoutPublishedLocationRule();

    $person = firstProviderWhere(eligible: true);

    if ($person === null) {
        ContentSubmodule::reportNoQualifyingContent('No eligible published provider in the content fixture.');
    }

    $frontEnd = Mockery::mock(FrontendController::class);
    $frontEnd->shouldReceive('index')->once()->andReturn('the front end');
    app()->instance(FrontendController::class, $frontEnd);

    // A request that matched no route carries no lens
    $response = app(PeopleLensController::class)(Request::create('/somewhere'), mustBeString($person->slug(), 'slug'));

    expect($response)->toBe('the front end');
});

it('404s the clinical lens for an ineligible person', function (): void {
    if (! Collection::find('people')) {
        ContentSubmodule::reportMissing('people collection');
    }

    $person = firstProviderWhere(eligible: false);

    if ($person === null) {
        ContentSubmodule::reportNoQualifyingContent('No ineligible published person in the content fixture.');
    }

    $slug = mustBeString($person->slug(), 'slug');

    withoutVite();
    get("/health/provider/{$slug}")->assertNotFound();
});
