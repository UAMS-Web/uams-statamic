<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Locations\LocationInheritedAddressService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * One-shot migration for UAMS-Web/uams-statamic-archive#2608: turns on all four address-group toggles for every
 * `clinical_locations` child that had the single UAMS-Web/uams-statamic-archive#1269 address override on.
 *
 * Before UAMS-Web/uams-statamic-archive#2608 that one toggle, `parent_address_override_query`, handed a child every
 * address component at once. It is now the master over four groups (street, facility,
 * unit and map), and a child owns only the groups whose own toggle is also on, so a child
 * migrated with the master alone would silently start inheriting everything again. Turning
 * all four on keeps each such child rendering exactly as it did.
 *
 * Only an entry with the master on and none of the four group toggles stored is changed, so
 * an entry an editor has already split is left alone and a second run changes nothing. The
 * write uses `saveQuietly()`, so no save listener runs. Run with `--dry-run` first to list
 * the entries it would change.
 */
#[Description('Turn on every address-group toggle for clinical_locations children with the address override on (UAMS-Web/uams-statamic-archive#2608)')]
#[Signature('content:split-location-address-override {--dry-run : List the locations without changing them}')]
class SplitLocationAddressOverride extends Command
{
    /**
     * The master toggle a child turned on to override its parent's address (UAMS-Web/uams-statamic-archive#1269).
     */
    private const string MASTER = 'parent_address_override_query';

    /**
     * Migrate each qualifying location.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', 'clinical_locations');
        /** @var Collection<int, EntryModel> $entries */
        $entries = $query->get();

        foreach ($entries as $entry) {
            // Skip anything that is not an unsplit child with the master on
            if (! $this->needsSplit($entry)) {
                continue;
            }

            $changed++;

            if (! $dryRun) {
                // Turn every group on, so the child keeps every component it owned before
                foreach (LocationInheritedAddressService::GROUP_OVERRIDE as $toggle) {
                    $entry->set($toggle, true);
                }

                $entry->saveQuietly();
            }

            $id = $entry->id();
            $this->line(sprintf('%s %s', $dryRun ? '[dry-run]' : 'Split', is_string($id) ? $id : '(no id)'));
        }

        $this->info(sprintf(
            '%s %d location%s.',
            $dryRun ? 'Would split' : 'Split',
            $changed,
            $changed === 1 ? '' : 's'
        ));

        return self::SUCCESS;
    }

    /**
     * Whether the entry is a child with the master on and stores none of the four group toggles.
     */
    private function needsSplit(EntryModel $entry): bool
    {
        // Only a child with the master on was overriding anything; a top-level location
        // owns every group whatever it stores, so a leftover master there needs nothing
        if (! $entry->get(self::MASTER) || $entry->parent() === null) {
            return false;
        }

        // An entry that stores any group toggle has already been split, by this or an editor
        return array_all(LocationInheritedAddressService::GROUP_OVERRIDE, fn (string $toggle): bool => $entry->get($toggle) === null);
    }
}
