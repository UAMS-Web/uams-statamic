<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Assert;
use Statamic\Assets\AssetContainer as AssetContainerModel;
use Statamic\Facades\AssetContainer;
use Tests\Support\FormsSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#899, UAMS-Web/uams-statamic-archive#1621, UAMS-Web/uams-statamic-archive#2817 — Statamic git-automation defaults (config/statamic/git.php).
 *
 * No mutable App\ PHP surface under test: this asserts package configuration
 * defaults and the git.php ↔ filesystems.php path reconciliation only, so
 * there is no class to target for mutation testing. The disk-root coverage
 * test below is verified by TDD red/green against the config instead.
 */

/**
 * The deployed `.env` (copied from `.env.example`, which sets
 * `STATAMIC_GIT_AUTOMATIC=false`) would make `config('statamic.git.automatic')`
 * read `false` from the environment even if the config file's own default
 * regressed to `true`. To actually pin the *file* default, evaluate the config
 * file with that env var removed so `env(..., $default)` falls back to it.
 */
it('defaults git auto-commit off in the config file, independent of any env override', function (): void {
    $key = 'STATAMIC_GIT_AUTOMATIC';
    $previous = getenv($key);
    $hadEnv = array_key_exists($key, $_ENV);
    $hadServer = array_key_exists($key, $_SERVER);

    putenv($key);
    unset($_ENV[$key], $_SERVER[$key]);

    try {
        /** @var array{automatic: mixed} $git */
        $git = require config_path('statamic/git.php');

        expect($git['automatic'])->toBeFalse();
    } finally {
        if ($previous !== false) {
            putenv("{$key}={$previous}");
        }
        if ($hadEnv) {
            $_ENV[$key] = $previous;
        }
        if ($hadServer) {
            $_SERVER[$key] = $previous;
        }
    }
});

it('uses a real UAMS fallback committer identity, not the Spock placeholder', function (): void {
    // No env var overrides these keys (absent from .env / .env.example), so the
    // resolved config value is the file default this ticket sets.
    expect(config('statamic.git.user.name'))
        ->toBe('UAMS Health Web')
        ->and(config('statamic.git.user.name'))->not->toBe('Spock');

    expect(config('statamic.git.user.email'))
        ->toBe('no-reply@uams.edu')
        ->and(config('statamic.git.user.email'))->not->toBe('spock@example.com');
});

/**
 * UAMS-Web/uams-statamic-archive#1621 — Every asset container's on-disk root must sit inside one of the two
 * asset submodules, and that submodule root must be a tracked git-automation
 * path — otherwise an editor's upload to that container is never staged,
 * committed, or pushed (it exists only on the one server, absent from every
 * other environment and from the git history that backs up assets).
 *
 * Checking against the *intended repo* (not merely "some tracked ancestor")
 * satisfies the acceptance criterion's "resolves into its intended repo"
 * clause: a too-broad tracked path such as `base_path('app')` would commit a
 * private upload into the app repo rather than the private assets submodule,
 * and this test rejects that. The invariant runs against the *real* containers
 * (from `content/assets/*.yaml`), so a newly added container rooted outside a
 * tracked asset submodule cannot silently go untracked.
 */
it('commits every asset container upload into the tracked submodule that owns its disk root', function (): void {
    // Normalize the tracked git-automation paths to a trailing-slash-free list.
    $trackedPaths = [];
    foreach (config()->array('statamic.git.paths') as $trackedPath) {
        if (is_string($trackedPath)) {
            $trackedPaths[] = rtrim($trackedPath, '/');
        }
    }

    // The two asset submodules that own every container's disk root. An upload is
    // committed only if its disk root sits inside one of these AND git automation
    // tracks that submodule root — so assert both roots are tracked up front.
    $assetRepoRoots = [
        rtrim(public_path('assets'), '/'),           // uams-statamic-assets-public
        rtrim(base_path('app/private/assets'), '/'), // uams-statamic-assets-private
    ];
    foreach ($assetRepoRoots as $assetRepoRoot) {
        expect(in_array($assetRepoRoot, $trackedPaths, true))->toBeTrue(
            "config/statamic/git.php does not track the asset submodule root [{$assetRepoRoot}], so uploads there are never committed."
        );
    }

    $containers = AssetContainer::all();

    // Guard against a vacuous pass: the container configs live in the `content`
    // submodule, so an empty collection would trivially satisfy the loop below
    // and hide a real regression.
    expect($containers)->not->toBeEmpty();

    $checkedContainers = 0;
    foreach ($containers as $container) {
        // `diskHandle()` lives on the concrete container, not the contract.
        if (! $container instanceof AssetContainerModel) {
            continue;
        }

        $diskHandle = $container->diskHandle();
        if (! is_string($diskHandle)) {
            continue;
        }
        $checkedContainers++;

        $root = config()->string("filesystems.disks.{$diskHandle}.root");

        // The root must resolve into one of the two asset submodules (the root
        // itself or a descendant), not merely under any tracked path.
        $ownedByAssetRepo = false;
        foreach ($assetRepoRoots as $assetRepoRoot) {
            if ($root === $assetRepoRoot || str_starts_with($root, $assetRepoRoot.'/')) {
                $ownedByAssetRepo = true;
                break;
            }
        }

        expect($ownedByAssetRepo)->toBeTrue(
            "Asset container disk [{$diskHandle}] root [{$root}] is not inside a tracked asset submodule, so uploads to it would never be committed."
        );
    }

    // Every container must be checked — the instanceof/is_string filters above
    // must not silently drop any container out of the assertion.
    expect($checkedContainers)->toBe($containers->count());
});

