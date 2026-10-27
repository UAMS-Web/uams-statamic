#!/usr/bin/env node
/**
 * run-detached-mutate.mjs: narrow Pest --mutate with a living status file.
 *
 * Cross-platform (macOS, Linux, Windows / Herd). Heartbeats
 * `running …` into `storage/logs/mutate-<stem>.status` while the child lives
 * and always finalizes `exit=` (try/finally). Pair with
 * `scripts/watch-detached-mutate.mjs` under Shell `notify_on_output`.
 *
 * Usage:
 *   node scripts/run-detached-mutate.mjs \
 *     --test=tests/Feature/Fieldtypes/HiddenFieldtypeTest.php \
 *     --class=App\\Fieldtypes\\Hidden \
 *     --stem=hidden
 *
 * Options:
 *   --test=<path>   Repo-relative Pest file (required)
 *   --class=<fqcn>  --class= value (required)
 *   --stem=<name>   Log/status basename (required)
 *   --path=<dir>       --path= value (default: app)
 *   --root=<dir>       Repository root (default: cwd)
 *   --parallel[=N]     Pass Pest `--parallel` (optional processes=N). Safe for
 *                      one mutate job's workers; do not overlap a second mutate.
 */

import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { needsShell, which, winQuote } from './spawn-helpers.mjs';

const HEARTBEAT_MS = 30_000;

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
 * @param {string} file
 * @param {string} line
 */
function writeStatus(file, line) {
    const payload = `${line}\n`;
    const tmp = `${file}.tmp`;
    // Atomic rename can EPERM on Windows when a watcher holds the status file.
    // Retry, then fall back to an in-place write so heartbeats never kill the job.
    for (let attempt = 0; attempt < 5; attempt++) {
        try {
            fs.writeFileSync(tmp, payload, 'utf8');
            fs.renameSync(tmp, file);
            return;
        } catch (err) {
            const code = err && typeof err === 'object' && 'code' in err ? err.code : '';
            if (code !== 'EPERM' && code !== 'EACCES' && code !== 'EBUSY') {
                throw err;
            }
            Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 25 * (attempt + 1));
        }
    }
    try {
        fs.writeFileSync(file, payload, 'utf8');
    } catch {
        // Last resort: drop the heartbeat rather than aborting the mutate child.
    }
}

/**
 * @param {string} file
 * @returns {{ bytes: number, writeIso: string }}
 */
function logStats(file) {
    try {
        const st = fs.statSync(file);
        return {
            bytes: st.size,
            writeIso: st.mtime.toISOString(),
        };
    } catch {
        return { bytes: 0, writeIso: '' };
    }
}

const args = parseArgs(process.argv.slice(2));
const testFile = args.test;
const className = args.class;
const stem = args.stem;
const pathArg = args.path ?? 'app';
const root = path.resolve(args.root ?? process.cwd());
const parallel = args.parallel;

if (!testFile || !className || !stem) {
    console.error(
        'Usage: node scripts/run-detached-mutate.mjs --test=<file> --class=<fqcn> --stem=<name> [--path=app] [--root=<dir>] [--parallel[=N]]',
    );
    process.exit(2);
}

const logDir = path.join(root, 'storage', 'logs');
fs.mkdirSync(logDir, { recursive: true });
const logFile = path.join(logDir, `mutate-${stem}.txt`);
const statusFile = path.join(logDir, `mutate-${stem}.status`);

try {
    fs.unlinkSync(logFile);
} catch {
    // absent is fine
}
try {
    fs.unlinkSync(statusFile);
} catch {
    // absent is fine
}

const logStream = fs.createWriteStream(logFile, { flags: 'w' });
await new Promise((resolve, reject) => {
    logStream.once('open', resolve);
    logStream.once('error', reject);
});

const env = {
    ...process.env,
    PCOV_DIRECTORY: 'app',
};

const php = which('php');
if (!php) {
    writeStatus(
        statusFile,
        `exit=1 finished=${new Date().toISOString()} reason=php-not-on-path runner=${process.pid}`,
    );
    console.error('php not found on PATH (Herd / php-cli required).');
    process.exit(1);
}

const pestArgs = [
    path.join(
        'vendor',
        'bin',
        'pest',
    ),
    testFile,
    '--mutate',
    `--path=${pathArg}`,
    `--class=${className}`,
    '--covered-only',
    '--no-cache',
    '--compact',
];
if (parallel !== undefined) {
    pestArgs.push('--parallel');
    if (parallel !== 'true' && parallel !== '') {
        pestArgs.push(`--processes=${parallel}`);
    }
}
const shell = needsShell(php);
const child = spawn(
    shell ? winQuote(php) : php,
    shell ? pestArgs.map(winQuote) : pestArgs,
    {
        cwd: root,
        env,
        shell,
        stdio: ['ignore', logStream, logStream],
        // Do not set detached:true: the parent heartbeat loop must see exit.
        windowsHide: true,
    },
);

writeStatus(
    statusFile,
    `started runner=${process.pid} child=${child.pid} at=${new Date().toISOString()} test=${testFile} class=${className}`,
);

/** @type {ReturnType<typeof setInterval> | null} */
let heartbeat = null;

/**
 * @param {number | null} code
 * @param {string} [reason]
 */
function finalize(code, reason) {
    if (heartbeat !== null) {
        clearInterval(heartbeat);
        heartbeat = null;
    }
    try {
        logStream.end();
    } catch {
        // ignore
    }
    const exitCode = code === null || code === undefined ? 1 : code;
    const reasonPart = reason ? ` reason=${reason}` : '';
    writeStatus(
        statusFile,
        `exit=${exitCode} finished=${new Date().toISOString()} child=${child.pid} runner=${process.pid}${reasonPart}`,
    );
}

heartbeat = setInterval(() => {
    if (child.exitCode !== null || child.signalCode) {
        return;
    }
    const { bytes, writeIso } = logStats(logFile);
    writeStatus(
        statusFile,
        `running runner=${process.pid} child=${child.pid} log_bytes=${bytes} log_write=${writeIso} at=${new Date().toISOString()}`,
    );
}, HEARTBEAT_MS);
heartbeat.unref?.();

child.on('error', (err) => {
    finalize(1, `spawn-error:${err.message.replace(/\s+/g, '_')}`);
    process.exit(1);
});

child.on('exit', (code, signal) => {
    if (signal) {
        finalize(1, `signal:${signal}`);
        process.exit(1);
    }
    finalize(code);
    process.exit(code === null ? 1 : code);
});

process.on('SIGINT', () => {
    try {
        child.kill('SIGINT');
    } catch {
        // ignore
    }
});
process.on('SIGTERM', () => {
    try {
        child.kill('SIGTERM');
    } catch {
        // ignore
    }
});
