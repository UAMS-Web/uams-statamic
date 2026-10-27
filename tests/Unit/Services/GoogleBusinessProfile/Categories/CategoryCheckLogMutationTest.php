<?php

declare(strict_types=1);

use App\Services\GoogleBusinessProfile\Categories\CategoryCheckLog;
use App\Services\GoogleBusinessProfile\Categories\CategoryDiffReport;
use App\Services\GoogleBusinessProfile\Categories\CategorySource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

covers(CategoryCheckLog::class);

/**
 * Narrow mutate coverage for App\Services\GoogleBusinessProfile\Categories\CategoryCheckLog.
 *
 * @command  php artisan test --compact tests/Unit/Services/GoogleBusinessProfile/Categories/CategoryCheckLogMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }

    Cache::forget(CategoryCheckLog::CACHE_KEY);
});

/**
 * A minimal source stub for recording checks.
 */
function categoryCheckLogSource(string $label = 'file: test.tsv'): CategorySource
{
    return new readonly class($label) implements CategorySource
    {
        public function __construct(private string $label) {}

        public function label(): string
        {
            return $this->label;
        }

        public function read(): never
        {
            throw new LogicException('not used in CategoryCheckLog mutation tests');
        }
    };
}

/**
 * An empty diff report for cache round-trips.
 */
function categoryCheckLogReport(): CategoryDiffReport
{
    return new CategoryDiffReport(
        new: ['zoo' => 'Zoo'],
        renamed: [],
        removed: [],
        withoutGcid: [],
        malformed: [],
    );
}

it('records a completed check and reads it back via latest', function (): void {
    $log = new CategoryCheckLog;
    $checkedAt = Carbon::parse('2026-09-30 12:00:00', 'UTC');
    $report = categoryCheckLogReport();

    $log->record(
        categoryCheckLogSource('file: categories.tsv'),
        $report,
        $checkedAt,
    );

    expect($log->latest())->toBe([
        'checked_at' => '2026-09-30T12:00:00+00:00',
        'source' => 'file: categories.tsv',
        'counts' => [
            'new' => 1,
            'renamed' => 0,
            'removed' => 0,
            'unreadable' => 0,
            'without_gcid' => 0,
            'malformed' => 0,
        ],
        'report' => $report->toArray(),
    ]);
});

it('returns null from latest when no check has completed', function (): void {
    expect((new CategoryCheckLog)->latest())->toBeNull();
});

it('returns null from latest when the cache value is not an array', function (): void {
    Cache::forever(CategoryCheckLog::CACHE_KEY, 'not-a-record');

    expect((new CategoryCheckLog)->latest())->toBeNull();
});

it('returns null from latest when the cache record is not usable', function (array $record): void {
    Cache::forever(CategoryCheckLog::CACHE_KEY, $record);

    expect((new CategoryCheckLog)->latest())->toBeNull();
})->with([
    'missing checked_at' => [['source' => 'file: x', 'counts' => [], 'report' => []]],
    'missing source' => [['checked_at' => '2026-09-30T12:00:00+00:00', 'counts' => [], 'report' => []]],
    'non-string checked_at' => [['checked_at' => 1, 'source' => 'file: x', 'counts' => [], 'report' => []]],
    'non-string source' => [['checked_at' => '2026-09-30T12:00:00+00:00', 'source' => 1, 'counts' => [], 'report' => []]],
]);

it('treats a check as overdue only after thirty days', function (): void {
    $log = new CategoryCheckLog;
    $checkedAt = '2026-09-01T12:00:00+00:00';

    expect($log->isOverdue($checkedAt, Carbon::parse('2026-10-01 12:00:00', 'UTC')))
        ->toBeFalse()
        ->and($log->isOverdue($checkedAt, Carbon::parse('2026-10-01 12:00:01', 'UTC')))
        ->toBeTrue();
});
