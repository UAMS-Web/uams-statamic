<?php

declare(strict_types=1);

use App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Pins the `{entry}/{section}` section-route precedence + `{section}` constraint
 * for UAMS-Web/uams-statamic-archive#1058's AoE virtual subpages (served by ClinicalExpertiseSectionController).
 * Statamic's front end is a catch-all, so the load-bearing (and previously
 * unproven) claim is that this app route captures the enum sections *before* the
 * catch-all, and that any out-of-enum trailing token falls through to it instead.
 * Publish/visibility gating + non-shadowing delegation live in the controller
 * (ClinicalExpertiseSectionControllerTest); the section 404 gate in
 * ClinicalExpertiseSectionResolverTest; this needs no content.
 *
 * @command  php artisan test --compact tests/Feature/ClinicalExpertise/ClinicalExpertiseSectionRouteTest.php
 */

/** The URI pattern of the route Laravel would match for a GET path. */
function matchedRouteUri(string $path): string
{
    return app('router')->getRoutes()->match(Request::create($path, 'GET'))->uri();
}

it('captures a known section URL with the AoE section route, at root and nested depth', function () {
    expect(matchedRouteUri('/cardiology/providers'))->toBe('{entry}/{section}');
    expect(matchedRouteUri('/areas-of-expertise/cardiology/electrophysiology/locations'))->toBe('{entry}/{section}');
    expect(matchedRouteUri('/cardiology/specialties'))->toBe('{entry}/{section}');
});

it('lets an out-of-enum trailing token fall through to the Statamic catch-all', function () {
    // A non-section trailing token must NOT be captured by the AoE section route.
    expect(matchedRouteUri('/cardiology/bogus'))->not->toBe('{entry}/{section}');
    expect(matchedRouteUri('/cardiology/conditions'))->not->toBe('{entry}/{section}');
});

it('constrains {section} to exactly the resolver tokens', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r): bool => $r->uri() === '{entry}/{section}');

    expect($route)->not->toBeNull();
    expect($route->wheres['section'] ?? null)->toBe(ClinicalExpertiseSectionResolver::routeConstraint());
    expect($route->wheres['entry'] ?? null)->toBe('.*');
});
