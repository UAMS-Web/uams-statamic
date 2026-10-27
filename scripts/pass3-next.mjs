#!/usr/bin/env node
/**
 * pass3-next.mjs: print the current pass-3 snowball head.
 *
 * Reads `storage/app/pass3-snowball.json` and skips any queue entry whose
 * `coverage-scope.csv` `pass3_mutation_clean` cell is already non-empty, so the
 * head advances without regenerating estimates after each class.
 *
 * Usage:
 *   node scripts/pass3-next.mjs
 *   node scripts/pass3-next.mjs --csv=… --queue=… --json
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
 * @param {string} csvPath
 * @returns {Map<string, string>}
 */
function readPass3Cells(csvPath) {
    const text = fs.readFileSync(csvPath, 'utf8');
    /** @type {Map<string, string>} */
    const cells = new Map();
    for (const line of text.split(/\r?\n/u)) {
        if (!line || line.startsWith('file,')) {
            continue;
        }
        // Minimal CSV split: file paths have no commas; pass3 may be quoted.
        const match = line.match(
            /^([^,]+),([^,]*),([^,]*),(.*)$/u,
        );
        if (!match) {
            continue;
        }
        let pass3 = match[4] ?? '';
        if (pass3.startsWith('"') && pass3.endsWith('"')) {
            pass3 = pass3.slice(1, -1).replace(/""/gu, '"');
        }
        cells.set(match[1], pass3.trim());
    }
    return cells;
}

const args = parseArgs(process.argv.slice(2));
const root = path.resolve(args.root ?? process.cwd());
const csvPath = path.resolve(
    args.csv ?? path.join(root, 'storage', 'app', 'coverage-scope.csv'),
);
const queuePath = path.resolve(
    args.queue ?? path.join(root, 'storage', 'app', 'pass3-snowball.json'),
);
const asJson = args.json === 'true';

if (!fs.existsSync(queuePath)) {
    console.error(
        `pass3-next: missing queue at ${queuePath} (run php scripts/estimate-pass3-mutants.php first)`,
    );
    process.exit(2);
}
if (!fs.existsSync(csvPath)) {
    console.error(`pass3-next: missing CSV at ${csvPath}`);
    process.exit(2);
}

/** @type {{ pass2_min?: number, queue?: Array<{ file: string, est_mutants: number, actual_mutants?: number | null, note?: string | null }> }} */
const snowball = JSON.parse(fs.readFileSync(queuePath, 'utf8'));
const pass3 = readPass3Cells(csvPath);
const queue = snowball.queue ?? [];

const next = queue.find((entry) => {
    const cell = pass3.get(entry.file);
    return cell === undefined || cell === '';
});

if (!next) {
    if (asJson) {
        console.log(JSON.stringify({ done: true, remaining: 0 }));
    } else {
        console.log('pass3-next: queue empty (every snowball row has a pass3 cell)');
    }
    process.exit(0);
}

const remaining = queue.filter((entry) => {
    const cell = pass3.get(entry.file);
    return cell === undefined || cell === '';
}).length;

const payload = {
    file: next.file,
    est_mutants: next.est_mutants,
    actual_mutants: next.actual_mutants ?? null,
    note: next.note ?? null,
    remaining,
    pass2_min: snowball.pass2_min ?? null,
    parallel: next.est_mutants >= 200,
};

if (asJson) {
    console.log(JSON.stringify(payload));
} else {
    const parallelHint = payload.parallel ? '  (use --parallel=6)' : '';
    console.log(
        `${payload.file}\test_mutants=${payload.est_mutants}\tremaining=${payload.remaining}${parallelHint}`,
    );
}

process.exit(0);
