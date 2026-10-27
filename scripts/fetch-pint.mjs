/**
 * fetch-pint.mjs — put Laravel Pint on a checkout that has no dev dependencies (UAMS-Web/uams-statamic-archive#1880).
 *
 * A Claude Code cloud session cannot install this repo's dev dependencies, so it has no Pint and no
 * way to know its formatting is wrong until a `pint` job reds in local CI. The install constraint is
 * structural rather than a misconfiguration — see `docs/runbooks/claude-code-cloud-sessions.md`:
 * `api.github.com` is unreachable for generic HTTP clients there, so `preferred-install=source` is
 * the only workable Composer strategy, and it covers 231 of 232 locked packages. The holdout is
 * `phpstan/phpstan`, which is dist-only (`"source": null` in `composer.lock`) and has no git path to
 * take. One unsatisfiable package aborts the whole install, so `--no-dev` is the only variant that
 * completes — dropping all 82 dev packages, Pint among them.
 *
 * **Pint does not actually need any of that.** It declares NO package dependencies (only `php ^8.2`
 * plus `ext-json`, `ext-mbstring`, `ext-tokenizer`, `ext-xml`) and ships a prebuilt ~22 MB phar at
 * `builds/pint`; `vendor/bin/pint` is only a Composer proxy to it. So Composer is how we happen to
 * deliver Pint, not something Pint requires. Fetching that phar over the git transport a cloud
 * session already has sidesteps the dependency problem entirely rather than solving it.
 *
 * **The reference is read from `composer.lock`, never hardcoded.** A pinned SHA written here would
 * be a second source of truth for "which Pint this project uses", and the two would drift silently —
 * the checkout would format against a version the project does not use, which is precisely the
 * class of quiet wrongness this script exists to prevent. Reading the lock makes drift impossible by
 * construction, so no test has to police it.
 *
 * Usage (from the repo root):
 *
 *     node scripts/fetch-pint.mjs           # fetch into .tools/pint if not already there
 *     node scripts/fetch-pint.mjs --check   # exit 3 if absent OR stale, 0 only at the locked ref
 *
 * Then run the formatter with plain PHP — no autoloader, no vendor/:
 *
 *     php .tools/pint/builds/pint --test --config pint.json
 *
 * `.tools/` is git-ignored, so nothing this script writes can be staged.
 */

import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, rmSync } from 'node:fs';
import { join } from 'node:path';

/** Where the phar lands, relative to the repo root. Git-ignored. */
export const TOOLS_DIR = join('.tools', 'pint');

/** The phar's path inside the fetched tree — this is the actual binary, not a wrapper. */
export const PHAR_PATH = join(TOOLS_DIR, 'builds', 'pint');

/**
 * Read Pint's git source and pinned reference out of a parsed `composer.lock`.
 *
 * Pint sits in `packages-dev`, but both arrays are searched rather than assuming which — a package
 * moving between `require` and `require-dev` is an ordinary change and must not silently break this.
 *
 * @param  {{packages?: Array<object>, 'packages-dev'?: Array<object>}} lock  Parsed composer.lock.
 * @returns {{url: string, reference: string}}
 */
export function pintSourceFromLock(lock) {
    const all = [...(lock.packages ?? []), ...(lock['packages-dev'] ?? [])];
    const pint = all.find((pkg) => pkg?.name === 'laravel/pint');

    if (!pint) {
        throw new Error('laravel/pint is not in composer.lock.');
    }

    const url = pint.source?.url;
    const reference = pint.source?.reference;

    // `phpstan/phpstan` is the cautionary case: dist-only, `"source": null`. If Pint ever publishes
    // the same way this script stops working, and it should say so rather than fetch nothing.
    if (!url || !reference) {
        throw new Error(
            `laravel/pint ${pint.version ?? ''} has no git source in composer.lock `
            + '(dist-only, like phpstan/phpstan), so it cannot be fetched over the git transport.'
        );
    }

    return { url, reference };
}

/** Read and parse `composer.lock` from a repo root. */
export function readLock(repoRoot) {
    return JSON.parse(readFileSync(join(repoRoot, 'composer.lock'), 'utf8'));
}

