<?php

declare(strict_types=1);

use App\Listeners\SetAssetMetadataFromUpload;
use App\Services\Assets\AssetUploadMetadataMapper;
use App\Services\Assets\AssetUploadTitleResolver;
use App\Services\Assets\PdfDocumentMetadataReader;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Events\AssetReuploaded;
use Statamic\Events\AssetUploaded;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Listeners\SetAssetMetadataFromUpload
 *
 * Mutation: 100% on App\Listeners\SetAssetMetadataFromUpload (30 mutants), measured on macOS on 2026-10-08, serial, --covered-only, with every test file that names the class.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/SetAssetMetadataFromUploadTest.php
 */
it('sets the title from embedded metadata and saves', function () {
    $titleResolver = Mockery::mock(AssetUploadTitleResolver::class);
    $titleResolver->shouldReceive('resolveEmbeddedTitle')
        ->once()
        ->andReturn('Embedded Title');
    $titleResolver->shouldReceive('titleFromOriginalFilename')->never();
    $titleResolver->shouldReceive('titleFromAssetFilenameStem')->never();

    $pdfReader = Mockery::mock(PdfDocumentMetadataReader::class);
    $pdfReader->shouldReceive('loadDetails')->never();

    $mapper = Mockery::mock(AssetUploadMetadataMapper::class);
    $mapper->shouldReceive('resolveMetaUpdates')
        ->once()
        ->andReturn([]);

    $asset = Mockery::mock(StatamicAsset::class);
    $asset->shouldReceive('extension')
        ->once()
        ->andReturn('png');
    $asset->shouldReceive('set')
        ->once()
        ->with('title', 'Embedded Title');
    $asset->shouldReceive('save')->once();

    $listener = new SetAssetMetadataFromUpload($titleResolver, $pdfReader, $mapper);
    $listener->handle(new AssetUploaded($asset, null));
});

/**
 * The three collaborators the listener is built from, as mocks keyed by role.
 *
 * @return array{titles: AssetUploadTitleResolver&MockInterface, pdf: PdfDocumentMetadataReader&MockInterface, mapper: AssetUploadMetadataMapper&MockInterface}
 */
function uploadMetadataCollaborators(): array
{
    return [
        'titles' => Mockery::mock(AssetUploadTitleResolver::class),
        'pdf' => Mockery::mock(PdfDocumentMetadataReader::class),
        'mapper' => Mockery::mock(AssetUploadMetadataMapper::class),
    ];
}

/**
 * Build the listener from the collaborators.
 *
 * @param  array{titles: AssetUploadTitleResolver, pdf: PdfDocumentMetadataReader, mapper: AssetUploadMetadataMapper}  $collaborators
 */
function uploadMetadataListener(array $collaborators): SetAssetMetadataFromUpload
{
    return new SetAssetMetadataFromUpload($collaborators['titles'], $collaborators['pdf'], $collaborators['mapper']);
}

/**
 * A mock asset with the given extension.
 */
function uploadMetadataAsset(string $extension): StatamicAsset&MockInterface
{
    $asset = Mockery::mock(StatamicAsset::class);
    $asset->shouldReceive('extension')->andReturn($extension);

    return $asset;
}

it('reads PDF details once, whatever the extension case, and hands them to both resolvers', function (string $extension): void {
    $details = ['Title' => 'Annual Report', 'Subject' => 'Finances'];
    $asset = uploadMetadataAsset($extension);

    $c = uploadMetadataCollaborators();
    $c['pdf']->shouldReceive('loadDetails')->once()->with($asset)->andReturn($details);
    $c['titles']->shouldReceive('resolveEmbeddedTitle')->once()->with($asset, $details)->andReturn('Annual Report');
    $c['mapper']->shouldReceive('resolveMetaUpdates')->once()->with($asset, $details)->andReturn([]);

    $asset->shouldReceive('set')->once()->with('title', 'Annual Report');
    $asset->shouldReceive('save')->once();

    uploadMetadataListener($c)->handle(new AssetUploaded($asset, 'annual-report.pdf'));
})->with(['lower case' => ['pdf'], 'upper case' => ['PDF']]);

it('passes no PDF details for a file that is not a PDF', function (): void {
    $asset = uploadMetadataAsset('jpg');

    $c = uploadMetadataCollaborators();
    $c['pdf']->shouldReceive('loadDetails')->never();
    $c['titles']->shouldReceive('resolveEmbeddedTitle')->once()->with($asset, null)->andReturn('Sunrise');
    $c['mapper']->shouldReceive('resolveMetaUpdates')->once()->with($asset, null)->andReturn([]);

    $asset->shouldReceive('set')->once()->with('title', 'Sunrise');
    $asset->shouldReceive('save')->once();

    uploadMetadataListener($c)->handle(new AssetUploaded($asset, 'sunrise.jpg'));
});

