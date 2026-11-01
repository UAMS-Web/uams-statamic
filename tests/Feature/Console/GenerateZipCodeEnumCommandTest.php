<?php

declare(strict_types=1);

use App\Console\Commands\GenerateZipCodeEnum;
use App\Services\EnumGeneration\ZipCode\ZipCodeEnumGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The `generate:zip-enum` command: it turns `--limit` into the generator's record
 * limit, reports the file the generator wrote, and turns a generator failure into
 * an error and exit code 1.
 *
 * The real {@see ZipCodeEnumGenerator} runs. It
 * reads HUD records from the cache key its limit selects, so each case seeds that
 * key, and the HUD token is cleared so a cache miss writes an empty enum instead of
 * reaching the network. `File::put()` is captured, so nothing is written to `app/Enums`.
 *
 * @command  php artisan test --compact tests/Feature/Console/GenerateZipCodeEnumCommandTest.php
 */
uses(TestCase::class);

covers(GenerateZipCodeEnum::class);

beforeEach(function (): void {
    // The generator caches HUD records; keep that in memory, not in the shared file cache
    config(['cache.default' => 'array']);

    // No token: a cache miss renders an empty enum rather than calling HUD
    config(['services.hud.usps_api_token' => null]);
});

/**
 * The command parameters for a `--limit` value, or none when it is null.
 *
 * @return array<string, string>
 */
function zipEnumCommandParameters(?string $limit): array
{
    return $limit === null ? [] : ['--limit' => $limit];
}

/**
 * Capture the one `File::put()` the generator makes, instead of writing to disk.
 *
 * @return object{path: ?string, php: ?string}
 */
function zipEnumCommandCapturedWrite(): object
{
    $written = (object) ['path' => null, 'php' => null];
    File::partialMock()->shouldReceive('put')->once()->andReturnUsing(static function (string $path, string $php) use ($written): int {
        $written->path = $path;
        $written->php = $php;

        return \strlen($php);
    });

    return $written;
}

it('passes the --limit it is given to the generator and reports the written file', function (?string $limit, string $cacheKey): void {
    cache()->put($cacheKey, [
        ['zip' => '72205', 'city' => 'Little Rock', 'state' => 'AR', 'state_geoId' => '05', 'county_geoId' => ['05119']],
    ]);
    $written = zipEnumCommandCapturedWrite();

    pendingArtisan('generate:zip-enum', zipEnumCommandParameters($limit))
        ->expectsOutput('Reading ZIP codes from HUD USPS Crosswalk API...')
        ->expectsOutputToContain('Wrote ')
        ->assertExitCode(0);

    // The seeded records were read, so the limit selected this cache key
    expect($written->php)->toContain("case ZIP_72205 = 'ZIP_72205';")
        ->and($written->php)->toContain('Total ZIP records: 1')
        ->and(str_replace('\\', '/', (string) $written->path))->toMatch('#/app/Enums/ZipCode_generated_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.php$#');
})->with([
    'no limit' => [null, 'hud_zip_comprehensive_data'],
    'a limit' => ['2', 'hud_zip_comprehensive_data_limit_2'],
    'an empty limit means no limit' => ['', 'hud_zip_comprehensive_data'],
]);

it('does not read the unlimited records when a limit is given', function (): void {
    cache()->put('hud_zip_comprehensive_data', [
        ['zip' => '72205', 'city' => 'Little Rock', 'state' => 'AR', 'state_geoId' => '05', 'county_geoId' => ['05119']],
    ]);
    $written = zipEnumCommandCapturedWrite();

    pendingArtisan('generate:zip-enum', ['--limit' => '5'])->assertExitCode(0);

    // The limited key is empty and there is no token, so the enum has no cases
    expect($written->php)->toContain('Total ZIP records: 0')
        ->and($written->php)->not->toContain('case ZIP_');
});

it('reports the exact path the generator wrote', function (): void {
    cache()->put('hud_zip_comprehensive_data', []);
    $written = zipEnumCommandCapturedWrite();

    // Artisan::call() keeps the output for reading after the run, when the path is known
    expect(Artisan::call('generate:zip-enum'))->toBe(0)
        ->and(Artisan::output())->toContain('Wrote '.$written->path.PHP_EOL);
});

it('turns a generator failure into an error and exit code 1', function (): void {
    cache()->put('hud_zip_comprehensive_data', []);
    File::partialMock()->shouldReceive('put')->once()->andThrow(new RuntimeException('disk is read-only'));

    pendingArtisan('generate:zip-enum')
        ->expectsOutput('disk is read-only')
        ->doesntExpectOutputToContain('Wrote ')
        ->assertExitCode(1);
});

/**
 * Bind a generator whose HUD download is a closure that counts its calls and
 * answers every state with one ZIP record.
 *
 * @param  list<string>  $calls
 */
function zipEnumCommandCountingGenerator(array &$calls): void
{
    app()->instance(ZipCodeEnumGenerator::class, new ZipCodeEnumGenerator(
        static function (string $url, string $path, array $headers, int $timeout) use (&$calls): bool {
            $calls[] = $url;
            file_put_contents($path, json_encode(['data' => ['year' => '2024', 'results' => [
                ['zip' => (string) (10000 + \count($calls)), 'city' => 'Town', 'state' => 'AR', 'geoid' => '05119'],
            ]]]));

            return true;
        },
        static function (int $microseconds): void {}
    ));
}

it('fetches every state when no limit, or an empty one, is given', function (?string $limit): void {
    // A token and an empty cache, so the generator downloads from the fake HUD
    config(['services.hud.usps_api_token' => 'secret-token']);
    $calls = [];
    zipEnumCommandCountingGenerator($calls);
    zipEnumCommandCapturedWrite();

    pendingArtisan('generate:zip-enum', zipEnumCommandParameters($limit))->assertExitCode(0);

    // With no limit the generator walks every state; a limit of 0 would stop after the first
    expect(\count($calls))->toBeGreaterThan(50);
})->with([
    'no limit' => [null],
    'an empty limit' => [''],
]);

it('stops after the first state for a limit of 0', function (): void {
    config(['services.hud.usps_api_token' => 'secret-token']);
    $calls = [];
    zipEnumCommandCountingGenerator($calls);
    zipEnumCommandCapturedWrite();

    pendingArtisan('generate:zip-enum', ['--limit' => '0'])->assertExitCode(0);

    expect($calls)->toHaveCount(1);
});
