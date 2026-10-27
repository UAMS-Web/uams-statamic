<?php

declare(strict_types=1);

use App\Modifiers\BoxMime;
use Statamic\Facades\Antlers;
use Tests\TestCase;

covers(BoxMime::class);

uses(TestCase::class);

/**
 * Behavior of the {@see BoxMime} Antlers modifier: resolve the MIME type for a
 * Box.com shared-static direct URL (delegating to BoxSharedStaticDirectUrl), or
 * an empty string for anything else. Covers the registered Antlers pipe (Box MP4
 * and a non-Box URL) plus the modifier's own guards (non-string, blank, and
 * whitespace-only input).
 *
 * Mutation: tracked under App\Modifiers\BoxMime (--covered-only).
 *
 * @command php artisan test --compact tests/Feature/Modifiers/BoxMimeModifierTest.php
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

it('returns the MIME type for a Box MP4 direct URL through Antlers', function () {
    $out = trim((string) Antlers::parse(
        '{{ u | box_mime }}',
        ['u' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4'],
        true
    ));

    expect($out)->toBe('video/mp4');
});

it('returns an empty string for a non-Box URL through Antlers', function () {
    $out = trim((string) Antlers::parse(
        '{{ u | box_mime }}',
        ['u' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        true
    ));

    expect($out)->toBe('');
});

it('returns an empty string for non-string and blank values', function (mixed $value) {
    expect((new BoxMime)->index($value, [], []))->toBe('');
})->with([
    'null' => [null],
    'array' => [[1, 2, 3]],
    'integer' => [42],
    'empty string' => [''],
    'whitespace only' => ['   '],
]);
