<?php

declare(strict_types=1);

/**
 * Compare each shard count's union of shards against the whole-band baseline
 * for the #384 sharding spike, and tabulate the per-job timings.
 *
 * Reads, from the directory given as the first argument, `junit-<id>.xml` and
 * `times-<id>.txt` for every job in the matrix. Writes a Markdown report to
 * stdout. Exits 1 when any shard count's union differs from the baseline.
 */
$dir = rtrim($argv[1] ?? '', '/');

if ($dir === '' || ! is_dir($dir)) {
    fwrite(STDERR, "Usage: php union.php <artifact-dir>\n");
    exit(2);
}

/**
 * Read every test case in a JUnit report as identity => outcome.
 *
 * @param  string  $path  The JUnit report
 * @return array<string, list<string>> Test identity => outcomes, one per occurrence
 */
function readCases(string $path): array
{
    // Load the report, refusing an unreadable one rather than reading it as empty
    $dom = new DOMDocument;
    if (! @$dom->load($path)) {
        fwrite(STDERR, "Cannot read {$path}\n");
        exit(2);
    }

    $cases = [];

    // Key each case on its class, file and name, and record how it ended
    foreach ($dom->getElementsByTagName('testcase') as $case) {
        $class = $case->getAttribute('class') ?: $case->getAttribute('classname');
        if ($class === '' && $case->parentNode instanceof DOMElement) {
            $class = $case->parentNode->getAttribute('name');
        }

        $key = $class.' | '.$case->getAttribute('file').' | '.$case->getAttribute('name');

        $outcome = 'passed';
        foreach (['failure', 'error', 'skipped'] as $tag) {
            if ($case->getElementsByTagName($tag)->length > 0) {
                $outcome = $tag;
                break;
            }
        }

        $cases[$key][] = $outcome;
    }

    return $cases;
}

/**
 * Read a job's recorded epoch marks.
 *
 * @param  string  $path  The times file, one `name=epoch` per line
 * @return array<string, int> Mark name => epoch seconds
 */
function readTimes(string $path): array
{
    // Parse each mark the job wrote, skipping anything malformed
    $marks = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^(\w+)=(\d+)$/', $line, $m) === 1) {
            $marks[$m[1]] = (int) $m[2];
        }
    }

    return $marks;
}

// Load the baseline every union is compared against
$baselinePath = "{$dir}/junit-baseline.xml";
if (! is_file($baselinePath)) {
    fwrite(STDERR, "No baseline report at {$baselinePath}\n");
    exit(2);
}
$baseline = readCases($baselinePath);

$failed = false;
$out = ["## Union of shards against the whole-band baseline\n"];
$out[] = sprintf("Baseline: %d test cases.\n", array_sum(array_map('count', $baseline)));
$out[] = '| N | Cases in union | Missing | Extra | Run more than once | Outcome differs |';
$out[] = '| --- | --- | --- | --- | --- | --- |';

// Find the shard counts this run measured, from the reports of their first shard
$totals = [];
foreach (glob("{$dir}/junit-n*-s1.xml") ?: [] as $first) {
    if (preg_match('/junit-n(\d+)-s1\.xml$/', $first, $m) === 1) {
        $totals[] = (int) $m[1];
    }
}
sort($totals);

// Prefer the shard count the plan names, so a shard that left no report is caught
if (isset($argv[2]) && ctype_digit($argv[2])) {
    $totals = [(int) $argv[2]];
}

if ($totals === []) {
    $failed = true;
    $out[] = '| - | no shard reports found | | | | |';
}

// Compare each shard count's union with the baseline
foreach ($totals as $total) {
    $union = [];
    $absent = [];

    // Merge every shard of this count, noting any report that is missing
    for ($index = 1; $index <= $total; $index++) {
        $path = "{$dir}/junit-n{$total}-s{$index}.xml";
        if (! is_file($path)) {
            $absent[] = "{$index}/{$total}";

            continue;
        }

        foreach (readCases($path) as $key => $outcomes) {
            $union[$key] = [...($union[$key] ?? []), ...$outcomes];
        }
    }

    if ($absent !== []) {
        $failed = true;
        $out[] = sprintf('| %d | no report for shard %s | | | | |', $total, implode(', ', $absent));

        continue;
    }

    $missing = array_diff_key($baseline, $union);
    $extra = array_diff_key($union, $baseline);
    $twice = array_filter($union, fn (array $o): bool => count($o) > 1);
    $differs = [];
    foreach (array_intersect_key($union, $baseline) as $key => $outcomes) {
        if ($outcomes[0] !== $baseline[$key][0]) {
            $differs[$key] = $baseline[$key][0].' -> '.$outcomes[0];
        }
    }

    if ($missing !== [] || $extra !== [] || $twice !== []) {
        $failed = true;
    }

    $out[] = sprintf('| %d | %d | %d | %d | %d | %d |', $total, array_sum(array_map('count', $union)), count($missing), count($extra), count($twice), count($differs));

    // List a sample of each kind of disagreement, so a failure names its cases
    foreach (['missing' => array_keys($missing), 'extra' => array_keys($extra), 'twice' => array_keys($twice), 'differs' => array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($differs), $differs)] as $label => $keys) {
        foreach (array_slice($keys, 0, 10) as $key) {
            $out[] = "<!-- N={$total} {$label}: {$key} -->";
        }
    }
}

$out[] = "\n## Timings (seconds)\n";
$out[] = '| Job | Setup (job start to band start) | Band | Job total | Cases | Skipped |';
$out[] = '| --- | --- | --- | --- | --- | --- |';

// Tabulate each job's setup, band and total time beside its case counts
foreach (glob("{$dir}/times-*.txt") ?: [] as $timesPath) {
    $id = substr(basename($timesPath, '.txt'), 6);
    $t = readTimes($timesPath);
    $cases = is_file("{$dir}/junit-{$id}.xml") ? readCases("{$dir}/junit-{$id}.xml") : [];
    $skipped = count(array_filter($cases, fn (array $o): bool => $o[0] === 'skipped'));

    $out[] = sprintf(
        '| %s | %s | %s | %s | %d | %d |',
        $id,
        isset($t['start'], $t['band_start']) ? $t['band_start'] - $t['start'] : '?',
        isset($t['band_start'], $t['band_end']) ? $t['band_end'] - $t['band_start'] : '?',
        isset($t['start'], $t['band_end']) ? $t['band_end'] - $t['start'] : '?',
        count($cases),
        $skipped,
    );
}

echo implode(PHP_EOL, $out).PHP_EOL;

exit($failed ? 1 : 0);