/**
 * The git commands that fetch exactly one commit — no branches, no tags, no history.
 *
 * `clone --depth 1` would take the default branch's tip instead, which is a DIFFERENT Pint from the
 * one the lock pins. Measured 2026-08-18: cloning the tip gave 1.30.5 against a locked 1.30.3. They
 * happened to agree on this repo, so the drift would have been invisible until the day it wasn't.
 *
 * **Every step is idempotent, and that is deliberate.** An earlier form ran `remote add origin`,
 * which fails with `remote origin already exists` on any rerun — so a single interrupted fetch (the
 * documented, expected cloud failure) wedged the script permanently behind an error that named the
 * remote rather than the real problem. `git fetch` takes a URL directly, so the remote buys nothing.
 *
 * @returns {Array<{args: Array<string>, cwd: string}>}
 */
export function fetchPlan(repoRoot, { url, reference }) {
    const dir = join(repoRoot, TOOLS_DIR);

    return [
        { args: ['init', '-q', dir], cwd: repoRoot },
        { args: ['-C', dir, 'fetch', '-q', '--depth', '1', url, reference], cwd: repoRoot },
        { args: ['-C', dir, 'checkout', '-q', 'FETCH_HEAD'], cwd: repoRoot },
    ];
}

/**
 * The commit `.tools/pint` is currently checked out at, or null when it is absent or not a repo.
 *
 * This is what makes "the reference comes from `composer.lock`" true on every run rather than only
 * the first. Testing for the phar FILE alone cannot distinguish a current install from a stale one,
 * so a lock bumped to a new Pint would leave the old binary in place indefinitely — formatting
 * against a version the project does not use, which is the exact failure the lock-reading exists to
 * prevent.
 */
export function installedReference(repoRoot) {
    const result = spawnSync('git', ['-C', join(repoRoot, TOOLS_DIR), 'rev-parse', 'HEAD'], {
        encoding: 'utf8',
    });

    return result.status === 0 ? result.stdout.trim() : null;
}

function run(args, cwd) {
    const result = spawnSync('git', args, { cwd, stdio: 'inherit' });

    // `error` is set when the binary could not be started at all; `status` is null in that case, so
    // reporting only the status says "failed with status null" for a missing git.
    if (result.error) {
        throw new Error(`git could not be started: ${result.error.message}`);
    }

    if (result.status !== 0) {
        throw new Error(`git ${args.join(' ')} failed with status ${result.status}.`);
    }
}

function main() {
    const repoRoot = process.cwd();
    const checkOnly = process.argv.includes('--check');
    const pharAbsolute = join(repoRoot, PHAR_PATH);

    // Read the lock FIRST. The install is "current" only when it matches what the lock pins, so
    // there is no state in which this script can answer without consulting it.
    const { url, reference } = pintSourceFromLock(readLock(repoRoot));
    const installed = existsSync(pharAbsolute) ? installedReference(repoRoot) : null;

    if (installed === reference) {
        process.stdout.write(`Pint is present at ${PHAR_PATH}, at the locked ${reference.slice(0, 9)}.\n`);
        process.exit(0);
    }

    const problem = installed === null
        ? `Pint is not present at ${PHAR_PATH}`
        : `Pint at ${PHAR_PATH} is ${installed.slice(0, 9)}, but composer.lock pins ${reference.slice(0, 9)}`;

    if (checkOnly) {
        process.stderr.write(`${problem}. Run: node scripts/fetch-pint.mjs\n`);
        process.exit(3);
    }

    process.stdout.write(`${problem}.\nFetching laravel/pint at ${reference.slice(0, 9)} from ${url}\n`);

    // Any tree reached here is absent, incomplete, or at the wrong commit, so discarding it is
    // always safe and makes a half-finished fetch self-healing rather than terminal.
    rmSync(join(repoRoot, TOOLS_DIR), { recursive: true, force: true });

    for (const step of fetchPlan(repoRoot, { url, reference })) {
        run(step.args, step.cwd);
    }

    if (!existsSync(pharAbsolute)) {
        process.stderr.write(`Fetch completed but ${PHAR_PATH} is missing.\n`);
        process.exit(1);
    }

    process.stdout.write(`\nPint is ready. Run the formatting check with:\n`);
    process.stdout.write(`  php ${PHAR_PATH} --test --config pint.json\n`);
}

// Only run when invoked directly, so the test can import the pure helpers above.
if (process.argv[1] && process.argv[1].endsWith('fetch-pint.mjs')) {
    main();
}
