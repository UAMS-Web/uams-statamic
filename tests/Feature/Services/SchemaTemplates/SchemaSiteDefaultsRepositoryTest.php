<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaSiteDefaultsRepository;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Site;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * `SchemaSiteDefaultsRepository` reads and writes each site's Schema Templates defaults in
 * `resources/addons/schema-templates.yaml` (#255 coverage batch 12).
 *
 * The repository writes under `base_path()`, so every test points the base path at a
 * directory of its own and the repository's real file is never read or written.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaSiteDefaultsRepositoryTest.php
 */
beforeEach(function (): void {
    $this->siteDefaultsBase = storage_path('framework/testing/schema-site-defaults-'.uniqid('', true));
    File::ensureDirectoryExists($this->siteDefaultsBase);

    // Keep storage where it is; only base_path() moves
    $this->app->useStoragePath(storage_path());
    $this->app->setBasePath($this->siteDefaultsBase);
});

afterEach(function (): void {
    File::deleteDirectory($this->siteDefaultsBase);
});

/**
 * The defaults file under the test's base path.
 */
function schemaSiteDefaultsFile(): string
{
    return base_path('resources/addons/schema-templates.yaml');
}

/**
 * Write the defaults file by hand.
 */
function schemaSiteDefaultsWrite(string $contents): void
{
    File::ensureDirectoryExists(dirname(schemaSiteDefaultsFile()));
    File::put(schemaSiteDefaultsFile(), $contents);
}

it('reads the base path it was pointed at, not the repository\'s file', function (): void {
    expect(base_path())->toBe($this->siteDefaultsBase)
        ->and(File::exists(schemaSiteDefaultsFile()))->toBeFalse();
});

it('returns no defaults when the file does not exist', function (): void {
    expect((new SchemaSiteDefaultsRepository)->get('news'))->toBe([]);
});

it('reads one site\'s defaults', function (): void {
    schemaSiteDefaultsWrite(Yaml::dump(['site_defaults' => [
        'news' => ['enabled' => true, 'template' => 'news'],
        'default' => ['enabled' => false],
    ]]));

    $repository = new SchemaSiteDefaultsRepository;

    expect($repository->get('news'))->toBe(['enabled' => true, 'template' => 'news'])
        ->and($repository->get())->toBe(['enabled' => false])
        ->and($repository->get('health'))->toBe([]);
});

it('returns no defaults for a file whose shape is not a map of site maps', function (string $contents): void {
    schemaSiteDefaultsWrite($contents);

    expect((new SchemaSiteDefaultsRepository)->get('news'))->toBe([]);
})->with([
    'a scalar file' => ['just text'],
    'an empty file' => [''],
    'site defaults that are not a map' => ["site_defaults: on\n"],
    'a site entry that is not a map' => ["site_defaults:\n  news: on\n"],
]);

it('reads and writes under the default site when multisite is off', function (): void {
    schemaSiteDefaultsWrite(Yaml::dump(['site_defaults' => ['default' => ['enabled' => true], 'news' => ['enabled' => false]]]));
    Site::partialMock()->shouldReceive('multiEnabled')->andReturnFalse();

    $repository = new SchemaSiteDefaultsRepository;

    expect($repository->get('news'))->toBe(['enabled' => true]);

    $repository->put('news', ['template' => 'saved']);

    expect(Yaml::parseFile(schemaSiteDefaultsFile()))->toBe(['site_defaults' => [
        'default' => ['template' => 'saved'],
        'news' => ['enabled' => false],
    ]]);
});

it('saves a site\'s defaults, creating the directory and keeping the other sites and keys', function (): void {
    $repository = new SchemaSiteDefaultsRepository;

    $repository->put('news', ['enabled' => true, 'template' => 'news']);

    expect(Yaml::parseFile(schemaSiteDefaultsFile()))->toBe(['site_defaults' => ['news' => ['enabled' => true, 'template' => 'news']]]);

    schemaSiteDefaultsWrite(Yaml::dump(['other_key' => 'kept', 'site_defaults' => ['news' => ['enabled' => true]]]));
    $repository->put('health', ['enabled' => false]);

    expect(Yaml::parseFile(schemaSiteDefaultsFile()))->toBe(['other_key' => 'kept', 'site_defaults' => [
        'news' => ['enabled' => true],
        'health' => ['enabled' => false],
    ]]);
});

it('adds the site defaults map to a file that has none', function (): void {
    schemaSiteDefaultsWrite(Yaml::dump(['other_key' => 'kept']));

    (new SchemaSiteDefaultsRepository)->put('news', ['enabled' => true]);

    expect(Yaml::parseFile(schemaSiteDefaultsFile()))->toBe(['other_key' => 'kept', 'site_defaults' => ['news' => ['enabled' => true]]]);
});

it('memoizes the parsed file for the request until a put clears it', function (): void {
    schemaSiteDefaultsWrite(Yaml::dump(['site_defaults' => ['news' => ['enabled' => true]]]));
    $repository = new SchemaSiteDefaultsRepository;

    expect($repository->get('news'))->toBe(['enabled' => true]);

    // A change written behind the repository's back is not seen until the memo is cleared
    schemaSiteDefaultsWrite(Yaml::dump(['site_defaults' => ['news' => ['enabled' => false], 'health' => ['enabled' => true]]]));

    expect($repository->get('news'))->toBe(['enabled' => true])
        ->and($repository->get('health'))->toBe([]);

    // put() starts from the memoized parse too, so it writes back the copy it read first:
    // the change made behind its back is overwritten, and only then is the memo cleared
    $repository->put('default', ['enabled' => true]);

    expect(Yaml::parseFile(schemaSiteDefaultsFile()))->toBe(['site_defaults' => [
        'news' => ['enabled' => true],
        'default' => ['enabled' => true],
    ]]);

    schemaSiteDefaultsWrite(Yaml::dump(['site_defaults' => ['news' => ['enabled' => false]]]));

    expect($repository->get('news'))->toBe(['enabled' => false]);
});

it('creates the directory with mode 0755 and writes YAML inlined below four levels, indented by two', function (): void {
    (new SchemaSiteDefaultsRepository)->put('news', ['enabled' => true, 'params' => ['a' => ['b' => ['c' => 1]]]]);

    $dir = dirname(schemaSiteDefaultsFile());

    // 0755, less the process umask. Windows has no POSIX mode to set, and PHP
    // reports every directory there as 0777, so the mode is checked elsewhere.
    if (PHP_OS_FAMILY !== 'Windows') {
        expect(fileperms($dir) & 0777)->toBe(0755 & ~umask());
    }

    expect(File::get(schemaSiteDefaultsFile()))->toBe(<<<'YAML'
            site_defaults:
              news:
                enabled: true
                params:
                  a: { b: { c: 1 } }

            YAML);
});
