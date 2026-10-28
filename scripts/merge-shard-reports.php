<?php

declare(strict_types=1);

/**
 * Merge the parallel band's shard reports into one band report, and refuse a
 * set of shards that did not run the band exactly once (#387).
 *
 * The `pest` job in ci.yml runs the parallel band as four `--shard=<i>/4` jobs,
 * each on its own runner. Two checks read the band as a whole:
 * `check-skip-baseline.php` compares the band's skip count with a baseline for
 * the WHOLE band, and `check-slowest-class.php` judges a class by its share of
 * the WHOLE band's time. Fed one shard's report, the first passes or fails on a
 * count it was never meant to see, and the second divides by a quarter of the
 * band. So the shards upload their reports, and the `pest-report` job merges them
 * here before either check runs.
 *
 * A merge alone would hide the failure sharding adds: a test class that no shard
 * ran, or that two shards ran. Pest assigns classes to shards in each job
 * separately, from the timings file that job read, so two shards that read
 * different timings can partition differently, and nothing in either shard's
 * output says so. This script therefore fails, naming what it found, when:
 *
 *   - a shard's report, test listing or timings note is missing or unreadable;
 *   - the shards read different timings, or listed different tests;
 *   - one test case appears in more than one shard;
 *   - any class's case count in the merged report differs from the count
 *     `pest --list-tests --exclude-group=serial` lists for it, which is what a
 *     class no shard ran, a class run twice, or a serial-group class leaking
 *     into the band each look like.
 *
 * The comparison is per class rather than per case because the listing and the
 * JUnit report name a case differently (`__pest_evaluable_it_x` against `it x`),
 * while both name its class the same way. A class is the unit Pest shards by, so
 * a per-class count catches every shape above.
 *
 * It also writes the band's per-class timings in the shape Pest's own
 * `--update-shards` writes to `tests/.pest/shards.json`, summed from the merged
 * report, so the next run can balance its shards on them without the
 * functional-mode cost of `--update-shards` (#384).
 *
 * PHP, not Node, because the `pest-report` job sets up PHP only, like the two
 * checks that read its output.
 *
 * Usage:
 *   php scripts/merge-shard-reports.php --dir=<downloaded artifacts> --total=<N>
 *       --report=<merged JUnit to write> [--timings=<shards.json to write>]
 *
 * Each shard <i> of N is expected to have left, in --dir:
 *   junit-parallel-<i>.xml   its JUnit report
 *   pest-list-<i>.txt        `pest --list-tests --exclude-group=serial` output
 *   shard-timings-<i>.txt    the source and SHA-256 of the timings it read
 *
 * Exit codes: 0 the shards ran the band exactly once and the report is written;
 * 1 they did not, or an input is missing; 2 a usage error.
 */

/**
 * Read a JUnit report into its document and its cases.
 *
 * @param  string  $path  The report to read
 * @return array{document: DOMDocument, cases: list<array{class: string, name: string}>}|null The
 *                                                                                            parsed report, or null when it cannot be read
 */
function readShardReport(string $path): ?array
{
    // Refuse a missing or empty file rather than reading it as a shard that ran nothing
    $xml = is_file($path) ? file_get_contents($path) : false;
    if ($xml === false || trim($xml) === '') {
        return null;
    }

    // Parse it, keeping libxml's warnings out of the output
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadXML($xml);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (! $loaded) {
        return null;
    }

    $cases = [];

    // Name each case by its class and its own name, as the report records them
    foreach ($document->getElementsByTagName('testcase') as $case) {
        $cases[] = [
            'class' => $case->getAttribute('class'),
            'name' => $case->getAttribute('name'),
        ];
    }

    return ['document' => $document, 'cases' => $cases];
}

/**
 * Count the cases a `pest --list-tests` listing names, per class.
 *
 * Each listed case is a line of the form ` - P\Tests\Foo\BarTest::name…`. The
 * `P\` prefix is Pest's namespace for the classes it generates from a test file,
 * and the JUnit report names the same class without it.
 *
 * @param  string  $listing  The listing's full output
 * @return array<string, int> Class name => number of cases listed for it
 */
