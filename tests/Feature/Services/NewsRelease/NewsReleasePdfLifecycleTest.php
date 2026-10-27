<?php

declare(strict_types=1);

use App\Services\NewsRelease\NewsReleasePdfGenerator;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for NewsReleasePdfGenerator::persistNewsReleasePdfOwnershipAndRemoveSuperseded().
 *
 * Mutation: 100% on App\Services\NewsRelease\NewsReleasePdfGenerator (10 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Services/NewsRelease/NewsReleasePdfLifecycleTest.php
 */
it('deletes other assets with the same entry meta when persisting ownership', function () {
    // Build the kept PDF asset (receives meta + save only).
    $keep = Mockery::mock(Asset::class);

    // Expect the asset to receive the entry id meta key.
    $keep->shouldReceive('set')
        ->once()
        ->with('news_release_entry', 'entry-uuid-1');

    // Expect the asset to save quietly.
    $keep->shouldReceive('saveQuietly')->once();

    // Expect the asset to return the expected id.
    $keep->shouldReceive('id')
        ->andReturn('news_releases::2026-04-03_slug-a.pdf');

    // Build a stale PDF for the same entry (must be deleted).
    $stale = Mockery::mock(Asset::class);

    // Expect the asset to return the expected id.
    $stale->shouldReceive('id')
        ->andReturn('news_releases::2026-04-02_slug-a.pdf');

    // Expect the asset to receive the entry id meta key.
    $stale->shouldReceive('get')
        ->with('news_release_entry')
        ->once()
        ->andReturn('entry-uuid-1');

    // Expect the asset to delete quietly.
    $stale->shouldReceive('deleteQuietly')->once();

    // Build another PDF owned by a different entry (must not be deleted).
    $other = Mockery::mock(Asset::class);

    // Expect the asset to return the expected id.
    $other->shouldReceive('id')
        ->andReturn('news_releases::2026-04-03_other.pdf');

    // Expect the asset to receive the entry id meta key.
    $other->shouldReceive('get')
        ->with('news_release_entry')
        ->once()
        ->andReturn('entry-uuid-2');

    // Expect the asset to not receive delete quietly.
    $other->shouldNotReceive('deleteQuietly');

    // Container returns all three assets in one listing.
    $container = Mockery::mock(StatamicAssetContainer::class);

    // Expect the container to return the expected assets.
    $container->shouldReceive('assets')
        ->once()
        ->andReturn(new Collection([$keep, $stale, $other]));

    // Invoke the private lifecycle helper.
    $generator = new NewsReleasePdfGenerator;

    // Get the private method.
    $method = new ReflectionMethod(
        $generator,
        'persistNewsReleasePdfOwnershipAndRemoveSuperseded'
    );

    // Invoke the method.
    $method->invoke(
        $generator,
        $container,
        $keep,
        'entry-uuid-1'
    );

    // Unset local variables to free memory.
    unset(
        $container,
        $generator,
        $keep,
        $method,
        $other,
        $stale
    );
});
