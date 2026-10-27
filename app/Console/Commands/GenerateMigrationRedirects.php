<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Redirects\MigrationRedirectGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Statamic\Facades\Site;

/**
 * Generates the WordPress → Statamic migration redirect map (UAMS-Web/uams-statamic-archive#1641).
 *
 * A repeatable, idempotent command that walks every imported entry and asset,
 * compares each one's stored `original_wordpress_url` to its current Statamic
 * URL, and writes a `301` into the shared `redirects` store for every URL that
 * changed — plus the synthesized structural WordPress URLs (date and author
 * archives, pagination, feeds, attachment pages, and `?p=`/`?page_id=` ugly
 * permalinks, UAMS-Web/uams-statamic-archive#1657) and a `301`-to-ancestor / `410 Gone` disposition for any
 * retired permalinks supplied via `--retired-from`, and a `301` from each news
 * duplicate the WXR cleaner dropped to its kept post, from the pair file given to
 * `--dedup-redirects` (UAMS-Web/uams-statamic-archive#2943). A pair that carries a WordPress post id is resolved among the
 * entries of the site named by `--dedup-site`, which such a file requires (UAMS-Web/uams-statamic-archive#2960). Safe to re-run after any re-import: a source an
 * existing redirect already covers is skipped.
 *
 * Run `--dry-run` first to preview the map without writing it.
 */
#[Description('Generate migration redirects from imported entries, assets, and retired permalinks (UAMS-Web/uams-statamic-archive#1641)')]
#[Signature('content:generate-migration-redirects
        {--dry-run : Report the redirects that would be written without writing them}
        {--retired-from= : Path to a newline-delimited list of retired WordPress permalinks to disposition (301-to-ancestor / 410)}
        {--dedup-redirects= : Path to the WXR cleaner\'s JSON list of duplicate redirect pairs (source, target) to send each dropped duplicate to its kept post (UAMS-Web/uams-statamic-archive#2943)}
        {--dedup-site= : The handle of the site the duplicates\' posts were imported into; required when a pair carries a WordPress post id (UAMS-Web/uams-statamic-archive#2960)}')]
class GenerateMigrationRedirects extends Command
{
    /**
     * Generate (and, unless dry-running, persist) the migration redirect map.
     */
    public function handle(MigrationRedirectGenerator $generator): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Load any retired permalinks to disposition; bail on an unreadable path.
        $retired = $this->retiredPaths();

        if ($retired === null) {
            return self::FAILURE;
        }

        // Load any duplicate pairs; bail on a file that is unreadable or not a list.
        $dedup = $this->dedupPairs();

        if ($dedup === null) {
            return self::FAILURE;
        }

        // The site a pair's post id is looked up in; bail when it is needed and missing or unknown.
        $dedupSite = $this->dedupSite($dedup['pairs']);

        if ($dedupSite === false) {
            return self::FAILURE;
        }

        // Plan (and optionally write) the map.
        $report = $generator->generate($dryRun, $retired, $dedup['pairs'], $dedupSite);

        // Summarize each disposition for the operator.
        $this->line(sprintf('Entry moves (301):     %d', \count($report->entryMoves)));
        $this->line(sprintf('Duplicates (301):      %d', \count($report->dedupMoves)));
        $this->line(sprintf('Asset moves (301):     %d', \count($report->assetMoves)));
        $this->line(sprintf('Structural URLs:       %d', \count($report->structural)));
        $this->line(sprintf('Retired → ancestor:    %d', \count($report->ancestorMoves)));
        $this->line(sprintf('Retired → gone (410):  %d', \count($report->gone)));
        $this->line(sprintf('Skipped (existing):    %d', $report->skippedExisting));

        // Every pair not written, with why, so a refused duplicate is never silent.
        foreach ([...$dedup['malformed'], ...$report->dedupRefused] as $row) {
            $this->warn(sprintf('Duplicate pair not written (%s): %s -> %s', $row['reason'], $row['source'], $row['target']));
        }

