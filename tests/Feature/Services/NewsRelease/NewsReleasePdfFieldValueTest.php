<?php

declare(strict_types=1);

use App\Services\NewsRelease\NewsReleasePdfGenerator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Assets\Asset;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Ensures `news_release_pdf` entry data uses paths Statamic's Assets field can
 * augment (`whereIn('path', ...)`), not full asset ids.
 *
 * Mutation: 100% on App\Services\NewsRelease\NewsReleasePdfGenerator (5 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Services/NewsRelease/NewsReleasePdfFieldValueTest.php
 */
it('returns the asset path rather than the asset id for the field value', function () {
    // Build a mock asset with distinct path and id
    $asset = Mockery::mock(Asset::class);

    // Expect path to be read for the field payload
    $asset->shouldReceive('path')
        ->once()
        ->andReturn('2026-04-06_example.pdf');

    // Expect id is not used for entry storage
    $asset->shouldNotReceive('id');

    // Resolve the protected helper via reflection
    $method = new ReflectionMethod(
        NewsReleasePdfGenerator::class,
        'rawNewsReleasePdfFieldValue'
    );

    // Invoke the helper with the mock asset
    $actual = $method->invoke(new NewsReleasePdfGenerator, $asset);

    // Assert the entry field receives a single path string
    expect($actual)->toBe(['2026-04-06_example.pdf']);
});
