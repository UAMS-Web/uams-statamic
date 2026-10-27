<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Support\ThrowawayRoot;
use Tests\TestCase;

/**
 * Every pin in `phpunit.xml` wins over a value the shell already exports
 * (UAMS-Web/uams-statamic-archive#2981, UAMS-Web/uams-statamic-archive#3031).
 *
 * PHPUnit leaves an existing variable alone unless an `<env>` entry is forced,
 * and Laravel's `env()` reads `$_SERVER` before `$_ENV` or `getenv()`, so a pin
 * binds only with both a forced `<env>` entry and a `<server>` entry, which
 * PHPUnit always writes. UAMS-Web/uams-statamic-archive#2981 bound `CACHE_STORE` that way after finding it
 * pinned under a name nothing read; UAMS-Web/uams-statamic-archive#3031 measured `DB_DATABASE`, `MAIL_MAILER`
 * and `QUEUE_CONNECTION` giving way to exported values and bound the rest.
 *
 * These cases run a child process with a value exported for every pinned name,
 * apply the `<php>` section with PHPUnit's own handler, and read what the
 * booted application resolves. Copies of the configuration missing the server
 * entry of one pin at a time are the controls: each lets its exported value
 * through, which shows the probe can see an unbound pin. The last case sweeps
 * every pinned name for a reader, so a renamed variable cannot leave a dead pin
 * behind again.
 *
 * A grandchild the child spawns with the inherited environment reads each pin
 * too, because that is what a test's own child process sees. A pin written as
 * `false` reaches it as an empty string, and on Windows not at all when the
 * shell exported the name, so no pin is written as `false` (UAMS-Web/uams-statamic-archive#3303).
 *
 * @command  php artisan test --compact tests/Feature/Config/PhpunitEnvironmentPinsTest.php
 */
uses(TestCase::class);

/**
 * The value an exported variable carries in these cases, for one pinned name.
 */
const EXPORTED_BY_SHELL = 'exported-by-shell';

/**
 * Read the pins a PHPUnit configuration's `<php>` section declares.
 *
 * @param  string  $phpunitXml  Absolute path of the PHPUnit configuration to read.
 * @return array{env: array<string, array{value: string, forced: bool}>, server: array<string, string>}
 *                                                                                                      Each `<env>` entry by name, and each `<server>` entry's value by name.
 */
function phpunitPins(string $phpunitXml): array
{
    $xml = simplexml_load_file($phpunitXml);
    if (! $xml instanceof SimpleXMLElement) {
        throw new RuntimeException("{$phpunitXml} could not be parsed.");
    }

    $pins = ['env' => [], 'server' => []];
    foreach ($xml->xpath('/phpunit/php/env') ?: [] as $entry) {
        $pins['env'][(string) $entry['name']] = [
            'value' => (string) $entry['value'],
            'forced' => (string) $entry['force'] === 'true',
        ];
    }
    foreach ($xml->xpath('/phpunit/php/server') ?: [] as $entry) {
        $pins['server'][(string) $entry['name']] = (string) $entry['value'];
    }

    return $pins;
}

/**
 * Boot the application's environment and configuration in a child process
 * after applying a PHPUnit configuration's `<php>` section, and report what
 * each named variable and a few pinned configuration values resolve to, and
 * what a grandchild process inheriting the child's environment reads for each.
 *
 * @param  string  $phpunitXml  Absolute path of the PHPUnit configuration to apply.
 * @param  array<string, string|false>  $environment  Variables to export to the child,
 *                                                    `false` to remove one.
 * @param  list<string>  $names  Variables to report.
 * @return array{env: array<string, mixed>, getenv: array<string, mixed>, config: array<string, mixed>, grandchild: array<string, mixed>}
 *                                                                                                                                        The raw value Laravel's environment repository returns and `getenv()` for each name, the configuration values, and `getenv()` in a grandchild for each name.
 */
