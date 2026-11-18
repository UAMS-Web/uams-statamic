/**
 * run-detached-mutate-args.mjs: the Pest argument list `run-detached-mutate.mjs` starts.
 *
 * Split out so the list is testable without spawning a mutation run.
 *
 * `--covered-only` is deliberately absent (#1033). With it, the plugin mutates only
 * the lines the tests already execute, so a class whose tests miss most of it can
 * still report 100%: on #1030 the existing tests reported 100% on 89 mutants with
 * the flag, and killed 37 of 108 without it. A run here mutates every line of the
 * class, and takes longer for it.
 */

import path from 'node:path';

/**
 * @param {{ testFile: string, pathArg: string, className: string, parallel?: string }} options
 * @returns {string[]}
 */
export function mutatePestArgs({ testFile, pathArg, className, parallel }) {
    const pestArgs = [
        path.join('vendor', 'bin', 'pest'),
        testFile,
        '--mutate',
        `--path=${pathArg}`,
        `--class=${className}`,
        '--no-cache',
        '--compact',
    ];

    if (parallel !== undefined) {
        pestArgs.push('--parallel');
        if (parallel !== 'true' && parallel !== '') {
            pestArgs.push(`--processes=${parallel}`);
        }
    }

    return pestArgs;
}
