<?php

declare(strict_types=1);

use App\Scopes\KnowledgeBaseArticleChildren;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

covers(KnowledgeBaseArticleChildren::class);

/**
 * @covers \App\Scopes\KnowledgeBaseArticleChildren
 *
 * @command  php artisan test --compact tests/Unit/Scopes/KnowledgeBaseArticleChildrenScopeTest.php
 */
it('apply adds article blueprint and parent', function (): void {
    $scope = new KnowledgeBaseArticleChildren;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(
            'blueprint',
            'article'
        )
        ->andReturnSelf();
    $query->shouldReceive('where')
        ->once()
        ->with(
            'parent',
            'p1'
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'parent' => 'p1',
        ]
    );
});

it('apply skips the parent clause when parent is omitted', function (): void {
    $scope = new KnowledgeBaseArticleChildren;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(
            'blueprint',
            'article'
        )
        ->andReturnSelf();
    $query->shouldNotReceive('where')->with(
        'parent',
        Mockery::any(),
    );

    $scope->apply(
        $query,
        [],
    );
});
