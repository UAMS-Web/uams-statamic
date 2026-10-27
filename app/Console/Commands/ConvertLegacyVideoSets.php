<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LegacyVideoSetConverter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;

/**
 * Converts stored legacy Bard `video` set blocks to the platform-specific
 * `youtube` / `vimeo` / `box` sets (UAMS-Web/uams-statamic-archive#834), routing by the `video_url` host.
 *
 * Run with `--dry-run` first to preview. Blocks whose host matches no known
 * platform are left untouched and reported so they can be handled by hand.
 */
#[Description('Convert stored legacy Bard `video` set blocks to youtube/vimeo/box sets (UAMS-Web/uams-statamic-archive#834)')]
#[Signature('content:convert-legacy-video-sets {--dry-run : Report the changes without saving}')]
class ConvertLegacyVideoSets extends Command
{
    /**
     * Walk every entry, converting legacy video set blocks in place.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $blocksConverted = 0;
        $blocksSkipped = 0;
        $entriesChanged = 0;

        foreach (Entry::all() as $entry) {
            // Guard the collection's mixed element type down to an Entry.
            if (! $entry instanceof EntryModel) {
                continue;
            }

            $rawData = $entry->data();
            $entryData = $rawData instanceof Collection ? $rawData->all() : (\is_array($rawData) ? $rawData : []);

            [$data, $converted, $skipped] = LegacyVideoSetConverter::apply($entryData);
            $blocksSkipped += $skipped;

            if ($converted === 0) {
                continue;
            }

            $blocksConverted += $converted;
            $entriesChanged++;

            if (! $dryRun) {
                $entry->data($data);
                $entry->save();
            }

            $id = $entry->id();

            $this->line(sprintf(
                '%s %s (%d block%s)',
                $dryRun ? '[dry-run]' : 'Converted',
                \is_scalar($id) ? (string) $id : '',
                $converted,
                $converted === 1 ? '' : 's'
            ));
        }

        $this->info(sprintf(
            '%s %d block(s) across %d %s.',
            $dryRun ? 'Would convert' : 'Converted',
            $blocksConverted,
            $entriesChanged,
            $entriesChanged === 1 ? 'entry' : 'entries'
        ));

        if ($blocksSkipped > 0) {
            $this->warn(sprintf('Skipped %d video block(s) with an unrecognized host — convert these by hand.', $blocksSkipped));
        }

        return self::SUCCESS;
    }
}
