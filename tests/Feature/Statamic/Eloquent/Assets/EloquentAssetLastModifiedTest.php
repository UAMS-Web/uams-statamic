<?php

declare(strict_types=1);

use App\Models\Asset as AssetModel;
use App\Statamic\Eloquent\Assets\Asset;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Mutation: 100% on App\Statamic\Eloquent\Assets\Asset (12 mutants, --covered-only).
 */
uses(TestCase::class);

it('falls back to model updated_at when meta omits last_modified', function (): void {
    /** @var string $timezone */
    $timezone = config('app.timezone');

    $expected = Carbon::parse(
        '2024-06-15 10:30:00',
        $timezone
    );

    $model = new AssetModel([
        'container' => 'test_container',
        'folder' => 'default',
        'basename' => 'logo.svg',
        'filename' => 'logo',
        'extension' => 'svg',
        'path' => 'default/logo.svg',
        'meta' => [
            'data' => [],
        ],
    ]);

    $model->updated_at = $expected;
    $model->created_at = Carbon::parse(
        '2024-01-01 08:00:00',
        $timezone
    );

    /** @var Asset $asset */
    $asset = Asset::fromModel($model);

    expect($asset->lastModified()->getTimestamp())->toBe($expected->getTimestamp());
});

it('uses meta unix timestamp when present', function (): void {
    $unix = (int) Carbon::parse(
        '2023-03-01 12:00:00',
        'UTC'
    )->timestamp;

    $model = new AssetModel([
        'container' => 'test_container',
        'folder' => '/',
        'basename' => 'a.png',
        'filename' => 'a',
        'extension' => 'png',
        'path' => 'a.png',
        'meta' => [
            'data' => [],
            'last_modified' => $unix,
        ],
    ]);

    /** @var Asset $asset */
    $asset = Asset::fromModel($model);

    expect($asset->lastModified()->getTimestamp())->toBe($unix);
});

it('falls back to now when no meta and no model timestamps', function (): void {
    $model = new AssetModel([
        'container' => 'test_container',
        'folder' => '/',
        'basename' => 'b.png',
        'filename' => 'b',
        'extension' => 'png',
        'path' => 'b.png',
        'meta' => [
            'data' => [],
        ],
    ]);

    $model->updated_at = null;
    $model->created_at = null;

    /** @var Asset $asset */
    $asset = Asset::fromModel($model);

    $before = Carbon::now()->getTimestamp();

    $result = $asset->lastModified()->getTimestamp();

    $after = Carbon::now()->getTimestamp();

    expect($result)->toBeGreaterThanOrEqual($before);
    expect($result)->toBeLessThanOrEqual($after);
});