/**
 * UAMS-Web/uams-statamic-archive#2817 — Nothing under `storage/` is committed by Git Automation. The directory
 * holds runtime state, and `storage/forms` in particular holds stored form
 * submissions: what people typed into a form. A tracked path anywhere under it
 * would add that to this repository's history on the first automatic commit.
 */
it('tracks no path under storage in git automation', function (): void {
    $storageRoot = rtrim(storage_path(), '/');
    $trackedPaths = config()->array('statamic.git.paths');

    // Guard against a vacuous pass over an empty list
    expect($trackedPaths)->not->toBeEmpty();

    foreach ($trackedPaths as $trackedPath) {
        // Require a string path, so no entry is skipped unchecked
        Assert::assertIsString($trackedPath);

        $trackedPath = rtrim($trackedPath, '/');

        expect($trackedPath === $storageRoot || str_starts_with($trackedPath, $storageRoot.'/'))->toBeFalse(
            "config/statamic/git.php tracks [{$trackedPath}], which is under storage/."
        );
    }
});

/**
 * UAMS-Web/uams-statamic-archive#2817 — A stored submission for every form in `resources/forms` is ignored by
 * this repository, so neither Git Automation nor a hand-run `git add` can stage
 * one. Read from git's EXIT STATUS with `--no-index`, so the answer depends on
 * the ignore rules alone and not on whether such a file exists or is tracked.
 *
 * A form definition beside it must NOT be ignored: that is the control showing
 * the check can answer both ways, rather than reporting every path as ignored.
 *
 * The forms are read from the private `resources/forms` submodule (UAMS-Web/uams-statamic-archive#2818), so the
 * case skips in a checkout without it.
 */
it('ignores a stored submission for every form', function (): void {
    FormsSubmodule::skipUnlessInitialized();

    // Forward slashes on every platform: base_path() uses backslashes on Windows.
    $submissions = rtrim(str_replace('\\', '/', config()->string('statamic.forms.submissions')), '/');
    $base = rtrim(str_replace('\\', '/', base_path()), '/');

    // The submissions directory must sit inside this repository for its ignore
    // rules to be the ones that apply
    expect(str_starts_with($submissions, $base.'/'))->toBeTrue();
    $relativeSubmissions = substr($submissions, strlen($base) + 1);

    $isIgnored = fn (string $relativePath): bool => Process::path($base)
        ->run(['git', 'check-ignore', '--no-index', '-q', $relativePath])
        ->exitCode() === 0;

    $forms = glob(resource_path('forms/*.yaml')) ?: [];

    // Guard against a vacuous pass over no forms
    expect($forms)->not->toBeEmpty();

    foreach ($forms as $form) {
        $handle = basename($form, '.yaml');

        // Check the control: the form's own definition is committable
        expect($isIgnored("resources/forms/{$handle}.yaml"))->toBeFalse(
            "resources/forms/{$handle}.yaml is ignored, so this check cannot tell ignored from not."
        );

        // Check a stored submission for the form is ignored
        $submission = "{$relativeSubmissions}/{$handle}/1700000000.0000.yaml";

        expect($isIgnored($submission))->toBeTrue("[{$submission}] is not ignored by git.");
    }
});
