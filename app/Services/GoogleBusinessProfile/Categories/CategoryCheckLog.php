<?php

declare(strict_types=1);

namespace App\Services\GoogleBusinessProfile\Categories;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The last completed category check, for the dashboard widget (UAMS-Web/uams-statamic-archive#2615).
 *
 * Kept in the cache rather than under `content/`, and holding the report rather than the
 * list it was read from: the Business Profile APIs storage policy quoted in UAMS-Web/uams-statamic-archive#2091 rules
 * out keeping the fetched list, and the report is what an editor acts on.
 */
final class CategoryCheckLog
{
    /**
     * Cache key holding the last completed check.
     */
    public const string CACHE_KEY = 'gbp_category_check.latest';

    /**
     * Days after which the last check is overdue and the widget says so.
     */
    public const int OVERDUE_AFTER_DAYS = 30;

    /**
     * Record a completed check.
     */
    public function record(CategorySource $source, CategoryDiffReport $report, CarbonInterface $checkedAt): void
    {
        Cache::forever(self::CACHE_KEY, [
            'checked_at' => $checkedAt->toIso8601String(),
            'source' => $source->label(),
            'counts' => $report->counts(),
            'report' => $report->toArray(),
        ]);
    }

    /**
     * The last completed check, or null when none has completed.
     *
     * @return array{checked_at: string, source: string, counts: array<string, int>, report: array<string, mixed>}|null
     */
    public function latest(): ?array
    {
        $record = Cache::get(self::CACHE_KEY);

        if (! \is_array($record) || ! \is_string($record['checked_at'] ?? null) || ! \is_string($record['source'] ?? null)) {
            return null;
        }

        /** @var array{checked_at: string, source: string, counts: array<string, int>, report: array<string, mixed>} $record */
        return $record;
    }

    /**
     * Whether a check made at the given time is overdue by now.
     */
    public function isOverdue(string $checkedAt, CarbonInterface $now): bool
    {
        return Carbon::parse($checkedAt)->addDays(self::OVERDUE_AFTER_DAYS)->lessThan($now);
    }
}
