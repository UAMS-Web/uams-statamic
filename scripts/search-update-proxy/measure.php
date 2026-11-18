<?php

declare(strict_types=1);

/**
 * Time `statamic:search:update --all` over a proxy content tree made by
 * generate.php (UAMS-Web/uams-statamic#971, reused by #967). See README.md.
 *
 * Usage:
 *   php -d memory_limit=-1 scripts/search-update-proxy/measure.php --root=<generate.php's --out>
 *
 * Boots the application with every Stache store, the file cache and both
 * search drivers' paths moved under --root, so the run reads only the proxy
 * tree and writes only under --root: `content/`, the application's own cache
 * and its search indexes are not touched. Runs the real Artisan commands
 * in-process. Makes no network request.
 */

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Artisan;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$options = getopt('', ['root:']);
$proxy = is_string($options['root'] ?? null) ? rtrim($options['root'], '/') : null;

if ($proxy === null || ! is_file($proxy.'/manifest.json')) {
    fwrite(STDERR, "Usage: php -d memory_limit=-1 scripts/search-update-proxy/measure.php --root=<a directory generate.php wrote>\n");
    exit(2);
}

$proxy = (string) realpath($proxy);

foreach (['cache', 'loupe', 'search-local'] as $directory) {
    if (file_exists($proxy.'/'.$directory)) {
        fwrite(STDERR, "Refusing to reuse {$proxy}/{$directory}: a measurement already ran here. Generate a fresh tree.\n");
        exit(2);
    }
}

