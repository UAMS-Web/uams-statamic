<?php

declare(strict_types=1);

use App\Support\BoxSharedStaticDirectUrl;

/**
 * Unit tests for {@see BoxSharedStaticDirectUrl}.
 *
 * Mutation-clean without `--covered-only` (#1067): 44 mutants, all killed. One equivalent
 * is annotated in the class and left out of that count: `TrueToFalse` on `in_array()`'s
 * strict flag, since the needle and every element are strings.
 *
 * @command php artisan test --compact tests/Unit/Support/BoxSharedStaticDirectUrlTest.php
 */
$sampleMp4 = 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4';
$sampleMp3 = 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp3';

it('accepts a valid https Box MP4 direct link', function () use ($sampleMp4) {
    $parsed = BoxSharedStaticDirectUrl::parse($sampleMp4);

    expect($parsed)->not->toBeNull();
    expect(data_get($parsed, 'extension'))->toBe('mp4');
    expect(data_get($parsed, 'category'))->toBe('video');
    expect(data_get($parsed, 'mime_type'))->toBe('application/mp4');
});

it('accepts a valid https Box MP3 direct link', function () use ($sampleMp3) {
    $parsed = BoxSharedStaticDirectUrl::parse($sampleMp3);

    expect($parsed)->not->toBeNull();
    expect(data_get($parsed, 'category'))->toBe('audio');
});

it('rejects the http scheme', function () {
    expect(BoxSharedStaticDirectUrl::parse('http://app.box.com/shared/static/file.mp4'))->toBeNull();
});

it('rejects a non-Box host', function () {
    expect(BoxSharedStaticDirectUrl::parse('https://evil.example.com/shared/static/file.mp4'))->toBeNull();
});

it('rejects the wrong path directory', function () {
    expect(BoxSharedStaticDirectUrl::parse('https://app.box.com/file/shared/static/file.mp4'))->toBeNull();
});

it('rejects a missing file extension', function () {
    expect(BoxSharedStaticDirectUrl::parse('https://app.box.com/shared/static/noextension'))->toBeNull();
});

it('returns an empty mime type string for an invalid url', function () {
    expect(BoxSharedStaticDirectUrl::mimeTypeString('not-a-url'))->toBe('');
});

it('returns the mime type string for a valid Box url', function () use ($sampleMp4) {
    expect(BoxSharedStaticDirectUrl::mimeTypeString($sampleMp4))->toBe('video/mp4');
});

it('accepts a direct link with surrounding whitespace', function () use ($sampleMp4) {
    expect(data_get(BoxSharedStaticDirectUrl::parse("  {$sampleMp4}\n"), 'extension'))->toBe('mp4');
});

it('accepts an upper-case scheme, host and extension, and reports the extension in lower case', function () {
    $parsed = BoxSharedStaticDirectUrl::parse('HTTPS://APP.BOX.COM/shared/static/file.MP3');

    expect(data_get($parsed, 'extension'))->toBe('mp3')
        ->and(data_get($parsed, 'category'))->toBe('audio');
});

it('accepts a file name that is only an extension', function () {
    expect(data_get(BoxSharedStaticDirectUrl::parse('https://app.box.com/shared/static/.mp3'), 'extension'))->toBe('mp3');
});

it('rejects a url that is blank, malformed, or has no usable file path', function (string $url) {
    expect(BoxSharedStaticDirectUrl::parse($url))->toBeNull();
})->with([
    'empty' => [''],
    'only whitespace' => ['   '],
    'not a url' => ['not-a-url'],
    // parse_url() reads https, app.box.com and a valid path here; only FILTER_VALIDATE_URL refuses it
    'a space in the path' => ['https://app.box.com/shared/static/a b.mp4'],
    'no path' => ['https://app.box.com'],
    'the root path' => ['https://app.box.com/'],
    'the directory alone' => ['https://app.box.com/shared/static/'],
    'a trailing dot' => ['https://app.box.com/shared/static/file.'],
    'a lone dot' => ['https://app.box.com/shared/static/.'],
    'an extension with no MIME type' => ['https://app.box.com/shared/static/file.zzqx'],
]);

it('returns the MIME type of a non-MP4 Box url', function () use ($sampleMp3) {
    expect(BoxSharedStaticDirectUrl::mimeTypeString($sampleMp3))->toBe('audio/mp3');
});
