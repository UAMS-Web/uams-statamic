<?php

declare(strict_types=1);

use App\Models\Asset;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature coverage for Asset::ensureMetaLastModified timestamp resolution.
 *
 * Mutation: 100% on App\Models\Asset (20 mutants, --covered-only).
 */
uses(TestCase::class);

it('preserves existing numeric last_modified', function (): void {
    $meta = [
        'data' => [],
        'last_modified' => 1_700_000_000,
    ];

    $out = Asset::ensureMetaLastModified(
        $meta,
        'logos_unrestricted',
        'default/x.jpg',
        null,
        null
    );

    expect($out['last_modified'])->toBe(1_700_000_000);
});

it('uses updated_at when disk path or container empty', function (): void {
    $updated = Carbon::parse('2024-01-02 12:00:00', 'UTC');

    $out = Asset::ensureMetaLastModified(
        ['data' => ['title' => 'x']],
        '',
        '',
        $updated,
        null
    );

    expect($out['last_modified'])->toBe($updated->getTimestamp());
});

it('falls back to created_at when updated_at null', function (): void {
    $created = Carbon::parse('2023-06-01 08:00:00', 'UTC');

    $out = Asset::ensureMetaLastModified(
        [],
        '',
        '',
        null,
        $created
    );

    expect($out['last_modified'])->toBe($created->getTimestamp());
});

it('uses current time when no timestamps and no disk', function (): void {
    $before = time();

    $out = Asset::ensureMetaLastModified(
        [],
        '',
        '',
        null,
        null
    );

    $after = time();

    expect($out['last_modified'])->toBeGreaterThanOrEqual($before);
    expect($out['last_modified'])->toBeLessThanOrEqual($after);
});

it('replaces non-numeric last_modified with fallback', function (mixed $lastModifiedValue): void {
    $updated = Carbon::parse('2025-01-01 00:00:00', 'UTC');
    $meta = array_merge(
        ['data' => []],
        ['last_modified' => $lastModifiedValue]
    );

    $out = Asset::ensureMetaLastModified(
        $meta,
        '',
        '',
        $updated,
        null
    );

    expect($out['last_modified'])->toBe($updated->getTimestamp());
})->with([
    'null value' => [null],
    'empty string' => [''],
    'non-scalar' => [[[]]],
]);
