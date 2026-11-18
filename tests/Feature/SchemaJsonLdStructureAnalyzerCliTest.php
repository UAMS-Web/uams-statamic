<?php

declare(strict_types=1);

use App\Services\EnumGeneration\Schema\SchemaJsonLdStructureAnalyzer;
use Symfony\Component\Process\Process;

/**
 * Subprocess coverage for the analyzer's command line.
 *
 * The class file has no load-time code (UAMS-Web/uams-statamic-archive#1008):
 * autoloading or including it, as Rector, PHPStan and the test suite do, or
 * even executing it directly, only defines the class. The command line is
 * `scripts/analyze-schema-jsonld.php`, which calls `runCli()` with the class's
 * directory (UAMS-Web/uams-statamic#894).
 *
 * The script is run only on paths that write nothing: a successful run writes
 * the committed reports in the class's directory, so the success paths are
 * covered in-process by `SchemaJsonLdStructureAnalyzerTest` instead.
 *
 * These are pure subprocess checks, so they deliberately avoid booting the
 * framework (the project only binds Pest's TestCase to tests/Browser).
 */
$analyzerPath = dirname(__DIR__, 2).'/app/Services/EnumGeneration/Schema/SchemaJsonLdStructureAnalyzer.php';
$scriptPath = dirname(__DIR__, 2).'/scripts/analyze-schema-jsonld.php';

it('defines the class and runs nothing when the file is included', function () use ($analyzerPath) {
    // Reproduce how Rector/PHPStan trip the file: include it while the host
    // process runs some other command, so argv[1] is e.g. "process". Before
    // UAMS-Web/uams-statamic-archive#1008 the file read that as a path, wrote to
    // STDERR, and exit(1)'d, aborting the host.
    $script = sprintf(
        '$_SERVER["argv"] = ["some-tool", "process"]; require %s; echo class_exists(%s) ? "REACHED" : "NO_CLASS";',
        var_export($analyzerPath, true),
        var_export(SchemaJsonLdStructureAnalyzer::class, true),
    );

    $process = new Process([PHP_BINARY, '-r', $script]);
    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toBe('REACHED')
        ->and($process->getErrorOutput())->toBe('');
});

it('runs nothing when the class file is executed directly', function () use ($analyzerPath) {
    $process = new Process([PHP_BINARY, $analyzerPath, 'unused.jsonld']);
    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toBe('')
        ->and($process->getErrorOutput())->toBe('');
});

it('exits 1 from the script, naming the path, when the input file does not exist', function () use ($scriptPath) {
    $missing = sys_get_temp_dir().'/jsonld-analyzer-missing-'.bin2hex(random_bytes(6)).'.jsonld';

    $process = new Process([PHP_BINARY, $scriptPath, $missing]);
    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toBe('')
        ->and($process->getErrorOutput())->toBe('Error: File not found or not readable: '.$missing.PHP_EOL);
});

it('exits 1 from the script with the parser message when the input is not valid JSON', function () use ($scriptPath) {
    $input = sys_get_temp_dir().'/jsonld-analyzer-invalid-'.bin2hex(random_bytes(6)).'.jsonld';
    file_put_contents($input, '{');

    try {
        $process = new Process([PHP_BINARY, $scriptPath, $input]);
        $process->run();

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toBe("Error: Invalid JSON: Syntax error\n");
    } finally {
        unlink($input);
    }
});
