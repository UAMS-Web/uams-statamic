<?php

declare(strict_types=1);

/**
 * Measure how many mutants each `@pest-mutate-ignore` annotation removes (UAMS-Web/uams-statamic-archive#3160).
 *
 * `scripts/check-mutate-ignore.php` reads the marker's TEXT, so it finds an annotation
 * that cannot apply. It cannot find one that applies too widely, or one whose spelling
 * and placement are both right and that still removes nothing, because breadth is a
 * property of the AST rather than of the line. Measured on
 * `app/Support/Forms/ConditionalLogicEvaluator.php`: at `faf7dac66` one
 * `TrueToFalse` annotation removed three mutants while its reason covered two, and the
 * text auditor reported clean.
 *
 * HOW IT COUNTS. For each annotation, count the mutants of each mutator it names, then
 * count again with that one marker made inert; the difference is what the annotation
 * removes. A bare marker names every mutator. The counting loop is
 * `Pest\Mutate\Support\MutationGenerator::generate()` with its cache call removed, since
 * that cache needs a Pest container binding only a real run provides. Everything that
 * decides suppression stays the installed plugin's: `PhpParserFactory`,
 * `NodeTraverserFactory`, `NodeVisitor`, the mutator classes, and the per-line ignore map
 * built the same way the generator builds it, `PHP_EOL` split included, so a run reports
 * what the plugin on this runtime would do.
 *
 * WHY THE MARKER IS MADE INERT RATHER THAN THE LINE DELETED. Deleting a line renumbers
 * every line after it, which moves the other annotations' entries in the plugin's line
 * map, and can change which node a docblock attaches to. Renaming the marker on that one
 * line keeps the comment, its node and every line number where they were, so the only
 * difference between the two counts is the marker.
 *
 * Flagged:
 *
 *   1. A named mutator the annotation removes more than one mutant of. One mutant per
 *      named mutator is what a reason written for one expression covers; more is the
 *      UAMS-Web/uams-statamic-archive#3147 case, where the annotation reached operands its reason never named.
 *   2. An annotation that removes no mutant at all. That covers a marker naming a
 *      mutator that does not apply to the node, and the qualified-call case UAMS-Web/uams-statamic-archive#3160
 *      measured, where `TrueToFalse`'s `in_array` exclusion stops firing.
 *
 * A bare marker removes every mutant in the node and its children, so it is reported with
 * its total and flagged only when that total is zero.
 *
 * BASELINE. Findings that predate this check are listed in a committed baseline, keyed by
 * path, the marker line's text and the measured counts rather than by line number, so an
 * edit elsewhere in a file does not move them. With `--baseline=<file>` a finding in the
 * baseline is reported as baselined and does not fail the run; a finding that is not in it
 * fails, and so does a baseline entry for a scanned file that no longer occurs, so the
 * baseline can only shrink. `--write-baseline=<file>` records the current findings.
 *
 * Usage:  php scripts/check-mutate-ignore-breadth.php [--baseline=<file>] [path ...]
 *         php scripts/check-mutate-ignore-breadth.php --write-baseline=<file> [path ...]
 *         A path is a directory searched for `*.php`, or a single file of any extension;
 *         the default is `app tests`.
 * Exit:   0 = no annotation flagged outside the baseline, 1 = findings or stale baseline
 *         entries, 2 = could not run (no mutator classes resolved, a file that does not
 *         parse or cannot be read, a path that is missing, an unreadable baseline).
 */

use Pest\Mutate\Contracts\Mutator;
use Pest\Mutate\Factories\NodeTraverserFactory;
use Pest\Mutate\Support\NodeVisitor;
use Pest\Mutate\Support\PhpParserFactory;
use Pest\Support\Str;
use PhpParser\Error;
use PhpParser\Parser;

require __DIR__.'/../vendor/autoload.php';

/**
 * Stop with exit code 2, which every caller reads as "could not run" rather than clean.
 *
 * @param  string  $message  What prevented the run.
 */
function breadthFail(string $message): never
{
    fwrite(STDERR, "check-mutate-ignore-breadth: {$message}\n");

    exit(2);
}

