/**
 * spawn-helpers.mjs — resolve a command and decide how it has to be spawned.
 *
 * Three helpers that every script shelling out to this repo's toolchain needs, kept in one place
 * because the rule they encode is security-driven rather than stylistic and a second copy would
 * drift silently (UAMS-Web/uams-statamic-archive#1948).
 *
 * Node refuses to spawn `.cmd` / `.bat` directly (CVE-2024-27980). On Windows this toolchain
 * straddles that line — `composer.bat`, `php.bat` and `npm.cmd` are shims, `git.exe` and `node.exe`
 * are not — so a caller has to know which kind it holds. Get it wrong and the failure points
 * somewhere else entirely: a bare `spawnSync composer ENOENT`, which reads as "Composer is not
 * installed" on a machine where Composer is plainly on `PATH`.
 *
 * The three are a set, not three conveniences. `which` resolves the extension, `needsShell` reads
 * it, and `winQuote` repairs the quoting that `shell: true` costs you — using any one without the
 * others reintroduces the bug. The pairing at a call site is:
 *
 *     const exe = which(file) ?? file;
 *     const shell = needsShell(exe);
 *     spawnSync(shell ? winQuote(exe) : exe, shell ? args.map(winQuote) : args, { shell, … });
 */

import { accessSync, constants, statSync } from 'node:fs';
import { delimiter, join } from 'node:path';

const isWindows = process.platform === 'win32';

/**
 * Whether a resolved executable has to run through a shell.
 *
 * Takes a resolved path, not a bare command name — the extension is the whole signal, and a bare
 * name carries none. See `which`.
 *
 * @param  string  $exe
 * @return bool
 */
export function needsShell(exe) {
    return isWindows && /\.(cmd|bat)$/i.test(exe);
}

/**
 * Quote an argument for a Windows shell invocation, per the CreateProcess argv rules.
 *
 * Only needed when `needsShell` said yes: `shell: true` hands the whole command line to `cmd.exe`
 * as text, so an argument holding a space or a quote has to survive being re-parsed. The empty
 * string is the case worth stating, because it has no character to notice — unquoted it simply
 * vanishes from the command line, silently shifting every argument after it by one position.
 *
 * @param  string  $arg
 * @return string
 */
export function winQuote(arg) {
    if (arg === '') {
        return '""';
    }

    if (!/[\s"]/.test(arg)) {
        return arg;
    }

    return `"${arg.replace(/(\\*)"/g, '$1$1\\"').replace(/(\\*)$/, '$1$1')}"`;
}

/**
 * Locate an executable on PATH, honoring PATHEXT on Windows. Returns an absolute path, or null.
 *
 * Resolving the absolute path is what lets `needsShell` see the real extension, so this is the
 * step that makes the shim visible at all rather than a lookup convenience.
 *
 * The extension in the returned path carries PATHEXT's case, not the filesystem's — a `.cmd` on
 * disk comes back as `.CMD` — because a case-insensitive `statSync` accepts the candidate as
 * built. Harmless (Windows resolves either, and `needsShell` matches case-insensitively), but do
 * not compare the result against a literal path.
 *
 * @param  string  $command
 * @return ?string
 */
export function which(command) {
    const dirs = (process.env.PATH || '').split(delimiter).filter(Boolean);
    const exts = isWindows
        ? (process.env.PATHEXT || '.COM;.EXE;.BAT;.CMD').split(';').filter(Boolean)
        : [''];

    for (const dir of dirs) {
        for (const ext of exts) {
            const candidate = join(dir, command + ext);

            try {
                if (statSync(candidate).isFile()) {
                    return candidate;
                }
            } catch {
                // Not here — keep looking.
            }
        }
    }

    return null;
}

/**
 * Can this OS actually execute the file at `candidate`?
 *
 * The question is deliberately NOT "does the path end in a known extension". A `.phar` with a
 * shebang and mode 755 spawns fine on POSIX and the identical bytes at 644 return `EACCES`, so
 * the suffix decides nothing there — measured on macOS for `UAMS-Web/uamswp-migration-api-archive#143`,
 * whose decision this ports. Refusing by suffix would reject a configuration that works today.
 *
 * The two platforms answer it differently because the OS does:
 *
 *   POSIX    the execute bit, via `accessSync(candidate, X_OK)`.
 *   Windows  there is no execute bit. What decides is whether the extension is one the shell
 *            will run, which is exactly `PATHEXT` — the same list `which()` searches. A `.phar`
 *            is absent from it and so is not executable, which is correct: spawning one needs
 *            `php` as an interpreter, and nothing here supplies that.
 *
 * Used to refuse a `LOCAL_CI_*` override AT RESOLUTION rather than letting it fail later as a
 * bare `EACCES` or `EFTYPE` from `spawnSync`, which points at the tool rather than at the
 * variable that chose it (UAMS-Web/uams-statamic-archive#2335).
 *
 * @param  {string}  candidate  absolute path to check
 * @return {boolean}
 */
export function isExecutable(candidate) {
    try {
        if (! statSync(candidate).isFile()) {
            return false;
        }
    } catch {
        return false;
    }

    if (! isWindows) {
        try {
            accessSync(candidate, constants.X_OK);

            return true;
        } catch {
            return false;
        }
    }

    const exts = (process.env.PATHEXT || '.COM;.EXE;.BAT;.CMD')
        .split(';')
        .filter(Boolean)
        .map((ext) => ext.toLowerCase());

    const lowered = candidate.toLowerCase();

    return exts.some((ext) => lowered.endsWith(ext));
}
