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

/**
 * Run a copy of the analyzer directly against the given input, in an isolated
 * temp dir, and hand back the exit code, STDERR and the files it wrote.
 *
 * @param  string|null  $input  the `.jsonld` contents, or null to pass a path that does not exist
 * @param  array<string, string>  $env  extra environment variables for the process
 * @return array{exit: int|null, stderr: string, files: array<string, string>}
 */
function runCopiedAnalyzer(string $analyzerPath, ?string $input, array $env = []): array
{
    $dir = sys_get_temp_dir().'/jsonld-analyzer-'.bin2hex(random_bytes(6));
    mkdir($dir);
    copy($analyzerPath, $dir.'/Analyzer.php');
    if ($input !== null) {
        file_put_contents($dir.'/sample.jsonld', $input);
    }

    try {
        $process = new Process([PHP_BINARY, $dir.'/Analyzer.php', $dir.'/sample.jsonld'], null, $env);
        $process->run();

        $files = [];
        foreach (glob($dir.'/schema-jsonld-*') ?: [] as $file) {
            $files[basename($file)] = (string) file_get_contents($file);
        }

        return [
            'exit' => $process->getExitCode(),
            'stderr' => str_replace(
                str_replace('\\', '/', $dir),
                '<dir>',
                str_replace('\\', '/', $process->getErrorOutput()),
            ),
            'files' => $files,
        ];
    } finally {
        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);
    }
}

it('exits 1 naming the path when the input file does not exist', function () use ($analyzerPath) {
    $result = runCopiedAnalyzer($analyzerPath, null);

    expect($result['exit'])->toBe(1)
        ->and($result['stderr'])->toBe('Error: File not found or not readable: <dir>/sample.jsonld'.PHP_EOL)
        ->and($result['files'])->toBe([]);
});

it('exits 1 with the parser message when the input is not valid JSON', function () use ($analyzerPath) {
    $result = runCopiedAnalyzer($analyzerPath, '{');

    expect($result['exit'])->toBe(1)
        ->and($result['stderr'])->toBe("Error: Invalid JSON: Syntax error\n")
        ->and($result['files'])->toBe([]);
});

it('unwraps @graph and writes every @id, sorted, to the all-ids file', function () use ($analyzerPath) {
    $result = runCopiedAnalyzer($analyzerPath, '{"@graph":[{"@id":"schema:B"},{"@id":"schema:A"}]}');

    expect($result['exit'])->toBe(0)
        ->and($result['stderr'])->toBe(
            'Wrote <dir>/schema-jsonld-structure-report.txt'.PHP_EOL
            ."Wrote <dir>/schema-jsonld-all-ids.txt (2 ids)\n"
        )
        ->and(array_keys($result['files']))->toBe(['schema-jsonld-all-ids.txt', 'schema-jsonld-structure-report.txt'])
        ->and($result['files']['schema-jsonld-all-ids.txt'])->toBe("schema:A\nschema:B\n")
        ->and($result['files']['schema-jsonld-structure-report.txt'])->toContain("## @id prefix distribution (node count per prefix)\n  schema: => 2\n");
});

it('writes no all-ids file when the document has no @id values', function () use ($analyzerPath) {
    $result = runCopiedAnalyzer($analyzerPath, 'null');

    expect($result['exit'])->toBe(0)
        ->and($result['stderr'])->toBe('Wrote <dir>/schema-jsonld-structure-report.txt'.PHP_EOL)
        ->and(array_keys($result['files']))->toBe(['schema-jsonld-structure-report.txt']);
});

it('writes the report as JSON when ANALYZE_OUTPUT is json', function () use ($analyzerPath) {
    $result = runCopiedAnalyzer($analyzerPath, '[{"@id":"x:1"}]', ['ANALYZE_OUTPUT' => 'json']);

    expect($result['exit'])->toBe(0)
        ->and(array_keys($result['files']))->toBe(['schema-jsonld-all-ids.txt', 'schema-jsonld-structure-report.json'])
        ->and(json_decode($result['files']['schema-jsonld-structure-report.json'], true)['_meta']['id_prefix_distribution'])->toBe(['x:' => 1])
        ->and($result['files']['schema-jsonld-structure-report.json'])->toEndWith("}\n");
});
