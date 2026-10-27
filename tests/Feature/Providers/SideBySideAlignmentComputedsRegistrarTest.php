<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\SideBySideAlignmentComputedsRegistrar;
use App\Services\PageBuilder\SideBySideAlignmentResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\TestCase;

covers(SideBySideAlignmentComputedsRegistrar::class);

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Verifies that the `side_by_side_alignments` computed is registered on exactly the
 * collections that host the replicator `page_builder` (so the Side-by-Side block's
 * auto-alternating options resolve there), and that the registered callback feeds
 * the entry's raw `page_builder` through {@see SideBySideAlignmentResolver} (UAMS-Web/uams-statamic-archive#1192).
 *
 * Mutation: tracked under SideBySideAlignmentComputedsRegistrar (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Providers/SideBySideAlignmentComputedsRegistrarTest.php
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

/** Invoke the registered computed callback with an entry whose page_builder is $blocks. */
function invokeSideBySideComputed(string $collection, mixed $pageBuilder): mixed
{
    /** @var Closure $callback */
    $callback = Collection::getComputedCallbacks($collection)->get(SideBySideAlignmentComputedsRegistrar::HANDLE);

    expect($callback)->not->toBeNull("{$collection} should register the alignment computed");

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('value')->with('page_builder')->andReturn($pageBuilder);

    return $callback($entry, null);
}

it('registers the alignment computed on every replicator page_builder collection', function (string $collection): void {
    expect(Collection::getComputedCallbacks($collection)->has(SideBySideAlignmentComputedsRegistrar::HANDLE))
        ->toBeTrue("{$collection} should register the {$collection} alignment computed");
})->with(['pages', 'reusable_content', 'clinical_locations', 'clinical_expertise']);

it('does not register the alignment computed on Bard/non-page-builder collections', function (string $collection): void {
    expect(Collection::getComputedCallbacks($collection)->has(SideBySideAlignmentComputedsRegistrar::HANDLE))
        ->toBeFalse("{$collection} should not register the alignment computed");
})->with(['people', 'posts', 'clinical_providers']);

it('feeds the entry page_builder through the resolver into a render-order map', function (): void {
    $blocks = [
        ['id' => 'a', 'type' => SideBySideAlignmentResolver::SET_TYPE, 'enabled' => true, 'image_position' => 'auto_default_left'],
        ['id' => 'b', 'type' => SideBySideAlignmentResolver::SET_TYPE, 'enabled' => true, 'image_position' => 'auto_default_left'],
        ['id' => 'c', 'type' => 'uamsweb_block_content', 'enabled' => true],
        ['id' => 'd', 'type' => SideBySideAlignmentResolver::SET_TYPE, 'enabled' => true, 'image_position' => 'auto_default_left'],
    ];

    // a,b alternate (left,right); c breaks the run; d falls back to its default (left).
    expect(invokeSideBySideComputed('pages', $blocks))
        ->toBe(['a' => 'left', 'b' => 'right', 'd' => 'left']);
});

it('returns an empty map when the entry has no page_builder', function (): void {
    expect(invokeSideBySideComputed('pages', null))->toBe([]);
    expect(invokeSideBySideComputed('pages', 'not-iterable'))->toBe([]);
});

it('reads page_builder via value() so localizations inherit the origin blocks', function (): void {
    // get() would miss an origin-inherited page_builder; the callback must call value().
    $captured = [];
    Collection::shouldReceive('computed')
        ->times(4)
        ->andReturnUsing(function (string $collection, string $handle, callable $callback) use (&$captured): void {
            expect($handle)->toBe(SideBySideAlignmentComputedsRegistrar::HANDLE);
            $captured[$collection] = $callback;
        });

    (new SideBySideAlignmentComputedsRegistrar(app(SideBySideAlignmentResolver::class)))->register();

    expect(array_keys($captured))->toEqualCanonicalizing([
        'pages',
        'reusable_content',
        'clinical_locations',
        'clinical_expertise',
    ]);

    $blocks = [
        [
            'id' => 'x',
            'type' => SideBySideAlignmentResolver::SET_TYPE,
            'enabled' => true,
            'image_position' => 'auto_default_left',
        ],
    ];

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('value')
        ->once()
        ->with('page_builder')
        ->andReturn($blocks);
    $entry->shouldReceive('get')->never();

    expect(($captured['pages'])($entry, null))->toBe(['x' => 'left']);
});
