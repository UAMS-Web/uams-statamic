<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * `scripts/check-one-class-per-file.php`, the gate's one-class-per-file check (#555).
 *
 * Each case writes PHP files under a per-run `sys_get_temp_dir()` path and runs the
 * script on them as a subprocess, the way the `composer-validate` job and the local
 * runner's `validate` job run it on the tree. A pure subprocess check with no
 * framework boot, so it is parallel-safe by the isolation guard's own rules.
 *
 * @command  php artisan test --compact tests/Feature/OneClassPerFileCheckTest.php
 */
$root = dirname(__DIR__, 2);

/**
 * Write each fixture, passed as `filename: body after the open tag`, and return their paths.
 *
 * The parameters are typed variadics because PHPStan reads no docblock on a closure
 * assigned to a variable, so an `array` parameter would arrive as `array<mixed>`.
 */
$writeClassFixtures = function (string ...$files): array {
    $dir = sys_get_temp_dir().'/one-class-per-file-'.bin2hex(random_bytes(6));
    mkdir($dir);
    $paths = [];

    foreach ($files as $name => $body) {
        $paths[] = $dir.'/'.$name;
        file_put_contents($dir.'/'.$name, "<?php\n\n".$body);
    }

    return ['dir' => $dir, 'paths' => $paths];
};

/**
 * Run the check on these paths, or on the tree when there are none.
 */
$runClassCheck = function (string ...$paths) use ($root): Process {
    $process = new Process([PHP_BINARY, 'scripts/check-one-class-per-file.php', ...$paths], $root);
    $process->run();

    return $process;
};

$cleanupClassFixtures = function (string $dir): void {
    foreach (glob($dir.'/*') ?: [] as $item) {
        @unlink($item);
    }
    @rmdir($dir);
};

it('fails on a file that declares a second type, naming the file and the extra type', function (string $body, string $reported) use ($writeClassFixtures, $runClassCheck, $cleanupClassFixtures): void {
    $fixtures = $writeClassFixtures(...['Enterprise.php' => $body]);

    try {
        $process = $runClassCheck(...$fixtures['paths']);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getOutput())->toContain('Enterprise.php: declares '.$reported)
            ->and($process->getOutput())->toContain('1 file(s) declare more than one');
    } finally {
        $cleanupClassFixtures($fixtures['dir']);
    }
})->with([
    'a second class' => ["class Kirk\n{\n}\n\nclass Spock\n{\n}\n", 'class Kirk, and also class Spock (line 7)'],
    'an attribute class beside an enum' => ["enum Rank: string\n{\n    case Captain = 'captain';\n}\n\nfinal class RankInfo\n{\n}\n", 'enum Rank, and also class RankInfo (line 8)'],
    'an interface and a trait beside a class' => ["interface Helm\n{\n}\n\ntrait Warp\n{\n}\n\nclass Sulu\n{\n}\n", 'interface Helm, and also trait Warp (line 7), class Sulu (line 11)'],
    'a comment between the keyword and the name' => ["class Uhura\n{\n}\n\nclass /* comms */ Chekov\n{\n}\n", 'class Uhura, and also class Chekov (line 7)'],
]);

it('passes a file with one type, however often the keyword appears elsewhere', function () use ($writeClassFixtures, $runClassCheck, $cleanupClassFixtures): void {
    $body = <<<'PHP'
        // class McCoy is not a declaration in a comment
        /** A docblock that names class Scotty and enum Rank. */
        final class Sickbay
        {
            public function patients(): array
            {
                $visitor = new class
                {
                };

                return [self::class, $visitor::class, 'trait Chapel', 'interface Rand'];
            }
        }
        PHP;

    $fixtures = $writeClassFixtures(...['Sickbay.php' => $body, 'Empty.php' => "return [];\n"]);

    try {
        $process = $runClassCheck(...$fixtures['paths']);

        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toContain('scanning 2 PHP files')
            ->and($process->getOutput())->toContain('every file declares at most one');
    } finally {
        $cleanupClassFixtures($fixtures['dir']);
    }
});

it('reports every offending file, not only the first', function () use ($writeClassFixtures, $runClassCheck, $cleanupClassFixtures): void {
    $fixtures = $writeClassFixtures(...[
        'Bridge.php' => "class Bridge\n{\n}\n\nclass Transporter\n{\n}\n",
        'Clean.php' => "class Clean\n{\n}\n",
        'Engineering.php' => "trait Engineering\n{\n}\n\nclass Shuttle\n{\n}\n",
    ]);

    try {
        $process = $runClassCheck(...$fixtures['paths']);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getOutput())->toContain('Bridge.php: declares class Bridge, and also class Transporter')
            ->and($process->getOutput())->toContain('Engineering.php: declares trait Engineering, and also class Shuttle')
            ->and($process->getOutput())->not->toContain('Clean.php')
            ->and($process->getOutput())->toContain('2 file(s) declare more than one');
    } finally {
        $cleanupClassFixtures($fixtures['dir']);
    }
});

it('passes on the tree as committed', function () use ($runClassCheck): void {
    $process = $runClassCheck();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('control reported; scanning')
        ->and($process->getOutput())->toContain('every file declares at most one');
});
