<?php

declare(strict_types=1);

use App\Scopes\AssetsByFileType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Statamic\Assets\QueryBuilder;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Stache;
use Tests\Support\AssetReferenceSweep;
use Tests\TestCase;

/**
 * Exercises `App\Scopes\AssetsByFileType` against a REAL file-backed asset
 * container (not a mocked builder), the way the v6 `BrowserController` applies it.
 *
 * This is the regression guard the ticket (UAMS-Web/uams-statamic-archive#1364) demands: the previous
 * `where('extension', 'in', …)` call used an operator Statamic's query builder
 * does not support, so it silently rewrote to `where('extension', '=', 'in')` and
 * the browser returned NOTHING. A mocked-builder test that only asserts the call
 * shape stays green through that bug; a real query does not.
 *
 * Runs in the `serial` group: `beforeEach` persists a file-backed container whose
 * disk is only `Storage::fake`d in this process (the container-leak convention —
 * see AssetsMinimumDimensionsEnforcementTest, UAMS-Web/uams-statamic-archive#1120).
 *
 * @covers \App\Scopes\AssetsByFileType
 *
 * @command  php artisan test --compact tests/Feature/Scopes/AssetsByFileTypeScopeTest.php
 */
uses(TestCase::class)->group('serial');

const FILE_TYPE_CONTAINER = 'file_type_probe';
const FILE_TYPE_DISK = 'file_type_probe_disk';

/**
 * Apply the scope to the probe container's asset query and return the matching
 * basenames, sorted for stable comparison.
 *
 * @param  array<int, string>  $queryScopes
 * @return array<int, string>
 */
function fileTypeScopeBasenames(array $queryScopes): array
{
    /** @var StatamicAssetContainer $container */
    $container = AssetContainer::find(FILE_TYPE_CONTAINER);
    /** @var QueryBuilder $query */
    $query = $container->queryAssets();

    (new AssetsByFileType)->apply($query, ['queryScopes' => $queryScopes]);

    /** @var Collection<int, Asset> $assets */
    $assets = $query->get();

    return $assets
        ->map(fn ($asset): string => $asset->basename())
        ->sort()
        ->values()
        ->all();
}

beforeEach(function (): void {
    Storage::fake(FILE_TYPE_DISK);
    /** @var StatamicAssetContainer $newContainer */
    $newContainer = AssetContainer::make(FILE_TYPE_CONTAINER);
    $newContainer->disk(FILE_TYPE_DISK);
    $newContainer->title('File Type Probe');
    $newContainer->save();

    /** @var StatamicAssetContainer $container */
    $container = AssetContainer::find(FILE_TYPE_CONTAINER);

    // One asset per extension we assert on. Content is irrelevant — the scope
    // filters on the extension the container derives from the path.
    foreach (['doc.pdf', 'sheet.docx', 'photo.png', 'picture.jpg'] as $basename) {
        Storage::disk(FILE_TYPE_DISK)->put($basename, 'probe');
        /** @var Asset $asset */
        $asset = $container->makeAsset($basename);
        $asset->save();
    }
});

afterEach(function (): void {
    $container = AssetContainer::find(FILE_TYPE_CONTAINER);

    if ($container instanceof StatamicAssetContainer) {
        // Delete without Statamic's asset reference sweep, which walks every entry,
        // term and global per delete and was most of this file's time (UAMS-Web/uams-statamic-archive#2500)
        AssetReferenceSweep::withoutSweep(function () use ($container): void {
            foreach (['doc.pdf', 'sheet.docx', 'photo.png', 'picture.jpg'] as $basename) {
                $container->asset($basename)?->delete();
            }

            $container->delete();
        });
    }

    // No Stache clear (UAMS-Web/uams-statamic-archive#2632): deleting the container above removes it from the
    // `asset-containers` store and its file, and assets live in the Eloquent asset
    // table, a `:memory:` database rebuilt for every test, so no later test can see
    // this file's container, assets or faked disk.
});

it('narrows an enum-case-restricted browser to exactly the matching extension', function (): void {
    // `assets_by_file_type_application_pdf` restricts to the pdf extension.
    expect(fileTypeScopeBasenames(['assets_by_file_type_application_pdf']))
        ->toBe(['doc.pdf']);
});

it('narrows a multi-type restriction to the union of matching extensions', function (): void {
    expect(fileTypeScopeBasenames([
        'assets_by_file_type_application_pdf',
        'assets_by_file_type_image_png',
    ]))->toBe(['doc.pdf', 'photo.png']);
});

it('matches every extension a category resolves to', function (): void {
    // The `image` category resolves to png/jpg (among others) — both probe images match.
    expect(fileTypeScopeBasenames(['assets_by_file_type_image']))
        ->toBe(['photo.png', 'picture.jpg']);
});

it('leaves an unrestricted browser showing every asset (scope no-ops with no handle)', function (): void {
    expect(fileTypeScopeBasenames([]))
        ->toBe(['doc.pdf', 'photo.png', 'picture.jpg', 'sheet.docx']);
});
