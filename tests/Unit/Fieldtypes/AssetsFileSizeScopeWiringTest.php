<?php

declare(strict_types=1);

use Tests\Support\Doubles\AssetsFileSizeScopeProbe;

/**
 * Producer side of the UAMS-Web/uams-statamic-archive#490 file-size scope: the Assets fieldtype derives the
 * encoded `assets_by_file_size_*` handles from `min_file_size`/`max_file_size`
 * (MB -> bytes) and merges them into `query_scopes` alongside the file-type
 * scopes. The scope's decode side is covered by AssetsByFileSizeScopeTest; this
 * covers the load-bearing encode + wiring.
 *
 * @command  php artisan test --compact tests/Unit/Fieldtypes/AssetsFileSizeScopeWiringTest.php
 */
/**
 * The probe that exposes the protected producer methods. They take $config
 * directly, so no Field is needed.
 */
function assetsFileSizeFieldtype(): AssetsFileSizeScopeProbe
{
    return new AssetsFileSizeScopeProbe;
}

it('encodes min/max file size (MB) as byte-bound scope handles', function () {
    $ft = assetsFileSizeFieldtype();

    // 1 MB -> 1,000,000 bytes; 1.5 MB -> 1,500,000 bytes (decimal MB).
    expect($ft->encodeForTest(['min_file_size' => 1]))->toBe(['assets_by_file_size_min_1000000']);
    expect($ft->encodeForTest(['max_file_size' => 10]))->toBe(['assets_by_file_size_max_10000000']);
    expect($ft->encodeForTest(['min_file_size' => 1.5]))->toBe(['assets_by_file_size_min_1500000']);
    expect($ft->encodeForTest(['min_file_size' => 1, 'max_file_size' => 10]))
        ->toBe(['assets_by_file_size_min_1000000', 'assets_by_file_size_max_10000000']);
    // No / non-positive / non-numeric bound -> nothing encoded.
    expect($ft->encodeForTest([]))->toBe([]);
    expect($ft->encodeForTest(['min_file_size' => 0]))->toBe([]);
    expect($ft->encodeForTest(['max_file_size' => 'huge']))->toBe([]);
});

it('adds the base + encoded size handles to query_scopes, preserving existing scopes', function () {
    $ft = assetsFileSizeFieldtype();

    $config = $ft->applyForTest([
        'min_file_size' => 1,
        'max_file_size' => 10,
        'query_scopes' => ['assets_by_file_type', 'assets_by_file_type_image_jpeg'],
    ]);

    expect($config['query_scopes'])
        ->toContain('assets_by_file_type')             // existing file-type scopes preserved
        ->toContain('assets_by_file_type_image_jpeg')
        ->toContain('assets_by_file_size')             // base handle (the one that resolves + reads the rest)
        ->toContain('assets_by_file_size_min_1000000') // encoded data handles
        ->toContain('assets_by_file_size_max_10000000');
});

it('leaves query_scopes untouched when no size bound is configured', function () {
    $ft = assetsFileSizeFieldtype();

    $config = $ft->applyForTest(['query_scopes' => ['assets_by_file_type']]);

    expect($config['query_scopes'])->toBe(['assets_by_file_type']);
});