it('titles the asset from the original filename when nothing is embedded', function (?string $embedded): void {
    $asset = uploadMetadataAsset('jpg');

    $c = uploadMetadataCollaborators();
    $c['titles']->shouldReceive('resolveEmbeddedTitle')->once()->andReturn($embedded);
    $c['titles']->shouldReceive('titleFromOriginalFilename')->once()->with('Campus Map.jpg')->andReturn('Campus Map');
    $c['titles']->shouldReceive('titleFromAssetFilenameStem')->never();
    $c['mapper']->shouldReceive('resolveMetaUpdates')->once()->andReturn([]);

    $asset->shouldReceive('set')->once()->with('title', 'Campus Map');
    $asset->shouldReceive('save')->once();

    uploadMetadataListener($c)->handle(new AssetUploaded($asset, 'Campus Map.jpg'));
})->with(['no embedded title' => [null], 'an empty embedded title' => ['']]);

it('titles a re-upload from the stored filename stem when the event has no original name', function (mixed $original): void {
    $asset = uploadMetadataAsset('jpg');
    $asset->shouldReceive('filename')->once()->andReturn('campus-map');

    $c = uploadMetadataCollaborators();
    $c['titles']->shouldReceive('resolveEmbeddedTitle')->once()->andReturn(null);
    $c['titles']->shouldReceive('titleFromOriginalFilename')->never();
    $c['titles']->shouldReceive('titleFromAssetFilenameStem')->once()->with('campus-map')->andReturn('Campus Map');
    $c['mapper']->shouldReceive('resolveMetaUpdates')->once()->andReturn([]);

    $asset->shouldReceive('set')->once()->with('title', 'Campus Map');
    $asset->shouldReceive('save')->once();

    uploadMetadataListener($c)->handle(new AssetReuploaded($asset, $original));
})->with(['null' => [null], 'empty' => [''], 'not text' => [['campus-map.jpg']]]);

it('does not save when there is no title and nothing to map', function (): void {
    $asset = uploadMetadataAsset('jpg');
    $asset->shouldReceive('filename')->andReturn('');

    $c = uploadMetadataCollaborators();
    $c['titles']->shouldReceive('resolveEmbeddedTitle')->once()->andReturn(null);
    $c['titles']->shouldReceive('titleFromAssetFilenameStem')->once()->andReturn('');
    $c['mapper']->shouldReceive('resolveMetaUpdates')->once()->andReturn([]);

    $asset->shouldReceive('set')->never();
    $asset->shouldReceive('save')->never();

    uploadMetadataListener($c)->handle(new AssetUploaded($asset, null));
});

it('writes each mapped field and saves, even with no title', function (): void {
    $asset = uploadMetadataAsset('jpg');
    $asset->shouldReceive('filename')->andReturn('');

    $c = uploadMetadataCollaborators();
    $c['titles']->shouldReceive('resolveEmbeddedTitle')->once()->andReturn(null);
    $c['titles']->shouldReceive('titleFromAssetFilenameStem')->once()->andReturn('');
    $c['mapper']->shouldReceive('resolveMetaUpdates')->once()->andReturn([
        'alt' => 'A campus map',
        'exempt_from_alt' => false,
    ]);

    $asset->shouldReceive('set')->once()->with('alt', 'A campus map');
    $asset->shouldReceive('set')->once()->with('exempt_from_alt', false);
    $asset->shouldReceive('set')->with('title', Mockery::any())->never();
    $asset->shouldReceive('save')->once();

    uploadMetadataListener($c)->handle(new AssetUploaded($asset, null));
});

it('reads an asset with no extension as not a PDF', function (): void {
    // Statamic types extension() loosely, and a file with no extension has none; the listener
    // casts before lower-casing, which strict types would otherwise reject.
    $asset = Mockery::mock(StatamicAsset::class);
    $asset->shouldReceive('extension')->andReturn(null);
    $asset->shouldReceive('filename')->andReturn('');

    $c = uploadMetadataCollaborators();
    $c['pdf']->shouldReceive('loadDetails')->never();
    $c['titles']->shouldReceive('resolveEmbeddedTitle')->once()->with($asset, null)->andReturn('Readme');
    $c['mapper']->shouldReceive('resolveMetaUpdates')->once()->with($asset, null)->andReturn([]);

    $asset->shouldReceive('set')->once()->with('title', 'Readme');
    $asset->shouldReceive('save')->once();

    uploadMetadataListener($c)->handle(new AssetUploaded($asset, 'README'));
});
