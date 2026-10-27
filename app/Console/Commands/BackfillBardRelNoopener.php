<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modifiers\RelNoopener;
use App\Support\BardRelNoopenerBackfill;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;

/**
 * One-shot backfill for UAMS-Web/uams-statamic-archive#724: rewrites stored Bard link marks that open in a new
 * window ({@code target: '_blank'}) but stored a null {@code rel}, setting
 * {@code rel} to {@code 'noopener noreferrer'}.
 *
 * The render-time {@see RelNoopener} modifier already backstops the
 * rendered output for both stored and future content; this command additionally
 * cleans the data at rest so the stored surface is consistent. Run with
 * {@code --dry-run} first to preview.
 */
#[Description('Backfill rel="noopener noreferrer" on stored Bard target="_blank" links (UAMS-Web/uams-statamic-archive#724)')]
#[Signature('content:backfill-bard-rel {--dry-run : Report the changes without saving}')]
class BackfillBardRelNoopener extends Command
{
    /**
     * Walk every entry, rewriting new-window Bard links that lack a rel.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $linksFixed = 0;
        $entriesFixed = 0;

        foreach (Entry::all() as $entry) {
            // Guard the collection's mixed element type down to an Entry.
            if (! $entry instanceof EntryModel) {
                continue;
            }

            $rawData = $entry->data();
            $entryData = $rawData instanceof Collection ? $rawData->all() : (\is_array($rawData) ? $rawData : []);

            [$data, $count] = BardRelNoopenerBackfill::apply($entryData);

            if ($count === 0) {
                continue;
            }

            $linksFixed += $count;
            $entriesFixed++;

            if (! $dryRun) {
                $entry->data($data);
                $entry->save();
            }

            $id = $entry->id();

            $this->line(sprintf(
                '%s %s (%d link%s)',
                $dryRun ? '[dry-run]' : 'Fixed',
                \is_scalar($id) ? (string) $id : '',
                $count,
                $count === 1 ? '' : 's'
            ));
        }

        $this->info(sprintf(
            '%s %d link(s) across %d %s.',
            $dryRun ? 'Would fix' : 'Fixed',
            $linksFixed,
            $entriesFixed,
            $entriesFixed === 1 ? 'entry' : 'entries'
        ));

        return self::SUCCESS;
    }
}
