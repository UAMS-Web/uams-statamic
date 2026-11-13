#!/usr/bin/env node
// Decides whether a staged change to the mirrored CI files touches behavior the
// OTHER side mirrors, so `.githooks/pre-commit` can gate on that rather than on
// the files' mere presence in the index (UAMS-Web/uams-statamic-archive#2376).
//
// The gate it serves is real: `.github/workflows/ci.yml` and the local runner's
// job list are hand-maintained mirrors with no shared source of truth, so editing
// one without the other lets local and remote CI drift silently.
//
// THE LOCAL SIDE (#296). The shared local CI runner reads its jobs from
// `local-ci.json`, and the jobs that run several commands hold them in
// `scripts/ci-job.mjs`. Both files exist only to mirror ci.yml, so ANY staged
// change to either counts as a mirrored change. The gate step scripts
// (`scripts/gate-*.mjs`, #295) also hold code ci.yml has no counterpart for, so
// only their `PARITY:` ... `PARITY-END` regions count.
//
// WHAT COUNTS AS A MARKED REGION in ci.yml: a `# PARITY:` marker governs the
// step it precedes, derived below. In a step script a marker governs the lines
// up to its `PARITY-END`; a marker with no end governs nothing, so an
// un-delimited marker cannot silently claim the rest of the file.
//
// FAILING SAFE. If this script cannot run at all, the hook keeps its old
// behavior and fires on any one-sided staging. That can only over-fire, never
// under-fire -- the opposite choice would disable a real gate silently, which is
// the failure mode the whole ticket is about.

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { isMainModule } from './gate-process.mjs';

/** A real one-sided change. Distinct from 1, which node itself returns when it cannot run this file. */
export const VIOLATION = 2;

export const CI_FILE = '.github/workflows/ci.yml';
// Files that are wholly the local mirror: any staged change to one is a mirrored change.
export const MIRROR_FILES = Object.freeze(['local-ci.json', 'scripts/ci-job.mjs']);

// The step scripts `local-ci.json` runs (#295). Each marks its mirrored code with
// one `PARITY:` line closed by `PARITY-END`; the rest of each file is local-only.
export const GATE_STEP_FILES = Object.freeze([
    'scripts/gate-browser.mjs',
    'scripts/gate-pest.mjs',
    'scripts/gate-pint.mjs',
    'scripts/gate-rector.mjs',
]);

const MARKER = /PARITY/;
const END_MARKER = /PARITY-END/;

/**
 * Line ranges (1-based, inclusive) that a `PARITY:` marker governs.
 *
 * @param {string} source  the file's full text
 * @param {'mjs'|'yml'} kind
 * @returns {{start: number, end: number}[]}
 */
export function parityRegions(source, kind) {
    const lines = source.split('\n');
    const regions = [];
    for (let i = 0; i < lines.length; i += 1) {
        if (!MARKER.test(lines[i]) || END_MARKER.test(lines[i])) continue;
        const start = i + 1;
        let end = null;

        // An explicit PARITY-END always wins, and is what the ambiguous cases use.
        for (let j = i + 1; j < lines.length; j += 1) {
            if (END_MARKER.test(lines[j])) { end = j + 1; break; }
            if (MARKER.test(lines[j])) break; // next marker: this one never closed
        }

        if (end === null && kind === 'yml') {
            // A marker governs the STEP it precedes; the step ends where the next
            // one begins (a `- ` at the same indent) or its indentation drops.
            //
            // A marker that precedes no step governs NOTHING -- the same rule the
            // `mjs` branch above applies to a marker outside any runner, and it
            // has to be stated here too. The file header carries two `# PARITY:`
            // notes at indent 0 describing the CONVENTION rather than any
            // mirrored step; an earlier draft let each of those run to EOF and
            // reported 2289 covered lines in a 1062-line file. That over-fires on
            // every edit, which would have made this gate worse than the one it
            // replaces rather than better.
            //
            // The step is found past any intervening COMMENT lines, not just
            // blank ones. Eight of this file's thirty markers sit in the middle
            // of a multi-line comment block rather than immediately above the
            // `- name:` they annotate; requiring the very next non-blank line to
            // be the step dropped all eight and left 20 regions for 30 markers.
            // That is an UNDER-fire -- a genuinely mirrored step reading as
            // unmarked -- which is the failure this ticket says is worse than
            // the friction it replaces.
            const indent = lines[i].search(/\S/);
            const isComment = (l) => /^\s*#/.test(l);
            const next = lines.slice(i + 1).find((l) => l.trim() !== '' && !isComment(l));
            //
            // The region ends on the step's LAST NON-BLANK line (UAMS-Web/uams-statamic-archive#2865). Ending
            // it at the line before the next step took in the blank separator,
            // and at EOF the empty string `split` returns after a final newline,
            // so a job appended after the last marked step, or a step added
            // after any marked one, read as an edit to that step.
            //
            // Lines at the marker's indent before the step are the comment the
            // marker sits in; the step's own `- ` line comes next, and after it
            // anything back at that indent (the next step, or the comment
            // above it) is outside the region (UAMS-Web/uams-statamic-archive#3238). Breaking on the first
            // `- ` line, as this did, stopped a mid-comment marker's region at
            // the comment above its step, so an edit to the step read as unmarked.
            if (next !== undefined && /^\s*- /.test(next) && next.search(/\S/) === indent) {
                let last = i;
                let inStep = false;
                for (let j = i + 1; j < lines.length; j += 1) {
                    const l = lines[j];
                    if (l.trim() === '') continue;
                    const ind = l.search(/\S/);
                    if (ind < indent) break;
                    if (ind === indent) {
                        if (inStep) break;
                        if (/^\s*- /.test(l)) inStep = true;
                    }
                    last = j;
                }
                end = last + 1;
            }
        }

        if (end !== null) regions.push({ start, end });
    }
    return regions;
}

