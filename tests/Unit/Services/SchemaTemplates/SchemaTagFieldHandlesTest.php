<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaTagFieldHandles;
use Tests\TestCase;

uses(TestCase::class);

covers(SchemaTagFieldHandles::class);

/**
 * Unit tests for SchemaTagFieldHandles::listFromParam() field handle parsing.
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/SchemaTagFieldHandlesTest.php
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

it('returns a single handle when no pipe is present', function (): void {
    expect(SchemaTagFieldHandles::listFromParam('gallery'))->toBe(['gallery']);
});

it('splits, trims, and skips empty segments', function (string $field, array $expected): void {
    expect(SchemaTagFieldHandles::listFromParam($field))->toBe($expected);
})->with([
    'trims around each piped segment' => [
        ' featured_image_16_9 | featured_image | thumbnail ',
        ['featured_image_16_9', 'featured_image', 'thumbnail'],
    ],
    'skips leading and doubled delimiters' => [
        '|a||b|',
        ['a', 'b'],
    ],
    'trims the whole parameter before splitting' => [
        '  solo  ',
        ['solo'],
    ],
]);

it('returns empty for non-string or blank input', function (mixed $field): void {
    expect(SchemaTagFieldHandles::listFromParam($field))->toBe([]);
})->with([
    'null' => [null],
    'empty string' => [''],
    'whitespace' => ['   '],
    'integer' => [12],
    'array' => [['gallery']],
]);
