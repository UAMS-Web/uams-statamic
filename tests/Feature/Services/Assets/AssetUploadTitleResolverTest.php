<?php

declare(strict_types=1);

use App\Services\Assets\AssetUploadTitleResolver;
use App\Services\Assets\PdfDocumentMetadataReader;
use Facades\Statamic\Assets\ExtractInfo;
use Statamic\Assets\Asset;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for upload title resolution (deslugify + getID3 title paths).
 *
 * Mutation: every mutant of App\Services\Assets\AssetUploadTitleResolver is killed
 * by this file, measured without `--covered-only` so the `TITLE_PATHS` items are in
 * the population (#883). One mutant is
 * equivalent and carries `@pest-mutate-ignore` in the class: `EmptyStringToNotEmpty`
 * on the `?? ''` after `preg_replace()`, which returns null only when its pattern
 * fails, and the fixed `~[-_]~` pattern over a string cannot.
 *
 * @command  php artisan test --compact tests/Feature/Services/Assets/AssetUploadTitleResolverTest.php
 */
it('builds a title from the original upload filename', function (string $filename, string $expected) {
    expect((new AssetUploadTitleResolver)->titleFromOriginalFilename($filename))->toBe($expected);
})->with([
    'deslugifies hyphens like the Statamic example' => ['REL-Match-Day-2026.pdf', 'REL Match Day 2026'],
    'replaces underscores and hyphens' => ['foo_bar-baz.png', 'foo bar baz'],
    'urldecodes the stem' => ['A%20B.jpg', 'A B'],
]);

it('deslugifies a stored asset filename stem', function () {
    expect((new AssetUploadTitleResolver)->titleFromAssetFilenameStem('rel-match-day-2026'))
        ->toBe('rel match day 2026');
});

it('reads the first usable title from a getID3 analysis payload', function () {
    $resolver = new AssetUploadTitleResolver;

    // comments.title as a plain string
    expect($resolver->firstTitleFromAnalysis([
        'comments' => ['title' => 'Embedded'],
    ]))->toBe('Embedded');

    // comments.title as a list returns the first element
    expect($resolver->firstTitleFromAnalysis([
        'comments' => ['title' => ['First', 'Second']],
    ]))->toBe('First');

    // Falls back to the id3v2 comments path
    expect($resolver->firstTitleFromAnalysis([
        'id3v2' => ['comments' => ['title' => ['ID3 Title']]],
    ]))->toBe('ID3 Title');

    // Null when no known title path is present
    expect($resolver->firstTitleFromAnalysis([]))->toBeNull();
});

/**
 * A mock asset with the given extension.
 */
function titleResolverAsset(string $extension): Asset
{
    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('extension')->andReturn($extension);

    return $asset;
}

it('reads a PDF title from preloaded details without reading the file again', function (string $extension) {
    $asset = titleResolverAsset($extension);

    $reader = Mockery::mock(PdfDocumentMetadataReader::class);
    $reader->shouldReceive('titleFromDetailsArray')->once()->with(['Title' => 'Annual Report'])->andReturn('Annual Report');
    $reader->shouldReceive('resolveTitle')->never();
    ExtractInfo::shouldReceive('fromAsset')->never();

    expect((new AssetUploadTitleResolver($reader))->resolveEmbeddedTitle($asset, ['Title' => 'Annual Report']))
        ->toBe('Annual Report');
})->with(['pdf', 'PDF']);

it('reads a PDF title from the file when no details are preloaded', function () {
    $asset = titleResolverAsset('pdf');

    $reader = Mockery::mock(PdfDocumentMetadataReader::class);
    $reader->shouldReceive('titleFromDetailsArray')->never();
    $reader->shouldReceive('resolveTitle')->once()->with($asset)->andReturn('From Disk');

    expect((new AssetUploadTitleResolver($reader))->resolveEmbeddedTitle($asset))->toBe('From Disk');
});

it('gives a PDF with no title no embedded title', function (?string $title) {
    $asset = titleResolverAsset('pdf');

    $reader = Mockery::mock(PdfDocumentMetadataReader::class);
    $reader->shouldReceive('resolveTitle')->once()->andReturn($title);
    ExtractInfo::shouldReceive('fromAsset')->never();

    expect((new AssetUploadTitleResolver($reader))->resolveEmbeddedTitle($asset))->toBeNull();
})->with(['null' => [null], 'empty' => ['']]);

it('reads any other file\'s title from its getID3 analysis', function () {
    $asset = titleResolverAsset('mp3');
    ExtractInfo::shouldReceive('fromAsset')->once()->with($asset)
        ->andReturn(['id3v2' => ['comments' => ['title' => ['Episode 12']]]]);

    expect((new AssetUploadTitleResolver)->resolveEmbeddedTitle($asset))->toBe('Episode 12');
});

it('gives no title when the getID3 analysis fails', function () {
    $asset = titleResolverAsset('mp4');
    ExtractInfo::shouldReceive('fromAsset')->once()->andThrow(new RuntimeException('unreadable'));

    expect((new AssetUploadTitleResolver)->resolveEmbeddedTitle($asset))->toBeNull();
});

/**
 * A getID3 analysis payload carrying a title at one dot path and nowhere else.
 *
 * @param  string  $path  Dot path such as `id3v2.comments.title`.
 * @return array<string, mixed> Nested payload with `['Only Here']` at `$path`.
 */
function titleOnlyAt(string $path): array
{
    // Wrap the title in one array level per path segment, innermost first
    $analysis = ['Only Here'];
    foreach (array_reverse(explode('.', $path)) as $segment) {
        $analysis = [$segment => $analysis];
    }

    return $analysis;
}

it('reads a title found only at one known getID3 path', function (string $path) {
    // Dropping this path from the lookup leaves the payload with no title it can find
    expect((new AssetUploadTitleResolver)->firstTitleFromAnalysis(titleOnlyAt($path)))->toBe('Only Here');
})->with([
    'comments.title',
    'id3v2.comments.title',
    'id3v1.comments.title',
    'quicktime.comments.title',
    'matroska.comments.title',
    'vorbiscomment.comments.title',
    'riff.comments.title',
    'asf.comments.title',
    'mpeg.comments.title',
]);

it('passes over blank and non-text title candidates to the next usable one', function () {
    $resolver = new AssetUploadTitleResolver;

    expect($resolver->firstTitleFromAnalysis([
        'comments' => ['title' => '   '],
        'id3v2' => ['comments' => ['title' => [42, '  ', ' Second Choice ']]],
    ]))->toBe('Second Choice')
        ->and($resolver->firstTitleFromAnalysis([
            'comments' => ['title' => [null, '']],
        ]))->toBeNull();
});

it('trims the spaces a leading or trailing separator leaves', function () {
    expect((new AssetUploadTitleResolver)->titleFromAssetFilenameStem('-draft_'))->toBe('draft')
        ->and((new AssetUploadTitleResolver)->titleFromOriginalFilename('_final-report-.pdf'))->toBe('final report');
});
