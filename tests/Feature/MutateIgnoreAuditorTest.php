<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for `scripts/check-mutate-ignore.php` (UAMS-Web/uams-statamic-archive#2304). Runs the auditor
 * against throwaway fixtures and asserts it flags each inert `@pest-mutate-ignore`
 * form — proving the check is live rather than vacuous.
 *
 * WHAT IT IS PROTECTING. `Pest\Mutate\Support\NodeVisitor` parses the marker with
 * `preg_match('/@pest-mutate-ignore(.*)/', ...)`. `.` does not cross a newline, so the
 * tail is the rest of that line; it is exploded on commas, trimmed, and compared to
 * MUTATOR NAMES. An annotation carrying prose therefore suppresses nothing, and says
 * so in no way at all — the mutant keeps surviving, which reads as a wrong reason
 * rather than as an annotation that never applied. Measured on pest-plugin-mutate
 * v5.0.2 against a four-mutant probe: `: GreaterToGreaterOrEqual because larastan
 * needs it` left 4 of 4 alive, while the same line with a comma after the name left 3.
 *
 * The three inert shapes are covered here because the auditor is only as good as its
 * weakest one, and the trailing-space case is the least obvious: it inverts meaning
 * rather than merely failing, since the same line without the space suppresses EVERY
 * mutator on the node. That case is CONDITIONAL — UAMS-Web/uams-statamic-archive#2327 patches the plugin to trim the
 * captured tail before testing it for empty, which makes the two forms identical — so it
 * is asserted in both directions against a stand-in plugin source rather than against
 * whichever state this checkout's `vendor/` happens to be in.
 *
 * THE NEGATIVE CASES ARE THE POINT. An auditor that flags everything is worse than
 * none — an earlier revision of this script matched any line containing the marker
 * and reported 22 findings, all of them docblock prose *mentioning* it and none an
 * annotation. So the fixtures assert non-detection too: a valid annotation and a
 * sentence that merely names the marker must both pass.
 *
 * Driven as a subprocess like {@see TiaSafetyGuardTest}, pinning the real CLI
 * contract — exit code and printed text — rather than internals. Framework-free,
 * per-run temp paths, so it is parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/MutateIgnoreAuditorTest.php
 */
$script = dirname(__DIR__, 2).'/scripts/check-mutate-ignore.php';

$audit = function (string $body, ?bool $pluginPatched = null, ?bool $lineMapLive = null) use ($script): array {
    $dir = sys_get_temp_dir().'/mutate-ignore-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/Fixture.php', $body);

    // A stand-in for the vendored plugin source, so a run can be pinned to either state
    // without touching the real `vendor/` — which is shared, and which a parallel run is
    // reading at the same time. Null leaves the auditor on its own default.
    $env = [];

    if ($pluginPatched !== null) {
        $visitor = $dir.'/NodeVisitor.php';
        file_put_contents($visitor, $pluginPatched
            ? '<?php $mutatorsToIgnore = trim($matches[1], " ");'
            : '<?php if ($matches[1] === \'\') {}');
        $env['MUTATE_IGNORE_VISITOR_PATH'] = $visitor;
    }

    // A stand-in `MutationGenerator` plus an EOL override, so the line-map branch can be
    // pinned in either state from either platform. Without this the Windows arm cannot
    // be exercised on macOS and vice versa, which is the gap that let the false positive
    // ship in the first place.
    if ($lineMapLive !== null) {
        $generator = $dir.'/MutationGenerator.php';
        file_put_contents($generator, '<?php foreach (explode(PHP_EOL, $contents) as $n => $line) {}');
        $env['MUTATE_IGNORE_GENERATOR_PATH'] = $generator;
        $env['MUTATE_IGNORE_EOL'] = $lineMapLive ? '\n' : '\r\n';
    }

    $process = new Process([PHP_BINARY, $script, $dir], null, $env ?: null);
    $process->run();

    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);

    return [$process->getExitCode(), $process->getOutput()];
};

it('flags a tail carrying prose, which suppresses nothing', function () use ($audit) {
    [$exit, $output] = $audit(<<<'FIXTURE'
        <?php
        class Fixture
        {
            // @pest-mutate-ignore: GreaterToGreaterOrEqual because larastan needs it
            public function a(int $n): bool
            {
                return $n > 10;
            }
        }
        FIXTURE);

    expect($exit)->toBe(1);
    expect($output)->toContain('not a mutator list');
});

$trailingFixture = <<<'FIXTURE'
    <?php
    class Fixture
    {
        public function a(int $n): bool
        {
            return $n > 10; // @pest-mutate-ignore
        }
    }
    FIXTURE;

it('flags an annotation that does not begin its line where the line map is dead', function () use ($audit, $trailingFixture) {
    [$exit, $output] = $audit($trailingFixture, lineMapLive: false);

    expect($exit)->toBe(1);
    expect($output)->toContain('does not begin its line');
});

