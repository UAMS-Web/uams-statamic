<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver;
use App\Tags\SchemaSubjectMatterKeywords;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Tags\SchemaSubjectMatterKeywords
 *
 * Mutation: 100% on App\Tags\SchemaSubjectMatterKeywords (33 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaSubjectMatterKeywordsTagTest.php
 */
it('index delegates to resolver with normalized mode', function () {
    $expected = '["kw"]';

    /** @var SchemaThingFromRelationshipResolver&MockInterface $resolver */
    $resolver = Mockery::mock(SchemaThingFromRelationshipResolver::class);
    $resolver->shouldReceive('resolveSubjectMatterKeywords')
        ->once()
        ->withArgs(
            fn (array $context, array $params): bool => $params['mode'] === 'direct'
                && ($params['current_field'] ?? null) === 'gallery'
        )
        ->andReturn($expected);

    app()->instance(SchemaThingFromRelationshipResolver::class, $resolver);

    $tag = new SchemaSubjectMatterKeywords;
    $tag->setContext([
        'gallery' => [],
    ]);
    $tag->setParameters([
        'mode' => ' DIRECT ',
        'current_field' => 'gallery',
    ]);

    expect($tag->index())->toBe($expected);
});
