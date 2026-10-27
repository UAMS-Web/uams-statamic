#!/usr/bin/env node
/**
 * local-ci-progress.mjs — report which stage a local-CI run is in, and how far into it.
 *
 * `scripts/local-ci.mjs` streams its output but publishes no machine-readable progress, and its
 * `phpunit` job routinely runs 20 minutes. Redirect a run to a log file and point this at it to
 * get a periodic one-line report — the agent arms it under the harness `Monitor` tool so each
 * line arrives as a notification.
 *
 *   node scripts/local-ci.mjs --branch <b> > ci.log 2>&1 &
 *   node scripts/local-ci-progress.mjs --log ci.log --worktree ../uams-statamic-ci
 *
 * The `phpunit` job runs six sub-phases whose progress signals differ, so it is reported per
 * sub-phase rather than as one misleading bar:
 *
 *   - parallel suite — paratest streams dots with no total, so only an emitted-test count is real.
 *   - serial tail    — plain Pest prints one `PASS <TestClass>` line per file, so N/total is real.
 *   - the two skip-baseline checks (UAMS-Web/uams-statamic-archive#1744) are near-instant and need no progress of their own.
 *
 * Counting `PASS` lines against the *whole* suite is the trap this exists to avoid: the parallel
 * phase prints none, so a run whose parallel phase had already finished reads as barely started.
 *
 * Usage:
 *   node scripts/local-ci-progress.mjs --log ci.log
 *   node scripts/local-ci-progress.mjs --log-dir /tmp/logs --interval 300
 *   node scripts/local-ci-progress.mjs --log ci.log --once
 */

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, basename, extname } from 'node:path';
import { pathToFileURL } from 'node:url';

/** Order the runner executes jobs in — shortest to longest (the cost gate). */
const JOB_ORDER = ['isolation', 'encoding', 'spell', 'js', 'build', 'pint', 'larastan', 'phpunit'];

const ANSI = /\x1b\[[0-9;]*[a-zA-Z]/g;

const HELP = `local-ci-progress.mjs — periodic stage/progress report for a local-CI run.

  --log <file>          Log file to report on.
  --log-dir <dir>       Directory to watch; reports on the newest local-ci-*.log in it.
  --worktree <path>     CI worktree, used to count serial-group test files (default ../uams-statamic-ci).
  --interval <seconds>  Seconds between reports (default 600, floor 10).
  --idle-interval <s>   While no run is in flight, repeat at most this often (default 3600).
  --stall-after <s>     Treat a run with no log write for this long as ended (default 900).
  --once                Print one report and exit.
  -h, --help            Show this help.

Exactly one of --log / --log-dir is required.
`;

/**
 * Parse CLI arguments.
 *
 * @param  string[]  $argv
 * @return array{log: ?string, logDir: ?string, worktree: string, interval: number, idleInterval: number, stallAfter: number, once: bool}
 */
function parseArgs(argv) {
    const options = {
        log: null,
        logDir: null,
        worktree: '../uams-statamic-ci',
        interval: 600,
        idleInterval: 3600,
        // A phpunit band writes progress far more often than this; 15 minutes of
        // total silence means the run is gone, not slow.
        stallAfter: 900,
        once: false,
    };

    for (let index = 0; index < argv.length; index += 1) {
        const [flag, inlineValue] = argv[index].split(/=(.*)/s);
        const nextValue = () => inlineValue ?? argv[++index];

        switch (flag) {
            case '--log': options.log = nextValue(); break;
            case '--log-dir': options.logDir = nextValue(); break;
            case '--worktree': options.worktree = nextValue(); break;
            case '--interval': options.interval = Math.max(10, Number(nextValue())); break;
            case '--idle-interval': options.idleInterval = Math.max(0, Number(nextValue())); break;
            case '--stall-after': options.stallAfter = Math.max(0, Number(nextValue())); break;
            case '--once': options.once = true; break;
            case '--help':
            case '-h':
                process.stdout.write(HELP);
                process.exit(0);
                break;
            default:
                throw new Error(`Unknown flag: ${flag}`);
        }
    }

    if (Boolean(options.log) === Boolean(options.logDir)) {
        throw new Error('Pass exactly one of --log or --log-dir.');
    }

    return options;
}

/**
 * Newest `local-ci-*.log` in a directory, or null when there is none yet.
 *
 * @param  string  $directory
 * @return ?string
 */
function newestLog(directory) {
    if (! existsSync(directory)) {
        return null;
    }

    const candidates = readdirSync(directory)
        .filter((name) => name.startsWith('local-ci-') && extname(name) === '.log')
        .map((name) => join(directory, name))
        .map((path) => ({ path, mtime: statSync(path).mtimeMs }))
        .sort((a, b) => b.mtime - a.mtime);

    return candidates.length > 0 ? candidates[0].path : null;
}

/**
 * Recursively collect `*.php` files under a directory.
 *
 * @param  string    $directory
 * @param  string[]  $collected
 * @return string[]
 */
function phpFiles(directory, collected = []) {
    if (! existsSync(directory)) {
        return collected;
    }

    for (const entry of readdirSync(directory, { withFileTypes: true })) {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            phpFiles(path, collected);
        } else if (entry.isFile() && entry.name.endsWith('.php')) {
            collected.push(path);
        }
    }

    return collected;
}