/**
 * New-file line numbers a unified diff touches.
 *
 * A pure deletion has no new-file line of its own, so it is attributed to the
 * GAP it sits in, as `n + 0.5` between lines `n` and `n + 1` -- otherwise
 * removing a mirrored step would register as touching nothing at all, which is
 * the under-fire this gate cannot afford. A gap is inside a region only when the
 * lines on both sides are (UAMS-Web/uams-statamic-archive#2865): one beside a region's edge is outside it, and
 * any real change to the region's own lines is still seen, on this side or on
 * the old-file side.
 *
 * @param {string} diff  output of `git diff -U0`
 * @returns {number[]}
 */
export function touchedLines(diff) {
    const touched = new Set();
    for (const m of diff.matchAll(/^@@ -\S+ \+(\d+)(?:,(\d+))? @@/gm)) {
        const start = Number(m[1]);
        const count = m[2] === undefined ? 1 : Number(m[2]);
        if (count === 0) { touched.add(start + 0.5); continue; }
        for (let n = start; n < start + count; n += 1) touched.add(n);
    }
    return [...touched].sort((a, b) => a - b);
}

/**
 * OLD-file line numbers a unified diff touches.
 *
 * The new-file side alone cannot see a region that was DELETED: the staged
 * source no longer contains it, so there is nothing left to intersect and the
 * gate passes. Removing a mirrored runner outright is exactly the drift it
 * exists to catch, so both sides are checked against their own file's regions.
 * Found by adversarial review of this change, not by a test (UAMS-Web/uams-statamic-archive#2376).
 */
export function touchedLinesOld(diff) {
    const touched = new Set();
    for (const m of diff.matchAll(/^@@ -(\d+)(?:,(\d+))? \+/gm)) {
        const start = Number(m[1]);
        const count = m[2] === undefined ? 1 : Number(m[2]);
        // A pure insertion is a gap in the old file, as a pure deletion is in
        // the new one (see `touchedLines`).
        if (count === 0) { touched.add(start + 0.5); continue; }
        for (let n = start; n < start + count; n += 1) touched.add(n);
    }
    return [...touched].sort((a, b) => a - b);
}

/** Does any touched line fall inside any marked region? */
export function touchesRegion(touched, regions) {
    return touched.some((n) => regions.some((r) => n >= r.start && n <= r.end));
}

/**
 * The gate's decision.
 *
 * Fires when exactly ONE side's staged change lands in a mirrored region: that
 * is a mirrored edit with no counterpart, which is the drift the gate exists to
 * catch. Both-in-region is the co-change it wants; neither-in-region is the
 * local-only edit UAMS-Web/uams-statamic-archive#2376 is about, and it passes.
 *
 * `mirrorDiffs` holds the staged diffs of the MIRROR_FILES: a non-empty one is in
 * region whatever it touches.
 *
 * @returns {{fire: boolean, ciInRegion: boolean, scriptInRegion: boolean}}
 */