it('still flags a bogus mutator name on a trailing annotation where the map is live', function () use ($audit) {
    // UAMS-Web/uams-statamic-archive#2351 review finding. Making the position check conditional made trailing annotations
    // legitimate where the map is live — and the `continue` after it meant their TAIL was
    // never validated. `: NotAMutatorAtAll` suppresses nothing whatever the map does, so the
    // auditor passing it in silence is a false negative in the tool whose whole job is
    // finding inert annotations. Nothing else would notice this.
    [$exit, $output] = $audit(<<<'FIXTURE'
        <?php
        class Fixture
        {
            public function a(int $n): int
            {
                return $n + 1; // @pest-mutate-ignore: NotAMutatorAtAll
            }
        }
        FIXTURE, lineMapLive: true);

    expect($exit)->toBe(1);
    expect($output)->toContain('not a mutator list');
    expect($output)->not->toContain('does not begin its line');
});

it('stays silent on that same annotation where the line map is live', function () use ($audit, $trailingFixture) {
    // UAMS-Web/uams-statamic-archive#2332: the plugin honors a trailing annotation through `mutatorsToIgnoreByLine`, so
    // reporting it there is a false positive. The map dies only when `MutationGenerator`'s
    // `explode(PHP_EOL, ...)` split meets LF files on a CRLF runtime — which is why this
    // arm and the one above are both reachable from either platform.
    [$exit, $output] = $audit($trailingFixture, lineMapLive: true);

    expect($output)->not->toContain('does not begin its line');
    expect($exit)->toBe(0);
});

$trailingSpaceFixture = "<?php\nclass Fixture\n{\n    // @pest-mutate-ignore \n    public function a(int \$n): bool\n    {\n        return \$n > 10;\n    }\n}\n";

it('flags a bare marker with trailing whitespace where the plugin still inverts it', function () use ($audit, $trailingSpaceFixture) {
    [$exit, $output] = $audit($trailingSpaceFixture, pluginPatched: false);

    expect($exit)->toBe(1);
    expect($output)->toContain('trailing whitespace');
});

it('stays silent on that same marker once the plugin trims the tail first', function () use ($audit, $trailingSpaceFixture) {
    // The inversion this reports is removed by UAMS-Web/uams-statamic-archive#2327, which trims the captured tail before
    // testing it for empty — so the marker suppresses everything and a finding here would
    // be a false positive. Asserted against a stand-in rather than the checkout's own
    // `vendor/`, so BOTH states are covered wherever this runs, and so a detection that
    // wrongly reported every tree as patched could not pass the arm above.
    [$exit, $output] = $audit($trailingSpaceFixture, pluginPatched: true);

    expect($exit)->toBe(0);
    expect($output)->not->toContain('trailing whitespace');
});

it('passes a valid annotation and prose that merely names the marker', function () use ($audit) {
    [$exit, $output] = $audit(<<<'FIXTURE'
        <?php
        class Fixture
        {
            /**
             * Left clean rather than annotated with `@pest-mutate-ignore`, which is prose
             * about the marker and must not be reported as an inert annotation.
             */
            // @pest-mutate-ignore: IncrementInteger
            public function a(int $n): bool
            {
                return $n > 10;
            }
        }
        FIXTURE);

    expect($exit)->toBe(0);
    expect($output)->toContain('no inert annotations found');
});

it('reads each file passed as a path against its own lines, not the first file\'s', function () use ($script) {
    // Two files whose annotation sits on the same line number. Line 6 of the first starts
    // with the marker; line 6 of the second trails a statement, which a dead line map makes
    // inert. Until UAMS-Web/uams-statamic-archive#3256 the split lines were cached across files, so the second annotation
    // was checked against the first file's line 6 and passed.
    $dir = sys_get_temp_dir().'/mutate-ignore-two-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/First.php', "<?php\n\nfunction first(int \$n): bool\n{\n    // the next line is a standalone annotation\n    // @pest-mutate-ignore\n    return \$n > 10;\n}\n");
    file_put_contents($dir.'/Second.php', "<?php\n\nfunction second(int \$n): bool\n{\n    \$m = \$n;\n    return \$m > 10; // @pest-mutate-ignore\n}\n");
    file_put_contents($dir.'/MutationGenerator.php', '<?php foreach (explode(PHP_EOL, $contents) as $n => $line) {}');

    $process = new Process([PHP_BINARY, $script, $dir.'/First.php', $dir.'/Second.php'], null, [
        'MUTATE_IGNORE_GENERATOR_PATH' => $dir.'/MutationGenerator.php',
        'MUTATE_IGNORE_EOL' => '\r\n',
    ]);
    $process->run();

    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('Second.php:6')
        ->and($process->getOutput())->not->toContain('First.php:6');
});

it('refuses a path that is neither a file nor a directory', function () use ($script) {
    $process = new Process([PHP_BINARY, $script, sys_get_temp_dir().'/mutate-ignore-missing-'.bin2hex(random_bytes(6))]);
    $process->run();

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('not a file or directory');
});
