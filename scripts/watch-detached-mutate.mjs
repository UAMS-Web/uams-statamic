#!/usr/bin/env node
// `pids` is part of the `status-stalled-pids-dead` reason token the status file records.
// cspell:ignore pids
/**
 * watch-detached-mutate.mjs: wake when a detached mutate finishes or stalls.
 *
 * Cross-platform. Prints AGENT_LOOP_WAKE_mutate_<stem> JSON lines for Shell
 * `notify_on_output`. When status+log go silent and recorded PIDs are dead,
 * rewrites `exit=… reason=status-stalled-pids-dead`, never "keep waiting."
 *
 * Usage:
 *   node scripts/watch-detached-mutate.mjs --stem=hidden
 *
 * Options:
 *   --stem=<name>     Required
 *   --root=<dir>      Repository root (default: cwd)
 *   --poll=<sec>      Poll interval (default: 45)
 *   --stall=<sec>     Status+log silence before stall logic (default: 180)
 *   --progress=<sec>  Emit a progress wake while still running (default: 900;
 *                     0 disables). Keeps the agent from idling through multi-
 *                     hour mutate walls.
 */

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

/**
 * @param {string[]} argv
 * @returns {Record<string, string>}
 */
function parseArgs(argv) {
    /** @type {Record<string, string>} */
    const out = {};
    for (const arg of argv) {
        if (!arg.startsWith('--')) {
            continue;
        }
        const eq = arg.indexOf('=');
        if (eq === -1) {
            out[arg.slice(2)] = 'true';
            continue;
        }
        out[arg.slice(2, eq)] = arg.slice(eq + 1);
    }
    return out;
}

/**
 * @param {number | null | undefined} pid
 * @returns {boolean}
 */
function pidAlive(pid) {
    if (pid === null || pid === undefined || !Number.isFinite(pid) || pid <= 0) {
        return false;
    }
    try {
        process.kill(pid, 0);
        return true;
    } catch {
        return false;
    }
}

/**
 * @param {string} text
 * @param {string} name
 * @returns {number | null}
 */
function statusPid(text, name) {
    const match = text.match(new RegExp(`${name}=(\\d+)`));
    return match ? Number(match[1]) : null;
}

/**
 * @param {string} file
 * @returns {number}
 */
function ageSeconds(file) {
    try {
        const st = fs.statSync(file);
        return Math.floor((Date.now() - st.mtimeMs) / 1000);
    } catch {
        return Number.POSITIVE_INFINITY;
    }
}

/**
 * @param {string} wake
 * @param {string} prompt
 * @param {string} token
 * @param {string[]} [extra]
 */
function emitWake(wake, prompt, token, extra = []) {
    console.log(`${wake} ${JSON.stringify({ prompt })}`);
    console.log(token);
    for (const line of extra) {
        if (line) {
            console.log(line);
        }
    }
}

/**
 * @param {number} ms
 * @returns {Promise<void>}
 */
function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

const args = parseArgs(process.argv.slice(2));
const stem = args.stem;
const root = path.resolve(args.root ?? process.cwd());
const pollSec = Number(args.poll ?? 45);
const stallSec = Number(args.stall ?? 180);
const progressSec = Number(args.progress ?? 900);

if (!stem) {
    console.error(
        'Usage: node scripts/watch-detached-mutate.mjs --stem=<name> [--root=<dir>] [--poll=45] [--stall=180] [--progress=900]',
    );
    process.exit(2);
}

const statusFile = path.join(root, 'storage', 'logs', `mutate-${stem}.status`);
const logFile = path.join(root, 'storage', 'logs', `mutate-${stem}.txt`);
const wake = `AGENT_LOOP_WAKE_mutate_${stem}`;
const stemUpper = stem.toUpperCase();
let lastProgressAt = Date.now();

/**
 * Summarize mutate progress from the log (dots/x after "Mutations for").
 *
 * @param {string} logText
 * @returns {string}
 */
function progressSummary(logText) {
    const match = logText.match(
        /(\d+)\s+Mutations for\s+(\d+)\s+Files created([\s\S]*)$/u,
    );
    if (!match) {
        return 'progress=unknown (suite or mutate setup still running)';
    }
    const total = Number(match[1]);
    const tail = match[3] ?? '';
    const markers = (tail.match(/[.xX]/gu) ?? []).join('');
    const done = markers.length;
    const failed = (markers.match(/[xX]/gu) ?? []).length;
    const pct = total > 0 ? ((100 * done) / total).toFixed(1) : '0.0';
    return `progress=${done}/${total} (${pct}%) x_markers=${failed} files=${match[2]}`;
}

