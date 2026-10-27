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
 * Mutation: 100% on App\Services\Assets\AssetUploadTitleResolver (22 mutants, --covered-only).
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
