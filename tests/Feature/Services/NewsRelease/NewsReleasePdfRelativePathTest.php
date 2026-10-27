<?php

declare(strict_types=1);

use App\Services\NewsRelease\NewsReleasePdfGenerator;
use Carbon\Carbon;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for NewsReleasePdfGenerator::relativePathForNewsReleasePdf().
 *
 * Asserts PDF paths use display_timezone for the date segment (not necessarily
 * the same calendar day as the entry markdown filename when app.timezone is UTC
 * and display differs).
 *
 * Mutation: 100% on App\Services\NewsRelease\NewsReleasePdfGenerator (17 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Services/NewsRelease/NewsReleasePdfRelativePathTest.php
 */
it('builds the relative path from the display timezone date and slug', function () {
    // Build a publish instant independent of repository content.
    // 2026-04-04 04:16 UTC is calendar date 2026-04-03 in America/Chicago
    // (see phpunit.xml STATAMIC_DISPLAY_TIMEZONE).
    $publishUtc = Carbon::parse('2026-04-04 04:16:00', 'UTC');

    // Mock an entry with that date and a stable slug (no Stache or content files).
    $entry = Mockery::mock(Entry::class);

    // Expect the entry to return the publish instant.
    $entry->shouldReceive('date')
        ->once()
        ->andReturn($publishUtc);

    // Expect the entry to return the slug.
    $entry->shouldReceive('slug')
        ->once()
        ->andReturn('news-release-test');

    // Reflect the private path-building method.
    $method = new ReflectionMethod(
        NewsReleasePdfGenerator::class,
        'relativePathForNewsReleasePdf',
    );

    // Invoke the method on an isolated generator instance.
    $actual = $method->invoke(new NewsReleasePdfGenerator, $entry);

    // The relative path uses the display timezone calendar date and slug.
    expect($actual)->toBe('2026-04-03_news-release-test.pdf');
});
