<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\RetiredFeaturedImageMigrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * One-shot migration for UAMS-Web/uams-statamic-archive#2005: moves every `pages` entry off the retired
 * `featured_image` handle onto `featured_image_16_9`.
 *
 * Scoped to `pages` deliberately. `clinical_locations` and `clinical_expertise`
 * still define `featured_image` in their blueprints, so the handle is alive there
 * and their entries must not be touched — a repo-wide sweep would corrupt them.
 *
 * Run with `--dry-run` first to preview. The importer-side half of this is
 * `UAMS-Web/wordpress-importer#433`, which stops new ones being written; it does
 * not clean up entries already on disk.
 */
#[Description('Move `pages` entries off the retired `featured_image` handle onto `featured_image_16_9` (UAMS-Web/uams-statamic-archive#2005)')]
#[Signature('content:migrate-pages-featured-image {--dry-run : Report the changes without saving}')]
class MigratePagesFeaturedImage extends Command
{
    /** The one collection whose blueprints no longer define the retired handle. */
    private const string COLLECTION = 'pages';

    /**
     * Walk the `pages` collection, migrating the retired handle in place.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $dropped = 0;
        $needsReview = 0;

        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', self::COLLECTION);
        /** @var Collection<int, EntryModel> $entries */
        $entries = $query->get();

        foreach ($entries as $entry) {
            $path = $entry->path();
            if (! \is_string($path)) {
                continue;
            }

            if (! is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            [$migrated, $outcome] = RetiredFeaturedImageMigrator::apply($contents);

            if ($outcome === RetiredFeaturedImageMigrator::OUTCOME_UNCHANGED) {
                continue;
            }

            if ($outcome === RetiredFeaturedImageMigrator::OUTCOME_NEEDS_REVIEW) {
                $needsReview++;
                $this->warn(sprintf('Needs review: %s carries the handle with no inline value.', $path));

                continue;
            }

            $outcome === RetiredFeaturedImageMigrator::OUTCOME_MOVED ? $moved++ : $dropped++;

            if (! $dryRun) {
                file_put_contents($path, $migrated);
            }

            $this->line(sprintf(
                '%s %s (%s)',
                $dryRun ? '[dry-run]' : 'Migrated',
                $path,
                $outcome
            ));
        }

        $this->info(sprintf(
            '%s %d %s; dropped %d empty or already-replaced key%s.',
            $dryRun ? 'Would move' : 'Moved',
            $moved,
            $moved === 1 ? 'entry' : 'entries',
            $dropped,
            $dropped === 1 ? '' : 's'
        ));

        if ($needsReview > 0) {
            $this->warn(sprintf(
                '%d %s left untouched — migrate by hand.',
                $needsReview,
                $needsReview === 1 ? 'entry' : 'entries'
            ));
        }

        return self::SUCCESS;
    }
}