/**
 * Resolve every concrete mutator class the installed plugin ships, grouped by its name.
 *
 * A name can belong to more than one class: `BitwiseOrToBitwiseAnd` is both an `Arithmetic`
 * mutator for `|` and an `Assignment` mutator for `|=`. The plugin's `NodeVisitor` matches an
 * ignore entry against the name, so one annotation covers every class that carries it, and a
 * map holding one class per name would measure whichever class the directory walk returned
 * last (UAMS-Web/uams-statamic-archive#3280).
 *
 * @return array<string, list<class-string<Mutator>>> Mutator name to the classes carrying it.
 */
function breadthMutators(): array
{
    // The override lets a test point this at a directory with no mutators in it
    $override = getenv('MUTATE_IGNORE_BREADTH_MUTATORS_PATH');
    $root = is_string($override) && $override !== '' ? $override : __DIR__.'/../vendor/pestphp/pest-plugin-mutate/src/Mutators';

    if (! is_dir($root)) {
        breadthFail("pest-plugin-mutate is not installed at {$root}");
    }

    $mutators = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        // Build the class name from the path under the plugin's PSR-4 root
        $relative = substr($file->getPathname(), strlen($root) + 1, -4);
        $class = 'Pest\\Mutate\\Mutators\\'.str_replace(['/', '\\'], '\\', $relative);

        if (! class_exists($class)) {
            continue;
        }

        // Keep only classes a run could actually instantiate as a mutator
        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || ! $reflection->implementsInterface(Mutator::class)) {
            continue;
        }

        /** @var class-string<Mutator> $class */
        $mutators[$class::name()][] = $class;
    }

    return $mutators;
}

/**
 * Build the plugin's per-line ignore map exactly as `MutationGenerator::generate()` does.
 *
 * @param  string  $contents  The file's source.
 * @return array<int, array<int, string>> Line number to the mutator names ignored there.
 */
function breadthIgnoreMap(string $contents): array
{
    $map = [];

    foreach (explode(PHP_EOL, $contents) as $lineNumber => $line) {
        if (! str_contains($line, '@pest-mutate-ignore')) {
            continue;
        }

        $mutatorsToIgnore = null;

        if (Str::after($line, '@pest-mutate-ignore:') !== $line) {
            $mutatorsToIgnore = array_map(trim(...), explode(',', Str::after($line, '@pest-mutate-ignore:')));
        }

        $map[$lineNumber + 1] = $mutatorsToIgnore ?? ['all'];
    }

    return $map;
}

/**
 * Count the mutants one mutator produces in a source, with the generator's own loop.
 *
 * @param  Parser  $parser  The plugin's parser.
 * @param  string  $contents  The source to mutate.
 * @param  class-string<Mutator>  $mutator  The mutator to count.
 * @return int How many mutants the generator would create.
 */
function breadthCount(Parser $parser, string $contents, string $mutator): int
{
    $ignoreMap = breadthIgnoreMap($contents);
    $offset = 0;
    $count = 0;

    while (true) {
        $mutated = false;

        // Traverse once per mutant, resuming after the last mutated node, as the generator does
        $traverser = NodeTraverserFactory::create();
        $traverser->addVisitor(new NodeVisitor(
            mutator: $mutator,
            offset: $offset,
            linesToMutate: [],
            mutatorsToIgnoreByLine: $ignoreMap,
            hasAlreadyMutated: function () use (&$mutated): bool {
                return $mutated;
            },
            trackMutation: function (int $nodeCount) use (&$mutated, &$offset): void {
                $mutated = true;
                $offset = $nodeCount;
            },
        ));

        $statements = $parser->parse($contents);

        if ($statements === null) {
            return $count;
        }

        $traverser->traverse($statements);

        if (! $mutated) {
            return $count;
        }

        $count++;
    }
}

/**
 * Count the mutants every class carrying one mutator name produces in a source.
 *
 * @param  Parser  $parser  The plugin's parser.
 * @param  string  $contents  The source to mutate.
 * @param  list<class-string<Mutator>>  $classes  The classes that share the name.
 * @return int The sum of how many mutants each class would create.
 */
function breadthCountNamed(Parser $parser, string $contents, array $classes): int
{
    return array_sum(array_map(static fn (string $class): int => breadthCount($parser, $contents, $class), $classes));
}

