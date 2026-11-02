<?php

declare(strict_types=1);

/**
 * Write a PHPUnit configuration that runs one Pest shard's test files (#504).
 *
 * `pest --shard=<i>/<n>` turns its share of the classes into one `--filter`
 * regex, about 9,000 characters per shard for this suite at four shards. Under
 * `--parallel`, paratest starts every worker through Symfony Process, which on
 * Windows runs the command through `cmd.exe`, and `cmd.exe` refuses a command
 * longer than 8,191 characters. Measured on Windows 11: a worker command of
 * 8,037 characters starts and one of 8,038 does not, and the worker then fails
 * its first assertion on line 82 of `vendor/pestphp/pest/bin/worker.php`.
 *
 * So on Windows the shard is chosen without workers and handed over as a file.
 * `pest --list-tests --shard=<i>/<n>` runs in one process and lists the shard's
 * tests, using the same partition `--shard` uses with `--parallel` (Pest reads
 * the same `tests/.pest/shards.json` and the same full listing either way).
 * This script maps each listed class to its test file and writes a copy of the
 * base configuration whose only test suite is those files. The band then runs
 * `pest --parallel --configuration=<that copy>`, and each worker is given the
 * configuration's path rather than the class list.
 *
 * Pest names a test class after its file: `P\Tests\Feature\FooTest` is
 * `tests/Feature/FooTest.php`. A listed class whose file does not exist stops
 * the script, as does a listing with no tests, so a shard can never run fewer
 * files than it was given, or none.
 *
 * Written in PHP rather than Node for the reason {@see check-skip-baseline.php}
 * gives: the `pest` job sets up PHP, and Node is not needed for anything else
 * there.
 *
 * USAGE
 *     php scripts/pest-shard-config.php --listing=<pest --list-tests output>
 *                                       --out=<configuration to write>
 *                                       [--config=phpunit.xml]
 *
 * EXIT
 *     0  the configuration was written
 *     1  the listing is missing, lists no tests, or names a class with no file
 */

/**
 * Read the listed test classes, in the order they first appear.
 *
 * @param  string  $listing  The text `pest --list-tests` printed
 * @return list<string> The distinct class names, such as `P\Tests\Feature\FooTest`
 */
function pestShardListedClasses(string $listing): array
{
    // Match each listed test's class, the part before `::`
    preg_match_all('/^ - (P\\\\[^:\s]+)::/m', $listing, $matches);

    // Keep each class once, in listing order
    return array_values(array_unique($matches[1]));
}

/**
 * Map a Pest test class to the path of the file that declares it.
 *
 * @param  string  $class  A class name such as `P\Tests\Feature\FooTest`
 * @return string The relative path, such as `tests/Feature/FooTest.php`
 */
function pestShardClassFile(string $class): string
{
    // Drop the `P\` prefix Pest adds and turn namespace separators into slashes
    $path = str_replace('\\', '/', substr($class, 2));

    // The namespace starts `Tests`, and the directory is `tests`
    return lcfirst($path).'.php';
}

/**
 * Build the shard's configuration from the base one.
 *
 * Every `<testsuite>` in the base is replaced by one suite named `Shard` that
 * lists the given files. Everything else in the base (bootstrap, `<php>`
 * pins, `<source>`, extensions) is kept as it is.
 *
 * @param  string  $baseXml  The base configuration's XML
 * @param  list<string>  $files  The test files, relative to the configuration
 * @return string The shard configuration's XML
 */
function pestShardConfiguration(string $baseXml, array $files): string
{
    // Load the base configuration, keeping its formatting where it is untouched
    $document = new DOMDocument;
    $document->preserveWhiteSpace = true;

    if (! $document->loadXML($baseXml)) {
        throw new RuntimeException('The base configuration is not valid XML.');
    }

    // Find the one <testsuites> element the base must have
    $suitesList = $document->getElementsByTagName('testsuites');

    if ($suitesList->length !== 1) {
        throw new RuntimeException(sprintf('The base configuration has %d <testsuites> elements, not 1.', $suitesList->length));
    }

    $suites = $suitesList->item(0);

    // Remove every existing suite
    while ($suites->firstChild !== null) {
        $suites->removeChild($suites->firstChild);
    }

    // Add one suite holding the shard's files
    $suite = $document->createElement('testsuite');
    $suite->setAttribute('name', 'Shard');

    foreach ($files as $file) {
        $suite->appendChild($document->createTextNode("\n            "));
        $suite->appendChild($document->createElement('file', './'.$file));
    }

    $suite->appendChild($document->createTextNode("\n        "));
    $suites->appendChild($document->createTextNode("\n        "));
    $suites->appendChild($suite);
    $suites->appendChild($document->createTextNode("\n    "));

    // Serialize the result
    $xml = $document->saveXML();

    if ($xml === false) {
        throw new RuntimeException('The shard configuration could not be serialized.');
    }

    return $xml;
}

/**
 * Run the script.
 *
 * @param  array<string, string|false|list<string|false>>  $options  The parsed command-line options
 * @return int The exit code
 */
function pestShardConfigMain(array $options): int
{
    // Read the options, each a single string
    $listingPath = $options['listing'] ?? null;
    $outPath = $options['out'] ?? null;
    $configPath = $options['config'] ?? 'phpunit.xml';

    if (! is_string($listingPath) || ! is_string($outPath) || ! is_string($configPath)) {
        fwrite(STDERR, "Usage: php scripts/pest-shard-config.php --listing=<file> --out=<file> [--config=phpunit.xml]\n");

        return 1;
    }

    // Read the listing and the base configuration
    $listing = is_file($listingPath) ? file_get_contents($listingPath) : false;
    $baseXml = is_file($configPath) ? file_get_contents($configPath) : false;

    if ($listing === false || $baseXml === false) {
        fwrite(STDERR, sprintf("Cannot read %s.\n", $listing === false ? $listingPath : $configPath));

        return 1;
    }

    // A shard with no tests is a failure, not an empty pass
    $classes = pestShardListedClasses($listing);

    if ($classes === []) {
        fwrite(STDERR, sprintf("%s lists no tests.\n", $listingPath));

        return 1;
    }

    // Every listed class must have its file, or the shard would run less than it was given
    $files = array_map(pestShardClassFile(...), $classes);
    $missing = array_values(array_filter($files, fn (string $file): bool => ! is_file($file)));

    if ($missing !== []) {
        fwrite(STDERR, sprintf("%d listed classes have no file, first: %s\n", count($missing), $missing[0]));

        return 1;
    }

    // Write the shard's configuration
    try {
        $xml = pestShardConfiguration($baseXml, $files);
    } catch (RuntimeException $exception) {
        fwrite(STDERR, $exception->getMessage()."\n");

        return 1;
    }

    if (file_put_contents($outPath, $xml) === false) {
        fwrite(STDERR, sprintf("Cannot write %s.\n", $outPath));

        return 1;
    }

    fwrite(STDOUT, sprintf("%s: %d test files from %d listed tests.\n", $outPath, count($files), substr_count($listing, "\n - ")));

    return 0;
}

// Run only when executed directly, so a test can load the functions
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(pestShardConfigMain(getopt('', ['listing:', 'out:', 'config:'])));
}