function listedCasesPerClass(string $listing): array
{
    $counts = [];

    // Strip color codes before matching, so a listing written to a terminal still reads
    $plain = preg_replace('/\e\[[0-9;]*m/', '', $listing) ?? $listing;

    // Count each listed case against its class
    foreach (preg_split('/\R/', $plain) ?: [] as $line) {
        if (preg_match('/^ - (?:P\\\\)?([^:\s]+)::/', $line, $match) !== 1) {
            continue;
        }

        $counts[$match[1]] = ($counts[$match[1]] ?? 0) + 1;
    }

    return $counts;
}

/**
 * Compare per-class case counts and describe each class that differs.
 *
 * @param  array<string, int>  $listed  Class => cases the listing names
 * @param  array<string, int>  $ran  Class => cases the other side holds
 * @param  string  $leftLabel  What the first count is called in the output
 * @param  string  $rightLabel  What the second count is called in the output
 * @return list<string> One line per differing class, sorted by class name
 */
function classCountDifferences(array $listed, array $ran, string $leftLabel = 'listed', string $rightLabel = 'ran'): array
{
    $differences = [];

    // Visit every class either side names, so a class only one side has is reported
    $classes = array_unique([...array_keys($listed), ...array_keys($ran)]);
    sort($classes);

    foreach ($classes as $class) {
        $expected = $listed[$class] ?? 0;
        $actual = $ran[$class] ?? 0;

        if ($expected !== $actual) {
            $differences[] = sprintf('%s: %s %d, %s %d', $class, $leftLabel, $expected, $rightLabel, $actual);
        }
    }

    return $differences;
}

/**
 * Sum each class's case times into the timings Pest balances shards on.
 *
 * @param  list<array{class: string, time: float}>  $cases  Every case in the band
 * @return array{timings: array<string, float>, checksum: string, updated_at: string} The
 *                                                                                    shards.json contents
 */
function shardTimings(array $cases): array
{
    $timings = [];

    // Add each case's time to its class
    foreach ($cases as $case) {
        $timings[$case['class']] = ($timings[$case['class']] ?? 0.0) + $case['time'];
    }

    // Round and order the classes the way Pest's own writer does
    $timings = array_map(fn (float $seconds): float => round($seconds, 4), $timings);
    ksort($timings);

    return [
        'timings' => $timings,
        'checksum' => md5(implode("\n", array_keys($timings))),
        'updated_at' => date('c'),
    ];
}

/**
 * Print a failure and stop.
 *
 * @param  string  $message  What is wrong, first line first
 * @param  list<string>  $details  Lines naming what was found, at most ten shown
 */
function failMerge(string $message, array $details = []): never
{
    fwrite(STDERR, 'Shard merge: '.$message.PHP_EOL);

    // Name a sample of what differs, and how many more there are
    foreach (array_slice($details, 0, 10) as $detail) {
        fwrite(STDERR, '  '.$detail.PHP_EOL);
    }

    if (count($details) > 10) {
        fwrite(STDERR, sprintf('  ... and %d more'.PHP_EOL, count($details) - 10));
    }

    exit(1);
}

// Read the options from argv rather than getopt, which reports an unknown option as absent
$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--(dir|total|report|timings)=(.*)$/', $argument, $match) !== 1) {
        fwrite(STDERR, "Shard merge: unknown argument {$argument}".PHP_EOL);
        exit(2);
    }

    $options[$match[1]] = $match[2];
}

$dir = rtrim($options['dir'] ?? '', '/');
$total = ctype_digit($options['total'] ?? '') ? (int) $options['total'] : 0;
$reportPath = $options['report'] ?? '';

if ($dir === '' || $total < 1 || $reportPath === '') {
    fwrite(STDERR, 'Usage: php scripts/merge-shard-reports.php --dir=<dir> --total=<N> --report=<path> [--timings=<path>]'.PHP_EOL);
    exit(2);
}

$missing = [];
$reports = [];
$listings = [];
$timingNotes = [];

