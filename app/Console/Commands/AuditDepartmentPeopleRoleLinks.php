<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ComputedFields\DepartmentHub\DepartmentHubPeopleComputedValues;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Report how completely the `job_title` org links the Departmental News Hub
 * people zone derives from are populated (UAMS-Web/uams-statamic-archive#1557).
 *
 * The people zone derives from each person's `job_title` role sets: a required
 * leadership set is the reliable spine, while the optional member/faculty sets
 * (e.g. `faculty_role_title.department`) may be sparsely linked. This audits the
 * link-population rate per mapped role set across published `people`, so the
 * derived-vs-curated call for a fuller roster rests on data rather than a guess.
 *
 * Informational (always exits zero): it prints a per-set completeness table and a
 * derived-vs-curated recommendation per optional set.
 *
 * @command php artisan people:audit-department-role-links
 */
#[Description('Report job_title org-link completeness for the Departmental News Hub people zone')]
#[Signature('people:audit-department-role-links')]
class AuditDepartmentPeopleRoleLinks extends Command
{
    /**
     * The linked-rate at or above which an optional set's link is complete enough
     * to derive a fuller roster from, rather than curate it.
     */
    private const float DERIVE_THRESHOLD = 0.8;

    public function __construct(private readonly DepartmentHubPeopleComputedValues $people)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int Always SUCCESS — the audit reports, it does not gate.
     */
    public function handle(): int
    {
        $report = $this->people->auditRoleLinkCompleteness();

        if ($report === []) {
            $this->warn('No Departmental News Hub role-set mapping is configured (config/department_hub.php).');

            return self::SUCCESS;
        }

        $this->info('Departmental News Hub — job_title org-link completeness (published people):');

        $rows = [];
        foreach ($report as $set => $stats) {
            $rows[] = [
                $set,
                $stats['leadership'] ? 'leadership' : 'member',
                $stats['link'],
                $stats['rows'],
                $stats['linked'],
                $this->percent($stats['linked'], $stats['rows']),
            ];
        }

        unset($stats);

        $this->table(['Role set', 'Kind', 'Link', 'Rows', 'Linked', 'Linked %'], $rows);

        // A per-set derived-vs-curated recommendation for the optional (member) sets;
        // leadership is always the derived spine.
        foreach ($report as $set => $stats) {
            if ($stats['leadership']) {
                continue;
            }

            // Check whether the set has any published rows to score
            if ($stats['rows'] === 0) {
                // Keep curated when the link sample is empty
                $recommendation = 'keep the fuller roster curated (link too sparse to derive)';
            } else {
                // Compute the linked-row rate for the derive threshold
                $rate = $stats['linked'] / $stats['rows'];

                // Recommend derive when the rate clears the threshold, else curate
                $recommendation = $rate >= self::DERIVE_THRESHOLD
                    ? 'derive the fuller roster from this set'
                    : 'keep the fuller roster curated (link too sparse to derive)';
            }

            // Print the per-set recommendation line
            $this->line(\sprintf('  - %s: %s.', $set, $recommendation));
        }

        unset($stats);

        return self::SUCCESS;
    }

    /**
     * Format a linked/total ratio as a whole-number percentage string (or "n/a"
     * when there are no rows of the set).
     */
    private function percent(int $linked, int $total): string
    {
        if ($total === 0) {
            return 'n/a';
        }

        return round($linked / $total * 100).'%';
    }
}
