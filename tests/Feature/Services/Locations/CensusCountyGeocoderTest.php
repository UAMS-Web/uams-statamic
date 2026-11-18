<?php

declare(strict_types=1);

use App\Services\Locations\CensusCountyGeocoder;
use App\Support\Http\OutboundUrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery\LegacyMockInterface;
use Tests\TestCase;

/**
 * Coverage for the Census county reverse-geocoder backing coordinate-derived
 * location regions (App\Services\Locations\CensusCountyGeocoder, UAMS-Web/uams-statamic-archive#962).
 *
 * Proves it: parses the county GEOID + name from a Census geographies response;
 * sends an identifying User-Agent with x=lng / y=lat; caches a resolved county
 * (so a repeated coordinate is not re-fetched) but does NOT cache a transient
 * failure (so a later save retries); routes through the SSRF egress guard; and
 * is a no-op when disabled. The external service and DNS are stubbed so the test
 * stays offline (Http::fake + OutboundUrlGuard::setResolver).
 *
 * Mutation-clean (#909): every mutant is killed except one equivalent, annotated in the
 * class. `parseCounty()`'s `! is_array($county)` early return (`RemoveEarlyReturn`) cannot
 * be killed: without it, `$county['GEOID'] ?? null` and `$county['NAME'] ?? null` read a
 * scalar entry as null, so the GEOID and name check returns null for the same input. The
 * "not an array" case below still pins that result.
 *
 * @command  php artisan test --compact tests/Feature/Services/Locations/CensusCountyGeocoderTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    config()->set('maps.census.enabled', true);
    config()->set('maps.census.endpoint', 'https://geocoding.geo.census.gov/geocoder/geographies/coordinates');
    config()->set('maps.census.user_agent', 'UAMS Census Test Agent');
    Cache::flush();
    OutboundUrlGuard::setResolver(fn (string $host): array => ['8.8.8.8']);
});

afterEach(function (): void {
    OutboundUrlGuard::setResolver(null);
    Cache::flush();
});

/**
 * Fake a Census geographies response returning a single county.
 */
function fakeCensusCounty(string $geoId, string $name): void
{
    Http::fake([
        'geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'geographies' => [
                    'Counties' => [
                        ['GEOID' => $geoId, 'NAME' => $name, 'STATE' => substr($geoId, 0, 2), 'COUNTY' => substr($geoId, 2)],
                    ],
                ],
            ],
        ], 200),
    ]);
}

it('reverse-geocodes a coordinate to its county with an identifying user-agent', function (): void {
    fakeCensusCounty('05119', 'Pulaski County');

    $county = (new CensusCountyGeocoder)->resolve(34.7465, -92.2896);

    expect($county)->toBe(['geoId' => '05119', 'name' => 'Pulaski County']);

    Http::assertSent(function (Request $request): bool {
        return str_starts_with($request->url(), 'https://geocoding.geo.census.gov/geocoder/geographies/coordinates')
            // x is the longitude (negative), y is the latitude (34.x) — never swapped.
            && str_contains($request->url(), 'x=-92')
            && str_contains($request->url(), 'y=34')
            && str_contains($request->url(), 'benchmark=Public_AR_Current')
            && str_contains($request->url(), 'format=json')
            && $request->header('User-Agent') === ['UAMS Census Test Agent'];
    });
});

it('caches a resolved county so a repeated coordinate is not re-fetched', function (): void {
    fakeCensusCounty('05119', 'Pulaski County');
    $geocoder = new CensusCountyGeocoder;

    expect($geocoder->resolve(34.7465, -92.2896))->toBe(['geoId' => '05119', 'name' => 'Pulaski County']);
    expect($geocoder->resolve(34.7465, -92.2896))->toBe(['geoId' => '05119', 'name' => 'Pulaski County']);

    Http::assertSentCount(1);
});

it('returns null when the coordinate resolves to no county', function (): void {
    Http::fake([
        'geocoding.geo.census.gov/*' => Http::response(['result' => ['geographies' => ['Counties' => []]]], 200),
    ]);

    expect((new CensusCountyGeocoder)->resolve(0.0, 0.0))->toBeNull();
});

it('does not cache a transient failure, so a later save retries', function (): void {
    Http::fake([
        'geocoding.geo.census.gov/*' => Http::response('upstream error', 500),
    ]);
    $geocoder = new CensusCountyGeocoder;

    expect($geocoder->resolve(34.7465, -92.2896))->toBeNull();
    expect($geocoder->resolve(34.7465, -92.2896))->toBeNull();

    Http::assertSentCount(2);
});