function environmentSeenByChild(string $phpunitXml, array $environment, array $names): array
{
    // Build a script that applies the configuration the way PHPUnit does, then
    // loads only the environment and configuration bootstrap classes.
    $script = <<<'PHP'
        require $argv[1].'/vendor/autoload.php';
        $configuration = (new PHPUnit\TextUI\XmlConfiguration\Loader)->load($argv[2]);
        (new PHPUnit\TextUI\Configuration\PhpHandler)->handle($configuration->php());
        $app = require $argv[1].'/bootstrap/app.php';
        $app->bootstrapWith([
            Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
            Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
        ]);
        $seen = ['env' => [], 'getenv' => [], 'config' => []];
        foreach (json_decode($argv[3], true) as $name) {
            $seen['env'][$name] = Illuminate\Support\Env::getRepository()->get($name);
            $seen['getenv'][$name] = getenv($name);
        }
        foreach (['cache.default', 'database.default', 'database.connections.sqlite.database', 'mail.default', 'queue.default', 'session.driver', 'statamic.stache.lock.enabled', 'link_health.on_publish.enabled', 'maps.census.enabled'] as $key) {
            $seen['config'][$key] = $app['config']->get($key);
        }
        $grandchild = proc_open([PHP_BINARY, '-r', 'echo json_encode(array_map("getenv", json_decode($argv[1], true)));', '--', $argv[3]], [1 => ['pipe', 'w']], $pipes);
        $seen['grandchild'] = array_combine(json_decode($argv[3], true), json_decode(stream_get_contents($pipes[1]), true));
        fclose($pipes[1]);
        proc_close($grandchild);
        echo json_encode($seen);
        PHP;

    // Run it from the repository root with the requested variables exported.
    // Diagnostics go to stderr: an exported APP_TIMEZONE that is not a zone
    // makes the configuration bootstrap warn, which must not corrupt the JSON.
    $process = new Process([PHP_BINARY, '-d', 'display_errors=stderr', '-r', $script, '--', base_path(), $phpunitXml, json_encode($names, JSON_THROW_ON_ERROR)], base_path(), $environment);
    $process->mustRun();

    /** @var array{env: array<string, mixed>, getenv: array<string, mixed>, config: array<string, mixed>, grandchild: array<string, mixed>} */
    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Run the probe against a copy of `phpunit.xml` with one exact piece of text
 * replaced, written to git-ignored test scratch named per process so parallel
 * workers cannot collide.
 *
 * @param  string  $from  Text that must appear exactly once in `phpunit.xml`.
 * @param  string  $to  Its replacement.
 * @param  array<string, string|false>  $environment  Variables to export to the child.
 * @param  list<string>  $names  Variables to report.
 * @return array{env: array<string, mixed>, getenv: array<string, mixed>, config: array<string, mixed>, grandchild: array<string, mixed>}
 */
function environmentSeenByChildWithout(string $from, string $to, array $environment, array $names): array
{
    $xml = (string) file_get_contents(base_path('phpunit.xml'));
    expect(substr_count($xml, $from))->toBe(1);

    $path = storage_path('framework/testing/phpunit-pin-'.ThrowawayRoot::segment().'.xml');
    file_put_contents($path, str_replace($from, $to, $xml));

    try {
        return environmentSeenByChild($path, $environment, $names);
    } finally {
        unlink($path);
    }
}

/**
 * The text a pin leaves in the process environment, which `getenv()` reads and
 * a spawned child inherits. PHPUnit's loader turns `true` into a boolean before
 * writing it with `putenv()`, so it arrives as `1`. Laravel reads the server
 * entry first, so `env()` still sees the boolean. No pin is written as `false`;
 * the case below says why.
 *
 * @param  string  $value  The pin's value as `phpunit.xml` writes it.
 */
function pinAsWrittenToTheProcessEnvironment(string $value): string
{
    return strtolower($value) === 'true' ? '1' : $value;
}

/**
 * The names `phpunit.xml` pins, read without the application, so a dataset can
 * list one case per pin.
 *
 * @return array<string, array{string}>
 */
function pinnedNamesDataset(): array
{
    $names = array_keys(phpunitPins(dirname(__DIR__, 3).'/phpunit.xml')['env']);

    return array_combine($names, array_map(fn (string $name): array => [$name], $names));
}

it('runs the suite on the file cache store', function () {
    expect(config('cache.default'))->toBe('file');
});

it('binds every pin with a forced env entry and a matching server entry', function () {
    $pins = phpunitPins(base_path('phpunit.xml'));
    expect($pins['env'])->toHaveKeys(['CACHE_STORE', 'DB_DATABASE', 'MAIL_MAILER', 'QUEUE_CONNECTION']);

    // Each pin, reported as the two halves of the binding it carries.
    $bindings = [];
    foreach ($pins['env'] as $name => $entry) {
        $bindings[$name] = ['forced' => $entry['forced'], 'server' => $pins['server'][$name] ?? null];
    }
    $expected = [];
    foreach ($pins['env'] as $name => $entry) {
        $expected[$name] = ['forced' => true, 'server' => $entry['value']];
    }
    expect($bindings)->toBe($expected);

    // A server entry with no env entry would bind this process and leave a
    // spawned child unpinned.
    expect(array_keys(array_diff_key($pins['server'], $pins['env'])))->toBe([]);
});

it('writes no pin as false or empty, which a spawned child could not read back', function () {
    $pins = phpunitPins(base_path('phpunit.xml'));
    $values = [...array_column($pins['env'], 'value'), ...array_values($pins['server'])];
    expect($values)->not->toBeEmpty();

    // PHPUnit writes `false` with putenv('NAME='), and on Windows that removes
    // a variable the shell exported, so a child reads the reader's default.
    $empty = array_values(array_filter($values, fn (string $value): bool => in_array(strtolower($value), ['false', ''], true)));
    expect($empty)->toBe([]);

    // The three switches that once carried `false` read 0, which every reader
    // casts to false with `(bool) env(...)`.
    expect(array_intersect_key($pins['server'], array_flip(['STATAMIC_STACHE_LOCK_ENABLED', 'LINK_HEALTH_SCAN_ON_PUBLISH', 'MAP_CENSUS_GEOCODER_ENABLED'])))->toBe([
        'STATAMIC_STACHE_LOCK_ENABLED' => '0',
        'LINK_HEALTH_SCAN_ON_PUBLISH' => '0',
        'MAP_CENSUS_GEOCODER_ENABLED' => '0',
    ]);
});

it('keeps every pinned value when the shell exports another', function (bool $export) {
    $pins = phpunitPins(base_path('phpunit.xml'))['env'];
    $names = array_keys($pins);
    $environment = array_fill_keys($names, $export ? EXPORTED_BY_SHELL : false);

    $seen = environmentSeenByChild(base_path('phpunit.xml'), $environment, $names);

    $pinned = array_map(fn (array $entry): string => $entry['value'], $pins);
    expect($seen['env'])->toBe($pinned);
    expect($seen['getenv'])->toBe(array_map(pinAsWrittenToTheProcessEnvironment(...), $pinned));
    expect($seen['grandchild'])->toBe($seen['getenv']);
    expect($seen['config'])->toBe([
        'cache.default' => 'file',
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'mail.default' => 'array',
        'queue.default' => 'sync',
        'session.driver' => 'array',
        'statamic.stache.lock.enabled' => false,
        'link_health.on_publish.enabled' => false,
        'maps.census.enabled' => false,
    ]);
})->with([
    'a different value exported for every pin' => [true],
    'nothing exported' => [false],
]);

it('lets an exported value through a pin without its server entry', function (string $name) {
    $value = phpunitPins(base_path('phpunit.xml'))['env'][$name]['value'];

    $seen = environmentSeenByChildWithout("\n        <server name=\"{$name}\" value=\"{$value}\"/>", '', [$name => EXPORTED_BY_SHELL], [$name]);

    // The exported value reaches env(); the forced env entry still reaches getenv().
    expect($seen['env'][$name])->toBe(EXPORTED_BY_SHELL);
    expect($seen['getenv'][$name])->toBe(pinAsWrittenToTheProcessEnvironment($value));
})->with(pinnedNamesDataset());

it('lets an exported CACHE_STORE through a plain unforced env entry', function () {
    $seen = environmentSeenByChildWithout(
        "<env name=\"CACHE_STORE\" value=\"file\" force=\"true\"/>\n        <server name=\"CACHE_STORE\" value=\"file\"/>",
        '<env name="CACHE_STORE" value="file"/>',
        ['CACHE_STORE' => 'array'],
        ['CACHE_STORE'],
    );

    expect($seen['config']['cache.default'])->toBe('array');
    expect($seen['getenv']['CACHE_STORE'])->toBe('array');
});

it('pins only variable names that something reads', function () {
    // Collect every variable name the configuration's <php> section sets.
    $xml = simplexml_load_file(base_path('phpunit.xml'));
    if (! $xml instanceof SimpleXMLElement) {
        throw new RuntimeException('phpunit.xml could not be parsed.');
    }
    $names = [];
    foreach ($xml->xpath('/phpunit/php/*[self::env or self::server]') ?: [] as $entry) {
        $names[] = (string) $entry['name'];
    }
    $names = array_values(array_unique($names));
    expect($names)->toContain('CACHE_STORE', 'DB_DATABASE');

    // Gather the sources that can read one: the app's code and config, and the
    // framework config Laravel merges beneath it.
    $sources = '';
    foreach (['app', 'bootstrap', 'config', 'routes', 'vendor/laravel/framework/config'] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $sources .= (string) file_get_contents($file->getPathname());
            }
        }
    }

    // Report each pinned name that no source reads by its quoted name.
    $unread = array_values(array_filter($names, fn (string $name): bool => ! str_contains($sources, "'{$name}'")));
    expect($unread)->toBe([]);

    // Show the sweep can see a dead name: the one UAMS-Web/uams-statamic-archive#2981 removed.
    expect(str_contains($sources, "'CACHE_DRIVER'"))->toBeFalse();
});
