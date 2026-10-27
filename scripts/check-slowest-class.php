<?php

declare(strict_types=1);

/**
 * Slowest-class cost guard (UAMS-Web/uams-statamic-archive#1890) — fail a Pest band whose costliest test class
 * takes too large a SHARE of the band's summed class time (UAMS-Web/uams-statamic-archive#2530), or exceeds a
 * generous absolute backstop.
 *
 * The gate reports PASS on any duration, so nothing in the repo pins what the
 * parallel band costs and a change that reclaims it passes in silence. That has
 * happened twice, and neither time did anything go red:
 *
 * - UAMS-Web/uams-statamic-archive#1348 added `mitydigital/sitemapamic`, which registers one `sitemap.xml` route
 *   per site — 255 on this multisite. Laravel's uncached URL generation scales with
 *   route count, so the render-heavy suite paid a ~2x tax on every test. Finding it
 *   took a wall-time bisect across a fixed 226-file set at successive commits
 *   (UAMS-Web/uams-statamic-archive#1521 → UAMS-Web/uams-statamic-archive#1573).
 * - One class grew to 1085.3s — 83% of a 1306s band. SchemaJsonLdLocationTest bound
 *   the band while eleven of twelve workers idled, and it surfaced only because
 *   UAMS-Web/uams-statamic-archive#1718's sharding spike happened to run a per-class profile (UAMS-Web/uams-statamic-archive#1797).
 *
 * WHAT THE NUMBER IS. A JUnit `time` attribute is the WALL-CLOCK time each test
 * took, not the CPU time it used. So a class's summed `time` moves with machine load
 * exactly as the band's wall time does: on an oversubscribed host every class
 * inflates together. UAMS-Web/uams-statamic-archive#2525 red the `pest` job this way on a branch that changed
 * nothing relevant — identical test and assertion counts, the costliest class up
 * from 263.7s to 784.9s, while the host carried eleven competing `pest` processes at
 * a 15-minute load average of 28.9 on 12 cores. An absolute ceiling on that number
 * is the flaky wall-time assert UAMS-Web/uams-statamic-archive#1537 ruled out, one level down.
 *
 * WHY A SHARE. The costliest class's share of the band's summed class time is
 * dimensionless, so inflation that hits every class alike cancels out of it. It is
 * also the quantity that decides whether a class binds the parallel band: paratest
 * distributes by class, so no scheduler finishes sooner than its largest single
 * unit, and a class starts costing wall-clock once its share passes 1/workers.
 *
 * WHY AN ABSOLUTE BACKSTOP AS WELL. A share cannot see growth that hits every class
 * at once — a per-test boot cost, like UAMS-Web/uams-statamic-archive#1348's route-count tax, raises every class
 * together and leaves every share where it was. The backstop catches that, and it is
 * set far enough above contended readings that load alone does not reach it.
 *
 * The signal costs nothing extra: the `phpunit` job already runs both bands under
 * `--log-junit`, and a JUnit report carries a `time` attribute per `<testcase>`.
 *
 * Written in PHP rather than Node deliberately: the `phpunit` job in ci.yml sets up
 * PHP but **not** Node, so a Node check would either fail there or force a new
 * toolchain pin into the job. This mirrors {@see check-skip-baseline.php}, whose
 * shape it follows throughout.
 *
 * USAGE
 *     php scripts/check-slowest-class.php --report=<junit.xml> [--band=<name>]
 *                                         [--share-ceiling=<percent>]
 *                                         [--ceiling=<seconds>]
 *
 * EXIT
 *     0  the costliest class is within both its share ceiling and the backstop
 *     1  it is over either one, or the report is missing, unparseable, or carries no
 *        durations — a band that produced no usable report has not been verified,
 *        and passing it would reinstate the silence this guard exists to remove.
 */

