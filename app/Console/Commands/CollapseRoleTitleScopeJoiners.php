<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\People\RoleTitleScopeJoinerMigrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Rewrite stored role_title scope joiners from the five combined values to a
 * preposition plus a separate article (UAMS-Web/uams-statamic-archive#2546).
 *
 * A dry run by default: it prints one row per legacy joiner and a count per
 * status. Every row is rewritten with `--write`, since each maps to a preposition
 * and an article that compose the same phrase. A status other than `collapsed`
 * marks a row whose title can still move -- through the object, not the scope
 * phrase -- and is listed for review.
 *
 * Run it before `role-title:link-scope-units`, which links the typed unit names
 * this leaves carrying the article.
 *
 * @command php artisan role-title:collapse-scope-joiners [--collection=people] [--write]
 */
#[Description('Rewrite legacy role_title scope joiners to a preposition plus a separate article')]
#[Signature('role-title:collapse-scope-joiners {--collection=people : Collection to scan} {--write : Save the rewritten joiners}')]
class CollapseRoleTitleScopeJoiners extends Command
{
    public function __construct(private readonly RoleTitleScopeJoinerMigrator $migrator)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int SUCCESS -- the report informs; rows to review do not fail the run.
     */
    public function handle(): int
    {
        $collection = (string) $this->option('collection');
        $write = (bool) $this->option('write');

        $findings = $this->migrator->scan($collection, $write);

        if ($findings === []) {
            $this->info(\sprintf('No legacy role_title scope joiners in `%s`.', $collection));

            return self::SUCCESS;
        }

        $this->table(
            ['Entry', 'Path', 'Status', 'From', 'To', 'Article'],
            \array_map(
                static fn (array $finding): array => [
                    $finding['entry'],
                    $finding['path'],
                    $finding['status'],
                    $finding['from'],
                    $finding['to'],
                    $finding['article'] ?? '',
                ],
                $findings
            )
        );

        $counts = \array_count_values(\array_column($findings, 'status'));
        \ksort($counts);

        foreach ($counts as $status => $count) {
            $this->line(\sprintf('  %s: %d', $status, $count));
        }

        $this->newLine();
        $this->line($write
            ? \sprintf('Rewrote %d joiner(s).', \count($findings))
            : \sprintf('Dry run: %d joiner(s) would be rewritten. Re-run with --write to save them.', \count($findings)));

        return self::SUCCESS;
    }
}
