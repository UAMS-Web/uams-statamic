<?php

declare(strict_types=1);

use App\Services\Assets\AssetUploadMetadataMapper;
use App\Services\Assets\PdfDocumentMetadataReader;
use Facades\Statamic\Assets\ExtractInfo;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for container-specific upload metadata mapping.
 *
 * Mutation: the image-style container routing — including the UAMS-Web/uams-statamic-archive#831 exclusion of
 * the logo containers — and the alt/caption/exemption mapping are fully covered.
 * Pre-existing survivors remain in the PDF/audio excerpt paths and the metadata
 * stringify helper (unchanged by UAMS-Web/uams-statamic-archive#831, exercised by the full suite).
 *
 * @command  php artisan test --compact tests/Feature/Services/Assets/AssetUploadMetadataMapperTest.php
 */
it('maps the PDF Subject to the excerpt for news releases', function () {
    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('handle')->andReturn('news_releases');

    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('container')->andReturn($container);
    $asset->shouldReceive('extension')->andReturn('pdf');
    $asset->shouldReceive('id')->andReturn('asset::news/foo.pdf');

    $updates = (new AssetUploadMetadataMapper(new PdfDocumentMetadataReader))
        ->resolveMetaUpdates($asset, ['Subject' => 'PDF subject line']);

    expect($updates)->toBe(['excerpt' => 'PDF subject line']);
});

it('maps the dc:description to the excerpt for audio', function () {
    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('handle')->andReturn('audio');

    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('container')->andReturn($container);
    $asset->shouldReceive('extension')->andReturn('pdf');
    $asset->shouldReceive('id')->andReturn('asset::audio/foo.pdf');

    $updates = (new AssetUploadMetadataMapper(new PdfDocumentMetadataReader))
        ->resolveMetaUpdates($asset, ['dc:description' => 'Summary from XMP']);

    expect($updates)->toBe(['excerpt' => 'Summary from XMP']);
});

it('maps XMP dc fields to alt, caption, and the alt exemption for images', function () {
    ExtractInfo::shouldReceive('fromAsset')
        ->once()
        ->andReturn([
            'xmp' => [
                'dc' => [
                    'title' => 'Alt from XMP',
                    'description' => 'Caption from XMP',
                ],
            ],
        ]);

    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('handle')->andReturn('images');

    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('container')->andReturn($container);
    $asset->shouldReceive('id')->andReturn('asset::images/foo.jpg');

    $updates = (new AssetUploadMetadataMapper(new PdfDocumentMetadataReader))
        ->resolveMetaUpdates($asset, null);

    expect($updates)->toBe([
        'caption' => 'Caption from XMP',
        'alt' => 'Alt from XMP',
        'exempt_from_alt' => true,
    ]);
});

it('does not write alt, caption, or exempt_from_alt for the logos container (#831)', function () {
    // Logo blueprints dropped these fields (UAMS-Web/uams-statamic-archive#49); the file must never be analyzed
    // for image-style meta on a logo upload.
    ExtractInfo::shouldReceive('fromAsset')->never();

    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('handle')->andReturn('logos');

    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('container')->andReturn($container);
    $asset->shouldReceive('id')->andReturn('asset::logos/brand.svg');

    $updates = (new AssetUploadMetadataMapper(new PdfDocumentMetadataReader))
        ->resolveMetaUpdates($asset, null);

    expect($updates)->toBe([]);
});

it('does not write alt, caption, or exempt_from_alt for the logos_unrestricted container (#831)', function () {
    ExtractInfo::shouldReceive('fromAsset')->never();

    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('handle')->andReturn('logos_unrestricted');

    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('container')->andReturn($container);
    $asset->shouldReceive('id')->andReturn('asset::logos_unrestricted/brand.svg');

    $updates = (new AssetUploadMetadataMapper(new PdfDocumentMetadataReader))
        ->resolveMetaUpdates($asset, null);

    expect($updates)->toBe([]);
});

it('still maps alt, caption, and the alt exemption for content-image containers (#831)', function (string $containerHandle) {
    // Every retained image-style container keeps the mapping unchanged; pinning
    // each const member so a future removal of one goes red.
    ExtractInfo::shouldReceive('fromAsset')
        ->once()
        ->andReturn([
            'xmp' => [
                'dc' => [
                    'title' => 'Alt from XMP',
                    'description' => 'Caption from XMP',
                ],
            ],
        ]);

    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('handle')->andReturn($containerHandle);

    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('container')->andReturn($container);
    $asset->shouldReceive('id')->andReturn('asset::'.$containerHandle.'/jane.jpg');

    $updates = (new AssetUploadMetadataMapper(new PdfDocumentMetadataReader))
        ->resolveMetaUpdates($asset, null);

    expect($updates)->toBe([
        'caption' => 'Caption from XMP',
        'alt' => 'Alt from XMP',
        'exempt_from_alt' => true,
    ]);
})->with(['portraits', 'portraits_wide']);

/**
 * A mock asset in the given container with the given extension.
 */
function mapperAsset(string $containerHandle, string $extension): Asset
{
    $container = Mockery::mock(AssetContainer::class);
    $container->shouldReceive('handle')->andReturn($containerHandle);

    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('container')->andReturn($container);
    $asset->shouldReceive('extension')->andReturn($extension);

    return $asset;
}

/**
 * Map an asset's metadata with a real PDF details reader.
 *
 * @param  array<string, mixed>|null  $pdfDetails
 * @return array<string, mixed>
 */
function mapperUpdates(Asset $asset, ?array $pdfDetails = null): array
{
    return (new AssetUploadMetadataMapper(new PdfDocumentMetadataReader))->resolveMetaUpdates($asset, $pdfDetails);
}

it('writes no news release excerpt for a file that is not a PDF, has no details, or no subject', function (string $extension, ?array $details) {
    ExtractInfo::shouldReceive('fromAsset')->never();

    /** @var array<string, mixed>|null $typedDetails */
    $typedDetails = $details;

    expect(mapperUpdates(mapperAsset('news_releases', $extension), $typedDetails))->toBe([]);
})->with([
    'not a PDF' => ['docx', ['Subject' => 'Ignored']],
    'no details' => ['pdf', null],
    'a blank subject' => ['PDF', ['Subject' => '   ']],
]);

it('reads a news release excerpt from a PDF in any extension case', function () {
    expect(mapperUpdates(mapperAsset('news_releases', 'PDF'), ['Subject' => ' Release summary ']))
        ->toBe(['excerpt' => 'Release summary']);
});

it('falls back to the analysis description for an audio or files upload without one in its PDF details', function (string $container, string $extension, ?array $details) {
    $asset = mapperAsset($container, $extension);
    ExtractInfo::shouldReceive('fromAsset')->once()->with($asset)
        ->andReturn(['xmp' => ['dc' => ['description' => [' ', 'From the analysis']]]]);

    /** @var array<string, mixed>|null $typedDetails */
    $typedDetails = $details;

    expect(mapperUpdates($asset, $typedDetails))->toBe(['excerpt' => 'From the analysis']);
})->with([
    'an audio file' => ['audio', 'mp3', null],
    'a PDF with no details' => ['files', 'pdf', null],
    'a PDF whose details have no description' => ['files', 'pdf', ['Title' => 'Only a title']],
]);

it('prefers the PDF description over the analysis', function () {
    ExtractInfo::shouldReceive('fromAsset')->never();

    expect(mapperUpdates(mapperAsset('files', 'PDF'), ['dc:description' => 'From the PDF']))
        ->toBe(['excerpt' => 'From the PDF']);
});

it('writes no audio or files excerpt when there is no description anywhere', function (Closure $analysis) {
    $asset = mapperAsset('files', 'mp3');
    ExtractInfo::shouldReceive('fromAsset')->once()->andReturnUsing($analysis);

    expect(mapperUpdates($asset))->toBe([]);
})->with([
    'an empty analysis' => [fn () => []],
    'a failed analysis' => [fn () => throw new RuntimeException('unreadable')],
]);

it('writes nothing for a container with no mapping', function () {
    ExtractInfo::shouldReceive('fromAsset')->never();

    expect(mapperUpdates(mapperAsset('documents', 'pdf'), ['Subject' => 'x', 'dc:description' => 'y']))->toBe([]);
});

it('writes no image meta when the image analysis fails', function () {
    $asset = mapperAsset('images', 'jpg');
    ExtractInfo::shouldReceive('fromAsset')->once()->andThrow(new RuntimeException('unreadable'));

    expect(mapperUpdates($asset))->toBe([]);
});

it('writes only what an image analysis provides', function (array $analysis, array $expected) {
    $asset = mapperAsset('portraits', 'jpg');
    ExtractInfo::shouldReceive('fromAsset')->once()->andReturn($analysis);

    expect(mapperUpdates($asset))->toBe($expected);
})->with([
    'a caption only' => [['comments' => ['caption' => ['A caption']]], ['caption' => 'A caption']],
    'an alt only' => [['comments' => ['headline' => 'A headline']], ['alt' => 'A headline', 'exempt_from_alt' => true]],
    'a numeric title' => [['comments' => ['title' => 2024]], ['alt' => '2024', 'exempt_from_alt' => true]],
    'a float description' => [['comments' => ['description' => 1.5]], ['caption' => '1.5']],
    'nothing usable' => [['comments' => ['title' => true, 'caption' => ['', null]]], []],
]);
