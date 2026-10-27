<?php

declare(strict_types=1);

use App\Support\Statamic\Tags\TagParam;
use Illuminate\Support\Collection;
use Tests\TestCase;

covers(TagParam::class);

uses(TestCase::class);

/**
 * @covers \App\Support\Statamic\Tags\TagParam
 *
 * Mutation: 100% on App\Support\Statamic\Tags\TagParam (6 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Support/Statamic/Tags/TagParamTest.php
 */
it('returns an array unchanged', function () {
    $raw = [
        'a' => 1,
        'b' => 'two',
    ];

    expect(TagParam::all($raw))->toBe($raw);
});

it('unwraps a collection via all()', function () {
    /** @var Collection<string, mixed> $collection */
    $collection = Collection::make([
        'x' => 10,
        'y' => 20,
    ]);

    expect(TagParam::all($collection))->toBe([
        'x' => 10,
        'y' => 20,
    ]);
});

it('reads a key from an array and a collection', function () {
    /** @var Collection<string, mixed> $collection */
    $collection = Collection::make(['handle' => 'ok']);

    expect(TagParam::get(['handle' => 'ok'], 'handle'))->toBe('ok');
    expect(TagParam::get($collection, 'handle'))->toBe('ok');
});

it('uses the default when the key is missing', function () {
    expect(TagParam::get([], 'missing', 'fallback'))->toBe('fallback');
});
