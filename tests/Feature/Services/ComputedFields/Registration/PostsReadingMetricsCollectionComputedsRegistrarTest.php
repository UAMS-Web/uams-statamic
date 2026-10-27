<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\PostsReadingMetricsCollectionComputedsRegistrar;
use App\Services\Posts\PostsReadingMetricsService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(PostsReadingMetricsCollectionComputedsRegistrar::class);

/**
 * @covers \App\Services\ComputedFields\Registration\PostsReadingMetricsCollectionComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Feature/Services/ComputedFields/Registration/PostsReadingMetricsCollectionComputedsRegistrarTest.php
 */
it('registers word_count and reading_time computeds on the posts collection', function (): void {
    $registrar = new PostsReadingMetricsCollectionComputedsRegistrar(
        app()->make(PostsReadingMetricsService::class)
    );

    Collection::shouldReceive('computed')
        ->withArgs(
            fn ($collection, $key, $callback): bool => $collection === 'posts'
                && $key === 'word_count'
                && \is_callable($callback)
        )
        ->once();

    Collection::shouldReceive('computed')
        ->withArgs(
            fn ($collection, $key, $callback): bool => $collection === 'posts'
                && $key === 'reading_time'
                && \is_callable($callback)
        )
        ->once();

    $registrar->register();
});