/**
 * The committed share ceilings, in percent of a band's summed class time, per band.
 *
 * MEASURED 2026-09-25 (US Central) on macOS (10 cores), at local-ci parity (route
 * cache on, a fresh `test:seed-stache` per band, the pest job's own flags), four
 * rounds, the third deliberately left running beside a second checkout's full
 * parallel band. "load" is the 1-minute load average as the band started, so a
 * serial row still carries the parallel band that ran just before it:
 *
 *     parallel      load       summed     costliest   share
 *     round 1        17        2834.1s     144.9s     5.11%
 *     round 2        23        2974.5s     149.4s     5.02%
 *     round 3       169        6042.1s     321.1s     5.31%   <- contended
 *     round 4        50        3071.7s     156.4s     5.09%
 *
 *     serial        load       summed     costliest   share
 *     round 1        77         416.9s      82.3s    19.74%
 *     round 2        77         477.6s      84.1s    17.61%
 *     round 3       203         508.1s     123.5s    24.31%   <- contended
 *     round 4       117         433.5s      85.5s    19.72%
 *
 * Every row carries 7683 parallel / 472 serial cases, so the workload was identical.
 * Each parallel band carried the same five failures, all environmental to that
 * machine and together about 9s of the band, so they do not move a share.
 * From the 12-core host that runs the gate: UAMS-Web/uams-statamic-archive#1890's worst class, 628.5s, against
 * that measurement's 10797s of summed work (5.82%), and 784.9s of 13299s (5.90%) in
 * the contended UAMS-Web/uams-statamic-archive#2525 band. No quiet gate-host report with its summed time
 * survives, so the gate host's share rests on those two points.
 *
 * PARALLEL — 7.5%. Uniform contention that doubled every raw number moved the share
 * by under 0.3 points, and across two machines the share stays between 5.0% and
 * 5.9%. The costliest class did inflate a little faster than the mean (x2.22 against
 * x2.13 in round 3), which is why the ceiling is not pressed against the readings.
 * 7.5% sits 27% above the worst observed and below 8.33%, the 1/12 point at which a
 * class starts binding the gate's twelve workers — so it fires before a class binds.
 * UAMS-Web/uams-statamic-archive#1797's 1085.3s class, against the 10797s of summed work UAMS-Web/uams-statamic-archive#1890 later measured,
 * would have read about 10% and failed it.
 *
 * SERIAL — 40%. The serial band runs one class at a time, so a load spike lands on
 * whichever class is running rather than spreading across all of them: against
 * round 1, round 3's costliest class absorbed +41.2s of the band's +91.2s and its
 * share went from 19.7% to 24.3%. The share is still bounded, but its spread is wide, so the ceiling
 * sits 65% above the worst observed. The serial band has no worker count to bind, so
 * this guards only against one class coming to dominate the serial tail.
 *
 * KNOWN LIMIT. A share cancels inflation spread across the band, not a load spike
 * confined to the window in which the costliest class runs. In the serial band that
 * window holds one class, so a spike slowing it about 2.7x takes round 1 past 40%.
 * In the parallel band the same window also slows the classes running beside it on
 * the other workers, so the share tends toward 1/workers: from 5.11% it takes about
 * 2.9x on ten workers and 5.7x on twelve to pass 7.5%. UAMS-Web/uams-statamic-archive#2525 inflated its costliest
 * class 2.98x, but alongside every other class, which is the case the share absorbs.
 *
 * A band name without its own entry takes the parallel ceiling, the stricter one.
 *
 * Raising a ceiling is a deliberate, reviewable act. A class over it is closing on
 * the point where it bounds the band while the other workers idle; split it (as
 * UAMS-Web/uams-statamic-archive#1797 split SchemaJsonLdLocationTest) rather than moving the number.
 *
 * @var array<string, float>
 */
const SLOWEST_CLASS_SHARE_CEILING_PERCENT = [
    'parallel' => 7.5,
    'serial' => 40.0,
];

/**
 * The absolute backstop, in seconds, for any single test class.
 *
 * This was the guard's only ceiling (700s, UAMS-Web/uams-statamic-archive#1890) until UAMS-Web/uams-statamic-archive#2530 showed contention
 * alone carries a class past it. It is now a backstop for growth a share cannot
 * see, set against the readings on the 12-core host that runs the gate: eight
 * quiet-host chains on 2026-09-25 read 249.7s to 412.2s, and the contended UAMS-Web/uams-statamic-archive#2525
 * band read 784.9s. 1500s is 3.6x the worst quiet reading and 1.9x the worst
 * contended one, so only a class that has grown several-fold on its own — or with
 * the whole suite — reaches it.
 */
