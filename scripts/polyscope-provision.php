<?php

declare(strict_types=1);

/*
 * Copy the gitignored artifacts a Polyscope workspace clone omits.
 *
 * Polyscope creates each Windows/Linux workspace with `git clone --local`,
 * which excludes gitignored files, and its `copyGitignored` option is a no-op
 * on this host. A Statamic dev environment still needs those files, so this
 * script copies them from the source repository (exposed to setup scripts as
 * POLYSCOPE_ROOT_PATH) into the current workspace directory: the env file,
 * installed dependencies, built assets, the CP login user, and the dev
 * database. Copying from the local source is faster and needs no PAT/network
 * compared with a fresh `composer install` + `npm install` + build.
 *
 * Invoked from polyscope.json: `php scripts/polyscope-provision.php`
 */

$root = getenv('POLYSCOPE_ROOT_PATH') ?: '';

if ($root === '' || ! is_dir($root)) {
    fwrite(STDERR, "POLYSCOPE_ROOT_PATH is not set to a directory; nothing to provision.\n");
    exit(1);
}

$isWindows = PHP_OS_FAMILY === 'Windows';

/**
 * Copy a directory tree using the platform's native fast copy tool.
 */
function copyDir(string $src, string $dst, bool $isWindows): void
{
    if (! is_dir($src)) {
        echo "  skip (absent): {$src}\n";

        return;
    }

    echo "  copy {$src} -> {$dst}\n";

    if ($isWindows) {
        // /E copies all subdirectories including empty ones, /MT copies
        // multithreaded; the remaining flags silence per-file logging.
        // robocopy exit codes 0-7 indicate success, 8+ a real failure.
        passthru(sprintf('robocopy "%s" "%s" /E /MT /NFL /NDL /NJH /NJS /NP', $src, $dst), $code);

        if ($code >= 8) {
            fwrite(STDERR, "robocopy failed ({$code}) copying {$src}\n");
            exit(1);
        }

        return;
    }

    passthru(sprintf('cp -a %s/. %s/', escapeshellarg($src), escapeshellarg($dst)), $code);

    if ($code !== 0) {
        fwrite(STDERR, "cp failed ({$code}) copying {$src}\n");
        exit(1);
    }
}

/**
 * Copy a single file, creating its destination directory as needed.
 */
function copyFile(string $src, string $dst): void
{
    if (! is_file($src)) {
        echo "  skip (absent): {$src}\n";

        return;
    }

    $directory = dirname($dst);

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    echo "  copy {$src} -> {$dst}\n";
    copy($src, $dst);
}

echo "Provisioning workspace from {$root}\n";

// The environment file carries APP_KEY and cannot be regenerated.
copyFile("{$root}/.env", '.env');

// Installed dependencies and built assets the app needs to boot and render.
copyDir("{$root}/vendor", 'vendor', $isWindows);
copyDir("{$root}/node_modules", 'node_modules', $isWindows);
copyDir("{$root}/public/build", 'public/build', $isWindows);
copyDir("{$root}/public/vendor", 'public/vendor', $isWindows);

// The Control Panel login user (file-based user repository; git-ignored).
copyDir("{$root}/users", 'users', $isWindows);

// The dev SQLite database, if the source uses one.
copyFile("{$root}/database/database.sqlite", 'database/database.sqlite');

echo "Provisioning complete.\n";
