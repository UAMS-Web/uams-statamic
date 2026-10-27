<?php

declare(strict_types=1);

/**
 * Build the pass-3 snowball queue: eligible coverage-scope rows ordered by
 * estimated Pest mutant count (smallest first).
 *
 * Estimates use Pest's `MutationGenerator` with the default mutator set on each
 * source file without running the covering suite. That count is a wall-time
 * proxy for `--covered-only` (measured #2834: Hidden 114, Gps 117, Currency ~196,
 * LikertScale 1510). A later narrow run that finds 0 covered mutants is still
 * recorded as `n/a` on the CSV and skipped, the same as Html.
 *
 * Usage:
 *   php scripts/estimate-pass3-mutants.php
 *   php scripts/estimate-pass3-mutants.php --csv=… --out=… --min=86.7 --print
 *
 * Exit: 0 on success, 2 on bad arguments / missing inputs.
 */

use Pest\Mutate\Cache\NullStore;
use Pest\Mutate\Mutators\Sets\DefaultSet;
use Pest\Mutate\Support\MutationGenerator;
use Pest\Support\Container;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Finder\SplFileInfo;

$root = dirname(__DIR__);
$autoload = $root.'/vendor/autoload.php';

if (! is_file($autoload)) {
    fwrite(STDERR, "estimate-pass3-mutants: vendor/autoload.php is missing; run composer install.\n");
    exit(2);
}

require $autoload;

// MutationGenerator touches MutationCache, which resolves CacheInterface from
// Pest's container. Register a null store so this CLI does not need a full
// Pest bootstrap (same approach CliConfiguration uses for --no-cache).
Container::getInstance()->add(CacheInterface::class, new NullStore);

$csvPath = $root.'/storage/app/coverage-scope.csv';
$outPath = $root.'/storage/app/pass3-snowball.json';
$pass2Min = 86.7;
$print = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--print') {
        $print = true;

        continue;
    }
    if (str_starts_with($argument, '--csv=')) {
        $csvPath = substr($argument, strlen('--csv='));

        continue;
    }
    if (str_starts_with($argument, '--out=')) {
        $outPath = substr($argument, strlen('--out='));

        continue;
    }
    if (str_starts_with($argument, '--min=')) {
        $pass2Min = (float) substr($argument, strlen('--min='));

        continue;
    }
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, "Usage: php scripts/estimate-pass3-mutants.php [--csv=path] [--out=path] [--min=86.7] [--print]\n");
        exit(0);
    }
    fwrite(STDERR, "estimate-pass3-mutants: unknown argument {$argument}\n");
    exit(2);
}

if (! is_file($csvPath)) {
    fwrite(STDERR, "estimate-pass3-mutants: missing CSV at {$csvPath}\n");
    exit(2);
}

$handle = fopen($csvPath, 'rb');
if ($handle === false) {
    fwrite(STDERR, "estimate-pass3-mutants: cannot open {$csvPath}\n");
    exit(2);
}

$header = fgetcsv($handle, null, ',', '"', '\\');
if ($header === false) {
    fclose($handle);
    fwrite(STDERR, "estimate-pass3-mutants: empty CSV\n");
    exit(2);
}

/** @var list<array{file: string, est_mutants: int, actual_mutants: null, note: null}> $queue */
$queue = [];
$generator = new MutationGenerator;
$mutators = DefaultSet::mutators();

while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
    $file = $row[0] ?? '';
    $pass1 = $row[1] ?? '';
    $pass2 = $row[2] ?? '';
    $pass3 = $row[3] ?? '';

    if ($file === '' || $file === 'file') {
        continue;
    }
    if ($pass1 !== 'yes') {
        continue;
    }
    if ($pass2 === '' || ! is_numeric($pass2) || (float) $pass2 < $pass2Min) {
        continue;
    }
    if (trim((string) $pass3) !== '') {
        continue;
    }

    $absolute = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (! is_file($absolute)) {
        fwrite(STDERR, "estimate-pass3-mutants: skip missing file {$file}\n");

        continue;
    }

    $spl = new SplFileInfo($absolute, dirname($file), basename($file));
    $mutations = $generator->generate(
        $spl,
        $mutators,
        [],
        [],
    );

    $queue[] = [
        'file' => $file,
        'est_mutants' => count($mutations),
        'actual_mutants' => null,
        'note' => null,
    ];
}

fclose($handle);

usort(
    $queue,
    static function (array $a, array $b): int {
        $byCount = $a['est_mutants'] <=> $b['est_mutants'];
        if ($byCount !== 0) {
            return $byCount;
        }

        return $a['file'] <=> $b['file'];
    },
);

$payload = [
    'generated_at' => gmdate('c'),
    'pass2_min' => $pass2Min,
    'csv' => $csvPath,
    'queue' => $queue,
];

$outDir = dirname($outPath);
if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "estimate-pass3-mutants: cannot create {$outDir}\n");
    exit(2);
}

$json = json_encode(
    $payload,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
);
if ($json === false) {
    fwrite(STDERR, "estimate-pass3-mutants: json_encode failed\n");
    exit(2);
}

if (file_put_contents($outPath, $json."\n") === false) {
    fwrite(STDERR, "estimate-pass3-mutants: cannot write {$outPath}\n");
    exit(2);
}

fwrite(STDOUT, 'estimate-pass3-mutants: wrote '.count($queue)." rows to {$outPath}\n");

if ($print) {
    foreach ($queue as $index => $entry) {
        $rank = $index + 1;
        fwrite(STDOUT, sprintf(
            "%4d  %5d  %s\n",
            $rank,
            $entry['est_mutants'],
            $entry['file'],
        ));
    }
}

exit(0);
