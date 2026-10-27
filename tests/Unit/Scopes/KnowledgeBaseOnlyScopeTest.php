<?php

declare(strict_types=1);

use App\Scopes\KnowledgeBaseOnly;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

covers(KnowledgeBaseOnly::class);

/**
 * @covers \App\Scopes\KnowledgeBaseOnly
 *
 * Mutation: 100% on App\Scopes\KnowledgeBaseOnly (2 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Scopes/KnowledgeBaseOnlyScopeTest.php
 */
it('apply filters collection and blueprint', function () {
    $scope = new KnowledgeBaseOnly;
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
            'knowledge_base'
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        []
    );
});
