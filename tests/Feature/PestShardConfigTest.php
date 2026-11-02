<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for the Windows shard configuration (#504). Runs
 * `scripts/pest-shard-config.php` over a throwaway tree and asserts it writes a
 * configuration whose one suite names each listed class's file once, keeps the
 * rest of the base configuration, and refuses a listing with no tests or a class
 * with no file, so a Windows shard can never run less than it was given.
 *
 * Driven as a subprocess, like `MergeShardReportsTest`, so the CLI contract
 * ci.yml depends on (the exit code and what it prints) is what is pinned.
 * Framework-free, with fixtures under a per-run `sys_get_temp_dir()` path, so it
 * is parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/PestShardConfigTest.php
 */
$script = dirname(__DIR__, 2).'/scripts/pest-shard-config.php';

/**
 * Make a fixture tree with a base configuration and the given test files.
 *
 * @param  list<string>  $files  Test files to create, relative to the tree
 */
$makeTree = function (array $files): string {
    $dir = sys_get_temp_dir().'/pest-shard-config-'.bin2hex(random_bytes(6));
    mkdir($dir);

    // A base configuration with two suites and the settings that must survive
    file_put_contents("{$dir}/phpunit.xml", <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <phpunit bootstrap="vendor/autoload.php" colors="true">
            <testsuites>
                <testsuite name="Unit">
                    <directory>./tests/Unit</directory>
                </testsuite>
                <testsuite name="Feature">
                    <directory>./tests/Feature</directory>
                </testsuite>
            </testsuites>
            <php>
                <env name="APP_ENV" value="testing"/>
            </php>
        </phpunit>
        XML);

    // Create each test file, with its directories
    // The path is cast at the point of use: PHPStan does not apply a docblock to a
    // closure assigned to a variable.
    foreach ($files as $file) {
        $path = $dir.'/'.(is_string($file) ? $file : '');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, "<?php\n");
    }

    return $dir;
};

/**
 * Run the script in a fixture tree.
 *
 * @param  string  $dir  The fixture tree, used as the working directory
 * @param  string|null  $listing  The listing to write and pass, or null to pass no options
 * @return array{0: int|null, 1: string}
 */
$run = function (string $dir, ?string $listing) use ($script): array {
    $arguments = [PHP_BINARY, $script];

    if ($listing !== null) {
        file_put_contents("{$dir}/listing.txt", $listing);
        $arguments[] = '--listing=listing.txt';
        $arguments[] = '--out=phpunit.shard-1.xml';
    }

    $process = new Process($arguments, $dir);
    $process->run();

    return [$process->getExitCode(), $process->getOutput().$process->getErrorOutput()];
};

/**
 * Read the values an XPath query selects from a written configuration.
 *
 * @param  string  $path  The configuration file
 * @param  string  $query  The XPath query
 * @return list<string>
 */
$select = function (string $path, string $query): array {
    $document = new DOMDocument;
    $xml = file_get_contents($path);

    if (! is_string($xml) || $xml === '') {
        throw new RuntimeException("{$path} could not be read.");
    }

    expect($document->loadXML($xml))->toBeTrue();

    $values = [];
    foreach ((new DOMXPath($document))->query($query) ?: [] as $node) {
        $values[] = (string) $node->nodeValue;
    }

    return $values;
};

it('writes one suite naming each listed class file once and keeps the rest of the base configuration', function () use ($makeTree, $run, $select): void {
    $dir = $makeTree(['tests/Feature/Fleet/KirkTest.php', 'tests/Unit/SpockTest.php', 'tests/Unit/UnlistedTest.php']);

    // The shape `pest --list-tests` prints, with a class listed for two tests and a dataset
    $listing = "\n  INFO  Available tests:\n\n"
        ." - P\\Tests\\Feature\\Fleet\\KirkTest::__pest_evaluable_it_commands\n"
        ." - P\\Tests\\Feature\\Fleet\\KirkTest::__pest_evaluable_it_logs with data set \"captain's log\"\n"
        ." - P\\Tests\\Unit\\SpockTest::__pest_evaluable_it_reasons\n";

    [$exitCode, $output] = $run($dir, $listing);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('2 test files from 3 listed tests');

    $config = "{$dir}/phpunit.shard-1.xml";

    // One suite, the two listed files in listing order, and nothing unlisted
    expect($select($config, '/phpunit/testsuites/testsuite/@name'))->toBe(['Shard'])
        ->and($select($config, '/phpunit/testsuites/testsuite/file'))
        ->toBe(['./tests/Feature/Fleet/KirkTest.php', './tests/Unit/SpockTest.php'])
        ->and($select($config, '//directory'))->toBe([]);

    // The base's bootstrap and <php> settings survive
    expect($select($config, '/phpunit/@bootstrap'))->toBe(['vendor/autoload.php'])
        ->and($select($config, '/phpunit/php/env/@value'))->toBe(['testing']);
});

it('refuses a listed class that has no file', function () use ($makeTree, $run): void {
    $dir = $makeTree(['tests/Unit/SpockTest.php']);

    $listing = " - P\\Tests\\Unit\\SpockTest::__pest_evaluable_it_reasons\n"
        ." - P\\Tests\\Unit\\McCoyTest::__pest_evaluable_it_heals\n";

    [$exitCode, $output] = $run($dir, $listing);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('1 listed classes have no file, first: tests/Unit/McCoyTest.php')
        ->and(is_file("{$dir}/phpunit.shard-1.xml"))->toBeFalse();
});

it('refuses a listing with no tests', function () use ($makeTree, $run): void {
    $dir = $makeTree(['tests/Unit/SpockTest.php']);

    [$exitCode, $output] = $run($dir, "\n  INFO  No tests found.\n");

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('lists no tests')
        ->and(is_file("{$dir}/phpunit.shard-1.xml"))->toBeFalse();
});

it('prints its usage and fails without options', function () use ($makeTree, $run): void {
    [$exitCode, $output] = $run($makeTree([]), null);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Usage: php scripts/pest-shard-config.php');
});
