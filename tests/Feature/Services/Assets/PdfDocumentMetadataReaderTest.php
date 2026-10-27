<?php

declare(strict_types=1);

use App\Services\Assets\PdfDocumentMetadataReader;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Statamic\Assets\Asset;
use Tests\TestCase;

// cspell:ignore endobj startxref

uses(TestCase::class);

/**
 * Unit tests for PDF Info / XMP metadata extraction helpers.
 *
 * Mutation: 100% on App\Services\Assets\PdfDocumentMetadataReader (41 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Services/Assets/PdfDocumentMetadataReaderTest.php
 */
it('resolves the first usable title from a details array', function () {
    $reader = new PdfDocumentMetadataReader;

    // Prefers the dc:title XMP key over the Info Title key
    expect($reader->firstTitleFromDetails([
        'dc:title' => 'From XMP',
        'Title' => 'From Info',
    ]))->toBe('From XMP');

    // Falls back to the Info Title key
    expect($reader->firstTitleFromDetails([
        'Title' => 'From Info',
    ]))->toBe('From Info');

    // Unwraps a single-element array value
    expect($reader->firstTitleFromDetails([
        'Title' => ['Wrapped'],
    ]))->toBe('Wrapped');

    // Scans suffix `:Title` keys
    expect($reader->firstTitleFromDetails([
        'xmp:Title' => 'Alt',
    ]))->toBe('Alt');

    // Null when every candidate is blank
    expect($reader->firstTitleFromDetails([
        'dc:title' => '   ',
        'Title' => '',
    ]))->toBeNull();
});

it('reads the subject from a details array', function () {
    expect((new PdfDocumentMetadataReader)->subjectFromDetailsArray([
        'Subject' => 'Release summary',
    ]))->toBe('Release summary');
});

it('reads the dc:description from a details array', function () {
    expect((new PdfDocumentMetadataReader)->dcDescriptionFromDetailsArray([
        'dc:description' => 'Longer body',
    ]))->toBe('Longer body');
});

/**
 * The bytes of a minimal one-page PDF whose Info dictionary holds the given entries.
 */
