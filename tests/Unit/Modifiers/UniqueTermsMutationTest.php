<?php

declare(strict_types=1);

use App\Modifiers\UniqueTerms;
use Tests\TestCase;

uses(TestCase::class);

covers(UniqueTerms::class);

/**
 * Narrow mutate coverage for UniqueTerms.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/UniqueTermsMutationTest.php
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

it('deduplicates id()-bearing objects and keeps later distinct items after a duplicate', function (): void {
    // ContinueToBreak on the duplicate arm would drop $beta.
    $alpha = new class
    {
        public function id(): string
        {
            return 'subject_matter::alpha';
        }
    };
    $alphaAgain = new class
    {
        public function id(): string
        {
            return 'subject_matter::alpha';
        }
    };
    $beta = new class
    {
        public function id(): string
        {
            return 'subject_matter::beta';
        }
    };

    expect((new UniqueTerms)->index([$alpha, $alphaAgain, $beta], [], []))->toBe([$alpha, $beta]);
});

it('deduplicates scalars and null by value', function (): void {
    expect((new UniqueTerms)->index(['a', 'b', 'a', null, null, 1, 1], [], []))
        ->toBe(['a', 'b', null, 1]);
});

it('deduplicates id-less objects by instance identity', function (): void {
    $plainA = new stdClass;
    $plainB = new stdClass;

    expect((new UniqueTerms)->index([$plainA, $plainA, $plainB], [], []))->toBe([$plainA, $plainB]);
});

it('string-casts object ids before comparing', function (): void {
    // RemoveStringCast leaves a float array key (coerced to int 1) that no
    // longer collides with the string "1.5".
    $asFloat = new class
    {
        public function id(): float
        {
            return 1.5;
        }
    };
    $asString = new class
    {
        public function id(): string
        {
            return '1.5';
        }
    };

    expect((new UniqueTerms)->index([$asFloat, $asString], [], []))->toBe([$asFloat]);
});

it('returns an empty array for empty input', function (): void {
    expect((new UniqueTerms)->index([], [], []))->toBe([]);
});

// A term's key is its id() string, unprefixed, so the 'scalar:', 'array:', 'obj:' and
// 'resource:' prefixes are what keep another item's key from equaling a term's. Each case
// pairs an item with two terms whose ids are that item's key with its prefix removed and
// with the prefix moved to the end; all three are distinct items and are kept. Removing
// the prefix (ConcatRemoveLeft) or moving it (ConcatSwitchSides) makes the item's key
// equal one of those ids, and the term is then dropped as a duplicate.
it('keeps a term whose id equals another item\'s key without its prefix, or with the prefix moved', function (Closure $makeItem, string $prefix): void {
    $term = static fn (string $id): object => new class($id)
    {
        public function __construct(private readonly string $key) {}

        public function id(): string
        {
            return $this->key;
        }
    };

    $item = $makeItem();

    try {
        // The item's key as UniqueTerms builds it, less the prefix
        $bare = match (true) {
            \is_object($item) => (string) spl_object_id($item),
            \is_resource($item) => (string) get_resource_id($item),
            default => serialize($item),
        };
        $list = [$item, $term($bare), $term($bare.$prefix)];

        expect((new UniqueTerms)->index($list, [], []))->toBe($list);
    } finally {
        if (\is_resource($item)) {
            fclose($item);
        }
    }
})->with([
    'a scalar' => [fn (): int => 5, 'scalar:'],
    'null' => [fn (): null => null, 'scalar:'],
    'an array' => [fn (): array => ['a'], 'array:'],
    'an id-less object' => [fn (): stdClass => new stdClass, 'obj:'],
    'a resource' => [fn (): mixed => fopen('php://memory', 'r'), 'resource:'],
]);
