<?php

declare(strict_types=1);

use App\Rules\UamsVideoUrl;
use Tests\TestCase;

uses(TestCase::class);

covers(UamsVideoUrl::class);

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
 * Behavior of the {@see UamsVideoUrl} validation rule: accept YouTube, Vimeo, or a
 * Box.com HTTPS direct file link under /shared/static/ with a video MIME type, and
 * reject everything else (including audio-only Box links). The platform-specific
 * matching lives in the delegated YouTubeUrl / VimeoUrl / BoxSharedStaticVideoUrl
 * rules; this covers the combined rule's accept/reject wiring.
 *
 * @covers \App\Rules\UamsVideoUrl
 *
 * @command php artisan test --compact tests/Feature/Rules/UamsVideoUrlRuleTest.php
 */
it('accepts supported video URLs and rejects others', function (string $url, bool $shouldPass) {
    $failed = false;

    (new UamsVideoUrl)->validate('video_url', $url, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBe(! $shouldPass);
})->with([
    'Box MP4 direct link' => ['https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4', true],
    'Box MP3 direct link (audio) rejected' => ['https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp3', false],
    'YouTube watch URL' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', true],
    'Vimeo URL' => ['https://vimeo.com/123456789', true],
    'unsupported URL rejected' => ['https://example.com/not-a-video', false],
]);
