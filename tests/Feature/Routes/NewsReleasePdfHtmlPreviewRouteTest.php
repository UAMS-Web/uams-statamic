<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @command  php artisan test --compact tests/Feature/Routes/NewsReleasePdfHtmlPreviewRouteTest.php
 *
 * No mutable PHP surface under test: the route is registered conditionally in
 * routes/web.php (route-config target), so there is no backing App class to mutate.
 */
it('only registers the preview route name in the local environment', function (): void {
    // Skip when running the suite under APP_ENV=local (preview is registered)
    if (app()->environment('local')) {
        Assert::markTestSkipped('Route exists in local; outside local it must be absent.');
    }

    // Avoid HTTP 404 handling (Statamic error pages need DB); assert registration only
    Assert::assertFalse(Route::has('dev.news-release-pdf-html'));
});
