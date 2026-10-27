<?php

declare(strict_types=1);

use App\Modifiers\BoxMime;
use Tests\TestCase;

covers(BoxMime::class);

uses(TestCase::class);

/**
 * Narrow mutate coverage for App\Modifiers\BoxMime (direct index() calls only).
 *
 * Avoids Antlers::parse so mutant children stay under Pest's timeout.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/BoxMimeMutationTest.php
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

it('returns the MIME type for a Box MP4 direct URL', function (): void {
    $mime = (new BoxMime)->index(
        'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        [],
        [],
    );

    expect($mime)->toBe('video/mp4');
});

it('trims surrounding whitespace before resolving the MIME type', function (): void {
    // UnwrapTrim would leave the spaces and fail BoxSharedStaticDirectUrl parsing.
    $mime = (new BoxMime)->index(
        '  https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4  ',
        [],
        [],
    );

    expect($mime)->toBe('video/mp4');
});

it('returns an empty string for a non-Box URL', function (): void {
    $mime = (new BoxMime)->index(
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        [],
        [],
    );

    expect($mime)->toBe('');
});

it('returns an empty string for non-string and blank values', function (mixed $value): void {
    expect((new BoxMime)->index(
        $value,
        [],
        [],
    ))->toBe('');
})->with([
    'null' => [null],
    'array' => [[1, 2, 3]],
    'integer' => [42],
    'empty string' => [''],
    'whitespace only' => ['   '],
]);