while (true) {
    let raw = '';
    try {
        raw = fs.readFileSync(statusFile, 'utf8');
    } catch {
        await sleep(pollSec * 1000);
        continue;
    }

    if (/^exit=/m.test(raw)) {
        /** @type {string[]} */
        let scoreLines = [];
        try {
            const logText = fs.readFileSync(logFile, 'utf8');
            scoreLines = logText
                .split(/\r?\n/)
                .filter((line) => /Score:|Mutations:|UNTESTED|SURVIVED/.test(line));
        } catch {
            scoreLines = [];
        }
        emitWake(
            wake,
            `Mutate stem=${stem} finished. Read storage/logs/mutate-${stem}.status and mutate-${stem}.txt for Score/UNTESTED/SURVIVED; triage to mutation-clean; commit that class only; update coverage-scope.csv + publish tracker; kick the next eligible class mutate; arm node scripts/watch-detached-mutate.mjs (notify_on_output on AGENT_LOOP_WAKE_mutate_*) before ending the turn.`,
            `MUTATE_${stemUpper}_DONE`,
            [raw.trim(), ...scoreLines],
        );
        process.exit(0);
    }

    const statusAge = ageSeconds(statusFile);
    const logAge = fs.existsSync(logFile) ? ageSeconds(logFile) : stallSec;
    const signalsStalled = statusAge >= stallSec && logAge >= stallSec;

    let childId = statusPid(raw, 'child');
    if (childId === null) {
        childId = statusPid(raw, 'pid');
    }
    const runnerId = statusPid(raw, 'runner');
    const childAlive = pidAlive(childId);
    const runnerAlive = pidAlive(runnerId);

    if (signalsStalled && !childAlive && !runnerAlive) {
        const repair =
            `exit=1 finished=${new Date().toISOString()} reason=status-stalled-pids-dead ` +
            `child=${childId ?? ''} runner=${runnerId ?? ''} status_age_sec=${statusAge} log_age_sec=${logAge}`;
        const tmp = `${statusFile}.tmp`;
        fs.writeFileSync(tmp, `${repair}\n`, 'utf8');
        fs.renameSync(tmp, statusFile);

        emitWake(
            wake,
            `Mutate stem=${stem} status stalled with dead PIDs; watchdog rewrote exit=orphan. Inspect mutate-${stem}.txt, decide relaunch vs triage, fix scripts/run-detached-mutate.mjs if the runner failed to finalize status, then continue the pass-3 wave with a fresh runner+watch pair.`,
            `MUTATE_${stemUpper}_STALL_REPAIRED`,
            [repair],
        );
        process.exit(0);
    }

    if (signalsStalled && statusAge >= stallSec * 2 && (childAlive || runnerAlive)) {
        emitWake(
            wake,
            `Mutate stem=${stem} status and log stalled for status_age=${statusAge}s log_age=${logAge}s but child=${childId} alive=${childAlive} runner=${runnerId} alive=${runnerAlive}. Inspect the process and log; do not keep idling: fix the runner heartbeat or kill/relaunch the hung mutate.`,
            `MUTATE_${stemUpper}_STALL_ALIVE`,
        );
        process.exit(0);
    }

    if (
        progressSec > 0
        && (childAlive || runnerAlive)
        && Date.now() - lastProgressAt >= progressSec * 1000
    ) {
        let summary = 'progress=unknown';
        try {
            summary = progressSummary(fs.readFileSync(logFile, 'utf8'));
        } catch {
            // ignore
        }
        lastProgressAt = Date.now();
        emitWake(
            wake,
            `Mutate stem=${stem} still running (${summary}). Status heartbeats are alive, so do not treat silence as done. Re-arm this watcher notify if needed; only triage when exit= appears. Prep the next eligible class (do not start its mutate).`,
            `MUTATE_${stemUpper}_PROGRESS`,
            [raw.trim(), summary],
        );
        // Keep watching: progress wakes are not terminal.
    }

    await sleep(pollSec * 1000);
}