/**
 * Count test classes carrying the `serial` group — the serial tail's denominator.
 *
 * Computed from the worktree rather than hardcoded, so it tracks the suite as it grows.
 *
 * @param  string  $worktree
 * @param  string  $runKey    Identifies the run, so a new run recomputes the total
 * @return number
 */
// UAMS-Web/uams-statamic-archive#1745 — keyed by worktree AND run, not a bare `let`. A persistent monitor
// outlives many runs, and a process-lifetime cache pinned the FIRST run's total
// forever: it reported 78 for a whole session while post-#1716 branches have 77.
// The total is still stable *within* a run, so this keeps the per-tick saving
// while a new run (or a different worktree) recomputes.
const serialTotalCache = new Map();

function serialGroupTotal(worktree, runKey = '') {
    const cacheKey = `${worktree}::${runKey}`;

    if (serialTotalCache.has(cacheKey)) {
        return serialTotalCache.get(cacheKey);
    }

    const marker = /group\('serial'\)|#\[Group\('serial'\)\]/;
    let count = 0;

    for (const directory of ['tests/Unit', 'tests/Feature']) {
        for (const file of phpFiles(join(worktree, directory))) {
            try {
                if (marker.test(readFileSync(file, 'utf8'))) {
                    count += 1;
                }
            } catch {
                // An unreadable file simply does not count toward the denominator.
            }
        }
    }

    // Bound the map so a session-length monitor cannot grow it without limit.
    if (serialTotalCache.size > 32) {
        serialTotalCache.clear();
    }

    serialTotalCache.set(cacheKey, count);

    return count;
}

// UAMS-Web/uams-statamic-archive#1745 — resolve the worktree from the run being reported.
//
// The monitor was invoked with `--worktree …/uams-ci-pipeline` while the runner
// worked out of `…/uams-ci`, so the serial denominator came from a different
// checkout entirely. That is a SECOND, independent cause of the same wrong total,
// which is why fixing only the cache left it wrong. The runner prints
// `Worktree:  <path>` in its header, so the log itself is authoritative; the
// --worktree flag is only a fallback for a log that predates the header.
export function worktreeFromLog(text, fallback) {
    const match = text.match(/^Worktree:\s+(.+?)\s*$/m);

    return match ? match[1] : fallback;
}

// UAMS-Web/uams-statamic-archive#1745 — a killed run must not be reported as still in flight.
//
// After a kill the log simply stops; the previous behavior replayed the last
// sub-phase forever, so a dead run and a live one were indistinguishable — and a
// stale mtime bump made it worse. A run with no write for longer than the
// threshold has ended, whatever its last line said.
export function runHasStalled(idleSeconds, stallAfterSeconds) {
    return stallAfterSeconds > 0 && idleSeconds >= stallAfterSeconds;
}

/**
 * Format a duration in seconds as `12m34s`.
 *
 * @param  number  $seconds
 * @return string
 */
function human(seconds) {
    const whole = Math.max(0, Math.round(seconds));

    return `${Math.floor(whole / 60)}m${String(whole % 60).padStart(2, '0')}s`;
}

/**
 * Build the report line for a log, or null when nothing should be emitted this tick.
 *
 * @param  string  $logPath
 * @param  array   $options
 * @param  array   $state
 * @return ?string
 */
