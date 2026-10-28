<?php

declare(strict_types=1);

use App\Rules\BaseRules\BaseValidationRule;
use App\Rules\UamsVideoUrl;
use App\Rules\VimeoUrl;
use App\Rules\YouTubeUrl;
use Tests\TestCase;

uses(TestCase::class);

covers(VimeoUrl::class, YouTubeUrl::class, UamsVideoUrl::class);

/**
 * Behavior of the video URL rules: a value that is not a URL fails with the
 * generic URL message, and a URL that is not a video page of the right host fails
 * with that rule's own message, which must resolve to its translation rather than
 * show the editor the bare key.
 *
 * @command  php artisan test --compact tests/Feature/Rules/VideoUrlRulesTest.php
 */

/**
 * Run a rule and return every message it failed with.
 *
 * @return list<mixed>
 */
function videoUrlMessages(BaseValidationRule $rule, mixed $value): array
{
    $messages = [];

    $rule->validate('video', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

it('fails each video rule with a translated message', function (BaseValidationRule $rule, string $key): void {
    $messages = videoUrlMessages($rule, 'https://example.com/watch?v=abc');

    expect(__($key))->not->toBe($key)
        ->and($messages)->toBe([__($key)]);
})->with([
    'Vimeo' => [new VimeoUrl, 'statamic::validation.url_video.vimeo'],
    'YouTube' => [new YouTubeUrl, 'statamic::validation.url_video.youtube'],
    'UAMS' => [new UamsVideoUrl, 'statamic::validation.url_video.uams'],
]);

it('accepts a Vimeo video page', function (string $value): void {
    expect(videoUrlMessages(new VimeoUrl, $value))->toBe([]);
})->with([
    'https' => ['https://vimeo.com/123456789'],
    'http' => ['http://vimeo.com/123456789'],
    'with a privacy hash' => ['https://vimeo.com/123456789/abcDEF123'],
]);

it('rejects a URL that is not a Vimeo video page', function (string $value): void {
    expect(videoUrlMessages(new VimeoUrl, $value))->toBe([__('statamic::validation.url_video.vimeo')]);
})->with([
    'www host' => ['https://www.vimeo.com/123456789'],
    'channel page' => ['https://vimeo.com/channels/staffpicks'],
    'id with a letter' => ['https://vimeo.com/12345a'],
    'hash with a symbol' => ['https://vimeo.com/123456789/abc-def'],
    'trailing slash' => ['https://vimeo.com/123456789/'],
    'another scheme' => ['ftp://vimeo.com/123456789'],
]);

it('accepts a YouTube video page', function (string $value): void {
    expect(videoUrlMessages(new YouTubeUrl, $value))->toBe([]);
})->with([
    'watch page' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    'watch page without www' => ['https://youtube.com/watch?v=dQw4w9WgXcQ'],
    'watch page over http' => ['http://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    'watch page with more parameters' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s'],
    'short link' => ['https://youtu.be/dQw4w9WgXcQ'],
    'short link with www' => ['https://www.youtu.be/dQw4w9WgXcQ'],
]);

it('rejects a URL that is not a YouTube video page', function (string $value): void {
    expect(videoUrlMessages(new YouTubeUrl, $value))->toBe([__('statamic::validation.url_video.youtube')]);
})->with([
    'channel page' => ['https://www.youtube.com/@uams'],
    'watch page without an id' => ['https://www.youtube.com/watch?v='],
    'id with a symbol' => ['https://youtu.be/dQw4w9WgXc!'],
    'another parameter first' => ['https://www.youtube.com/watch?t=42&v=dQw4w9WgXcQ'],
    'another host' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ'],
]);

it('rejects a value that is not a URL with the generic URL message', function (BaseValidationRule $rule): void {
    expect(videoUrlMessages($rule, 'vimeo.com/123456789'))->toBe([__('statamic::validation.url')]);
})->with([
    'Vimeo' => [new VimeoUrl],
    'YouTube' => [new YouTubeUrl],
]);
