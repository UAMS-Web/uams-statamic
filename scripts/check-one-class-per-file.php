<?php

declare(strict_types=1);

/**
 * One-class-per-file check (UAMS-Web/uams-statamic#555): fail when a PHP file declares
 * more than one class, enum, interface or trait, naming the file and each extra type.
 *
 * The rule is #449's. Composer's PSR-4 check cannot enforce it: with `--strict-psr` a
 * second class beside one that matches its path is dropped from the class map
 * silently, and the command exits 0. Such a class then loads only when its file
 * happens to be included first.
 *
 * Why a token scan, and not one of the tools the gate already runs:
 *
 *   - PHPStan skips the large generated enum files by path (`phpstan.neon.dist`), and
 *     9 of the 10 enum files that held a second class before #554 are among them.
 *   - Pint (PHP-CS-Fixer) has no fixer for the rule.
 *   - A Pest architecture test reads classes through the autoloader, and a class
 *     Composer dropped is the one it cannot see.
 *
 * A token scan reads the file itself, needs no `composer install`, and runs in a few
 * seconds, so it sits beside the PSR-4 check in the `composer-validate` job.
 *
 * Detection runs over PHP tokens ({@see token_get_all}), so a declaration keyword
 * inside a comment, docblock or string is ignored. A keyword counts only when a
 * name follows it, so `Foo::class` and an anonymous `new class` are not counted.
 *
 * Before scanning, every run checks the committed control fixture
 * ({@see CONTROL_FIXTURE}), which declares two classes, and exits 2 unless it is
 * reported with exactly its second class. A scan that cannot see the defect it
 * exists for would otherwise pass every tree.
 *
 * Usage:
 *   php scripts/check-one-class-per-file.php            scan the tracked and untracked, not ignored, PHP files
 *   php scripts/check-one-class-per-file.php <file>...  scan these files only
 *
 * Exit codes: 0 clean, 1 a file declares more than one type, 2 the check could not
 * run (the control fixture is missing or not reported, or git could not list files).
 */
const CONTROL_FIXTURE = 'tests/Fixtures/one-class-per-file/TwoClasses.php.txt';

/**
 * The class-like types a PHP source declares, in order.
 *
 * @return list<array{kind: string, name: string, line: int}>
 */
function declaredTypes(string $source): array
{
    $tokens = token_get_all($source);
    $count = count($tokens);
    $types = [];
    $kinds = [T_CLASS => 'class', T_ENUM => 'enum', T_INTERFACE => 'interface', T_TRAIT => 'trait'];
    $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];

        if (! is_array($token) || ! isset($kinds[$token[0]])) {
            continue;
        }

        // The name is the next token that is not whitespace or a comment; `Foo::class`
        // and an anonymous `new class` have none, so they are not counted
        $after = $index + 1;
        while ($after < $count && is_array($tokens[$after]) && in_array($tokens[$after][0], $skip, true)) {
            $after++;
        }

        $name = $tokens[$after] ?? null;

        if (is_array($name) && $name[0] === T_STRING) {
            $types[] = ['kind' => $kinds[$token[0]], 'name' => $name[1], 'line' => $token[2]];
        }
    }

    return $types;
}

/**
 * The report line for a file that declares more than one type, or null when it declares one or none.
 *
 * @param  list<array{kind: string, name: string, line: int}>  $types
 */
function finding(string $path, array $types): ?string
{
    if (count($types) < 2) {
        return null;
    }

    $first = array_shift($types);
    $extra = implode(', ', array_map(
        fn (array $type): string => sprintf('%s %s (line %d)', $type['kind'], $type['name'], $type['line']),
        $types
    ));

    return sprintf('%s: declares %s %s, and also %s', $path, $first['kind'], $first['name'], $extra);
}

/**
 * The PHP files git tracks, plus untracked files it does not ignore, relative to the root.
 *
 * @return list<string>|null Null when git could not list them
 */
function listedPhpFiles(string $root): ?array
{
    $output = [];
    $status = 0;
    exec('git -C '.escapeshellarg($root).' ls-files --cached --others --exclude-standard -- "*.php"', $output, $status);

    if ($status !== 0) {
        return null;
    }

    return array_values(array_unique(array_filter($output, fn (string $line): bool => $line !== '')));
}

$root = dirname(__DIR__);

// The control: the fixture must be reported, naming its second class
$fixture = $root.'/'.CONTROL_FIXTURE;
$fixtureSource = is_file($fixture) ? file_get_contents($fixture) : false;
$control = is_string($fixtureSource) ? finding(CONTROL_FIXTURE, declaredTypes($fixtureSource)) : null;

if ($control === null || ! str_contains($control, 'class SecondShip (line')) {
    fwrite(STDERR, 'check-one-class-per-file: the control fixture '.CONTROL_FIXTURE." was not reported with its second class, so this check cannot see the defect it exists for.\n");
    exit(2);
}

// The files to scan: the arguments, or every listed PHP file
$paths = array_slice($argv, 1);

if ($paths === []) {
    $paths = listedPhpFiles($root);

    if ($paths === null) {
        fwrite(STDERR, "check-one-class-per-file: git could not list the PHP files to scan.\n");
        exit(2);
    }
}

// The denominator goes out first, so a crash mid-scan cannot read as a clean result
fwrite(STDOUT, sprintf("check-one-class-per-file: control reported; scanning %d PHP files\n", count($paths)));

$findings = [];

foreach ($paths as $path) {
    $absolute = is_file($path) ? $path : $root.'/'.$path;
    $source = is_file($absolute) ? file_get_contents($absolute) : false;

    if (! is_string($source)) {
        fwrite(STDERR, "check-one-class-per-file: could not read {$path}\n");
        exit(2);
    }

    $line = finding(str_replace('\\', '/', $path), declaredTypes($source));

    if ($line !== null) {
        $findings[] = $line;
    }
}

if ($findings !== []) {
    foreach ($findings as $line) {
        fwrite(STDOUT, $line."\n");
    }

    fwrite(STDOUT, sprintf("check-one-class-per-file: %d file(s) declare more than one class, enum, interface or trait. Move each extra type to a file of its own, named for it (#449).\n", count($findings)));
    exit(1);
}

fwrite(STDOUT, "check-one-class-per-file: every file declares at most one class, enum, interface or trait.\n");
exit(0);
