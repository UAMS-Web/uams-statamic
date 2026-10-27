<?php

declare(strict_types=1);

/**
 * Band-lock release guard (UAMS-Web/uams-statamic-archive#3018): fail the `pest` job when the content mirror
 * band lock is still held after the suite has finished, and name the processes
 * holding it.
 *
 * Every test process takes a shared `flock()` on the band lock at boot and holds
 * it for its lifetime (`App\Support\ContentMirrorBandLock::hold()`, UAMS-Web/uams-statamic-archive#2764), and
 * `test:seed-stache` refuses while anything holds it. The design relies on the
 * operating system dropping the lock when the holding process exits. That holds
 * for the process that took the lock, not for a child it started: a `proc_open`
 * child inherits the open descriptor, and the lock is released only once every
 * process holding a duplicate has exited. A test that leaves such a child behind
 * keeps the lock alive past the whole suite, and the failure surfaces later as
 * unrelated `SeedTestStacheCommandTest` cases, or as a refused seed on the next
 * run, with nothing in the output naming a lock or a process.
 *
 * So this runs once, after the serial tail, when no band is live: an exclusive
 * non-blocking lock that is refused then means something outlived the suite.
 * It must never run between bands, where a live band legitimately holds the
 * shared lock and the probe would refuse on a healthy run.
 *
 * Holders are listed with `lsof`, which macOS and Linux ship. Where it is absent
 * (Git Bash on Windows) the guard still fails on a refusal, and says the holders
 * could not be listed rather than printing an empty list.
 *
 * Written in PHP rather than Node for the reason given in
 * `check-skip-baseline.php`: the `pest` job in ci.yml sets up PHP but not Node.
 *
 * USAGE
 *     php scripts/check-band-lock-released.php [--path=<lock file>]
 *
 *     The path defaults to the testing environment's band lock,
 *     `storage/framework/testing/content-mirror.band.lock`.
 *
 * EXIT
 *     0  the lock file is absent, or an exclusive lock on it was acquired
 *     1  the exclusive lock was refused, or the lock file could not be opened
 */

/**
 * List the processes other than this one that have a file open, as reported by
 * `lsof`.
 *
 * @param  string  $path  The lock file to look up
 * @return list<array{pid: int, command: string}>|null The holders, or null when
 *                                                     `lsof` could not be run.
 */
function bandLockHolders(string $path): ?array
{
    // Ask lsof for the process ids alone, one per line
    $output = [];
    $code = 0;
    exec('lsof -t -- '.escapeshellarg($path).' 2>/dev/null', $output, $code);

    // Treat a missing lsof as unknown rather than as no holders: lsof exits 1 with
    // no output both when nothing holds the file and when it is not installed
    if ($code !== 0 && $output === [] && trim((string) shell_exec('command -v lsof')) === '') {
        return null;
    }

    $holders = [];

    foreach ($output as $line) {
        // Skip this process, in case it still has the file open
        $pid = (int) trim($line);
        if ($pid <= 0 || $pid === getmypid()) {
            continue;
        }

        // Read the full command line so the responsible test can be identified
        $command = trim((string) shell_exec('ps -ww -o command= -p '.$pid.' 2>/dev/null'));

        $holders[] = ['pid' => $pid, 'command' => $command !== '' ? $command : '(exited before its command line was read)'];
    }

    return $holders;
}

// Resolve the lock path, defaulting to the testing environment's band lock
$options = getopt('', ['path:']);
$path = is_string($options['path'] ?? null)
    ? $options['path']
    : dirname(__DIR__).'/storage/framework/testing/content-mirror.band.lock';

// Pass when no test process ever created the lock file
if (! is_file($path)) {
    echo "Band lock: {$path} does not exist, so nothing holds it.\n";
    exit(0);
}

// Open the existing file without creating or truncating it
$handle = @fopen($path, 'r');
if ($handle === false) {
    fwrite(STDERR, "Band lock: could not open {$path}, so whether anything still holds it is unknown.\n");
    exit(1);
}

// Pass when nothing that outlived the suite still holds the lock
if (flock($handle, LOCK_EX | LOCK_NB)) {
    flock($handle, LOCK_UN);
    fclose($handle);
    echo "Band lock: released. No process holds {$path} after the suite.\n";
    exit(0);
}

// Close the probe's descriptor before listing holders, since the shell that runs
// lsof would otherwise inherit it and be reported as a holder itself
fclose($handle);

// Report the refusal and every process still holding the lock
fwrite(STDERR, "Band lock: still held after the suite finished: {$path}\n");
fwrite(STDERR, "A process the suite started outlived it, most often a child a test spawned, which inherits the\n");
fwrite(STDERR, "lock descriptor. Every later `test:seed-stache` in this checkout is refused until it exits (UAMS-Web/uams-statamic-archive#3018).\n");

$holders = bandLockHolders($path);

if ($holders === null) {
    fwrite(STDERR, "The holders could not be listed: lsof is not available here.\n");
} elseif ($holders === []) {
    fwrite(STDERR, "lsof listed no holder; it may have exited after the probe. Re-run this check to confirm.\n");
} else {
    fwrite(STDERR, 'Holding process'.(count($holders) > 1 ? 'es' : '').":\n");
    foreach ($holders as $holder) {
        fwrite(STDERR, "  pid {$holder['pid']}: {$holder['command']}\n");
    }
}

exit(1);
