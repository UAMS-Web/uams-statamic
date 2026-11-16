<?php

declare(strict_types=1);

use App\Console\Commands\GenerateCountyEnum;
use App\Console\Commands\GenerateStateEnum;
use App\Services\EnumGeneration\County\CountyEnumGenerator;
use App\Services\EnumGeneration\State\StateEnumGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The `generate:state-enum` and `generate:county-enum` commands: each announces the
 * TIGERweb fetch, runs its generator, and reports the file written or the
 * generator's error with exit code 1.
 *
 * The real generators run, built with their TIGERweb fetch replaced by a closure
 * and bound into the container the command resolves them from, so nothing reaches
 * the network. `File::put()` is captured, so nothing is written to `app/Enums`.
 *
 * @command  php artisan test --compact tests/Feature/Console/GenerateTigerWebEnumCommandsTest.php
 */
uses(TestCase::class);

covers(GenerateStateEnum::class, GenerateCountyEnum::class);

/**
 * Capture the one `File::put()` a generator makes, instead of writing to disk.
 *
 * @return object{path: ?string, php: ?string}
 */
function tigerWebEnumCommandCapturedWrite(): object
{
    $written = (object) ['path' => null, 'php' => null];
    File::partialMock()->shouldReceive('put')->once()->andReturnUsing(static function (string $path, string $php) use ($written): int {
        $written->path = $path;
        $written->php = $php;

        return \strlen($php);
    });

    return $written;
}

it('writes the state enum from the TIGERweb records and reports the path', function (): void {
    $layers = [];
    app()->instance(StateEnumGenerator::class, new StateEnumGenerator(static function (int $layer) use (&$layers): array {
        $layers[] = $layer;

        return [['STUSAB' => 'AR', 'NAME' => 'Arkansas', 'BASENAME' => 'Arkansas', 'STATE' => '05', 'GEOID' => '05']];
    }));
    $written = tigerWebEnumCommandCapturedWrite();

    // Artisan::call() keeps the output for reading after the run, when the path is known
    expect(Artisan::call('generate:state-enum'))->toBe(0);
    $output = Artisan::output();

    expect($layers)->toBe([0])
        ->and($written->php)->toContain("case AR = 'AR';")
        ->and($output)->toStartWith('Fetching TIGERweb state attributes...'.PHP_EOL)
        ->and($output)->toContain('Wrote '.$written->path.PHP_EOL);
});

it('reports a failed state fetch and exits 1 without writing', function (): void {
    app()->instance(StateEnumGenerator::class, new StateEnumGenerator(static fn (int $layer): ?array => null));
    File::partialMock()->shouldReceive('put')->never();

    pendingArtisan('generate:state-enum')
        ->expectsOutput('Fetching TIGERweb state attributes...')
        ->expectsOutput('Failed to fetch TIGERweb states')
        ->doesntExpectOutputToContain('Wrote ')
        ->assertExitCode(1);
});

it('writes the county enum from the TIGERweb records and reports the path', function (): void {
    $layers = [];
    app()->instance(CountyEnumGenerator::class, new CountyEnumGenerator(static function (int $layer) use (&$layers): ?array {
        $layers[] = $layer;

        return $layer === 1
            ? [['NAME' => 'Pulaski County', 'BASENAME' => 'Pulaski', 'STATE' => '05', 'COUNTY' => '119', 'GEOID' => '05119']]
            : null;
    }));
    $written = tigerWebEnumCommandCapturedWrite();

    expect(Artisan::call('generate:county-enum'))->toBe(0);
    $output = Artisan::output();

    expect($layers)->toBe([1, 55])
        ->and($written->php)->toContain('05119')
        ->and($output)->toStartWith('Fetching TIGERweb county data...'.PHP_EOL)
        ->and($output)->toContain('Wrote '.$written->path.PHP_EOL);
});

it('reports a failed county fetch and exits 1 without writing', function (): void {
    app()->instance(CountyEnumGenerator::class, new CountyEnumGenerator(static fn (int $layer): ?array => null));
    File::partialMock()->shouldReceive('put')->never();

    pendingArtisan('generate:county-enum')
        ->expectsOutput('Fetching TIGERweb county data...')
        ->expectsOutput('Failed to fetch TIGERweb layer 1 counties')
        ->doesntExpectOutputToContain('Wrote ')
        ->assertExitCode(1);
});
