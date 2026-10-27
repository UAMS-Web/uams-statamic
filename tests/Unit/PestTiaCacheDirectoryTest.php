<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Storage;

/**
 * Guards the `pest()->tia()->directory('tests/.pest/tia')` line in `tests/Pest.php` (UAMS-Web/uams-statamic-archive#1991).
 *
 * Delete that line and every checkout silently falls back to
 * `~/.pest/tia/<folder-slug>-<origin-hash>`. `Storage::slug()` lowercases and collapses
 * runs of non-alphanumerics, so two checkouts of this repo whose directory names slugify
 * alike — `uams-statamic-a` and `uams_statamic_a` — resolve to one key. Edges are global
 * to that key (`Graph::replaceEdges()` takes no branch), so one checkout's edge set drives
 * another's affected-set computation: a green `--tia` run that executed nothing,
 * indistinguishable from a fast one. That is the failure UAMS-Web/uams-statamic-archive#1717 adopted Tia to avoid, and
 * nothing else detects it — the graph lives outside the repo and Pest never names it.
 *
 * The guard is BEHAVIORAL rather than a source match on `tests/Pest.php`, because
 * asserting the line is present proves the line is present, not that it takes effect.
 *
 * The fixture pair carries a shared `[remote "origin"]` url on purpose. Without one
 * `Storage::projectKey()` falls back to hashing the path, the two roots key differently by
 * accident, and the ISOLATION test below would stay green even with the pin deleted —
 * proving nothing. (The sensitivity control goes the other way and reds on its collapse
 * assertion, so clearing the origin does not merely weaken the file, it makes two tests
 * disagree.) The shared origin is what makes the collapse real and both tests sensitive.
 * Measured 2026-08-14: both roots resolve to
 * `~/.pest/tia/uams-statamic-a-3a83e6201b505a9b` once the directory is cleared.
 *
 * There is no backing `App\` class, so there is no mutable PHP surface to mutation-test.
 *
 * @command  php artisan test --compact tests/Unit/PestTiaCacheDirectoryTest.php
 */
beforeEach(function (): void {
    // `Storage::$directory` is process-static and is set once, at bootstrap, by
    // tests/Pest.php. Capture the live value so afterEach can put back exactly what was
    // there: restoring the ORIGINAL rather than nulling it is what keeps this file from
    // becoming the bug it guards against — a null would leave the rest of a `--tia`
    // process keying off $HOME.
    //
    // The class exposes no getter, so read it the only way it can be read: resolve two
    // arbitrary roots and infer the setting from how they differ.
    $probeA = DIRECTORY_SEPARATOR.'pest-tia-probe-a';
    $probeB = DIRECTORY_SEPARATOR.'pest-tia-probe-b';
    $resolvedA = Storage::tempDir($probeA);

    $this->configuredDirectory = match (true) {
        // An absolute setting ignores the root entirely, so both probes agree.
        $resolvedA === Storage::tempDir($probeB) => $resolvedA,
        // A relative setting is appended to the root; strip it back off.
        str_starts_with($resolvedA, $probeA.DIRECTORY_SEPARATOR) => substr($resolvedA, strlen($probeA) + 1),
        // Unconfigured — keyed off $HOME, so there is nothing to restore.
        default => null,
    };

    // Two sibling roots whose basenames slugify to the same string, each carrying the
    // same git origin: the shape that collapses onto one cache key by default.
    $parent = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-tia-'.bin2hex(random_bytes(6));
    $this->fixtureParent = $parent;
    $this->fixtureRoots = [$parent.DIRECTORY_SEPARATOR.'uams-statamic-a', $parent.DIRECTORY_SEPARATOR.'uams_statamic_a'];

    foreach ($this->fixtureRoots as $root) {
        mkdir($root.DIRECTORY_SEPARATOR.'.git', 0o777, true);
        file_put_contents(
            $root.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'config',
            "[remote \"origin\"]\n\turl = https://github.com/UAMS-Web/uams-statamic.git\n"
        );
    }
});

afterEach(function (): void {
    Storage::useDirectory($this->configuredDirectory);

    foreach ($this->fixtureRoots as $root) {
        @unlink($root.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'config');
        @rmdir($root.DIRECTORY_SEPARATOR.'.git');
        @rmdir($root);
    }

    @rmdir($this->fixtureParent);
});

it('resolves the Tia cache inside the project root, at the configured relative path', function (): void {
    $root = $this->fixtureRoots[0];

    // The literal is duplicated from tests/Pest.php deliberately. `/tests/.pest/` is the
    // only git-ignore rule covering this cache (.gitignore line 73), so moving it
    // elsewhere in the tree would start committing the graph — this assertion is what
    // makes that a decision rather than an accident.
    expect(Storage::tempDir($root))->toBe($root.DIRECTORY_SEPARATOR.'tests/.pest/tia');
});

it('gives two checkouts whose folder names slugify alike their own cache directories', function (): void {
    [$a, $b] = $this->fixtureRoots;

    // The isolation property itself. Both roots share a slug AND an origin, so this is
    // the pair that collapses without the configuration.
    expect(Storage::tempDir($a))->not->toBe(Storage::tempDir($b));
});

it('is sensitive to the configuration: clearing it breaks both properties', function (): void {
    [$a, $b] = $this->fixtureRoots;

    // Default keying only reaches projectKey() when a home directory resolves. Without
    // one `tempDir()` returns `$root/.pest/tia` — per-root, so BOTH assertions below
    // fail rather than passing vacuously. The vacuous pass in that environment is the
    // ISOLATION test above, which stays green on the per-root fallback even with the pin
    // deleted; naming that cause here is what this precondition is for.
    //
    // Resolve the home exactly the way `Storage::homeDir()` does: it tries HOME then
    // USERPROFILE and validates EACH independently, so a HOME that is set but cannot be
    // stat'd — an unconverted MSYS `/c/Users/...` under Git Bash — must fall through to
    // USERPROFILE. A `?:` short-circuits on that unreadable value instead, reddening this
    // test on a Windows checkout where the property is perfectly fine, and reporting a
    // reason ("neither resolves") that is not true.
    $home = null;

    foreach (['HOME', 'USERPROFILE'] as $key) {
        $value = getenv($key);

        if (is_string($value) && $value !== '' && is_dir($value)) {
            $home = $value;

            break;
        }
    }

    expect($home)->not->toBeNull(
        'No home directory resolves, so default keying returns a per-root path: the isolation '.
        'test above passes vacuously, and the assertions below fail for the wrong reason.'
    );

    Storage::useDirectory(null);

    // 1. The cache is no longer inside the project root at all.
    expect(Storage::tempDir($a))->not->toStartWith($a.DIRECTORY_SEPARATOR);

    // 2. And the two checkouts now SHARE one graph. This is the silent failure, and it is
    //    the assertion that proves the two above are testing the configuration rather
    //    than passing on upstream's default keying for a root pair that happens to differ.
    expect(Storage::tempDir($a))->toBe(Storage::tempDir($b));
});

it('leaves the configuration intact for later tests in the same process', function (): void {
    // Pest runs a file's tests in source order, so this one executes AFTER the control
    // above cleared the static. `Storage::$directory` is process-global: a test that
    // left it null would silently re-point every later caller in this process at
    // $HOME — this file becoming the very bug it guards against.
    //
    // Asserted rather than trusted, because the leak is invisible outside a --tia run.
    $root = $this->fixtureRoots[0];

    expect(Storage::tempDir($root))->toBe($root.DIRECTORY_SEPARATOR.'tests/.pest/tia');
});