it('refuses to fetch when the configured endpoint targets an internal host (SSRF guard)', function (): void {
    config()->set('maps.census.endpoint', 'http://169.254.169.254/geocoder');
    Http::fake();

    expect((new CensusCountyGeocoder)->resolve(34.7465, -92.2896))->toBeNull();
    Http::assertNothingSent();
});

it('is a no-op returning null when coordinate-derived regions are disabled', function (): void {
    config()->set('maps.census.enabled', false);
    Http::fake();

    expect((new CensusCountyGeocoder)->resolve(34.7465, -92.2896))->toBeNull();
    Http::assertNothingSent();
});

/**
 * Fake the Census endpoint, recording each request's URL, headers and transfer
 * options, and answering every request with `$body`.
 *
 * @param  array<int, array{url: string, userAgent: array<int, string>, accept: array<int, string>, timeout: mixed, connectTimeout: mixed}>  $sent
 * @param  array<mixed>  $body
 */
function recordCensusRequests(array &$sent, array $body): void
{
    Http::fake(function (Request $request, array $options) use (&$sent, $body) {
        $sent[] = [
            'url' => $request->url(),
            'userAgent' => $request->header('User-Agent'),
            'accept' => $request->header('Accept'),
            'timeout' => $options['timeout'] ?? null,
            'connectTimeout' => $options['connect_timeout'] ?? null,
        ];

        return Http::response($body, 200);
    });
}

/**
 * A Census geographies body whose first county is `$county`.
 *
 * @return array<string, mixed>
 */
function censusBodyWithCounty(mixed $county): array
{
    return ['result' => ['geographies' => ['Counties' => [$county]]]];
}

/**
 * Remove one key from the `maps.census` config, so the geocoder reads its default.
 */
function forgetCensusConfig(string $key): void
{
    $census = config('maps.census');

    if (! \is_array($census)) {
        throw new RuntimeException('config/maps.php has no census section');
    }

    unset($census[$key]);
    config()->set('maps.census', $census);
}

it('builds the exact Census request URL from the configured endpoint and options', function (): void {
    config()->set('maps.census.benchmark', '  Public_AR_2020  ');
    config()->set('maps.census.vintage', 'Census2020_Census2020');
    $sent = [];
    recordCensusRequests($sent, censusBodyWithCounty(['GEOID' => '05119', 'NAME' => 'Pulaski County']));

    (new CensusCountyGeocoder)->resolve(34.7465, -92.2896);

    expect(array_column($sent, 'url'))->toBe([
        'https://geocoding.geo.census.gov/geocoder/geographies/coordinates'
        .'?x=-92.2896&y=34.7465&benchmark=Public_AR_2020&vintage=Census2020_Census2020&layers=Counties&format=json',
    ]);
});

it('falls back to the default benchmark and vintage when they are not configured or not scalar', function (): void {
    forgetCensusConfig('benchmark');
    config()->set('maps.census.vintage', ['not', 'a', 'string']);
    $sent = [];
    recordCensusRequests($sent, censusBodyWithCounty(['GEOID' => '05119', 'NAME' => 'Pulaski County']));

    (new CensusCountyGeocoder)->resolve(34.7465, -92.2896);

    expect($sent[0]['url'] ?? null)->toContain('&benchmark=Public_AR_Current&vintage=Current_Current&');
});

it('reads a non-string scalar config value as a string', function (): void {
    config()->set('maps.census.benchmark', 2020);
    $sent = [];
    recordCensusRequests($sent, censusBodyWithCounty(['GEOID' => '05119', 'NAME' => 'Pulaski County']));

    (new CensusCountyGeocoder)->resolve(34.7465, -92.2896);

    expect($sent[0]['url'] ?? null)->toContain('&benchmark=2020&');
});

it('sends the User-Agent, a JSON Accept header and the transfer timeouts', function (): void {
    config()->set('maps.census.timeout', 12);
    $sent = [];
    recordCensusRequests($sent, censusBodyWithCounty(['GEOID' => '05119', 'NAME' => 'Pulaski County']));

    (new CensusCountyGeocoder)->resolve(34.7465, -92.2896);

    expect($sent)->toHaveCount(1)
        ->and($sent[0]['userAgent'])->toBe(['UAMS Census Test Agent'])
        ->and($sent[0]['accept'])->toBe(['application/json'])
        ->and($sent[0]['timeout'])->toBe(12)
        ->and($sent[0]['connectTimeout'])->toBe(3);
});