// Collect every shard's three files, noting each one that is absent or unreadable
for ($index = 1; $index <= $total; $index++) {
    $report = readShardReport("{$dir}/junit-parallel-{$index}.xml");
    $listing = is_file("{$dir}/pest-list-{$index}.txt") ? file_get_contents("{$dir}/pest-list-{$index}.txt") : false;
    $note = is_file("{$dir}/shard-timings-{$index}.txt") ? file_get_contents("{$dir}/shard-timings-{$index}.txt") : false;

    if ($report === null) {
        $missing[] = "shard {$index}/{$total}: no readable junit-parallel-{$index}.xml";
    } else {
        $reports[$index] = $report;
    }

    if ($listing === false || listedCasesPerClass($listing) === []) {
        $missing[] = "shard {$index}/{$total}: no test listing in pest-list-{$index}.txt";
    } else {
        $listings[$index] = listedCasesPerClass($listing);
    }

    if ($note === false || trim($note) === '') {
        $missing[] = "shard {$index}/{$total}: no timings note in shard-timings-{$index}.txt";
    } else {
        $timingNotes[$index] = trim($note);
    }
}

if ($missing !== []) {
    failMerge("the inputs for {$total} shards are incomplete, so the band cannot be shown to have run whole.", $missing);
}

// Require one timings file across the shards, since each partitions from its own copy
if (count(array_unique($timingNotes)) !== 1) {
    failMerge(
        'the shards read different timings, so they may have partitioned the band differently. Re-run the workflow.',
        array_map(fn (int $index, string $note): string => "shard {$index}: {$note}", array_keys($timingNotes), $timingNotes),
    );
}

// Require one test listing across the shards, since each partitions its own discovery
$firstListing = reset($listings);
foreach ($listings as $index => $listing) {
    if ($listing !== $firstListing) {
        failMerge(
            "shard {$index} listed different tests from shard 1.",
            classCountDifferences($firstListing, $listing, 'shard 1 listed', "shard {$index} listed"),
        );
    }
}

$seen = [];
$duplicates = [];
$ranPerClass = [];
$timedCases = [];

// Gather every case, noting any that more than one shard ran
foreach ($reports as $index => $report) {
    foreach ($report['cases'] as $case) {
        $key = $case['class'].' :: '.$case['name'];

        if (isset($seen[$key])) {
            $duplicates[] = "{$key} (shards {$seen[$key]} and {$index})";
        }

        $seen[$key] ??= $index;
        $ranPerClass[$case['class']] = ($ranPerClass[$case['class']] ?? 0) + 1;
    }
}

if ($duplicates !== []) {
    failMerge('a test case ran in more than one shard.', $duplicates);
}

// Compare what the shards ran with what the band holds, class by class
$differences = classCountDifferences($firstListing, $ranPerClass);
if ($differences !== []) {
    failMerge('the shards did not run the band exactly once.', $differences);
}

// Build the band report, one root holding every shard's suites
$merged = new DOMDocument('1.0', 'UTF-8');
$merged->formatOutput = false;
$root = $merged->appendChild($merged->createElement('testsuites'));

foreach ($reports as $report) {
    $shardRoot = $report['document']->documentElement;
    if ($shardRoot === null) {
        continue;
    }

    // Take the shard's suites, or the shard's root itself when it is a lone suite
    $suites = $shardRoot->nodeName === 'testsuites' ? iterator_to_array($shardRoot->childNodes) : [$shardRoot];
    foreach ($suites as $suite) {
        if ($suite instanceof DOMElement) {
            $root->appendChild($merged->importNode($suite, true));
        }
    }
}

// Read every case's time back out of the merged report, for the timings
foreach ($merged->getElementsByTagName('testcase') as $case) {
    $timedCases[] = [
        'class' => $case->getAttribute('class'),
        'time' => is_numeric($case->getAttribute('time')) ? (float) $case->getAttribute('time') : 0.0,
    ];
}

if ($merged->save($reportPath) === false) {
    failMerge("could not write the band report to {$reportPath}.");
}

// Write the timings the next run balances its shards on, when asked to
if (isset($options['timings']) && $options['timings'] !== '') {
    $timingsDir = dirname($options['timings']);
    if (! is_dir($timingsDir) && ! mkdir($timingsDir, 0755, true) && ! is_dir($timingsDir)) {
        failMerge("could not create {$timingsDir} for the timings.");
    }

    $json = json_encode(shardTimings($timedCases), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    if ($json === false || file_put_contents($options['timings'], $json.PHP_EOL) === false) {
        failMerge("could not write the timings to {$options['timings']}.");
    }
}

printf(
    'Shard merge: %d shards ran %d cases in %d classes, matching the listing exactly. Timings: %s.'.PHP_EOL,
    $total,
    count($seen),
    count($ranPerClass),
    reset($timingNotes),
);

exit(0);
