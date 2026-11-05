<?php

declare(strict_types=1);

/**
 * Drift guard for #580: `composer test:pint` reaches every tracked PHP file, dot-directories included.
 *
 * The remote Pint check is the shared chain's `pint` job, which runs `composer test:pint`. A bare
 * `pint --test` does not descend into dot-directories (Pint's finder ignores dot files), so a
 * tracked PHP file under `.cursor/` or `.claude/` would pass unread (UAMS-Web/uams-statamic-archive#2341).
 * The script therefore names those directories in a second command. A PHP file added under a
 * dot-directory the script does not name would be skipped the same way, and nothing else would
 * object, so this reads the tracked files and fails naming the directory.
 *
 * A pure file-and-git read; it does not boot the framework.
 *
 * @command  php artisan test --compact tests/Feature/Config/PintCoversDotDirectoriesTest.php
 */
$root = dirname(__DIR__, 3);

/**
 * The top-level dot-directories holding tracked PHP that Pint parses (Blade templates excluded,
 * since Pint skips them anywhere).
 *
 * @return list<string>
 */
$dotDirectoriesWithPhp = function () use ($root): array {
    $output = [];
    exec('git -C '.escapeshellarg($root).' ls-files -- "*.php"', $output, $status);

    expect($status)->toBe(0);

    $directories = [];

    foreach ($output as $path) {
        if (str_ends_with($path, '.blade.php') || str_starts_with($path, 'vendor/')) {
            continue;
        }

        foreach (explode('/', $path) as $segment) {
            if (str_starts_with($segment, '.')) {
                $directories[] = explode('/', $path)[0];

                break;
            }
        }
    }

    $directories = array_values(array_unique($directories));
    sort($directories);

    return $directories;
};

/**
 * The commands of the `test:pint` Composer script, in order.
 *
 * @return list<string>
 */
$pintCommands = function () use ($root): array {
    $manifest = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $scripts = is_array($manifest) && is_array($manifest['scripts'] ?? null) ? $manifest['scripts'] : [];
    $commands = is_array($scripts['test:pint'] ?? null) ? $scripts['test:pint'] : [];

    return array_values(array_filter($commands, is_string(...)));
};

/**
 * The paths the `test:pint` commands pass after `--test`.
 *
 * @return list<string>
 */
$namedInScript = function () use ($pintCommands): array {
    $paths = [];

    foreach ($pintCommands() as $command) {
        if (preg_match('/pint --test (.+)$/', $command, $match) === 1) {
            $paths = [...$paths, ...preg_split('/\s+/', trim($match[1])) ?: []];
        }
    }

    sort($paths);

    return $paths;
};

it('finds the dot-directories that hold tracked PHP', function () use ($dotDirectoriesWithPhp): void {
    // The control: a scan that found none would make the case below vacuous.
    expect($dotDirectoriesWithPhp())->toContain('.cursor');
});

it('names every dot-directory holding tracked PHP in composer test:pint', function () use ($dotDirectoriesWithPhp, $namedInScript): void {
    expect(array_values(array_diff($dotDirectoriesWithPhp(), $namedInScript())))->toBe([]);
});

it('keeps the bare whole-tree run as the first command', function () use ($pintCommands): void {
    expect($pintCommands()[0] ?? null)->toBe('@php vendor/bin/pint --test');
});
