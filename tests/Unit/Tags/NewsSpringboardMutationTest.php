<?php

declare(strict_types=1);

use App\Services\Posts\NewsSpringboardService;
use App\Tags\NewsSpringboard;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(NewsSpringboard::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(12_000_000);
        $padded = true;
    }
});

/**
 * Narrow mutate coverage for App\Tags\NewsSpringboard ternary / coalesce arms.
 *
 * Mutation: 100% on App\Tags\NewsSpringboard (with NewsSpringboardTagTest).
 *
 * @command  php artisan test --compact tests/Unit/Tags/NewsSpringboardMutationTest.php
 */
it('passes an empty site handle when Site::current()->handle() is not a string', function (): void {
    // TernaryNegated / EmptyStringToNotEmpty on ``is_string($handle) ? $handle : ''``.
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn(123);
    Site::shouldReceive('current')->once()->andReturn($site);

    $service = Mockery::mock(NewsSpringboardService::class);
    $service->shouldReceive('resolveSections')
        ->once()
        ->with([], '')
        ->andReturn([]);
    app()->instance(NewsSpringboardService::class, $service);

    $tag = new NewsSpringboard;
    $tag->setContext(['sections' => []]);
    $tag->setParameters([]);

    expect($tag->index())->toBe([
        'sections' => [],
        'no_results' => true,
    ]);
});

it('prefers the explicit sections param over the context field', function (): void {
    // CoalesceRemoveLeft on ``$param ?? $context->get('sections')``.
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('news');
    Site::shouldReceive('current')->once()->andReturn($site);

    $fromParam = [
        ['section_heading' => 'From param', 'category' => ['cat-a'], 'count' => 1],
    ];
    $fromContext = [
        ['section_heading' => 'From context', 'category' => ['cat-b'], 'count' => 1],
    ];

    $service = Mockery::mock(NewsSpringboardService::class);
    $service->shouldReceive('resolveSections')
        ->once()
        ->withArgs(function (array $sections, string $handle): bool {
            // NewsSectionGrid::normalize maps section_heading → heading.
            return $handle === 'news'
                && data_get($sections, '0.heading') === 'From param';
        })
        ->andReturn([
            ['heading' => 'From param', 'posts' => []],
        ]);
    app()->instance(NewsSpringboardService::class, $service);

    $tag = new NewsSpringboard;
    $tag->setContext(['sections' => $fromContext]);
    $tag->setParameters(['sections' => $fromParam]);

    $result = $tag->index();

    expect($result['no_results'])->toBeFalse()
        ->and(data_get($result, 'sections.0.heading'))->toBe('From param');
});
