<?php

declare(strict_types=1);

use App\Http\Controllers\Seo\RobotsController;
use PHPUnit\Framework\Assert;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

use function Pest\Laravel\get;

/**
 * Feature test for the dynamic `robots.txt` route.
 *
 * `/robots.txt` is served by the app-owned {@see RobotsController}
 * (UAMS-Web/uams-statamic-archive#1326, replacing studio1902/statamic-peak-seo), which renders the `robots`
 * field on the `seo` global and appends the sitemap-index pointer. This guards
 * that the route resolves `200` and serves the migrated crawler rules against
 * real content. The controller's hermetic body-composition and the sitemap
 * index are covered in tests/Feature/Seo/AppOwnedSeoRoutesTest.php.
 */
uses(TestCase::class);

/**
 * Skip when the SEO global / `robots` value is absent (content submodule
 * not checked out — see UAMS-Web/uams-statamic-archive#859), so CI without `content` stays green.
 */
beforeEach(function (): void {
    // Resolve the migrated robots rules from the SEO global
    /** @var Variables|null $variables */
    $variables = GlobalSet::findByHandle('seo')?->inDefaultSite();
    $robots = $variables?->get('robots');

    // Skip gracefully when the content-backed global is unavailable
    if (blank($robots)) {
        ContentSubmodule::reportMissing('SEO global `robots` value');
    }
});

/**
 * @command  php artisan test --compact tests/Feature/Routes/RobotsTxtRouteTest.php
 */
it('serves 200 with crawler rules', function (): void {
    // Request the dynamic robots.txt route
    $response = get('/robots.txt');

    // Assert the route resolves successfully as plain text
    $response->assertOk();
    $response->assertHeader(
        'Content-Type',
        'text/plain; charset=utf-8'
    );

    // Assert the migrated crawler rules are present in the body
    $response->assertSee(
        'User-agent: *',
        false
    );
    $response->assertSee(
        'User-agent: ClaudeBot',
        false
    );
    $response->assertSee(
        'User-agent: GPTBot',
        false
    );

    // Assert the sitemap reference appended by RobotsController::buildBody is present
    $response->assertSee(
        'Sitemap:',
        false
    );
});
