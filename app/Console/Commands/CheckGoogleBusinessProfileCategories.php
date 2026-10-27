<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GoogleBusinessProfile\Categories\ApiCategorySource;
use App\Services\GoogleBusinessProfile\Categories\CategoryCheckLog;
use App\Services\GoogleBusinessProfile\Categories\CategoryDiff;
use App\Services\GoogleBusinessProfile\Categories\CategorySource;
use App\Services\GoogleBusinessProfile\Categories\CategorySourceUnavailable;
use App\Services\GoogleBusinessProfile\Categories\FileCategorySource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Reports how a Google Business Profile category list differs from the taxonomy (UAMS-Web/uams-statamic-archive#2615).
 *
 * Reads a pasted `GCID<TAB>Name` file (UAMS-Web/uams-statamic-archive#2091), or with `--api` the Google Business
 * Information API (UAMS-Web/uams-statamic-archive#2617), and prints the GCIDs no term carries, the terms Google has
 * renamed, the terms whose GCID Google no longer lists, the terms whose GCID it lists only
 * on a line that could not be read (UAMS-Web/uams-statamic-archive#2944), and any terms with no GCID. It
 * changes no term and writes nothing under `content/`; the report is the output. A
 * completed check is recorded for the dashboard widget. A source that cannot be read is
 * logged and records nothing, so the last good check, and its overdue state, stay shown.
 */
#[Description('Report how a Google Business Profile category list differs from the taxonomy')]
#[Signature("gbp:check-categories {file? : A file of GCID<TAB>Name lines, as pleper.com's GBP categories tool gives them} {--api : Read the list from the Google Business Information API instead of a file}")]
final class CheckGoogleBusinessProfileCategories extends Command
{
    public function handle(CategoryDiff $diff, CategoryCheckLog $log): int
    {
        $source = $this->source();

        if (! $source instanceof CategorySource) {
            $this->error('Name a category file, or pass --api to read the Google Business Information API, but not both.');

            return self::FAILURE;
        }

        try {
            $list = $source->read();
        } catch (CategorySourceUnavailable $categorySourceUnavailable) {
            $this->error($categorySourceUnavailable->getMessage());
            Log::error('gbp:check-categories could not read its source; the last completed check is unchanged.', [
                'source' => $source->label(),
                'reason' => $categorySourceUnavailable->getMessage(),
            ]);

            return self::FAILURE;
        }

        $report = $diff->compare($list);
        $log->record($source, $report, Carbon::now());

        $counts = $report->counts();
        $this->info(\sprintf(
            '%d categories read: %d new, %d renamed, %d removed, %d listed but unreadable, %d terms with no GCID, %d malformed lines.',
            \count($list->categories),
            $counts['new'],
            $counts['renamed'],
            $counts['removed'],
            $counts['unreadable'],
            $counts['without_gcid'],
            $counts['malformed'],
        ));

        if ($report->new !== []) {
            $this->newLine();
            $this->line('New (no term carries the GCID):');
            $this->table(['GCID', 'Google name'], array_map(
                static fn (string $gcid, string $name): array => [$gcid, $name],
                array_keys($report->new),
                $report->new,
            ));
        }

        if ($report->renamed !== []) {
            $this->newLine();
            $this->line("Renamed (the term title differs from Google's name):");
            // Each row's fields are already in column order
            $this->table(['GCID', 'Term', 'Title', 'Google name'], $report->renamed);
        }

        if ($report->removed !== []) {
            $this->newLine();
            $this->line('Removed (Google no longer lists the GCID):');
            $this->table(['GCID', 'Term', 'Title'], $report->removed);
        }

        if ($report->unreadable !== []) {
            $this->newLine();
            $this->line('Listed but unreadable (Google lists the GCID on a line that was skipped, so the term is not removed):');
            $this->table(['GCID', 'Term', 'Title'], $report->unreadable);
        }

        if ($report->withoutGcid !== []) {
            $this->newLine();
            $this->line('Not compared (the term has no GCID):');
            $this->table(['Term', 'Title'], $report->withoutGcid);
        }

        foreach ($report->malformed as $row) {
            $this->warn(\sprintf('Line %d skipped: %s.', $row['line'], $row['reason']));
        }

        return self::SUCCESS;
    }

    /**
     * The source the options name: a file, or the API with `--api`; null for neither or both.
     */
    private function source(): ?CategorySource
    {
        $file = $this->argument('file');
        $api = $this->option('api') === true;

        if (\is_string($file) && $file !== '' && ! $api) {
            return new FileCategorySource($file);
        }

        if ($api && ($file === null || $file === '')) {
            return ApiCategorySource::fromConfig();
        }

        return null;
    }
}