const SLOWEST_CLASS_BACKSTOP_SECONDS = 1500.0;

/**
 * Sum each test class's `time` across every `<testcase>` in a JUnit report.
 *
 * Parsed with DOM rather than by regex because a test name is free text that reaches
 * the report as an attribute value, and libxml does not escape `>` there. An
 * attribute-scanning pattern ends its match on that `>` and re-enters the document
 * mid-element, shifting every case boundary after it — the failure
 * {@see check-skip-baseline.php} records having verified against a real report.
 *
 * Aggregated from `<testcase>` rather than read off `<testsuite time="…">` because
 * paratest MERGES per-process reports: one class can appear as several `<testsuite>`
 * elements, and taking any single one under-reports the class. Summing the leaves is
 * correct under either shape.
 *
 * @param  string  $xml  The JUnit report contents
 * @return array<string, float> Total seconds per class, highest first
 *
 * @throws RuntimeException When the report is not parseable XML, or carries no timings
 */
function slowestClassTotals(string $xml): array
{
    if (trim($xml) === '') {
        throw new RuntimeException('The JUnit report is empty — the band cannot be verified.');
    }

    $document = new DOMDocument;

    // A truncated report (a band killed mid-write) must fail, never read as zero.
    if (! @$document->loadXML($xml)) {
        throw new RuntimeException('The JUnit report is not parseable XML — the band cannot be verified.');
    }

    $totals = [];

    /** @var DOMNodeList<DOMElement> $cases */
    $cases = (new DOMXPath($document))->query('//testcase');

    foreach ($cases as $case) {
        // paratest's merger writes `class`; PHPUnit's own logger writes `classname`.
        // A case carrying neither is still counted, under a name that says so —
        // dropping it would be the silent under-report this guard exists to remove.
        $class = $case->getAttribute('class') !== ''
            ? $case->getAttribute('class')
            : ($case->getAttribute('classname') !== '' ? $case->getAttribute('classname') : '(unattributed)');

        $totals[$class] = ($totals[$class] ?? 0.0) + (float) $case->getAttribute('time');
    }

    if ($totals === []) {
        throw new RuntimeException('The JUnit report holds no <testcase> elements — the band cannot be verified.');
    }

    // Every case timed at zero means the report carries no usable durations, so a
    // PASS here would assert nothing at all. That is a setup failure, not a pass —
    // the same reasoning the mutation smoke applies to `0 Mutations`.
    if (array_sum($totals) <= 0.0) {
        throw new RuntimeException('The JUnit report carries no test durations — the band cannot be verified.');
    }

    arsort($totals);

    return $totals;
}

/**
 * Judge the costliest class against its share ceiling and the absolute backstop.
 *
 * @param  array<string, float>  $totals  Total seconds per class, highest first
 * @param  float  $shareCeiling  The share ceiling, in percent of summed class time
 * @param  float  $backstop  The absolute per-class backstop in seconds
 * @return array{ok: bool, class: string, seconds: float, summed: float, share: float, shareCeiling: float, shareOk: bool, backstop: float, backstopOk: bool}
 */
function evaluateSlowestClass(array $totals, float $shareCeiling, float $backstop): array
{
    $class = (string) array_key_first($totals);
    $seconds = $totals[$class];
    $summed = array_sum($totals);
    $share = 100.0 * $seconds / $summed;

    $shareOk = $share <= $shareCeiling;
    $backstopOk = $seconds <= $backstop;

    return [
        'ok' => $shareOk && $backstopOk,
        'class' => $class,
        'seconds' => $seconds,
        'summed' => $summed,
        'share' => $share,
        'shareCeiling' => $shareCeiling,
        'shareOk' => $shareOk,
        'backstop' => $backstop,
        'backstopOk' => $backstopOk,
    ];
}

$options = getopt('', ['report:', 'band::', 'share-ceiling::', 'ceiling::']);
$report = $options['report'] ?? null;
$band = is_string($options['band'] ?? null) ? $options['band'] : 'suite';

