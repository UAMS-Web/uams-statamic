<?php

declare(strict_types=1);

use App\Scopes\KnowledgeBaseTopicOnly;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

covers(KnowledgeBaseTopicOnly::class);

/**
 * @covers \App\Scopes\KnowledgeBaseTopicOnly
 *
 * Mutation: 100% on App\Scopes\KnowledgeBaseTopicOnly (2 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Scopes/KnowledgeBaseTopicOnlyScopeTest.php
 */
it('apply filters collection and topic blueprint', function () {
    $scope = new KnowledgeBaseTopicOnly;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(
            'collection',
            'knowledge_bases'
        )
        ->andReturnSelf();
    $query->shouldReceive('where')
        ->once()
        ->with(
            'blueprint',
            'topic'
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        []
    );
});
