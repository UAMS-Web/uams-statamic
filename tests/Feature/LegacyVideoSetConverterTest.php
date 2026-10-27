<?php

declare(strict_types=1);

use App\Support\LegacyVideoSetConverter;

/**
 * Coverage for UAMS-Web/uams-statamic-archive#834: the legacy `video` set converter routes stored blocks to
 * the platform sets by URL host, renames `video_url` → `url`, defaults
 * `embed_mode`, and preserves the rest — leaving unrecognized hosts and other
 * sets untouched.
 *
 * @command  php artisan test --compact tests/Feature/LegacyVideoSetConverterTest.php
 */

/**
 * A Bard document holding one legacy `video` set block with the given url.
 *
 * @return array<string, mixed>
 */
function legacyVideoDoc(string $url): array
{
    return [
        'content' => [
            [
                'type' => 'set',
                'attrs' => [
                    'id' => 'abc',
                    'values' => [
                        'type' => 'video',
                        'video_url' => $url,
                        'caption' => 'A caption',
                        'aspect_ratio' => '16by9',
                        'align' => 'aligncenter',
                    ],
                ],
            ],
        ],
    ];
}

it('routes each host to its platform set and normalizes the block', function (string $url, string $platform): void {
    [$data, $converted, $skipped] = LegacyVideoSetConverter::apply(legacyVideoDoc($url));

    expect($converted)->toBe(1);
    expect($skipped)->toBe(0);

    /** @var array{content: array<int, array{attrs: array{values: array<string, mixed>}}>} $data */
    $values = $data['content'][0]['attrs']['values'];
    expect($values['type'])->toBe($platform);
    // video_url renamed to url; embed_mode defaulted; other fields preserved.
    expect($values['url'])->toBe($url);
    expect($values)->not->toHaveKey('video_url');
    expect($values['embed_mode'])->toBe('simple');
    expect($values['caption'])->toBe('A caption');
    expect($values['aspect_ratio'])->toBe('16by9');
    expect($values['align'])->toBe('aligncenter');
})->with([
    ['https://www.youtube.com/watch?v=abc', 'youtube'],
    ['https://youtu.be/abc', 'youtube'],
    ['https://vimeo.com/12345', 'vimeo'],
    ['https://app.box.com/s/xyz', 'box'],
]);

it('skips a video block with an unrecognized host and leaves it untouched', function (): void {
    $doc = legacyVideoDoc('https://example.com/video.mp4');

    [$data, $converted, $skipped] = LegacyVideoSetConverter::apply($doc);

    expect($converted)->toBe(0);
    expect($skipped)->toBe(1);
    expect($data)->toBe($doc);
});

it('does not mis-route lookalike hosts', function (string $url): void {
    // dropbox.com must not become `box`; a spoofed subdomain must not match.
    [, $converted, $skipped] = LegacyVideoSetConverter::apply(legacyVideoDoc($url));

    expect($converted)->toBe(0);
    expect($skipped)->toBe(1);
})->with([
    'https://www.dropbox.com/s/x/clip.mp4',
    'https://youtube.com.evil.com/watch?v=x',
    'https://youtu.be.evil.com/x',
]);

it('leaves non-video set blocks alone', function (): void {
    $doc = ['content' => [
        ['type' => 'set', 'attrs' => ['values' => ['type' => 'youtube', 'url' => 'https://youtu.be/x']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'hello']]],
    ]];

    [$data, $converted, $skipped] = LegacyVideoSetConverter::apply($doc);

    expect($converted)->toBe(0);
    expect($skipped)->toBe(0);
    expect($data)->toBe($doc);
});