it('derives the request timeout from config, at least one second', function (mixed $configured, int $expected): void {
    if ($configured === 'unset') {
        forgetCensusConfig('timeout');
    } else {
        config()->set('maps.census.timeout', $configured);
    }

    $sent = [];
    recordCensusRequests($sent, censusBodyWithCounty(['GEOID' => '05119', 'NAME' => 'Pulaski County']));

    (new CensusCountyGeocoder)->resolve(34.7465, -92.2896);

    expect($sent[0]['timeout'] ?? null)->toBe($expected);
})->with([
    'not configured' => ['unset', 8],
    'not numeric' => ['soon', 8],
    'zero raises to one' => [0, 1],
    'a numeric string truncates' => ['5.7', 5],
    'a larger value is kept' => [20, 20],
]);

it('builds a User-Agent from the app name and URL when none is configured', function (mixed $userAgent, mixed $appName, mixed $appUrl, string $expected): void {
    config()->set('maps.census.user_agent', $userAgent);
    config()->set('app.name', $appName);
    config()->set('app.url', $appUrl);
    $sent = [];
    recordCensusRequests($sent, censusBodyWithCounty(['GEOID' => '05119', 'NAME' => 'Pulaski County']));

    (new CensusCountyGeocoder)->resolve(34.7465, -92.2896);

    expect($sent[0]['userAgent'] ?? null)->toBe([$expected]);
})->with([
    'blank user agent' => ['', 'UAMS Health', 'https://uams.test', 'UAMS Health (https://uams.test)'],
    'non-scalar user agent' => [['agent'], 'UAMS Health', 'https://uams.test', 'UAMS Health (https://uams.test)'],
    'blank app name' => ['', '', 'https://uams.test', 'Statamic (https://uams.test)'],
    'non-scalar app URL' => ['', 'UAMS Health', ['https://uams.test'], 'UAMS Health ()'],
]);

it('is enabled when the switch is not configured, and reads a numeric switch as a boolean', function (mixed $enabled, int $requests): void {
    if ($enabled === 'unset') {
        forgetCensusConfig('enabled');
    } else {
        config()->set('maps.census.enabled', $enabled);
    }

    $sent = [];
    recordCensusRequests($sent, censusBodyWithCounty(['GEOID' => '05119', 'NAME' => 'Pulaski County']));

    (new CensusCountyGeocoder)->resolve(34.7465, -92.2896);

    expect($sent)->toHaveCount($requests);
})->with([
    'not configured' => ['unset', 1],
    'one' => [1, 1],
    'zero' => [0, 0],
]);

it('caches a resolved county under the rounded coordinate key, for the configured TTL', function (mixed $configured, int $ttl): void {
    if ($configured === 'unset') {
        forgetCensusConfig('cache_ttl');
    } else {
        config()->set('maps.census.cache_ttl', $configured);
    }

    $sent = [];
    recordCensusRequests($sent, censusBodyWithCounty(['GEOID' => '05119', 'NAME' => 'Pulaski County']));
    /** @var LegacyMockInterface $cache */
    $cache = Cache::spy();

    (new CensusCountyGeocoder)->resolve(34.7465123, -92.2896789);

    $cache->shouldHaveReceived('put')->once()->with(
        'maps:census:county:34.74651,-92.28968',
        ['geoId' => '05119', 'name' => 'Pulaski County'],
        $ttl,
    );
})->with([
    'not configured' => ['unset', 2592000],
    'not numeric' => ['forever', 2592000],
    'negative raises to zero' => [-5, 0],
    'a numeric string truncates' => ['60.9', 60],
    'a positive value is kept' => [60, 60],
]);

it('caches a confirmed absence, so the coordinate is not fetched again', function (): void {
    $sent = [];
    recordCensusRequests($sent, ['result' => ['geographies' => ['Counties' => []]]]);
    $geocoder = new CensusCountyGeocoder;

    expect($geocoder->resolve(34.7465, -92.2896))->toBeNull()
        ->and($geocoder->resolve(34.7465, -92.2896))->toBeNull()
        ->and(Cache::get('maps:census:county:34.7465,-92.2896'))->toBe('none')
        ->and($sent)->toHaveCount(1);
});

