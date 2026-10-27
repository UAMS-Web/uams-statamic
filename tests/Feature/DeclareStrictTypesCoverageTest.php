<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Residue guard for UAMS-Web/uams-statamic-archive#1927. UAMS-Web/uams-statamic-archive#1132 enabled Pint's `declare_strict_types` fixer and
 * gated it on *changed* files, which converts a file the moment a PR touches it
 * and leaves everything else alone. UAMS-Web/uams-statamic-archive#1927 drained the 543-file tail that scoping
 * had accumulated; this asserts the tail cannot silently regrow behind the same
 * gate, because a file no PR ever touches is a file the gate never reads.
 *
 * WHY NOT `vendor/bin/pint --test` ITSELF. That is the literal contract, and it
 * costs six minutes over 1,666 files — two orders of magnitude past what belongs
 * in the suite. The whole-repo Pint report is single-rule by construction here:
 * every failing file in the UAMS-Web/uams-statamic-archive#1927 baseline traced to `declare_strict_types`, and
 * the secondary fixers alongside it (`blank_line_after_opening_tag` and friends)
 * are downstream of inserting the declaration, not independent drift. So asserting
 * the declaration directly is the same assertion at 1/1000th the cost, and the
 * *fixer* half of the contract — that Pint really adds the declaration — is pinned
 * separately by {@see PintDeclareStrictTypesEnforcementTest}.
 *
 * WHERE THE TWO VERDICTS STILL DIFFER, measured rather than asserted (UAMS-Web/uams-statamic-archive#2314). The
 * shebang case that motivated that ticket now agrees — Pint passes such a file and
 * so does this guard. One divergence remains, and this guard is the stricter side:
 *
 *   file shape                                    pint    this guard
 *   shebang, then <?php, then declaration         pass    pass
 *   shebang, then <?php, no declaration           FAIL    fail
 *   shebang, BLANK LINE, <?php, declaration       pass    fail   <- diverges
 *
 * The third row cannot run. The blank line is inline output, so the declaration is
 * no longer the first statement and PHP refuses the file outright: `strict_types
 * declaration must be the very first statement in the script`. Pint's
 * `declare_strict_types` fixer sees a declaration present and is satisfied; it does
 * not evaluate whether the declaration is live. Rejecting that shape is therefore
 * the guard being right where the contract it approximates is not, and it is the one
 * case where "the same assertion at 1/1000th the cost" is an understatement rather
 * than an overstatement.
 *
 * WHY `git ls-files` AND NOT A DIRECTORY WALK. The gate lints tracked files, so
 * the guard must too: a walk would red on a developer's untracked scratch `.php`,
 * which no CI job would ever have flagged.
 *
 * The three exclusions below reproduce Pint's own finder, and are not guesswork —
 * applying exactly these to `git ls-files '*.php'` reproduced Pint's flagged set
 * to the file (543 of 543) while measuring UAMS-Web/uams-statamic-archive#1927.
 *
 * A pure filesystem check; it deliberately avoids booting the framework (the
 * project only binds Pest's TestCase to tests/Browser) and never writes, so it is
 * parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/DeclareStrictTypesCoverageTest.php
 */
$root = dirname(__DIR__, 2);

/**
 * Whether Pint lints this repo-relative path, mirroring its default finder.
 *
 * Symfony's Finder skips dot-directories, Pint excludes any `vendor` directory
 * (which is why the published `lang/vendor/statamic/**` translations are out of
 * scope as well as `vendor/` itself), and Blade templates are excluded by name.
 */
$isLinted = function (string $path): bool {
    if (str_ends_with($path, '.blade.php')) {
        return false;
    }

    foreach (explode('/', $path) as $segment) {
        if ($segment === 'vendor' || str_starts_with($segment, '.')) {
            return false;
        }
    }

    return true;
};

/**
 * Whether the source declares strict types as its first statement.
 *
 * Matched structurally rather than with `str_contains()`: the declaration is only
 * live in the one position PHP accepts it, and a bare substring search would count
 * this repo's own Pint fixture strings — which embed the declaration inside a
 * heredoc — as a declaring file.
 *
 * A single shebang line may precede the open tag. PHP's CLI SAPI strips it and Pint
 * lints such a file clean, so rejecting it made the guard stricter than the contract
 * it stands in for — a red on correct code, under a message whose remedy could not
 * clear it (UAMS-Web/uams-statamic-archive#2314).
 *
 * The shebang must be followed IMMEDIATELY by the open tag, and that strictness is
 * deliberate rather than an oversight. Anything between them is inline output, which
 * stops the declaration being the first statement: PHP fatals with `strict_types
 * declaration must be the very first statement in the script`. Admitting that shape
 * would have the guard pass a file that cannot run.
 */
$declaresStrictTypes = function (string $source): bool {
    return preg_match(
        '/^(?:\#![^\n]*\n)?<\?php\s+(?:(?:\/\*.*?\*\/|\/\/[^\n]*|\#[^\n]*)\s*)*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/s',
        $source,
    ) === 1;
};

it('declares strict types in every PHP file Pint lints', function () use ($root, $isLinted, $declaresStrictTypes) {
    $process = new Process(['git', 'ls-files', '-z', '*.php'], $root);
    $process->run();

    expect($process->isSuccessful())->toBeTrue(
        sprintf('git ls-files failed (exit %s): %s', var_export($process->getExitCode(), true), $process->getErrorOutput()),
    );

    $tracked = array_filter(explode("\0", $process->getOutput()), static fn (string $path): bool => $path !== '');
    $linted = array_values(array_filter($tracked, $isLinted));

    // Vacuity guard. A filter bug, a detached checkout, or a `git` that returned
    // nothing all leave an empty list, and every assertion below would then pass
    // over no files at all — the "0 Mutations for 0 Files created" failure mode.
    // The tree held 1,666 linted files when UAMS-Web/uams-statamic-archive#1927 landed; the floor is set well
    // under that so ordinary churn never trips it.
    expect(count($linted))->toBeGreaterThan(
        1_000,
        'git ls-files matched no PHP for Pint to lint — the guard would pass over an empty set.',
    );

    $missing = [];

    foreach ($linted as $path) {
        // The declaration must precede every statement, so the head of the file is
        // the whole search space. 8 KiB clears the longest leading docblock in the
        // tree by a wide margin while keeping the pass under a second.
        $head = file_get_contents($root.'/'.$path, false, null, 0, 8192);

        if ($head === false || ! $declaresStrictTypes($head)) {
            $missing[] = $path;
        }
    }

    expect($missing)->toBe([], sprintf(
        "%d of %d Pint-linted PHP files are missing declare(strict_types=1) — the #1927 tail is regrowing.\n"
            ."Run: vendor/bin/pint %s\n%s",
        count($missing),
        count($linted),
        implode(' ', array_slice($missing, 0, 5)).(count($missing) > 5 ? ' …' : ''),
        implode("\n", array_map(static fn (string $path): string => '  - '.$path, $missing)),
    ));
});

/**
 * Both directions of the shebang case, against fixtures rather than tree files.
 *
 * A tree file can be repaired out from under an assertion — UAMS-Web/uams-statamic-archive#2311 removes the shebang
 * that surfaced UAMS-Web/uams-statamic-archive#2314, so a test pointed at that path would go green for the wrong
 * reason and stay green. These strings cannot be edited by anyone fixing something else.
 */
it('accepts a declaration behind a shebang, and still rejects a shebang file without one', function () use ($declaresStrictTypes): void {
    // What UAMS-Web/uams-statamic-archive#2314 reported as a false negative.
    expect($declaresStrictTypes("#!/usr/bin/env php\n<?php\n\ndeclare(strict_types=1);\n"))->toBeTrue();

    // Arguments after the interpreter are ordinary in a shebang.
    expect($declaresStrictTypes("#!/usr/bin/php -q\n<?php\ndeclare(strict_types=1);\n"))->toBeTrue();

    // Not loosened into vacuity: a shebang file genuinely lacking the declaration is
    // still caught, which is the whole point of the guard.
    expect($declaresStrictTypes("#!/usr/bin/env php\n<?php\n\necho 1;\n"))->toBeFalse();
});

it('rejects material between the shebang and the open tag, because PHP does too', function () use ($declaresStrictTypes): void {
    // A blank line here is inline output, so the declaration is no longer the first
    // statement and PHP fatals: `strict_types declaration must be the very first
    // statement in the script`. Verified by executing that shape, not inferred.
    expect($declaresStrictTypes("#!/usr/bin/env php\n\n<?php\ndeclare(strict_types=1);\n"))->toBeFalse();

    // Only the first line may be a shebang; a second is inline output as well.
    expect($declaresStrictTypes("#!/usr/bin/env php\n#!/usr/bin/env php\n<?php\ndeclare(strict_types=1);\n"))->toBeFalse();
});

it('keeps the pre-existing behaviors the shebang change could have disturbed', function () use ($declaresStrictTypes): void {
    expect($declaresStrictTypes("<?php\n\ndeclare(strict_types=1);\n"))->toBeTrue();
    expect($declaresStrictTypes("<?php\n// note\ndeclare(strict_types=1);\n"))->toBeTrue();
    expect($declaresStrictTypes("<?php\n\necho 1;\n"))->toBeFalse();

    // The declaration must precede every statement.
    expect($declaresStrictTypes("<?php\necho 1;\ndeclare(strict_types=1);\n"))->toBeFalse();

    // The heredoc case the helper docblock names: a Pint fixture embedding the
    // declaration as data must not read as a declaring file.
    expect($declaresStrictTypes("<?php\n\$s = <<<'P'\ndeclare(strict_types=1);\nP;\n"))->toBeFalse();
});