try {
    $app = require $root.'/bootstrap/app.php';

    if (! $app instanceof Application) {
        throw new RuntimeException('bootstrap/app.php did not return an application');
    }

    // Move every surface the run reads or writes under the proxy root, before any provider boots
    $app->afterBootstrapping(LoadConfiguration::class, static function (Application $app) use ($root, $proxy): void {
        $config = $app->make(Repository::class);
        $content = $root.'/content';

        foreach ((array) $config->get('statamic.stache.stores', []) as $store => $settings) {
            $directory = is_array($settings) ? ($settings['directory'] ?? null) : null;

            if (is_string($directory) && str_starts_with($directory, $content)) {
                $config->set("statamic.stache.stores.{$store}.directory", $proxy.'/content'.substr($directory, strlen($content)));
            }
        }

        $config->set('statamic.stache.watcher', false);
        $config->set('cache.default', 'file');
        $config->set('cache.stores.file.path', $proxy.'/cache');
        $config->set('cache.stores.file.lock_path', $proxy.'/cache');
        $config->set('statamic.search.drivers.loupe.path', $proxy.'/loupe');
        $config->set('statamic.search.drivers.local.path', $proxy.'/search-local');
    });

    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    // Refuse to run if any store still reads the application's own content
    foreach ((array) config('statamic.stache.stores', []) as $store => $settings) {
        $directory = is_array($settings) ? ($settings['directory'] ?? null) : null;

        if (is_string($directory) && str_starts_with($directory, $root.'/content')) {
            fwrite(STDERR, "Store {$store} still reads {$directory}; refusing to run.\n");
            exit(3);
        }
    }

    /**
     * Run an Artisan command and time it.
     *
     * @param  string  $command  The command
     * @param  array<string, mixed>  $parameters  Its parameters
     * @return array{seconds: float, exit: int, output: string}
     */
    $run = static function (string $command, array $parameters = []): array {
        $started = hrtime(true);
        $exit = Artisan::call($command, $parameters);

        return [
            'seconds' => round((hrtime(true) - $started) / 1e9, 2),
            'exit' => $exit,
            'output' => trim(Artisan::output()),
        ];
    };

    /**
     * The first column of the first row a query returns.
     *
     * @param  string  $dsn  The SQLite DSN
     * @param  string  $sql  The query
     */
    $scalar = static function (string $dsn, string $sql): string {
        $statement = new PDO($dsn)->query($sql);

        if ($statement === false) {
            throw new RuntimeException(sprintf('Query failed on %s: %s', $dsn, $sql));
        }

        $value = $statement->fetchColumn();

        return is_scalar($value) ? (string) $value : '';
    };

    // The versions of the packages the run exercises, from the lock file
    $lock = json_decode((string) file_get_contents($root.'/composer.lock'), true);
    $packages = [];

    foreach (is_array($lock) ? array_merge((array) ($lock['packages'] ?? []), (array) ($lock['packages-dev'] ?? [])) : [] as $package) {
        if (is_array($package) && in_array($package['name'] ?? null, ['statamic/cms', 'daun/statamic-loupe', 'loupe/loupe', 'laravel/framework'], true)) {
            $packages[(string) $package['name']] = is_string($package['version'] ?? null) ? $package['version'] : '';
        }
    }

    ksort($packages);

    $warm = $run('statamic:stache:warm');
    fwrite(STDOUT, sprintf("statamic:stache:warm: %.2fs, exit %d\n", $warm['seconds'], $warm['exit']));

    $memoryBefore = memory_get_peak_usage(true);
    $update = $run('statamic:search:update', ['--all' => true]);
    fwrite(STDOUT, sprintf("statamic:search:update --all: %.2fs, exit %d\n", $update['seconds'], $update['exit']));

    // Each index's files, with and without the write-ahead log, and its document count
    $indexes = [];

    foreach (glob($proxy.'/{loupe,search-local}/*', GLOB_BRACE) ?: [] as $path) {
        // Every file in the index, its subdirectories included
        $files = is_dir($path)
            ? iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)), false)
            : [new SplFileInfo($path)];
        $bytes = 0;
        $wal = 0;

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            $size = (int) $file->getSize();
            $bytes += $size;

            // The write-ahead log and its shared-memory index
            if (str_ends_with($file->getFilename(), '-wal') || str_ends_with($file->getFilename(), '-shm')) {
                $wal += $size;
            }
        }

        $documents = null;

        // Loupe keeps its documents in loupe.db; tickets.db holds no documents table
        if (is_file($path.'/loupe.db')) {
            $documents = (int) $scalar('sqlite:'.$path.'/loupe.db', 'SELECT COUNT(*) FROM documents');
        }

        if (str_ends_with($path, '.json')) {
            $decoded = json_decode((string) file_get_contents($path), true);
            $documents = is_array($decoded) ? count($decoded) : null;
        }

        $indexes[basename(dirname($path)).'/'.basename($path)] = [
            'documents' => $documents,
            'bytes_with_wal' => $bytes,
            'bytes_without_wal' => $bytes - $wal,
        ];
    }

    ksort($indexes);

    $results = [
        'measured_at' => gmdate('c'),
        'php' => PHP_VERSION,
        'sqlite' => $scalar('sqlite::memory:', 'SELECT sqlite_version()'),
        'os' => php_uname('s').' '.php_uname('r'),
        'packages' => $packages,
        'manifest' => json_decode((string) file_get_contents($proxy.'/manifest.json'), true),
        'stache_warm' => $warm,
        'search_update_all' => $update + ['peak_memory_bytes' => memory_get_peak_usage(true), 'peak_memory_before_bytes' => $memoryBefore],
        'indexes' => $indexes,
        'total_bytes_with_wal' => array_sum(array_column($indexes, 'bytes_with_wal')),
        'total_bytes_without_wal' => array_sum(array_column($indexes, 'bytes_without_wal')),
    ];

    file_put_contents($proxy.'/results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    fwrite(STDOUT, json_encode(array_diff_key($results, ['manifest' => true]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    exit($update['exit'] === 0 && $warm['exit'] === 0 ? 0 : 1);
} catch (Throwable $throwable) {
    fwrite(STDERR, sprintf("%s: %s at %s:%d\n", $throwable::class, $throwable->getMessage(), $throwable->getFile(), $throwable->getLine()));
    exit(1);
}