        $this->info(sprintf(
            '%s %d migration redirect%s.',
            $report->written ? 'Wrote' : ($dryRun ? 'Would write' : 'Wrote'),
            $report->total(),
            $report->total() === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }

    /**
     * Read the optional duplicate-pair file the WXR cleaner writes (UAMS-Web/uams-statamic-archive#2943).
     *
     * The file is a JSON list of objects carrying a `source` and a `target` WordPress URL, and
     * optionally `notes`, `source_wordpress_post_id` and `target_wordpress_post_id` (UAMS-Web/uams-statamic-archive#2960);
     * other keys are ignored. A row without both URLs, or with a post id that is not a positive
     * integer, is reported rather than read, and the rest are read. A malformed id is never
     * dropped in favor of the URL: the id is the reliable key, so a bad one is worth seeing.
     *
     * @return array{pairs: list<array{source: string, target: string, notes: string|null, source_wordpress_post_id: int|null, target_wordpress_post_id: int|null}>, malformed: list<array{source: string, target: string, reason: string}>}|null
     *                                                                                                                                                                                                                                            The pairs and the rows that could not be read, or null when the file is unreadable or not a list
     */
    private function dedupPairs(): ?array
    {
        /** @var string|null $path */
        $path = $this->option('dedup-redirects');

        // No file supplied: no duplicates to redirect.
        if ($path === null || $path === '') {
            return ['pairs' => [], 'malformed' => []];
        }

        // is_file() is checked first, and an existing file that cannot be read raises a warning
        // before this comparison is reached, so `=== false` and `=== true` decide the same cases.
        // @pest-mutate-ignore: FalseToTrue
        if (! is_file($path) || ($contents = file_get_contents($path)) === false) {
            $this->error(sprintf('Could not read duplicate redirect pairs: %s', $path));

            return null;
        }

        $decoded = json_decode($contents, true);

        if (! \is_array($decoded) || ! array_is_list($decoded)) {
            $this->error(sprintf('Duplicate redirect pairs are not a JSON list: %s', $path));

            return null;
        }

        $pairs = [];
        $malformed = [];

        foreach ($decoded as $index => $row) {
            $source = \is_array($row) && \is_string($row['source'] ?? null) ? trim($row['source']) : '';
            $target = \is_array($row) && \is_string($row['target'] ?? null) ? trim($row['target']) : '';

            if ($source === '' || $target === '') {
                $malformed[] = ['source' => $source, 'target' => $target, 'reason' => sprintf('row %d has no source or no target', $index + 1)];

                continue;
            }

            // A row is an array here: a non-array row has no source and was reported above
            /** @var array<string, mixed> $row */
            $sourceId = $this->postId($row['source_wordpress_post_id'] ?? null);
            $targetId = $this->postId($row['target_wordpress_post_id'] ?? null);
            $badKey = match (false) {
                $sourceId => 'source_wordpress_post_id',
                $targetId => 'target_wordpress_post_id',
                default => null,
            };

            if ($sourceId === false || $targetId === false) {
                $malformed[] = ['source' => $source, 'target' => $target, 'reason' => sprintf('row %d has a %s that is not a positive integer', $index + 1, $badKey)];

                continue;
            }

            $notes = \is_string($row['notes'] ?? null) && trim($row['notes']) !== '' ? trim($row['notes']) : null;
            $pairs[] = [
                'source' => $source,
                'target' => $target,
                'notes' => $notes,
                'source_wordpress_post_id' => $sourceId,
                'target_wordpress_post_id' => $targetId,
            ];
        }

        return ['pairs' => $pairs, 'malformed' => $malformed];
    }

    /**
     * The site whose entries a pair's WordPress post id is looked up among (UAMS-Web/uams-statamic-archive#2960).
     *
     * Post ids repeat across WordPress subsites, so an id means nothing without the site its post
     * was imported into, and the importer records no site of its own. The handle is therefore
     * required as soon as any pair carries an id, and an unknown handle is refused rather than
     * matching no entry. Without ids the option is read when given and otherwise not needed.
     *
     * @param  list<array{source: string, target: string, notes: string|null, source_wordpress_post_id: int|null, target_wordpress_post_id: int|null}>  $pairs  The pairs read from the file
     * @return string|false|null The site handle, null when none is given and none is needed, or false when the command must stop
     */
    private function dedupSite(array $pairs): string|false|null
    {
        /** @var string|null $option */
        $option = $this->option('dedup-site');
        $handle = $option === null ? '' : trim($option);

        if ($handle === '') {
            foreach ($pairs as $pair) {
                if ($pair['source_wordpress_post_id'] !== null || $pair['target_wordpress_post_id'] !== null) {
                    $this->error('Duplicate redirect pairs carry WordPress post ids, which repeat across sites: pass --dedup-site=<handle> with the site their posts were imported into.');

                    return false;
                }
            }

            return null;
        }

        if (Site::get($handle) === null) {
            $this->error(sprintf('No site has the handle given to --dedup-site: %s', $handle));

            return false;
        }

        return $handle;
    }

    /**
     * A pair row's WordPress post id: null when absent, false when present but not a positive
     * integer. The cleaner writes integers; digits in a string are accepted, as the generator
     * accepts them on an entry's `wordpress_post_id`.
     */
    private function postId(mixed $value): int|false|null
    {
        if ($value === null) {
            return null;
        }

        if (\is_int($value)) {
            return $value > 0 ? $value : false;
        }

        if (\is_string($value) && ctype_digit(trim($value)) && (int) trim($value) > 0) {
            return (int) trim($value);
        }

        return false;
    }

    /**
     * Read and canonicalize the optional retired-permalink list.
     *
     * @return list<string>|null The retired paths, or null when the given path is unreadable
     */
    private function retiredPaths(): ?array
    {
        /** @var string|null $path */
        $path = $this->option('retired-from');

        // No list supplied — nothing to disposition.
        if ($path === null || $path === '') {
            return [];
        }

        // A supplied-but-unreadable path is an operator error, not an empty list.
        if (! is_file($path) || ($contents = file_get_contents($path)) === false) {
            $this->error(sprintf('Could not read retired-permalink list: %s', $path));

            return null;
        }

        // One permalink per line; drop blanks and surrounding whitespace.
        $paths = [];

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                $paths[] = $line;
            }
        }

        return $paths;
    }
}
