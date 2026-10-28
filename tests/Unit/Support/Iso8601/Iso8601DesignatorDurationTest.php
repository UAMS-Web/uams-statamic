<?php

declare(strict_types=1);

use App\Support\Iso8601\Iso8601DesignatorDuration;

it('writes years, months and days as an ISO 8601 calendar duration', function (int $years, int $months, int $days, string $expected): void {
    expect(Iso8601DesignatorDuration::formatCalendarDateDesignators($years, $months, $days))->toBe($expected);
})->with([
    'every component' => [2, 3, 15, 'P2Y3M15D'],
    'zero components are kept' => [0, 0, 0, 'P0Y0M0D'],
    'multi-digit values' => [10, 11, 30, 'P10Y11M30D'],
]);
