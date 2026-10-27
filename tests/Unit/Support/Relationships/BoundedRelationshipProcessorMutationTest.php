<?php

declare(strict_types=1);

use App\Support\Relationships\BoundedRelationshipProcessor;
use Stillat\Relationships\Comparisons\ComparisonResult;
use Stillat\Relationships\EntryRelationship;
use Stillat\Relationships\Processors\RelationshipProcessor;
use Tests\TestCase;

covers(BoundedRelationshipProcessor::class);

uses(TestCase::class);

/**
 * Narrow mutate coverage for App\Support\Relationships\BoundedRelationshipProcessor.
 *
 * Hermetic only: no Stache / collection-tree paths (those live in the feature suite).
 *
 * @command  php artisan test --compact tests/Unit/Support/Relationships/BoundedRelationshipProcessorMutationTest.php
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

/**
 * Seed a protected working-set property on the vendor processor.
 *
 * @param  array<int|string, mixed>|mixed  $value  The value to assign
 */
function boundedProcessorSeed(
    BoundedRelationshipProcessor $processor,
    string $property,
    mixed $value,
): void {
    (new ReflectionProperty(RelationshipProcessor::class, $property))->setValue(
        $processor,
        $value,
    );
}

it('reports working-set sizes for every accumulator', function (): void {
    $processor = new BoundedRelationshipProcessor;

    boundedProcessorSeed(
        $processor,
        'effectedEntries',
        [
            'id-a' => 'entry-a',
            'id-b' => 'entry-b',
        ],
    );
    boundedProcessorSeed(
        $processor,
        'effectedUsers',
        ['id-u' => 'user-u'],
    );
    boundedProcessorSeed(
        $processor,
        'effectedTerms',
        ['id-t' => 'term-t'],
    );
    boundedProcessorSeed(
        $processor,
        'dependencies',
        [
            'dep-1',
            'dep-2',
        ],
    );

    expect($processor->workingSetSizes())->toBe([
        'entries' => 2,
        'users' => 1,
        'terms' => 1,
        'dependencies' => 2,
    ]);
});

it('counts non-array dependencies as zero', function (): void {
    // Ternary / is_array guard on the vendor's untyped $dependencies.
    $processor = new BoundedRelationshipProcessor;
    boundedProcessorSeed(
        $processor,
        'dependencies',
        'not-an-array',
    );

    expect($processor->workingSetSizes()['dependencies'])->toBe(0);
});

it('clears every accumulator via resetWorkingSet', function (): void {
    $processor = new BoundedRelationshipProcessor;

    boundedProcessorSeed(
        $processor,
        'effectedEntries',
        ['id-a' => 'entry-a'],
    );
    boundedProcessorSeed(
        $processor,
        'effectedUsers',
        ['id-u' => 'user-u'],
    );
    boundedProcessorSeed(
        $processor,
        'effectedTerms',
        ['id-t' => 'term-t'],
    );
    boundedProcessorSeed(
        $processor,
        'dependencies',
        ['dep-1'],
    );

    $processor->resetWorkingSet();

    expect($processor->workingSetSizes())->toBe([
        'entries' => 0,
        'users' => 0,
        'terms' => 0,
        'dependencies' => 0,
    ]);
});

it('clears every accumulator before processing a relationship', function (): void {
    $processor = new BoundedRelationshipProcessor;

    // Canary payloads the vendor early-return path never clears: only our reset does.
    boundedProcessorSeed(
        $processor,
        'effectedEntries',
        [
            'canary-entry' => 'stale',
        ],
    );
    boundedProcessorSeed(
        $processor,
        'effectedUsers',
        ['canary-user' => 'stale'],
    );
    boundedProcessorSeed(
        $processor,
        'effectedTerms',
        ['canary-term' => 'stale'],
    );
    boundedProcessorSeed(
        $processor,
        'dependencies',
        ['canary-dep'],
    );

    expect($processor->workingSetSizes()['entries'])->toBe(1);

    // Empty comparison → vendor returns without touching the sets (RemoveMethodCall dies).
    $processor->processRelationship(
        new EntryRelationship,
        new ComparisonResult,
    );

    expect($processor->workingSetSizes())->toBe([
        'entries' => 0,
        'users' => 0,
        'terms' => 0,
        'dependencies' => 0,
    ]);
});
