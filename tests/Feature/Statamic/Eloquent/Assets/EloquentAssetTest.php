<?php

declare(strict_types=1);

use App\Statamic\Eloquent\Assets\Asset;
use Facades\Statamic\Assets\Attributes;
use Facades\Statamic\Assets\ExtractInfo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The app's Eloquent asset adds a bitrate to the metadata it generates for audio and video,
 * and reads it back (#255 coverage batch 12).
 *
 * Each test puts its file on a local disk rooted in a directory of its own, so the parent's
 * metadata pass reads a real file. Statamic's own attribute pass is stubbed, so the only
 * analysis the test observes is the one this class asks for.
 *
 * @command  php artisan test --compact tests/Feature/Statamic/Eloquent/Assets/EloquentAssetTest.php
 */
beforeEach(function (): void {
    $this->eloquentAssetRoot = storage_path('framework/testing/eloquent-asset-'.uniqid('', true));
    File::ensureDirectoryExists($this->eloquentAssetRoot);
    config(['filesystems.disks.eloquent_asset_probe' => ['driver' => 'local', 'root' => $this->eloquentAssetRoot]]);

    // Statamic's width, height and duration pass; this class's own analysis is mocked per test
    $attributes = Mockery::mock();
    $attributes->shouldReceive('get')->andReturn([]);
    Attributes::shouldReceive('asset')->andReturn($attributes);
});

afterEach(function (): void {
    File::deleteDirectory($this->eloquentAssetRoot);
});

/**
 * An asset on the probe disk at the given path, with the file written when contents are given.
 */
function eloquentAssetOnDisk(string $root, string $path, ?string $contents): Asset
{
    if ($contents !== null) {
        File::put($root.'/'.$path, $contents);
    }

    $container = mustBe(Statamic\Assets\AssetContainer::class, AssetContainer::make('eloquent_asset_probe'), 'the probe container');
    $container->disk('eloquent_asset_probe');

    $asset = new Asset;
    $asset->container($container);
    $asset->path($path);

    return $asset;
}

it('adds the analyzed bitrate to an audio or video file\'s metadata, rounded to whole bits', function (string $path, array $analysis, int $expected): void {
    ExtractInfo::shouldReceive('fromAsset')->once()->andReturn($analysis);

    $meta = eloquentAssetOnDisk($this->eloquentAssetRoot, $path, 'not really media')->generateMeta();

    expect($meta['bitrate'] ?? null)->toBe($expected)
        ->and($meta['size'] ?? null)->toBe(16);
})->with([
    'audio, from the audio stream' => ['episode.mp3', ['audio' => ['bitrate' => 128000.4], 'bitrate' => 99], 128000],
    'video, from the container' => ['clip.mp4', ['bitrate' => 499999.6], 500000],
]);

it('adds no bitrate when the analysis has none, or it cannot be read', function (string $case): void {
    $expectation = ExtractInfo::shouldReceive('fromAsset')->once();

    match ($case) {
        'none' => $expectation->andReturn(['audio' => ['format' => 'mp3']]),
        'zero' => $expectation->andReturn(['bitrate' => 0]),
        default => $expectation->andThrow(new RuntimeException('unreadable')),
    };

    $meta = eloquentAssetOnDisk($this->eloquentAssetRoot, 'episode.mp3', 'not really media')->generateMeta();

    expect($meta)->not->toHaveKey('bitrate')
        ->and($meta['size'] ?? null)->toBe(16);
})->with([
    'no bitrate in the analysis' => ['none'],
    'a zero bitrate' => ['zero'],
    'the analysis throws' => ['throws'],
]);

it('leaves a file that is neither audio nor video, and a missing file, without analyzing it', function (string $path, ?string $contents): void {
    ExtractInfo::shouldReceive('fromAsset')->never();

    $meta = eloquentAssetOnDisk($this->eloquentAssetRoot, $path, $contents)->generateMeta();

    expect($meta)->not->toHaveKey('bitrate')
        ->and(array_key_exists('size', $meta))->toBe($contents !== null);
})->with([
    'a text file' => ['notes.txt', 'plain text'],
    'a missing audio file' => ['gone.mp3', null],
]);

it('reads no bitrate when the metadata cannot be read', function (): void {
    $asset = new class extends Asset
    {
        public function meta(mixed $key = null): mixed
        {
            throw new RuntimeException('metadata unavailable');
        }
    };

    expect($asset->bitrate())->toBeNull();
});

it('reads the last-modified time from the disk when metadata is not cached', function (): void {
    config(['statamic.assets.cache_meta' => false]);
    $asset = eloquentAssetOnDisk($this->eloquentAssetRoot, 'dated.txt', 'dated');
    touch($this->eloquentAssetRoot.'/dated.txt', 1_700_000_000);

    expect($asset->lastModified()->getTimestamp())->toBe(1_700_000_000);
});

it('reads a bitrate from metadata as a number, rounding to the nearest whole bit', function (mixed $stored, ?int $expected): void {
    $asset = new class extends Asset
    {
        public mixed $metaBitrate = null;

        public function meta(mixed $key = null): mixed
        {
            return $key === 'bitrate' ? $this->metaBitrate : [];
        }
    };
    $asset->metaBitrate = $stored;

    expect($asset->bitrate())->toBe($expected);
})->with([
    'a numeric string' => ['191999.6', 192000],
    'rounding down' => [191999.4, 191999],
    'half a bit' => [0.5, 1],
    'under half a bit' => [0.4, 0],
    'zero' => [0, null],
    'negative' => [-5, null],
]);

it('reads an analyzed bitrate as a number, rounding to the nearest whole bit', function (mixed $value, ?int $expected): void {
    $asset = new class extends Asset
    {
        /**
         * @param  array<string, mixed>  $analysis
         */
        public function resolve(array $analysis): ?int
        {
            return $this->resolveBitrateFromAnalysis($analysis);
        }
    };

    expect($asset->resolve(['bitrate' => $value]))->toBe($expected);
})->with([
    'a numeric string' => ['128000.4', 128000],
    'half a bit' => [0.5, 1],
    'zero' => [0, null],
]);

/**
 * An asset hydrated from a database row with the given metadata and update time.
 *
 * @param  array<string, mixed>  $meta
 */
function eloquentAssetFromRow(array $meta, string $updatedAt): Asset
{
    $model = new App\Models\Asset([
        'container' => 'eloquent_asset_probe',
        'folder' => 'default',
        'basename' => 'missing.txt',
        'filename' => 'missing',
        'extension' => 'txt',
        'path' => 'default/missing.txt',
        'meta' => $meta,
    ]);
    $model->updated_at = Carbon::parse($updatedAt);

    return mustBe(Asset::class, Asset::fromModel($model), 'the hydrated asset');
}

it('keeps a cached last-modified time to the whole second', function (): void {
    config(['statamic.assets.cache_meta' => true]);

    $lastModified = eloquentAssetFromRow(['data' => [], 'last_modified' => 1_700_000_000.75], '2024-06-15 10:30:00')->lastModified();

    expect($lastModified->getTimestamp())->toBe(1_700_000_000)
        ->and($lastModified->format('u'))->toBe('000000');
});

it('falls back to the row, then the current time, for a missing file when metadata is not cached', function (?string $updatedAt, ?string $createdAt, string $expected): void {
    // Statamic's parent would read the missing last_modified as 0, the Unix epoch (UAMS-Web/uams-statamic-archive#2995)
    config(['statamic.assets.cache_meta' => false]);
    Carbon::setTestNow('2025-01-02 03:04:05');
    $model = new App\Models\Asset;
    $model->updated_at = $updatedAt === null ? null : Carbon::parse($updatedAt);
    $model->created_at = $createdAt === null ? null : Carbon::parse($createdAt);
    $asset = eloquentAssetOnDisk($this->eloquentAssetRoot, 'gone.txt', null)->model($model);

    try {
        $lastModified = mustBe(Asset::class, $asset, 'the asset')->lastModified();
    } finally {
        Carbon::setTestNow();
    }

    expect($lastModified->getTimestamp())->toBe(Carbon::parse($expected)->getTimestamp());
})->with([
    'the updated time' => ['2024-06-15 10:30:00', '2024-01-01 00:00:00', '2024-06-15 10:30:00'],
    'the created time when there is no updated time' => [null, '2024-01-01 00:00:00', '2024-01-01 00:00:00'],
    'the current time when the row has neither' => [null, null, '2025-01-02 03:04:05'],
]);

it('reads the row rather than the file when cached metadata has no last-modified time', function (): void {
    // meta('last_modified') would fall back to generateMeta(), which needs the container and reads the file
    config(['statamic.assets.cache_meta' => true]);
    File::ensureDirectoryExists($this->eloquentAssetRoot.'/default');
    File::put($this->eloquentAssetRoot.'/default/missing.txt', 'present');
    touch($this->eloquentAssetRoot.'/default/missing.txt', 1_700_000_000);

    $lastModified = eloquentAssetFromRow(['data' => []], '2024-06-15 10:30:00')->lastModified();

    expect($lastModified->getTimestamp())->toBe(Carbon::parse('2024-06-15 10:30:00')->getTimestamp());
});

it('falls back to the current time for a missing file with no row when metadata is not cached', function (): void {
    config(['statamic.assets.cache_meta' => false]);
    Carbon::setTestNow('2025-01-02 03:04:05');

    try {
        $lastModified = eloquentAssetOnDisk($this->eloquentAssetRoot, 'gone.txt', null)->lastModified();
    } finally {
        Carbon::setTestNow();
    }

    expect($lastModified->getTimestamp())->toBe(Carbon::parse('2025-01-02 03:04:05')->getTimestamp());
});