/**
 * Find the line of every marker that sits in a comment, which is what the plugin honors.
 *
 * @param  string  $contents  The file's source.
 * @return array<int, int> The line numbers, ascending.
 */
function breadthAnnotationLines(string $contents): array
{
    $lines = [];

    foreach (token_get_all($contents) as $token) {
        if (! is_array($token) || ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT)) {
            continue;
        }

        // A comment token can span several lines, and each marker line counts on its own.
        // The marker must open the line's comment content, as `check-mutate-ignore.php`
        // requires; a sentence that mentions it is documentation, not an annotation
        foreach (preg_split('/\R/', $token[1]) ?: [] as $offset => $text) {
            $content = preg_replace('{^\s*(/\*\*?|\*|//|#)?\s*}', '', $text, 1) ?? $text;

            if (str_starts_with($content, '@pest-mutate-ignore')) {
                $lines[] = $token[2] + $offset;
            }
        }
    }

    return array_values(array_unique($lines));
}

/**
 * Read which mutator names a marker line carries, the way `NodeVisitor` reads it.
 *
 * @param  string  $line  The marker's line.
 * @return array<int, string>|null The names, or null for a bare marker.
 */
function breadthNamedMutators(string $line): ?array
{
    preg_match('/@pest-mutate-ignore(.*)/', $line, $matches);

    $tail = trim($matches[1] ?? '', " \t\n\r*/:");

    if ($tail === '') {
        return null;
    }

    return array_values(array_filter(
        array_map(static fn (string $name): string => trim($name, ' */:'), explode(',', $tail)),
        static fn (string $name): bool => $name !== '',
    ));
}

// Split the options from the paths
$baselinePath = null;
$writeBaselinePath = null;
$roots = [];

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--baseline=')) {
        $baselinePath = substr($argument, strlen('--baseline='));
    } elseif (str_starts_with($argument, '--write-baseline=')) {
        $writeBaselinePath = substr($argument, strlen('--write-baseline='));
    } elseif (str_starts_with($argument, '--')) {
        breadthFail("unknown option: {$argument}");
    } else {
        $roots[] = $argument;
    }
}

$roots = $roots ?: ['app', 'tests'];
$mutators = breadthMutators();

if ($mutators === []) {
    breadthFail('no mutator classes resolved from the installed plugin');
}

echo 'check-mutate-ignore-breadth: '.array_sum(array_map(count(...), $mutators))." mutator classes resolved from the installed plugin\n";

// Collect the files: a directory contributes its PHP files, a named file is read as given
$files = [];

foreach ($roots as $root) {
    if (is_file($root)) {
        $files[] = str_replace('\\', '/', $root);

        continue;
    }

    if (! is_dir($root)) {
        breadthFail("not a file or directory: {$root}");
    }

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }
}

sort($files);

$parser = PhpParserFactory::make();
$annotations = 0;
$findings = [];

foreach ($files as $path) {
    $contents = file_get_contents($path);

    if ($contents === false) {
        breadthFail("cannot read {$path}");
    }

    // Cheap skip for the common case before any tokenizing or parsing
    if (! str_contains($contents, '@pest-mutate-ignore')) {
        continue;
    }

    try {
        $parser->parse($contents);
    } catch (Error $error) {
        breadthFail("cannot parse {$path}: {$error->getMessage()}");
    }

    $sourceLines = preg_split('/\R/', $contents) ?: [];
    $baseline = [];

    foreach (breadthAnnotationLines($contents) as $lineNumber) {
        $annotations++;
        $line = $sourceLines[$lineNumber - 1] ?? '';
        $named = breadthNamedMutators($line);
        $known = $named === null ? array_keys($mutators) : array_values(array_filter($named, static fn (string $name): bool => isset($mutators[$name])));

        // Make this one marker inert, keeping the comment and every line where it was
        $without = $sourceLines;
        $without[$lineNumber - 1] = str_replace('@pest-mutate-ignore', '@pest-mutate-inert', $line);
        $withoutContents = implode("\n", $without);

        $removed = [];

        foreach ($known as $name) {
            $baseline[$name] ??= breadthCountNamed($parser, $contents, $mutators[$name]);
            $removed[$name] = breadthCountNamed($parser, $withoutContents, $mutators[$name]) - $baseline[$name];
        }

        $where = "{$path}:{$lineNumber}";
        $marker = trim($line);
        $total = array_sum($removed);
        $detail = $named === null
            ? "bare marker, removes {$total} across all mutators"
            : implode(', ', array_map(static fn (string $name, int $count): string => "{$name}={$count}", array_keys($removed), $removed));

        if ($total === 0) {
            $findings[] = [$where, 'removes NO mutant', $named === null ? $detail : ($detail === '' ? 'names no known mutator' : $detail), $path, $marker];

            continue;
        }

        if ($named !== null) {
            $wide = array_filter($removed, static fn (int $count): bool => $count > 1);

            if ($wide !== []) {
                $findings[] = [$where, 'removes MORE than one mutant of a named mutator', $detail, $path, $marker];
            }
        }
    }
}

