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

// --- #1086: case folding, the guards on a set block, counting, and recursion ---------------------

/**
 * The values of a legacy `video` set block with the given url.
 *
 * @return array<string, mixed>
 */
function legacyVideoValues(string $url): array
{
    return ['type' => 'video', 'video_url' => $url, 'caption' => 'A caption'];
}

/**
 * A Bard set node carrying the given values.
 *
 * @param  array<string, mixed>  $values
 * @return array<string, mixed>
 */
function legacyVideoNode(array $values): array
{
    return ['type' => 'set', 'attrs' => ['id' => 'abc', 'values' => $values]];
}

it('matches a host whatever its case', function (): void {
    expect(LegacyVideoSetConverter::platformFor('https://WWW.YouTube.COM/watch?v=abc'))->toBe('youtube');
});

it('maps a url with no host to no platform', function (string $url): void {
    expect(LegacyVideoSetConverter::platformFor($url))->toBeNull();
})->with([
    'empty' => [''],
    'a path only' => ['/videos/clip.mp4'],
    'not a url' => ['http:///youtube.com'],
]);

it('keeps an embed mode the block already has', function (): void {
    $doc = ['content' => [legacyVideoNode([...legacyVideoValues('https://vimeo.com/1'), 'embed_mode' => 'advanced'])]];

    [$data] = LegacyVideoSetConverter::apply($doc);

    expect(data_get($data, 'content.0.attrs.values.embed_mode'))->toBe('advanced')
        ->and(data_get($data, 'content.0.attrs.values.type'))->toBe('vimeo');
});

it('skips a video block whose url is missing or not a string', function (array $node): void {
    $doc = ['content' => [$node]];

    [$data, $converted, $skipped] = LegacyVideoSetConverter::apply($doc);

    expect([$converted, $skipped])->toBe([0, 1])
        ->and($data)->toBe($doc);
})->with([
    'missing' => [legacyVideoNode(['type' => 'video', 'caption' => 'A caption'])],
    'an integer' => [legacyVideoNode(['type' => 'video', 'video_url' => 12345])],
    'a list' => [legacyVideoNode(['type' => 'video', 'video_url' => ['https://vimeo.com/1']])],
]);

it('converts only a set node that carries attribute values of type video', function (array $node): void {
    $doc = ['content' => [$node]];

    [$data, $converted, $skipped] = LegacyVideoSetConverter::apply($doc);

    expect([$converted, $skipped])->toBe([0, 0])
        ->and($data)->toBe($doc);
})->with([
    'not a set' => [['type' => 'paragraph', 'attrs' => ['values' => ['type' => 'video', 'video_url' => 'https://vimeo.com/1']]]],
    'no type' => [['attrs' => ['values' => ['type' => 'video', 'video_url' => 'https://vimeo.com/1']]]],
    'attrs not an array' => [['type' => 'set', 'attrs' => 'video']],
    'values not an array' => [['type' => 'set', 'attrs' => ['values' => 'video']]],
    'values without a type' => [['type' => 'set', 'attrs' => ['values' => ['video_url' => 'https://vimeo.com/1']]]],
]);

it('counts every block it converts and skips, however deeply nested', function (): void {
    $video = static fn (string $url): array => legacyVideoNode(legacyVideoValues($url));

    $doc = [
        'title' => 'Not an array',
        'body' => [
            $video('https://youtu.be/a'),
            ['type' => 'bullet_list', 'content' => [
                ['type' => 'list_item', 'content' => [$video('https://vimeo.com/2'), $video('https://example.com/x')]],
            ]],
        ],
        'replicator' => [['type' => 'section', 'body' => [$video('https://app.box.com/s/3')]]],
    ];

    [$data, $converted, $skipped] = LegacyVideoSetConverter::apply($doc);

    expect([$converted, $skipped])->toBe([3, 1])
        ->and(data_get($data, 'body.0.attrs.values.type'))->toBe('youtube')
        ->and(data_get($data, 'body.1.content.0.content.0.attrs.values.type'))->toBe('vimeo')
        ->and(data_get($data, 'body.1.content.0.content.1.attrs.values.type'))->toBe('video')
        ->and(data_get($data, 'replicator.0.body.0.attrs.values.type'))->toBe('box')
        ->and(data_get($data, 'title'))->toBe('Not an array');
});

it('returns a value that is not an array unchanged, without walking it', function (mixed $value): void {
    // Walking a scalar would reach `foreach` over it, which PHP reports as a warning
    $warnings = [];
    set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $result = LegacyVideoSetConverter::apply($value);
    } finally {
        restore_error_handler();
    }

    expect($result)->toBe([$value, 0, 0])
        ->and($warnings)->toBe([]);
})->with([
    'null' => [null],
    'a string' => ['type: video'],
    'an integer' => [7],
]);
