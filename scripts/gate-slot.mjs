/**
 * gate-slot.mjs: what the local CI gate checks out into its slot, and whether a slot's
 * applied Composer patches are the ones its commit declares.
 *
 * Kept apart from local-ci.mjs because scripts/worktree-slots.mjs judges the CI slot by the
 * same rules and imports them, and local-ci.mjs is retired when this repository moves onto
 * the shared runner (#2837). A second copy of either rule would drift silently.
 */

// The submodules every gate checks out ahead of the pest job (UAMS-Web/uams-statamic-archive#2477), in the order
// git receives them. `content` holds the entries and collection configs the suite
// reads. `users` is what lets tests/Feature/Users/RegistrantRecordsAreGitIgnoredTest.php
// run rather than skip: since UAMS-Web/uams-statamic-archive#2454 it holds only the two seed accounts and the
// .gitignore allowlist, a 2 KB pack. `resources/forms` holds the form configurations
// (UAMS-Web/uams-statamic-archive#2818), which the tests that read the real forms need. The two asset submodules stay out: binary-heavy,
// and nothing in the suite reads them. One list, read from three places: the init
// step and the post-suite litter probe in scripts/local-ci.mjs, and scripts/worktree-slots.mjs, whose
// CI-slot expectations must name exactly what local-ci.mjs initializes. ci.yml's pest
// job names the same three by hand, and tests/Feature/GatesInitializeUsersSubmoduleTest.php
// pins both copies.
export const GATE_SUBMODULES = Object.freeze(['content', 'users', 'resources/forms'])

// UAMS-Web/uams-statamic-archive#1743: decide whether the worktree's applied patches match the branch's.
//
// On a REUSED worktree `composer install` reports "Nothing to install, update or
// remove", so no package-install event fires and cweagans/composer-patches never
// applies a patch the branch newly added. The band then validates the *previous*
// patch set and reports green: the gate testing unpatched code.
//
// `patches.lock.json` carries a `_hash` over the whole patch set, so comparing it
// against a stamp written after the last successful patch application answers
// "are the installed patches the ones this branch declares?" without inspecting
// vendor/. A missing stamp means unknown (a cold install, or a worktree from
// before this check existed) and is treated as stale, since re-patching is cheap
// and safe while a false "fresh" is exactly the failure being fixed.
//
// Returns the reason to re-patch, or null when the worktree is current.
export function patchStaleReason(lockHash, stampHash) {
    if (!lockHash) {
        // No lock hash to compare against; nothing declared, nothing to verify.
        return null
    }
    if (!stampHash) {
        return 'no record of previously applied patches'
    }
    if (stampHash !== lockHash) {
        return `patch set changed (${stampHash.slice(0, 12)} -> ${lockHash.slice(0, 12)})`
    }
    return null
}

// UAMS-Web/uams-statamic-archive#1743: the `_hash` from patches.lock.json, or null when absent/unreadable.
// Parse failures are non-fatal: an unreadable lock yields null, which reads as
// "nothing declared" rather than failing the run on a malformed file.
export function readPatchLockHash(raw) {
    if (!raw) {
        return null
    }
    try {
        const parsed = JSON.parse(raw)
        return typeof parsed?._hash === 'string' ? parsed._hash : null
    } catch {
        return null
    }
}

// UAMS-Web/uams-statamic-archive#1743: the package names carrying patches, so a re-patch can remove exactly
// those from vendor/ and let `composer install` reinstall (and re-patch) them.
export function patchedPackages(raw) {
    if (!raw) {
        return []
    }
    try {
        const parsed = JSON.parse(raw)

        return Object.keys(parsed?.patches ?? {}).sort()
    } catch {
        return []
    }
}
