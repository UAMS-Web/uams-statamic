<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

// Kept in the `serial` group: each case spawns `vendor/bin/pint` and does temp-file
// I/O, which gains nothing from paratest's parallelism and only piles subprocess
// load onto every worker — so it belongs in the single-process tail, not the
// parallel pass.
uses()->group('serial');

/**
 * Enforcement guard for UAMS-Web/uams-statamic-archive#1132. `pint.json` enables the `declare_strict_types`
 * fixer, and CI runs Pint on *changed* files, so every touched `.php` file must
 * carry `declare(strict_types=1)`. This proves the rule is live by asserting on
 * Pint's *behavior*, not on its report: run Pint over a throwaway file lacking
 * the declaration and confirm Pint adds it; run it over a file that already
 * declares and confirm exactly one declaration survives.
 *
 * Why behavior and not `--test --format=json`: under full-suite load, `pint
 * --format=json` intermittently emits the bundled PHP-CS-Fixer's native JSON
 * schema (`{"about":…,"files":[{"name":…}]}`) instead of Pint's own
 * (`{"tool":"pint","result":…,"files":[{"path","fixers":[…]}]}`). Both are valid
 * and agree on pass/fail, but the shapes differ, so any assertion on
 * `result`/`fixers` is environment-sensitive (it flaked the serial tail after the
 * full parallel pass). Asserting on the transformed file is schema-independent and
 * a stronger proof of the contract — the fixer actually runs. Fix mode exits 0
 * whether or not it changed anything, so a non-zero exit means Pint itself failed;
 * that is surfaced explicitly so it is never mistaken for a disabled fixer.
 *
 * Samples are written to an isolated temp dir and never committed — a
 * declaration-less file committed to the repo would itself fail the changed-files
 * Pint gate — and fixed with the project `pint.json` via `--config`.
 *
 * A pure subprocess check; it deliberately avoids booting the framework (the
 * project only binds Pest's TestCase to tests/Browser).
 */
$root = dirname(__DIR__, 2);
$config = $root.'/pint.json';

/**
 * Fix one PHP source string in place with the project Pint config (no `--test`)
 * and return the resulting file contents.
 */
$fix = function (string $php) use ($root, $config): string {
    $dir = sys_get_temp_dir().'/pint-strict-'.bin2hex(random_bytes(6));
    mkdir($dir);
    $file = $dir.'/Sample.php';
    file_put_contents($file, $php);

    try {
        $process = new Process(
            [PHP_BINARY, 'vendor/bin/pint', '--config', $config, $file],
            $root,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                "Pint fix run failed (exit %s).\nStdout: %s\nStderr: %s",
                var_export($process->getExitCode(), true),
                trim($process->getOutput()) ?: '(empty)',
                trim($process->getErrorOutput()) ?: '(empty)',
            ));
        }

        /** @var string $contents */
        $contents = file_get_contents($file);

        return $contents;
    } finally {
        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);
    }
};

// An otherwise Pint-clean class; the `declare` line is the only thing that varies
// between the two cases, so the fixer's effect on it alone decides the outcome.
$body = "namespace App\\PintStrictSample;\n\nclass Sample\n{\n    public function value(): int\n    {\n        return 1;\n    }\n}\n";

it('adds declare(strict_types=1) to a new file that lacks it', function () use ($fix, $body) {
    $fixed = $fix("<?php\n\n".$body);

    expect($fixed)->toContain('declare(strict_types=1);');
});

it('leaves a file that already declares strict types with exactly one declaration', function () use ($fix, $body) {
    $fixed = $fix("<?php\n\ndeclare(strict_types=1);\n\n".$body);

    expect($fixed)->toContain('declare(strict_types=1);');
    expect(substr_count($fixed, 'declare(strict_types=1)'))->toBe(1);
});