echo "check-mutate-ignore-breadth: {$annotations} annotation(s) measured in ".count($files)." file(s)\n";

// Record the current findings as the baseline when asked to, and stop there
if ($writeBaselinePath !== null) {
    $entries = array_map(static fn (array $finding): array => [
        'path' => $finding[3],
        'marker' => $finding[4],
        'finding' => $finding[1],
        'detail' => $finding[2],
    ], $findings);

    $json = json_encode(['findings' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    if (file_put_contents($writeBaselinePath, $json."\n") === false) {
        breadthFail("cannot write {$writeBaselinePath}");
    }

    echo 'check-mutate-ignore-breadth: '.count($entries)." finding(s) written to {$writeBaselinePath}\n";

    exit(0);
}

// Load the baseline as counted keys, since one file can repeat a marker line
$baselined = [];

if ($baselinePath !== null) {
    $raw = is_file($baselinePath) ? file_get_contents($baselinePath) : false;
    $decoded = $raw === false ? null : json_decode($raw, true);

    if (! is_array($decoded) || ! is_array($decoded['findings'] ?? null)) {
        breadthFail("cannot read baseline {$baselinePath}");
    }

    foreach ($decoded['findings'] as $entry) {
        if (! is_array($entry) || ! is_string($entry['path'] ?? null) || ! is_string($entry['marker'] ?? null) || ! is_string($entry['detail'] ?? null)) {
            breadthFail("malformed entry in baseline {$baselinePath}");
        }

        $key = "{$entry['path']}\t{$entry['marker']}\t{$entry['detail']}";
        $baselined[$key] = ($baselined[$key] ?? 0) + 1;
    }
}

// Set aside every finding the baseline accounts for
$new = [];
$matched = 0;

foreach ($findings as $finding) {
    $key = "{$finding[3]}\t{$finding[4]}\t{$finding[2]}";

    if (($baselined[$key] ?? 0) > 0) {
        $baselined[$key]--;
        $matched++;

        continue;
    }

    $new[] = $finding;
}

// A leftover entry for a file this run scanned no longer occurs, so the baseline is stale
$scanned = array_flip($files);
$stale = [];

foreach ($baselined as $key => $remaining) {
    [$path, $marker, $detail] = explode("\t", $key, 3);

    if ($remaining > 0 && isset($scanned[$path])) {
        $stale[] = "{$path}\n  {$marker}\n  {$detail}";
    }
}

if ($baselinePath !== null) {
    echo "check-mutate-ignore-breadth: {$matched} finding(s) accounted for by {$baselinePath}\n";
}

if ($new === [] && $stale === []) {
    echo $matched === 0
        ? "check-mutate-ignore-breadth: every annotation removes exactly one mutant per named mutator\n"
        : "check-mutate-ignore-breadth: no annotation flagged outside the baseline\n";

    exit(0);
}

foreach ($new as [$where, $why, $detail]) {
    echo "{$where}\n  {$why}\n  {$detail}\n\n";
}

foreach ($stale as $entry) {
    echo "{$entry}\n  baseline entry no longer occurs: remove it from the baseline\n\n";
}

echo 'check-mutate-ignore-breadth: '.count($new).' annotation(s) flagged, '.count($stale)." stale baseline entry(ies)\n";

exit(1);
