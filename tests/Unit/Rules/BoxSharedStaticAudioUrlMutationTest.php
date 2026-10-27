<?php

declare(strict_types=1);

use App\Rules\BoxSharedStaticAudioUrl;
use Tests\TestCase;

uses(TestCase::class);

covers(BoxSharedStaticAudioUrl::class);

const AUDIO_MUTATE_BOX_MP3 = 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp3';
const AUDIO_MUTATE_BOX_MP4 = 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4';

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
 * @covers \App\Rules\BoxSharedStaticAudioUrl
 *
 * @command  php artisan test --compact tests/Unit/Rules/BoxSharedStaticAudioUrlMutationTest.php
 */
function audioMutateRuleFails(string $value): bool
{
    $failed = false;

    (new BoxSharedStaticAudioUrl)->validate(
        'url',
        $value,
        function () use (&$failed): void {
            $failed = true;
        },
    );

    return $failed;
}

it('accepts a Box shared-static audio URL', function (): void {
    expect(audioMutateRuleFails(AUDIO_MUTATE_BOX_MP3))->toBeFalse();
});

it('rejects a Box shared-static video URL', function (): void {
    expect(audioMutateRuleFails(AUDIO_MUTATE_BOX_MP4))->toBeTrue();
});

it('rejects a non-Box URL', function (): void {
    expect(audioMutateRuleFails('https://example.com/file.mp3'))->toBeTrue();
});