export function evaluate({
    ciDiff = '', ciSource = '', ciSourceOld = '', mirrorDiffs = [], gateSteps = [],
}) {
    const inRegion = (diff, source, sourceOld, kind) => diff !== '' && (
        touchesRegion(touchedLines(diff), parityRegions(source, kind))
        || touchesRegion(touchedLinesOld(diff), parityRegions(sourceOld, kind))
    );
    const ciInRegion = inRegion(ciDiff, ciSource, ciSourceOld, 'yml');
    const scriptInRegion = mirrorDiffs.some((diff) => diff !== '')
        || gateSteps.some(({ diff, source, sourceOld }) => inRegion(diff, source, sourceOld, 'mjs'));
    return { fire: ciInRegion !== scriptInRegion, ciInRegion, scriptInRegion };
}

const git = (args) => execFileSync('git', args, { encoding: 'utf8' });

/**
 * Read the staged state and report. Returns an exit code rather than calling
 * `process.exit`, so a test can import this without ending its own run — the
 * same shape `scripts/check-no-emoji.mjs` uses.
 *
 * EXIT CODES ARE A CONTRACT WITH THE HOOK, and the reason is a defect this
 * change shipped once. `0` is parity satisfied; `VIOLATION` is a real
 * one-sided change. Anything else means the script could not RUN, and the hook
 * MUST be able to tell that apart so it can fall back to the old file-level
 * check. Returning `1` for a violation made it indistinguishable from node's
 * own `1` on a missing module — so the documented fallback was unreachable
 * whenever node existed and this file did not, which `git bisect` onto any
 * earlier commit reproduces.
 */
export function main() {
    const staged = git(['diff', '--cached', '--name-only']).split('\n');
    const ciStaged = staged.includes(CI_FILE);
    const stagedMirrors = MIRROR_FILES.filter((f) => staged.includes(f));
    const stagedSteps = GATE_STEP_FILES.filter((f) => staged.includes(f));
    if (!ciStaged && stagedMirrors.length === 0 && stagedSteps.length === 0) return 0;

    // Region extents are read from the INDEX, not the working tree: the commit
    // records the index, so a marker moved-but-not-staged must not shift the
    // regions this decision is made against.
    const at = (f) => git(['show', `:${f}`]);
    // The pre-image, for the deleted-region case. A file new in this commit has
    // none, and an empty source simply yields no regions.
    // stderr is ignored, not merely caught: a file new in this commit makes git
    // print `fatal: path '…' exists on disk, but not in 'HEAD'` before the throw
    // reaches the catch, which lands as noise in the hook's own output.
    const atHead = (f) => {
        try {
            return execFileSync('git', ['show', `HEAD:${f}`], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
        } catch { return ''; }
    };
    const diffOf = (f) => git(['diff', '--cached', '--unified=0', '--', f]);
    // A step file staged for deletion has no index entry to read.
    const atOrEmpty = (f) => {
        try {
            return execFileSync('git', ['show', `:${f}`], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
        } catch { return ''; }
    };

    const { fire, ciInRegion, scriptInRegion } = evaluate({
        ciDiff: ciStaged ? diffOf(CI_FILE) : '',
        ciSource: ciStaged ? at(CI_FILE) : '',
        ciSourceOld: ciStaged ? atHead(CI_FILE) : '',
        mirrorDiffs: stagedMirrors.map(diffOf),
        gateSteps: stagedSteps.map((f) => ({ diff: diffOf(f), source: atOrEmpty(f), sourceOld: atHead(f) })),
    });
    if (!fire) return 0;

    const inOne = ciInRegion ? CI_FILE : [...stagedMirrors, ...stagedSteps].join(', ');
    const other = ciInRegion ? `${MIRROR_FILES.join(', ')} or a gate step script` : CI_FILE;
    console.error('');
    console.error('CI parity gate: a staged change lands in a mirrored region of');
    console.error(`    ${inOne}`);
    console.error(`with no matching change in a mirrored region of`);
    console.error(`    ${other}`);
    console.error('');
    console.error('A `PARITY:` region and its local mirror must change together, so the');
    console.error('local and remote runners cannot drift. Update the other side, or if');
    console.error('this really is one-sided:');
    console.error('    git commit --no-verify');
    console.error('');
    console.error('Note that --no-verify also skips the merge-resolution gate.');
    console.error('');
    return VIOLATION;
}

if (isMainModule(import.meta.url)) process.exit(main());
