<?php

declare(strict_types=1);

/**
 * Choose the shard timings cache entry every shard of one CI run restores (#519).
 *
 * Each `pest` shard partitions the parallel band from `tests/.pest/shards.json`, which
 * a push to `main` saves to the Actions cache. When each shard restored the newest
 * entry by prefix, a `main` push that saved a new entry while a run's shards were
 * starting split them across two entries, and `merge-shard-reports.php` refused the
 * run. Run #41440485266 on #514 failed that way: shard 1 read one entry, shards 2 to 4
 * another.
 *
 * Every shard now runs this over the same listing rule and the same start time, that
 * of the run's first attempt (so a shard re-run alone with "Re-run failed jobs" agrees
 * with the shards that passed in attempt 1), and restores the exact key it prints: the
 * newest entry created at or before that start. An entry saved after the run started cannot be chosen, so a save that lands
 * between two shards' starts no longer changes the answer, and every shard prints the
 * same key. When no entry qualifies it prints nothing, and every shard falls back to
 * the same committed file alike.
 *
 * Each operating system saves its timings under its own prefix, such as
 * `pest-shard-timings-windows-latest-` (#516), and `--prefix` names the one to choose
 * from, so a shard never chooses another operating system's entry. It defaults to
 * `pest-shard-timings-`, every entry.
 *
 * Usage:
 *   php scripts/select-shard-timings.php --caches=<file> --started=<ISO 8601 time> [--prefix=<key prefix>]
 *
 * `--caches` is the JSON body of `GET /repos/{owner}/{repo}/actions/caches`. Exits 0
 * and prints the chosen key (or nothing) on success, and exits 2 naming the problem
 * when an argument is missing or the listing cannot be read, so a failed fetch fails
 * the shard rather than reading as "no entry".
 */
const SHARD_TIMINGS_PREFIX = 'pest-shard-timings-';

/**
 * Print a message to standard error and exit 2.
 */
function selectShardTimingsFail(string $message): never
{
    fwrite(STDERR, 'select-shard-timings: '.$message.PHP_EOL);

    exit(2);
}

/**
 * The newest key with the prefix created at or before the run started.
 *
 * @param  list<array<string, mixed>>  $caches  The `actions_caches` entries.
 * @param  string  $prefix  The key prefix an entry must start with.
 * @return string|null The chosen key, or null when no entry qualifies.
 */
function selectShardTimingsKey(array $caches, DateTimeImmutable $started, string $prefix): ?string
{
    $chosenKey = null;
    $chosenAt = null;

    foreach ($caches as $cache) {
        $key = $cache['key'] ?? null;
        $createdAt = $cache['created_at'] ?? null;

        if (! is_string($key) || ! str_starts_with($key, $prefix) || ! is_string($createdAt)) {
            continue;
        }

        try {
            $created = new DateTimeImmutable($createdAt);
        } catch (Exception) {
            selectShardTimingsFail("cache entry {$key} has an unreadable created_at: {$createdAt}");
        }

        if ($created > $started) {
            continue;
        }

        // Ties on time go to the greater key, so the choice never depends on list order.
        if ($chosenAt === null || $created > $chosenAt || ($created == $chosenAt && strcmp($key, (string) $chosenKey) > 0)) {
            $chosenKey = $key;
            $chosenAt = $created;
        }
    }

    return $chosenKey;
}

$options = getopt('', ['caches:', 'started:', 'prefix:']);
$cachesPath = $options['caches'] ?? null;
$startedText = $options['started'] ?? null;
$prefix = $options['prefix'] ?? SHARD_TIMINGS_PREFIX;

if (! is_string($cachesPath) || $cachesPath === '' || ! is_string($startedText) || $startedText === '') {
    selectShardTimingsFail('usage: --caches=<file> --started=<ISO 8601 time> [--prefix=<key prefix>]');
}

if (! is_string($prefix) || ! str_starts_with($prefix, SHARD_TIMINGS_PREFIX)) {
    selectShardTimingsFail('--prefix must start with '.SHARD_TIMINGS_PREFIX);
}

$json = is_file($cachesPath) ? file_get_contents($cachesPath) : false;

if ($json === false) {
    selectShardTimingsFail("cannot read {$cachesPath}");
}

$listing = json_decode($json, true);

if (! is_array($listing) || ! isset($listing['actions_caches']) || ! is_array($listing['actions_caches'])) {
    selectShardTimingsFail("{$cachesPath} is not an Actions cache listing (no actions_caches array)");
}

try {
    $started = new DateTimeImmutable($startedText);
} catch (Exception) {
    selectShardTimingsFail("unreadable --started time: {$startedText}");
}

/** @var list<array<string, mixed>> $caches */
$caches = array_values(array_filter($listing['actions_caches'], is_array(...)));

echo selectShardTimingsKey($caches, $started, $prefix) ?? '';
