<?php

declare(strict_types=1);

use App\Services\EnumGeneration\County\CountyEnumGenerator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * `CountyEnumGenerator::generate()` end to end (UAMS-Web/uams-statamic#887): it
 * fetches TIGERweb layer 1 and then layer 55 through the closure it was built
 * with, refuses to write when layer 1 fails, merges layer 55 only when that fetch
 * succeeds, and writes the rendered enum, with its data-source lines, to a
 * timestamped path under `app/Enums`. `File::put()` is captured, so nothing is
 * written to disk and nothing reaches the network.
 *
 * @command  php artisan test --compact tests/Feature/Services/EnumGeneration/CountyEnumGeneratorGenerateTest.php
 */
uses(TestCase::class);

covers(CountyEnumGenerator::class);

/**
 * Capture the one `File::put()` the generator makes, instead of writing to disk.
 *
 * @return object{path: ?string, php: ?string}
 */
function countyGenerateCapturedWrite(): object
{
    $written = (object) ['path' => null, 'php' => null];
    File::partialMock()->shouldReceive('put')->once()->andReturnUsing(static function (string $path, string $php) use ($written): int {
        $written->path = $path;
        $written->php = $php;

        return \strlen($php);
    });

    return $written;
}

/**
 * A generator whose TIGERweb fetch returns `$byLayer[$layer]`, recording each layer asked for.
 *
 * @param  array<int, ?array<int, array<string, string>>>  $byLayer
 * @param  list<int>  $layers
 */
function countyGenerateGenerator(array $byLayer, array &$layers): CountyEnumGenerator
{
    return new CountyEnumGenerator(static function (int $layer) use ($byLayer, &$layers): ?array {
        $layers[] = $layer;

        return $byLayer[$layer] ?? null;
    });
}

it('fetches layers 1 and 55, merges the new layer 55 county, and writes the enum', function (): void {
    $layers = [];
    $written = countyGenerateCapturedWrite();

    $path = countyGenerateGenerator([
        1 => [['STATE' => '05', 'NAME' => 'Pulaski County', 'GEOID' => '05119']],
        55 => [
            ['STATE' => '05', 'NAME' => 'Pulaski County (layer 55)', 'GEOID' => '05119'],
            ['STATE' => '05', 'NAME' => 'Saline County', 'GEOID' => '05125'],
        ],
    ], $layers)->generate();

    // GeneratorUtils builds the path the same way, so it matches on Windows too
    $prefix = app_path('Enums/County_generated_');
    assert($prefix !== '');

    expect($layers)->toBe([1, 55])
        ->and($path)->toBe($written->path)
        ->and($path)->toStartWith($prefix)
        ->and($path)->toEndWith('.php')
        ->and($written->php)->toContain("case AR_PULASKI_COUNTY = 'AR_PULASKI_COUNTY';")
        ->and($written->php)->toContain("case AR_SALINE_COUNTY = 'AR_SALINE_COUNTY';")
        ->and($written->php)->not->toContain('layer 55)')
        ->and($written->php)->toContain(' * Total counties: 2');
});

it('writes the layer 1 counties alone when the layer 55 fetch fails', function (): void {
    $layers = [];
    $written = countyGenerateCapturedWrite();

    countyGenerateGenerator([1 => [['STATE' => '05', 'NAME' => 'Pulaski County', 'GEOID' => '05119']]], $layers)->generate();

    expect($layers)->toBe([1, 55])
        ->and($written->php)->toContain("case AR_PULASKI_COUNTY = 'AR_PULASKI_COUNTY';")
        ->and($written->php)->toContain(' * Total counties: 1');
});

it('refuses to write, and asks for nothing else, when the layer 1 fetch fails', function (): void {
    $layers = [];
    File::partialMock()->shouldReceive('put')->never();

    $generator = countyGenerateGenerator([55 => [['STATE' => '05', 'NAME' => 'Saline County', 'GEOID' => '05125']]], $layers);

    expect(fn (): string => $generator->generate())->toThrow(RuntimeException::class, 'Failed to fetch TIGERweb layer 1 counties')
        ->and($layers)->toBe([1]);
});

it('embeds the TIGERweb data-source lines in the generated docblock', function (): void {
    $layers = [];
    $written = countyGenerateCapturedWrite();

    countyGenerateGenerator([1 => [], 55 => []], $layers)->generate();

    $fields = 'outFields=STATE,COUNTY,COUNTYNS,NAME,COUNTYCC,FUNCSTAT,GEOID,MTFCC,OID,BASENAME,LSADC,AREALAND,AREAWATER,OBJECTID,CENTLAT,CENTLON,INTPTLAT,INTPTLON&returnGeometry=false&f=json';

    expect($written->php)->toContain(implode("\n", [
        ' * Data Source:',
        ' * - TIGERweb/State_County (MapServer) API, Counties (or statistically equivalent entities):',
        ' * -   - Layer 1 (January 1, 2025 vintage):',
        ' * -     https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/State_County/MapServer/1/query?where=1%3D1&'.$fields,
        ' * -   - Layer 55 (2020 Census - January 1, 2020 vintage):',
        ' * -     https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/State_County/MapServer/55/query?where=1%3D1&'.$fields,
        ' *',
    ]));
});
