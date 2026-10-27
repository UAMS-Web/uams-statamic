<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SchemaType;
use App\Scopes\EntriesBySchemaType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Manage the EntriesBySchemaType scope alias cache (clear, warm, refresh).
 *
 * Usage:
 *   php artisan scope-alias-cache clear
 *   php artisan scope-alias-cache warm
 *   php artisan scope-alias-cache refresh
 */
#[Description('Clear, warm, or refresh the EntriesBySchemaType scope alias cache')]
#[Signature('scope-alias-cache
                            {action : The action to perform (clear, warm, refresh)}')]
class ScopeAliasCache extends Command
{
    /**
     * Execute the console command.
     *
     * @return int Command exit code
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['clear', 'warm', 'refresh'], true)) {
            $this->components->error('Invalid action. Must be: clear, warm, or refresh.');

            return 1;
        }

        if ($action === 'clear') {
            EntriesBySchemaType::clearAliasesCache();
            $this->components->info('Scope alias cache cleared successfully.');
            unset($action);

            return 0;
        }

        if ($action === 'refresh') {
            // The warm below overwrites the same key in both stores, so the clear changes
            // nothing a reader can see; it is kept so a failed warm leaves no stale list.
            // Removing it is a surviving, equivalent mutant, and no marker can reach a
            // mutation of the statement it sits on.
            EntriesBySchemaType::clearAliasesCache();
            $this->components->info('Scope alias cache cleared successfully.');
        }

        $typeNames = array_map(
            fn (SchemaType $case): string => $case->value,
            SchemaType::cases()
        );
        EntriesBySchemaType::warmAliasesCache($typeNames);

        $count = count($typeNames);
        $this->components->info(sprintf('Scope alias cache warmed successfully (%d types).', $count));
        unset(
            $action,
            $count,
            $typeNames
        );

        return 0;
    }
}
