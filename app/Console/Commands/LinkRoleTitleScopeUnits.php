<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\People\RoleTitleScopeUnitLinker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Report role_title scopes that name an organizational unit as typed text, and
 * optionally link the ones that can be linked without changing the title (UAMS-Web/uams-statamic-archive#2146).
 *
 * A dry run by default: it prints one row per unit-naming scope and a count per
 * status. `--write` links only the MATCH rows -- a typed name equal to the unit's
 * display name -- so the composed title is unchanged by construction. Every
 * other status is a discrepancy to resolve by hand, and is never rewritten.
 *
 * @command php artisan role-title:link-scope-units [--collection=people] [--write]
 */
#[Description('Report typed role_title scope units, and link the exact matches with --write')]
#[Signature('role-title:link-scope-units {--collection=people : Collection to scan} {--write : Link the exact matches and clear their typed names}')]
class LinkRoleTitleScopeUnits extends Command
{
    public function __construct(private readonly RoleTitleScopeUnitLinker $linker)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int SUCCESS -- the report informs; discrepancies do not fail the run.
     */
    public function handle(): int
    {
        $collection = (string) $this->option('collection');
        $write = (bool) $this->option('write');

        $findings = $this->linker->scan($collection, $write);

        if ($findings === []) {
            $this->info(\sprintf('No unit-naming role_title scopes in `%s`.', $collection));

            return self::SUCCESS;
        }

        $this->table(
            ['Entry', 'Path', 'Status', 'Typed name', 'Unit name'],
            \array_map(
                static fn (array $finding): array => [
                    $finding['entry'],
                    $finding['path'],
                    $finding['status'],
                    $finding['typed'],
                    $finding['unit_name'] ?? '',
                ],
                $findings
            )
        );

        $counts = \array_count_values(\array_column($findings, 'status'));
        \ksort($counts);

        foreach ($counts as $status => $count) {
            $this->line(\sprintf('  %s: %d', $status, $count));
        }

        $matches = $counts[RoleTitleScopeUnitLinker::MATCH] ?? 0;

        $this->newLine();
        $this->line($write
            ? \sprintf('Linked %d scope(s); everything else was left as it is.', $matches)
            : \sprintf('Dry run: %d scope(s) would be linked. Re-run with --write to link them.', $matches));

        return self::SUCCESS;
    }
}