if (! is_string($report)) {
    fwrite(STDERR, "Usage: php scripts/check-slowest-class.php --report=<junit.xml> [--band=<name>] [--share-ceiling=<percent>] [--ceiling=<seconds>]\n");
    exit(1);
}

// An override that does not parse must fail rather than fall back to the committed
// number, since judging the band against a ceiling nobody asked for is a verdict on
// the wrong question. Read from argv rather than from getopt, which reports
// `--share-ceiling 5` (a space, not `=`) as a flag with no value and drops an empty
// `--ceiling=` altogether.
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--(share-ceiling|ceiling)(?:=(.*))?$/', (string) $argument, $flag) === 1) {
        $value = $flag[2] ?? '';

        if (! is_numeric($value) || (float) $value <= 0.0) {
            fwrite(STDERR, "Slowest class: --{$flag[1]} needs a positive number, written as --{$flag[1]}=<value>.\n");
            exit(1);
        }
    }
}

$shareCeiling = is_string($options['share-ceiling'] ?? null)
    ? (float) $options['share-ceiling']
    : (SLOWEST_CLASS_SHARE_CEILING_PERCENT[$band] ?? SLOWEST_CLASS_SHARE_CEILING_PERCENT['parallel']);

$backstop = is_string($options['ceiling'] ?? null)
    ? (float) $options['ceiling']
    : SLOWEST_CLASS_BACKSTOP_SECONDS;

if (! is_file($report)) {
    fwrite(STDERR, "Slowest class: no JUnit report at {$report} — the {$band} band cannot be verified.\n");
    exit(1);
}

// A misconfigured guard must read as a failure, not as a stack trace — every throw
// above names a band this run could not verify.
try {
    $totals = slowestClassTotals((string) file_get_contents($report));
} catch (RuntimeException $failure) {
    fwrite(STDERR, "Slowest class: {$failure->getMessage()}\n");
    exit(1);
}

$result = evaluateSlowestClass($totals, $shareCeiling, $backstop);

if ($result['ok']) {
    printf(
        "Slowest class OK: %s band's costliest class is %s at %.1fs, %.2f%% of %.1fs summed class time (share ceiling %.2f%%, backstop %.1fs).\n",
        $band,
        $result['class'],
        $result['seconds'],
        $result['share'],
        $result['summed'],
        $result['shareCeiling'],
        $result['backstop'],
    );
    exit(0);
}

if (! $result['shareOk']) {
    fwrite(STDERR, sprintf(
        "Slowest class EXCEEDED: %s band's %s is %.2f%% of %.1fs summed class time, share ceiling %.2f%% (over by %.2f points).\n",
        $band,
        $result['class'],
        $result['share'],
        $result['summed'],
        $result['shareCeiling'],
        $result['share'] - $result['shareCeiling'],
    ));
}

if (! $result['backstopOk']) {
    fwrite(STDERR, sprintf(
        "Slowest class EXCEEDED: %s band's %s cost %.1fs, backstop %.1fs (over by %.1fs).\n",
        $band,
        $result['class'],
        $result['seconds'],
        $result['backstop'],
        $result['seconds'] - $result['backstop'],
    ));
}

fwrite(STDERR, "Costliest classes in this band:\n");

foreach (array_slice($totals, 0, 5, true) as $class => $seconds) {
    fwrite(STDERR, sprintf("  %8.1fs  %6.2f%%  %s\n", $seconds, 100.0 * $seconds / $result['summed'], $class));
}

fwrite(
    STDERR,
    "\nparatest distributes by CLASS, so the band cannot finish sooner than its largest "
    .'one — a class this size bounds the whole run while the other workers idle (UAMS-Web/uams-statamic-archive#1797 '
    .'found one at 1085.3s of a 1306s band). Split the class rather than raising a '
    .'ceiling in scripts/check-slowest-class.php. A share is load-invariant, so a share '
    .'failure is a property of the change, not of a busy host (UAMS-Web/uams-statamic-archive#2530). '
    .'`vendor/bin/pest --update-shards` writes a per-class profile to diagnose it, but '
    .'note it runs the suite functionally at roughly 4x the CPU, so it is the follow-up '
    ."diagnostic and never a band time.\n"
);

exit(1);