it('shares a cache entry between coordinates equal to five decimal places, and only those', function (float $firstLatitude, float $firstLongitude, float $secondLatitude, float $secondLongitude, int $requests): void {
    $sent = [];
    recordCensusRequests($sent, censusBodyWithCounty(['GEOID' => '05119', 'NAME' => 'Pulaski County']));
    $geocoder = new CensusCountyGeocoder;

    $geocoder->resolve($firstLatitude, $firstLongitude);
    $geocoder->resolve($secondLatitude, $secondLongitude);

    expect($sent)->toHaveCount($requests);
})->with([
    'latitude differs at the sixth place' => [34.746501, -92.28961, 34.746502, -92.28961, 1],
    'longitude differs at the sixth place' => [34.74651, -92.289601, 34.74651, -92.289602, 1],
    'latitude differs at the fifth place' => [34.74651, -92.28961, 34.74652, -92.28961, 2],
    'longitude differs at the fifth place' => [34.74651, -92.28961, 34.74651, -92.28962, 2],
]);

it('returns null for a cached value that is not a county', function (mixed $cached): void {
    Cache::put('maps:census:county:34.7465,-92.2896', $cached, 60);
    Http::fake();

    expect((new CensusCountyGeocoder)->resolve(34.7465, -92.2896))->toBeNull();
    Http::assertNothingSent();
})->with([
    'a string other than the sentinel' => ['garbage'],
    'a number' => [42],
    'a non-string GEOID' => [['geoId' => 5119, 'name' => 'Pulaski County']],
    'a non-string name' => [['geoId' => '05119', 'name' => 7]],
    'an empty GEOID' => [['geoId' => '', 'name' => 'Pulaski County']],
    'an empty name' => [['geoId' => '05119', 'name' => '']],
]);

it('returns a valid cached county without fetching', function (): void {
    Cache::put('maps:census:county:34.7465,-92.2896', ['geoId' => '05119', 'name' => 'Pulaski County', 'extra' => true], 60);
    Http::fake();

    expect((new CensusCountyGeocoder)->resolve(34.7465, -92.2896))->toBe(['geoId' => '05119', 'name' => 'Pulaski County']);
    Http::assertNothingSent();
});

it('returns null, and caches the absence, for a county entry Census returned malformed', function (mixed $county): void {
    $sent = [];
    recordCensusRequests($sent, censusBodyWithCounty($county));

    expect((new CensusCountyGeocoder)->resolve(34.7465, -92.2896))->toBeNull()
        ->and(Cache::get('maps:census:county:34.7465,-92.2896'))->toBe('none');
})->with([
    'not an array' => ['Pulaski County'],
    'a non-string GEOID' => [['GEOID' => 5119, 'NAME' => 'Pulaski County']],
    'a non-string name' => [['GEOID' => '05119', 'NAME' => 7]],
    'an empty GEOID' => [['GEOID' => '', 'NAME' => 'Pulaski County']],
    'an empty name' => [['GEOID' => '05119', 'NAME' => '']],
]);

it('does not cache a failed connection, so a later save retries', function (): void {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('connection refused');
    });
    $geocoder = new CensusCountyGeocoder;

    expect($geocoder->resolve(34.7465, -92.2896))->toBeNull()
        ->and($geocoder->resolve(34.7465, -92.2896))->toBeNull()
        ->and($attempts)->toBe(2)
        ->and(Cache::get('maps:census:county:34.7465,-92.2896'))->toBeNull();
});

it('does not cache a refused endpoint, so a later save retries', function (): void {
    config()->set('maps.census.endpoint', 'http://169.254.169.254/geocoder');
    Http::fake();
    $geocoder = new CensusCountyGeocoder;

    expect($geocoder->resolve(34.7465, -92.2896))->toBeNull()
        ->and(Cache::get('maps:census:county:34.7465,-92.2896'))->toBeNull();
});

it('refuses an endpoint whose host resolves internally after a single DNS lookup', function (): void {
    config()->set('maps.census.endpoint', 'https://census.internal.test/geocoder');
    $lookups = 0;
    OutboundUrlGuard::setResolver(function (string $host) use (&$lookups): array {
        $lookups++;

        return ['10.0.0.5'];
    });
    Http::fake();

    expect((new CensusCountyGeocoder)->resolve(34.7465, -92.2896))->toBeNull()
        ->and($lookups)->toBe(1);
    Http::assertNothingSent();
});
