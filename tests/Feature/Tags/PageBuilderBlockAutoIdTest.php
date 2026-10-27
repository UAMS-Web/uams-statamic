<?php

declare(strict_types=1);

use App\Tags\PageBuilderBlockAutoId;
use Statamic\Facades\Antlers;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

covers(PageBuilderBlockAutoId::class);

/**
 * Feature tests for the `page_builder_block_auto_id` tag, rendered through Antlers.
 *
 * Mutation: tracked under App\Tags\PageBuilderBlockAutoId (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/PageBuilderBlockAutoIdTest.php
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
it('resolves from block id', function () {
    $out = (string) Antlers::parse(
        '{{ block_id = {page_builder_block_auto_id} }}{{ block_id }}',
        ['block' => ['id' => 'abc123']],
        true
    );

    expect(trim($out))->toBe('module-abc123');
});

it('resolves from top level id when block id absent', function () {
    $out = (string) Antlers::parse(
        '{{ block_id = {page_builder_block_auto_id} }}{{ block_id }}',
        ['id' => 'fallback-id'],
        true
    );

    expect(trim($out))->toBe('module-fallback-id');
});

it('resolves from index when no ids', function () {
    $out = (string) Antlers::parse(
        '{{ block_id = {page_builder_block_auto_id} }}{{ block_id }}',
        ['uamsweb_block_i' => 2],
        true
    );

    expect(trim($out))->toBe('module-3');
});

it('defaults to module-1 when no ids or index are present', function (): void {
    $tag = new PageBuilderBlockAutoId;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('module-1');
});

it('ignores a non-numeric block index', function (): void {
    $tag = new PageBuilderBlockAutoId;
    $tag->setContext(['uamsweb_block_i' => 'nope']);
    $tag->setParameters([]);

    expect($tag->index())->toBe('module-1');
});

it('coerces a numeric string block index', function (): void {
    $tag = new PageBuilderBlockAutoId;
    $tag->setContext(['uamsweb_block_i' => '2']);
    $tag->setParameters([]);

    expect($tag->index())->toBe('module-3');
});

it('truncates a float block index before adding one', function (): void {
    $tag = new PageBuilderBlockAutoId;
    $tag->setContext(['uamsweb_block_i' => 2.5]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('module-3');
});

it('unwraps a Statamic Value on block id', function (): void {
    $tag = new PageBuilderBlockAutoId;
    $tag->setContext([
        'block' => ['id' => new Value('wrapped-id')],
    ]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('module-wrapped-id');
});

it('returns an empty suffix for a non-scalar id', function (): void {
    $tag = new PageBuilderBlockAutoId;
    $tag->setContext([
        'block' => ['id' => ['not', 'scalar']],
    ]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('module-');
});
