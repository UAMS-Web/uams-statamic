<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Guard for UAMS-Web/uams-statamic-archive#2589. `gen_release_notes.py`'s `fix_acro()` applied its acronym casing across the
 * whole title, so it rewrote code spans and names joined by `-`, `/` or `.`. Cutting `v0.31.0`
 * turned four bullets into text their pull requests do not carry, one of them a path that does
 * not exist, and a direct call renamed `phpstan analyse` to `Larastan analyse`. The notes were
 * repaired by hand; these cases stop the generator doing it again.
 *
 * Narrowed by UAMS-Web/uams-statamic-archive#2850. The shared `writing-release-notes` skill now owns the generator, and its
 * `AcronymCasingTest` (run by the `encoding` gate job) covers spans left byte-identical, prose
 * still cased, a title mixing both, and joined names. So the two cases that drove `fix_acro()`
 * directly were retired here. What stays runs real titles through `clean_title()`, the call
 * the generator makes for every bullet: the four `v0.31.0` titles, and the span-plus-prose title
 * that renamed `phpstan analyse`. The second is the one that can see span corruption, since
 * every span in the four is also a joined name; measured on UAMS-Web/uams-statamic-archive#2850, casing span text, or a
 * `clean_title()` that skips `fix_acro()`, leaves the four green and turns it red.
 *
 * A pure subprocess check: it imports the generator and calls `clean_title()`
 * directly, so it boots no framework and makes no network call.
 *
 * @command  php artisan test --compact tests/Feature/ReleaseNotesCodeSpanCasingTest.php
 */
$script = dirname(__DIR__, 2).'/.claude/skills/writing-release-notes/gen_release_notes.py';

$resolvePython = static function (): string {
    foreach (['python3', 'python'] as $candidate) {
        $probe = new Process([$candidate, '-c', 'import sys; print(sys.version_info[0])']);
        $probe->run();

        if ($probe->isSuccessful() && str_starts_with(trim($probe->getOutput()), '3')) {
            return $candidate;
        }
    }

    return '';
};

$python = $resolvePython();

/**
 * Run one generator function over each title in a subprocess and return the results in order.
 *
 * @param  list<string>  $titles
 * @return list<string>
 */
$apply = static function (string $python, string $script, string $function, array $titles): array {
    $driver = <<<'PY'
import importlib.util, json, sys
spec = importlib.util.spec_from_file_location("gen", sys.argv[1])
gen = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gen)
fn = getattr(gen, sys.argv[2])
sys.stdout.write(json.dumps([fn(t) for t in json.loads(sys.argv[3])]))
PY;

    // No bytecode beside the generator; ReleaseNotesBucketRoutingTest guards that (UAMS-Web/uams-statamic-archive#2527).
    $process = new Process(
        [$python, '-X', 'pycache_prefix=', '-c', $driver, $script, $function, json_encode($titles, JSON_THROW_ON_ERROR)],
        null,
        ['PYTHONDONTWRITEBYTECODE' => '1', 'PYTHONIOENCODING' => 'utf-8']
    );
    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException($function.'() driver failed: '.$process->getErrorOutput());
    }

    /** @var list<string> $results */
    $results = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    return $results;
};

it('round-trips the four v0.31.0 titles the generator corrupted', function () use ($python, $script, $apply): void {
    if ($python === '') {
        $this->markTestSkipped('No Python 3 on PATH.');
    }

    // Verbatim pull-request titles: UAMS-Web/uams-statamic-archive#2571, UAMS-Web/uams-statamic-archive#2515, UAMS-Web/uams-statamic-archive#2488 and UAMS-Web/uams-statamic-archive#2473. The last carries
    // `wordpress-importer` unquoted, so only the joined-name guard protects it.
    $titles = [
        "Keep each test process's Comb search indexes out of the shared `storage/statamic/search`",
        'Document the `PUSH` event and the `handed-back` label in the `local-ci` skill',
        'Single-source the `laravel-best-practices` skill across both harnesses',
        'Bump the wordpress-importer constraint to ^0.19 and update the lock',
    ];

    expect($apply($python, $script, 'clean_title', $titles))->toBe($titles);
});

it('keeps a span verbatim while casing the same word in the prose around it', function () use ($python, $script, $apply): void {
    if ($python === '') {
        $this->markTestSkipped('No Python 3 on PATH.');
    }

    expect($apply($python, $script, 'clean_title', ['Run `phpstan analyse` so phpstan stops failing']))
        ->toBe(['Run `phpstan analyse` so PHPStan stops failing']);
});
