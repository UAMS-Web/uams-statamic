<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for `scripts/check-mutation-instrument.php` (UAMS-Web/uams-statamic-archive#2322). Drives the real CLI
 * as a subprocess and asserts each branch, so the check is live rather than vacuous.
 *
 * WHAT IT IS PROTECTING. `Pest\Mutate\MutationTest::start()` spawns each mutant in a child
 * process built from `$_SERVER['argv']`, reusing argv[0] verbatim as the executable, and
 * `hasFinished()` reads any non-zero exit as "the test failed" — so a child that could not be
 * launched at all is recorded as a KILL. PHP sets argv[0] to the script path as typed, so on
 * Windows both `vendor/bin/pest` and `php vendor/bin/pest` hand the child a forward-slashed
 * relative path cmd.exe cannot start. Measured on Windows 11 Pro 10.0.26200 / PHP 8.4.20 /
 * pest-plugin-mutate v5.0.2, one class, one tree, cache cleared between arms: the two
 * forward-slashed forms reported `20 tested, 0 untested, 100.00%` while `php vendor\bin\pest`
 * and `composer mutate` reported `14 tested, 6 untested, 70.00%`.
 *
 * THE NEGATIVE CASES ARE THE POINT, and more so here than usual: a checker for a false green
 * that itself always passes is the same defect one level up. So the fixtures assert that an
 * unguarded plugin ALONE is not a finding (every form launches → clean) and that an
 * form that cannot start is ALONE not a finding either (the guard aborts the run instead of
 * scoring it). Only the two together fabricate a score, and only that pair exits 1.
 *
 * Framework-free, per-run temp paths, stand-in runner sources rather than the real `vendor/`
 * — which is shared, and which a parallel run is reading at the same time — so it is
 * parallel-safe and pins the CLI contract rather than internals.
 *
 * @command  php artisan test --compact tests/Feature/MutationInstrumentGuardTest.php
 */
$script = dirname(__DIR__, 2).'/scripts/check-mutation-instrument.php';

$runCheck = function (?bool $pluginGuarded, string $argv0) use ($script): array {
    $dir = sys_get_temp_dir().'/mutation-instrument-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    $arguments = [PHP_BINARY, $script];

    if ($pluginGuarded !== null) {
        $runner = $dir.'/MutationTestRunner.php';
        file_put_contents($runner, $pluginGuarded
            ? '<?php private function canLaunchMutantProcess(): bool { return true; }'
            : '<?php public function run(): int { return 0; }');
        $arguments[] = '--runner='.$runner;
    } else {
        $arguments[] = '--runner='.$dir.'/absent.php';
    }

    $arguments[] = $argv0;

    $process = new Process($arguments, cwd: dirname(__DIR__, 2), timeout: 120);
    $process->run();

    $output = $process->getOutput().$process->getErrorOutput();

    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);

    return ['exit' => $process->getExitCode(), 'output' => $output];
};

$cannotStart = sys_get_temp_dir().'/no-such-pest-binary-'.bin2hex(random_bytes(4));

it('flags fabrication when a form cannot launch and the plugin carries no guard', function () use ($runCheck, $cannotStart): void {
    $result = $runCheck(false, $cannotStart);

    // Both conditions present: this is the state that scores every mutant killed and exits 0.
    expect($result['exit'])->toBe(1);
    expect($result['output'])->toContain('FABRICATION LIKELY');
});

it('does not flag a form that cannot start when the plugin aborts on it', function () use ($runCheck, $cannotStart): void {
    $result = $runCheck(true, $cannotStart);

    // The guard turns that same form into a loud abort rather than a fake score,
    // so the run is honest and the check must say so.
    expect($result['exit'])->toBe(0);
    expect($result['output'])->not->toContain('FABRICATION LIKELY');
});

it('does not flag an unguarded plugin when every form launches', function () use ($runCheck): void {
    $result = $runCheck(false, PHP_BINARY);

    // The discriminating case. Without this the check could "pass" by reporting every
    // unpatched checkout as broken, which is exactly as uninformative as never firing.
    expect($result['exit'])->toBe(0);
    expect($result['output'])->toContain('launches');
});

it('reports that nothing was measured when the plugin cannot be located', function () use ($runCheck): void {
    $result = $runCheck(null, PHP_BINARY);

    // An absent instrument must not read as a clean one — the whole subject of this ticket.
    expect($result['exit'])->toBe(2);
});