function buildReport(logPath, options, state) {
    const stats = statSync(logPath);
    const text = readFileSync(logPath, 'utf8').replace(ANSI, '');
    const name = basename(logPath, '.log');
    const now = Date.now();
    const born = stats.birthtimeMs || stats.mtimeMs;

    if (text.includes('CI summary')) {
        // Duration is birth -> last write. Measuring to *now* would inflate on every tick
        // once the run has finished, reporting an ever-growing figure for a finished run.
        const duration = (stats.mtimeMs - born) / 1000;
        const idle = (now - stats.mtimeMs) / 1000;

        const summary = text
            .split('\n')
            .slice(text.split('\n').findIndex((line) => line.includes('CI summary')) + 1)
            .slice(0, JOB_ORDER.length + 2)
            .map((line) => line.trim())
            .filter((line) => /^[a-z]+\s+(PASS|FAIL|SKIP)/.test(line))
            .map((line) => {
                const [job, result] = line.split(/\s+/);

                return `${job}=${result}`;
            })
            .join(' ');

        const failed = /\b(FAIL)\b/.test(summary);
        const exitMatch = text.match(/^EXIT=(\d+)/m);
        const verdict = exitMatch ? `exit ${exitMatch[1]}` : (failed ? 'FAILED' : 'passed');

        const line = `HEARTBEAT  ${name}  COMPLETE (${verdict})  ${summary}  ran ${human(duration)}, finished ${human(idle)} ago — idle, no run in flight`;

        // While a run is in flight every tick is signal. Once it finishes the line stops
        // changing, so announce the transition, then throttle to --idle-interval.
        state.lastStalled = null;

        if (state.lastComplete !== name) {
            state.lastComplete = name;
            state.idleSince = now;

            return line;
        }

        if (options.idleInterval > 0 && (now - state.idleSince) / 1000 >= options.idleInterval) {
            state.idleSince = now;

            return line;
        }

        return null;
    }

    // A run is in flight; the next COMPLETE is a fresh transition worth announcing.
    state.lastComplete = null;

    const elapsed = (now - born) / 1000;
    const idleSeconds = (now - stats.mtimeMs) / 1000;

    // UAMS-Web/uams-statamic-archive#1745 — a killed run leaves the log frozen mid-phase. Report it as ended
    // rather than replaying its last sub-phase as though still running.
    if (runHasStalled(idleSeconds, options.stallAfter)) {
        const line = `HEARTBEAT  ${name}  ENDED WITHOUT COMPLETING (no log write for ${human(idleSeconds)}; killed or crashed)  ran ${human(elapsed - idleSeconds)}`;

        if (state.lastStalled !== name) {
            state.lastStalled = name;
            state.idleSince = now;

            return line;
        }

        if (options.idleInterval > 0 && (now - state.idleSince) / 1000 >= options.idleInterval) {
            state.idleSince = now;

            return line;
        }

        return null;
    }

    state.lastStalled = null;
    const jobsHeader = text.match(/^Jobs:\s*(.+)$/m);
    const configured = jobsHeader ? jobsHeader[1].split(',').map((job) => job.trim()).filter(Boolean) : [];

    const jobLines = text.split('\n').filter((line) => line.includes('==> JOB '));

    if (jobLines.length === 0) {
        return `HEARTBEAT  ${name}  setup (worktree / composer install / npm ci), no job started  elapsed ${human(elapsed)}`;
    }

    const lastJobLine = jobLines[jobLines.length - 1];
    const stage = lastJobLine.match(/==> JOB ([a-z]+)/)?.[1] ?? '?';
    const position = configured.indexOf(stage) + 1;
    const where = `stage=${stage} (${position > 0 ? position : '?'} of ${configured.length || '?'})`;

    if (stage !== 'phpunit') {
        return `HEARTBEAT  ${name}  ${where}  no per-item progress from this stage  elapsed ${human(elapsed)}`;
    }

    const subPhase = lastJobLine.split('==> JOB phpunit - ')[1]?.trim() ?? '';
    const after = text.slice(text.lastIndexOf(lastJobLine) + lastJobLine.length);

    if (subPhase.includes('serial tail')) {
        const done = (after.match(/^\s*(PASS|FAIL|WARN|SKIP)\s+Tests\\/gm) ?? []).length;
        const total = serialGroupTotal(worktreeFromLog(text, options.worktree), name);
        const progress = total > 0
            ? `${done}/${total} classes (${Math.floor((done * 100) / total)}%)`
            : `${done} classes done`;

        return `HEARTBEAT  ${name}  ${where} sub-phase='serial tail' (4 of 6 — parallel suite already done)  ${progress}  elapsed ${human(elapsed)}`;
    }

    if (subPhase.includes('parallel suite')) {
        const dots = (after.match(/\./g) ?? []).length;

        return `HEARTBEAT  ${name}  ${where} sub-phase='parallel suite' (3 of 6)  ~${dots} tests emitted (paratest streams no total)  elapsed ${human(elapsed)}`;
    }

    return `HEARTBEAT  ${name}  ${where} sub-phase='${subPhase}'  elapsed ${human(elapsed)}`;
}

// Only run the CLI when invoked directly, so the helpers above can be imported by
// tests without the module parsing argv and exiting. Mirrors local-ci.mjs.
const isEntryPoint = process.argv[1] !== undefined
    && import.meta.url === pathToFileURL(process.argv[1]).href;

let options;

try {
    options = isEntryPoint ? parseArgs(process.argv.slice(2)) : null;
} catch (error) {
    process.stderr.write(`${error.message}\n\n${HELP}`);
    process.exit(2);
}

const state = { lastComplete: null, idleSince: Date.now() };

/**
 * Emit one report, tolerating a log that has not appeared yet.
 *
 * @return void
 */
function tick() {
    const logPath = options.log ?? newestLog(options.logDir);

    if (! logPath || ! existsSync(logPath)) {
        console.log(`HEARTBEAT  no local-ci log found yet (${options.log ?? options.logDir})`);

        return;
    }

    try {
        const line = buildReport(logPath, options, state);

        if (line !== null) {
            console.log(line);
        }
    } catch (error) {
        console.log(`HEARTBEAT  report failed — ${(error.message || '').slice(0, 200)}`);
    }
}

if (isEntryPoint) {
    tick();

    if (! options.once) {
        setInterval(tick, options.interval * 1000);
    }
}
