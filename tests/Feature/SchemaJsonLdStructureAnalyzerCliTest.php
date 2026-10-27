<?php

declare(strict_types=1);

use App\Services\EnumGeneration\Schema\SchemaJsonLdStructureAnalyzer;
use Symfony\Component\Process\Process;

/**
 * Regression coverage for UAMS-Web/uams-statamic-archive#1008. `SchemaJsonLdStructureAnalyzer.php` is a
 * standalone CLI script that also defines a class. Its bootstrap must run only
 * when the file is executed directly — never when it is autoloaded or `include`d
 * by another tool (Rector, PHPStan, the test suite), where running it would
 * `exit()` inside the host process.
 *
 * These are pure subprocess checks, so they deliberately avoid booting the
 * framework (the project only binds Pest's TestCase to tests/Browser).
 */
$analyzerPath = dirname(__DIR__, 2).'/app/Services/EnumGeneration/Schema/SchemaJsonLdStructureAnalyzer.php';

it('does not execute its bootstrap (or exit) when the file is included', function () use ($analyzerPath) {
    // Reproduce how Rector/PHPStan trip the file: include it while the host
    // process runs some other command, so argv[1] is e.g. "process". Before the
    // guard the closure read that as a path, wrote to STDERR, and exit(1)'d —
    // aborting the host. The class must still end up defined.
    $script = sprintf(
        '$_SERVER["argv"] = ["some-tool", "process"]; require %s; echo class_exists(%s) ? "REACHED" : "NO_CLASS";',
        var_export($analyzerPath, true),
        var_export(SchemaJsonLdStructureAnalyzer::class, true),
    );

    $process = new Process([PHP_BINARY, '-r', $script]);
    $process->run();

    expect($process->getExitCode())->toBe(0);
    expect($process->getOutput())->toBe('REACHED');
    expect($process->getErrorOutput())->toBe('');
});

it('still runs the analysis and writes a report when executed directly', function () use ($analyzerPath) {
    // Copy the script into an isolated temp dir so the reports it writes
    // (alongside itself) land there, not in the source tree. The class is defined
    // in the same file, so the copy runs self-contained.
    $dir = sys_get_temp_dir().'/jsonld-analyzer-'.bin2hex(random_bytes(6));
    mkdir($dir);
    copy($analyzerPath, $dir.'/Analyzer.php');
    file_put_contents($dir.'/sample.jsonld', json_encode([
        '@graph' => [
            ['@id' => 'schema:Thing', '@type' => 'rdfs:Class', 'rdfs:label' => 'Thing'],
        ],
    ]));

    try {
        $process = new Process([PHP_BINARY, $dir.'/Analyzer.php', $dir.'/sample.jsonld']);
        $process->run();

        expect($process->getExitCode())->toBe(0);
        expect($process->getErrorOutput())->toContain('Wrote');
        expect(file_exists($dir.'/schema-jsonld-structure-report.txt'))->toBeTrue();
    } finally {
        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);
    }
});
