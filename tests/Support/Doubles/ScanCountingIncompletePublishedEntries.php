<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Widgets\IncompletePublishedEntries;

/**
 * Counts every run of the expensive scan (UAMS-Web/uams-statamic-archive#2506), so a test can assert a dashboard
 * request performs none rather than inferring it from how long the request took.
 *
 * The count is static because the Control Panel resolves the widget itself during a
 * dashboard request; the test never holds that instance.
 */
class ScanCountingIncompletePublishedEntries extends IncompletePublishedEntries
{
    /**
     * Scans run since the last reset.
     */
    public static int $scans = 0;

    /**
     * Record the scan, then run the real one.
     *
     * @return array{ids: list<string>, scanned: int, total: int, truncated: bool}
     */
    #[\Override]
    protected function failingCandidateEntryIds(): array
    {
        self::$scans++;

        return parent::failingCandidateEntryIds();
    }
}
