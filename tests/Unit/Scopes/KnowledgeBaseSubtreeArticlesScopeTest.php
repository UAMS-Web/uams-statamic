<?php

declare(strict_types=1);

use App\Scopes\KnowledgeBaseSubtreeArticles;
use App\Services\KnowledgeBase\KnowledgeBaseSubtreeArticleIds;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(KnowledgeBaseSubtreeArticles::class);

/**
 * The KB search subtree scope (UAMS-Web/uams-statamic-archive#506) intersects search results with the published
 * article ids of the submitted subtree root.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/KnowledgeBaseSubtreeArticlesScopeTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

it('intersects the results with the subtree article ids for a valid root', function (): void {
    $resolver = Mockery::mock(KnowledgeBaseSubtreeArticleIds::class);
    $resolver->shouldReceive('forRoot')
        ->once()
        ->with('kb-root')
        ->andReturn(['article-1', 'article-2']);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', ['article-1', 'article-2'])
        ->andReturnSelf();

    (new KnowledgeBaseSubtreeArticles($resolver))->apply(
        $query,
        [
            'scope_root' => 'kb-root',
        ]
    );
});

it('reads the scope root from an ArrayAccess parameter bag (the tag render path)', function (): void {
    // InstanceOfToTrue on ArrayAccess would accept a non-ArrayAccess object and
    // throw on subscript or, with a stdClass, fail closed incorrectly.
    $resolver = Mockery::mock(KnowledgeBaseSubtreeArticleIds::class);
    $resolver->shouldReceive('forRoot')
        ->once()
        ->with('kb-root')
        ->andReturn(['article-1']);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', ['article-1'])
        ->andReturnSelf();

    (new KnowledgeBaseSubtreeArticles($resolver))->apply(
        $query,
        new ArrayObject(['scope_root' => 'kb-root'])
    );
});

it('constrains to no results when values is neither an array nor ArrayAccess', function (): void {
    $resolver = Mockery::mock(KnowledgeBaseSubtreeArticleIds::class);
    $resolver->shouldReceive('forRoot')->never();

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', [])
        ->andReturnSelf();

    (new KnowledgeBaseSubtreeArticles($resolver))->apply(
        $query,
        new stdClass
    );
});

it('constrains to no results and never resolves when the scope root is missing', function (): void {
    $resolver = Mockery::mock(KnowledgeBaseSubtreeArticleIds::class);
    $resolver->shouldReceive('forRoot')->never();

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', [])
        ->andReturnSelf();

    (new KnowledgeBaseSubtreeArticles($resolver))->apply(
        $query,
        []
    );
});

it('constrains to no results when the scope root is blank or non-string', function (mixed $root): void {
    $resolver = Mockery::mock(KnowledgeBaseSubtreeArticleIds::class);
    $resolver->shouldReceive('forRoot')->never();

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', [])
        ->andReturnSelf();

    (new KnowledgeBaseSubtreeArticles($resolver))->apply(
        $query,
        [
            'scope_root' => $root,
        ]
    );
})->with([
    'empty string' => [''],
    'array' => [['not', 'a', 'string']],
    'integer' => [12],
]);

it('does nothing when the query is not a Statamic query builder', function (): void {
    $resolver = Mockery::mock(KnowledgeBaseSubtreeArticleIds::class);
    $resolver->shouldReceive('forRoot')->never();

    (new KnowledgeBaseSubtreeArticles($resolver))->apply(
        new stdClass,
        [
            'scope_root' => 'kb-root',
        ]
    );
});
