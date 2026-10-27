<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\PostsReverseReferenceComputedsRegistrar;
use App\Services\ComputedFields\Reverse\PostsReverseReferenceComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(PostsReverseReferenceComputedsRegistrar::class);

/**
 * @covers \App\Services\ComputedFields\Registration\PostsReverseReferenceComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/PostsReverseReferenceComputedsRegistrarTest.php
 */
it('registers the reverse computed exactly once on the posts collection', function (): void {
    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collection;
        }
    );

    app(PostsReverseReferenceComputedsRegistrar::class)->register();

    expect($callCount)->toBe(1)
        ->and(array_keys($captured))->toBe(['referenced_by_posts'])
        ->and($captured['referenced_by_posts'])->toBe('posts');
});

it('wires the computed handle to its resolver method', function (): void {
    $expected = EntryCollection::make();

    $values = Mockery::mock(PostsReverseReferenceComputedValues::class);
    $entry = Mockery::mock(Entry::class);
    $values->shouldReceive('resolveReferencedByPosts')->with($entry)->andReturn($expected);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    (new PostsReverseReferenceComputedsRegistrar($values))->register();

    expect(($captured['referenced_by_posts'])($entry))->toBe($expected);
});
