<?php

declare(strict_types=1);

use App\Support\BoxSharedStaticDirectUrl;

/**
 * Unit tests for {@see BoxSharedStaticDirectUrl}.
 *
 * Mutation: 100% on App\Support\BoxSharedStaticDirectUrl (66 mutants, --covered-only).
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
