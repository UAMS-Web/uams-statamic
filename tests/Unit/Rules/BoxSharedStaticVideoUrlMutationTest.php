<?php

declare(strict_types=1);

use App\Rules\BoxSharedStaticVideoUrl;
use Tests\TestCase;

uses(TestCase::class);

covers(BoxSharedStaticVideoUrl::class);

const VIDEO_MUTATE_BOX_MP4 = 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4';
const VIDEO_MUTATE_BOX_MP3 = 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp3';

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(12_000_000);
        $padded = true;
    }
});

/**
 * @covers \App\Rules\BoxSharedStaticVideoUrl
 *
 * @command  php artisan test --compact tests/Unit/Rules/BoxSharedStaticVideoUrlMutationTest.php
 */
function videoMutateRuleFails(string $value): bool
{
    $failed = false;

    (new BoxSharedStaticVideoUrl)->validate(
        'url',
        $value,
        function () use (&$failed): void {
            $failed = true;
        },
    );

    return $failed;
}

it('accepts a Box shared-static video URL', function (): void {
    expect(videoMutateRuleFails(VIDEO_MUTATE_BOX_MP4))->toBeFalse();
});

it('rejects a Box shared-static audio URL', function (): void {
    expect(videoMutateRuleFails(VIDEO_MUTATE_BOX_MP3))->toBeTrue();
});

it('rejects a non-Box URL', function (): void {
    expect(videoMutateRuleFails('https://example.com/file.mp4'))->toBeTrue();
});
