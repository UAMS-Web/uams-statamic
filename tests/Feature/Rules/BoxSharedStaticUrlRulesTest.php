<?php

declare(strict_types=1);

use App\Rules\BaseRules\BaseValidationRule;
use App\Rules\BoxSharedStaticAudioUrl;
use App\Rules\BoxSharedStaticUrl;
use App\Rules\BoxSharedStaticVideoUrl;
use Tests\TestCase;

uses(TestCase::class);

covers(BoxSharedStaticUrl::class);

const SAMPLE_BOX_MP4 = 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4';
const SAMPLE_BOX_MP3 = 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp3';

/**
 * Behavior of the Box.com shared-static URL validation rules
 * (App\Rules\BoxSharedStaticUrl / ...VideoUrl / ...AudioUrl): accept HTTPS
 * /shared/static/ direct links with a known MIME mapping, and for the video/audio
 * variants only when the extension's MIME category matches. URL parsing is
 * delegated to BoxSharedStaticDirectUrl (covered by its own Unit test).
 *
 * @command php artisan test --compact tests/Feature/Rules/BoxSharedStaticUrlRulesTest.php
 */
function boxRuleFails(BaseValidationRule $rule, string $value): bool
{
    $failed = false;

    $rule->validate('url', $value, function () use (&$failed) {
        $failed = true;
    });

    return $failed;
}

it('validates the base Box shared static URL rule', function (string $url, bool $shouldPass) {
    expect(boxRuleFails(new BoxSharedStaticUrl, $url))->toBe(! $shouldPass);
})->with([
    'accepts a valid MP4 direct link' => [SAMPLE_BOX_MP4, true],
    'skips an empty value (required handles it)' => ['', true],
    'rejects a non-Box URL' => ['https://example.com/file.mp4', false],
]);

it('validates the Box video URL rule (video category only)', function (string $url, bool $shouldPass) {
    expect(boxRuleFails(new BoxSharedStaticVideoUrl, $url))->toBe(! $shouldPass);
})->with([
    'accepts an MP4' => [SAMPLE_BOX_MP4, true],
    'rejects an MP3 (audio)' => [SAMPLE_BOX_MP3, false],
]);

it('validates the Box audio URL rule (audio category only)', function (string $url, bool $shouldPass) {
    expect(boxRuleFails(new BoxSharedStaticAudioUrl, $url))->toBe(! $shouldPass);
})->with([
    'accepts an MP3' => [SAMPLE_BOX_MP3, true],
    'rejects an MP4 (video)' => [SAMPLE_BOX_MP4, false],
]);
