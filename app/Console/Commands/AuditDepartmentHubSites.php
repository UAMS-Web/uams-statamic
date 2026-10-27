<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Multisite\DepartmentHubResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Report opt-in Departmental News Hub sites whose `schema_subject` is
 * unpopulated (UAMS-Web/uams-statamic-archive#1556).
 *
 * A hub site keys its schema entity node on the per-site `schema_subject`
 * global; a hub that opts in (names a `department_organization`) but leaves
 * `schema_subject` empty would render a broken, subject-less entity node. This
 * is the data-integrity gate UAMS-Web/uams-statamic-archive#1556 asks for — the `department_organization`
 * side of the same audit the importer crosswalk runs for unmapped org
 * associations (`wordpress-importer#438`).
 *
 * Reports rather than blocks: it exits non-zero when any hub site is missing a
 * `schema_subject` so it can gate a pre-launch check, and zero when every hub
 * site is schema-complete (or there are no hub sites yet).
 *
 * @command php artisan multisite:audit-department-hubs
 */
#[Description('Report Departmental News Hub sites whose schema_subject is unpopulated')]
#[Signature('multisite:audit-department-hubs')]
class AuditDepartmentHubSites extends Command
{
    public function __construct(private readonly DepartmentHubResolver $resolver)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int SUCCESS when every hub site is schema-complete; FAILURE when any hub site is missing a `schema_subject`.
     */
    public function handle(): int
    {
        $missing = $this->resolver->hubSitesMissingSchemaSubject();

        if ($missing === []) {
            $this->info('All Departmental News Hub sites have a populated schema_subject.');

            return self::SUCCESS;
        }

        $this->error(\sprintf(
            '%d Departmental News Hub site(s) are missing a schema_subject and would emit a broken entity node:',
            \count($missing)
        ));

        foreach ($missing as $handle) {
            $this->line('  - '.$handle);
        }

        unset($handle);

        return self::FAILURE;
    }
}