function pdfReaderMinimalPdf(string $info): string
{
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>',
        '<< '.$info.' >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $index => $body) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n{$body}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R /Info 4 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}

/**
 * A mock asset at the given path whose disk is the given filesystem.
 */
function pdfReaderAsset(object $filesystem, mixed $path = 'docs/report.pdf', bool $exists = true): Asset
{
    $disk = Mockery::mock();
    $disk->shouldReceive('filesystem')->andReturn($filesystem);

    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('exists')->andReturn($exists);
    $asset->shouldReceive('disk')->andReturn($disk);
    $asset->shouldReceive('path')->andReturn($path);

    return $asset;
}

/**
 * The reader's temporary copies currently in the system temp directory.
 *
 * @return list<string>
 */
function pdfReaderTempCopies(): array
{
    return glob(sys_get_temp_dir().'/statamic-pdf-meta-*') ?: [];
}

beforeEach(function (): void {
    $this->pdfPath = storage_path('framework/testing/pdf-reader-'.uniqid().'.pdf');
    File::ensureDirectoryExists(dirname($this->pdfPath));
    File::put($this->pdfPath, pdfReaderMinimalPdf('/Title (Annual Report) /Subject (Finances)'));
});

afterEach(function (): void {
    File::delete($this->pdfPath);
});

it('reads details straight from a local disk file it can read', function () {
    $local = Mockery::mock(FilesystemAdapter::class);
    $local->shouldReceive('path')->once()->with('docs/report.pdf')->andReturn($this->pdfPath);
    $local->shouldReceive('get')->never();

    $details = (new PdfDocumentMetadataReader)->loadDetails(pdfReaderAsset($local));

    expect($details['Title'] ?? null)->toBe('Annual Report')
        ->and($details['Subject'] ?? null)->toBe('Finances')
        // The file was read in place, not copied, so it is still there.
        ->and(is_file($this->pdfPath))->toBeTrue();
});

it('copies the file to a temporary path when it cannot read it in place, and removes the copy', function (bool $local) {
    $filesystem = $local
        ? Mockery::mock(FilesystemAdapter::class)
        : Mockery::mock(Filesystem::class);

    if ($local) {
        $filesystem->shouldReceive('path')->andReturn('/no/such/file.pdf');
    }

    $filesystem->shouldReceive('get')->once()->with('docs/report.pdf')->andReturn(File::get($this->pdfPath));

    $before = pdfReaderTempCopies();

    $details = (new PdfDocumentMetadataReader)->loadDetails(pdfReaderAsset($filesystem));

    expect($details['Title'] ?? null)->toBe('Annual Report')
        ->and(array_diff(pdfReaderTempCopies(), $before))->toBe([]);
})->with(['a local disk with an unreadable path' => [true], 'a remote disk' => [false]]);

it('reads an asset with no string path from the disk root path', function () {
    $remote = Mockery::mock(Filesystem::class);
    $remote->shouldReceive('get')->once()->with('')->andReturn(File::get($this->pdfPath));

    expect((new PdfDocumentMetadataReader)->loadDetails(pdfReaderAsset($remote, null))['Title'] ?? null)
        ->toBe('Annual Report');
});

it('gives no details, and leaves no copy, when the file cannot be read or parsed', function (Closure $get) {
    $remote = Mockery::mock(Filesystem::class);
    $remote->shouldReceive('get')->andReturnUsing($get);

    $before = pdfReaderTempCopies();

    expect((new PdfDocumentMetadataReader)->loadDetails(pdfReaderAsset($remote)))->toBeNull()
        ->and(array_diff(pdfReaderTempCopies(), $before))->toBe([]);
})->with([
    'the read throws' => [fn () => throw new RuntimeException('disk offline')],
    'the file is empty' => [fn () => ''],
    'the read returns nothing' => [fn () => null],
    'the file is not a PDF' => [fn () => 'plain text, not a PDF'],
]);

it('gives no details for an asset whose file is missing, without touching the disk', function () {
    $remote = Mockery::mock(Filesystem::class);
    $remote->shouldReceive('get')->never();

    expect((new PdfDocumentMetadataReader)->loadDetails(pdfReaderAsset($remote, exists: false)))->toBeNull();
});

it('resolves a title from the file, or none when it cannot be read', function () {
    $local = Mockery::mock(FilesystemAdapter::class);
    $local->shouldReceive('path')->andReturn($this->pdfPath);

    $missing = Mockery::mock(Filesystem::class);

    $reader = new PdfDocumentMetadataReader;

    expect($reader->resolveTitle(pdfReaderAsset($local)))->toBe('Annual Report')
        ->and($reader->resolveTitle(pdfReaderAsset($missing, exists: false)))->toBeNull();
});

it('reads the title from the delegated details-array entry point', function () {
    expect((new PdfDocumentMetadataReader)->titleFromDetailsArray(['Title' => ' From Info ']))->toBe('From Info');
});

it('falls back to any other title-like key when the known ones are blank', function (array $details, ?string $expected) {
    /** @var array<string, mixed> $typedDetails */
    $typedDetails = $details;

    expect((new PdfDocumentMetadataReader)->firstTitleFromDetails($typedDetails))->toBe($expected);
})->with([
    'a namespaced title' => [['Title' => '', 'pdf:Title' => ' Namespaced '], 'Namespaced'],
    'an upper-case title key' => [['TITLE' => 'Shouting'], 'Shouting'],
    'a blank fallback then a usable one' => [['xmp:title' => '  ', 'dc:Title' => 'Second'], 'Second'],
    'no title-like key' => [['Author' => 'Someone', 'Subtitle' => 'Not a title'], null],
]);

it('reads the first usable value from a list of detail values', function (mixed $value, ?string $expected) {
    expect((new PdfDocumentMetadataReader)->subjectFromDetailsArray(['Subject' => $value]))->toBe($expected);
})->with([
    'the first usable of several' => [['', ['  '], 'Second', 'Third'], 'Second'],
    'a single blank entry' => [['   '], null],
    'several blank entries' => [['', null, []], null],
    'a number' => [42, null],
]);

it('reads past a missing or blank key to the next', function () {
    expect((new PdfDocumentMetadataReader)->firstDetailStringFromDetails(
        ['Subject' => '  ', 'subject' => 'Lower-case key'],
        ['Missing', 'Subject', 'subject'],
    ))->toBe('Lower-case key');
});

it('reads past a numeric detail key to a title-like one', function () {
    // PHP stores a numeric-string key as an int, which the declared key type does not show.
    // @phpstan-ignore argument.type (deliberately off-contract: an int key must not break the string comparison)
    expect((new PdfDocumentMetadataReader)->firstTitleFromDetails([0 => 'Untitled scan', 'pdf:Title' => 'Scan Title']))
        ->toBe('Scan Title');
});

it('reads the second of two detail values when the first is blank', function () {
    expect((new PdfDocumentMetadataReader)->subjectFromDetailsArray(['Subject' => ['  ', 'Second of two']]))
        ->toBe('Second of two');
});
