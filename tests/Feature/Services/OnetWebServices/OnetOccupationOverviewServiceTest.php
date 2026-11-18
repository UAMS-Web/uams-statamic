<?php

declare(strict_types=1);

use App\Services\OnetWebServices\OnetOccupationOverviewService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Feature tests for OnetOccupationOverviewService (O*NET OnLine occupation
 * overview fetch, caching, and shared rate-limit behavior).
 *
 * Every wait goes through the service's `pause()` seam. The tests that reach a
 * wait use `pausingOnetService()`, which records each wait and spends it on the
 * test clock, so the rate limiter sees time pass without the test sleeping.
 *
 * @command  php artisan test --compact tests/Feature/Services/OnetWebServices/OnetOccupationOverviewServiceTest.php
 */
beforeEach(function () {
    Cache::flush();
    Config::set('services.onet_web_services.api_key', 'test-api-key');
    Config::set('services.onet_web_services.base_url', 'https://api-v2.onetcenter.org');
    Config::set('services.onet_web_services.cache_ttl', 3600);
    Config::set('services.onet_web_services.delay_ms', 0);
    Config::set('services.onet_web_services.rate_limit_store', 'array');
    Config::set('services.onet_web_services.max_requests_per_second', 5);
});

it('returns null when the api key is missing', function () {
    Config::set('services.onet_web_services.api_key', '');
    Http::fake();

    $service = app(OnetOccupationOverviewService::class);

    expect($service->overview('17-2051.00'))->toBeNull();
    Http::assertNothingSent();
});

it('returns null for an invalid soc code', function (string $code) {
    Http::fake();

    expect(app(OnetOccupationOverviewService::class)->overview($code))->toBeNull();
    Http::assertNothingSent();
})->with([
    'not a code' => ['invalid'],
    'empty' => [''],
    'blank' => ['   '],
]);

it('returns null without a request when the api key is not a string', function () {
    Config::set('services.onet_web_services.api_key', 12345);
    Http::fake();

    expect(app(OnetOccupationOverviewService::class)->overview('17-2051.00'))->toBeNull();
    Http::assertNothingSent();
});

it('trims the code before validating and requesting it', function () {
    Http::fake(['*' => Http::response(['title' => 'Civil Engineers'], 200)]);

    expect(app(OnetOccupationOverviewService::class)->overview(" 17-2051.00\n"))->toBe(['title' => 'Civil Engineers']);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-v2.onetcenter.org/online/occupations/17-2051.00/');
});

it('returns title and description on success', function () {
    Http::fake([
        'api-v2.onetcenter.org/online/occupations/17-2051.00/*' => Http::response([
            'code' => '17-2051.00',
            'title' => 'Civil Engineers',
            'description' => 'Perform engineering duties.',
        ], 200),
    ]);

    $service = app(OnetOccupationOverviewService::class);
    $result = $service->overview('17-2051.00');

    expect($result)->toHaveCount(2);
    expect(data_get($result, 'title'))->toBe('Civil Engineers');
    expect(data_get($result, 'description'))->toBe('Perform engineering duties.');
});

it('omits description when the api returns an empty description', function () {
    Http::fake([
        'api-v2.onetcenter.org/online/occupations/17-2051.00/*' => Http::response([
            'code' => '17-2051.00',
            'title' => 'Civil Engineers',
            'description' => '',
        ], 200),
    ]);

    $service = app(OnetOccupationOverviewService::class);
    $result = $service->overview('17-2051.00');

    expect($result)->toHaveCount(1);
    expect(data_get($result, 'title'))->toBe('Civil Engineers');
});

it('returns null on an http error', function () {
    Http::fake([
        'api-v2.onetcenter.org/*' => Http::response([], 500),
    ]);

    $service = app(OnetOccupationOverviewService::class);

    expect($service->overview('17-2051.00'))->toBeNull();
});

it('caches a successful response', function () {
    Http::fake([
        'api-v2.onetcenter.org/online/occupations/17-2051.00/*' => Http::response([
            'title' => 'Civil Engineers',
            'description' => 'Perform engineering duties.',
        ], 200),
    ]);

    $service = app(OnetOccupationOverviewService::class);
    $service->overview('17-2051.00');
    $service->overview('17-2051.00');

    Http::assertSentCount(1);
});

it('does not cache a failed response', function () {
    Http::fake([
        'api-v2.onetcenter.org/*' => Http::response([], 404),
    ]);

    $service = app(OnetOccupationOverviewService::class);
    expect($service->overview('17-2051.00'))->toBeNull();
    expect($service->overview('17-2051.00'))->toBeNull();

    Http::assertSentCount(2);
});

it('skips the shared rate limit when the store is not configured', function () {
    Config::set('services.onet_web_services.rate_limit_store', '');

    Http::fake([
        'api-v2.onetcenter.org/online/occupations/17-2051.00/*' => Http::response([
            'title' => 'Civil Engineers',
        ], 200),
    ]);

    $service = app(OnetOccupationOverviewService::class);
    $result = $service->overview('17-2051.00');

    expect($result)->toHaveCount(1);
    expect(data_get($result, 'title'))->toBe('Civil Engineers');
    Http::assertSentCount(1);
});

it('waits at least the configured delay before each request', function () {
    Config::set('services.onet_web_services.delay_ms', 50);
    Http::fake(['*' => Http::response(['title' => 'Civil Engineers'], 200)]);

    $started = microtime(true);
    app(OnetOccupationOverviewService::class)->overview('17-2051.00');

    expect(microtime(true) - $started)->toBeGreaterThanOrEqual(0.04);
});

it('holds a request back until the shared per-second budget frees a slot', function () {
    Config::set('services.onet_web_services.max_requests_per_second', 1);
    $sentAt = [];
    Http::fake(function () use (&$sentAt) {
        $sentAt[] = time();

        return Http::response(['title' => 'Civil Engineers'], 200);
    });

    $service = app(OnetOccupationOverviewService::class);
    $service->overview('17-2051.00');
    $service->overview('17-2051.01');

    expect($sentAt)->toHaveCount(2)
        ->and($sentAt[1])->toBeGreaterThan($sentAt[0]);
});

/**
 * Set one O*NET setting, or remove it for null so the service's default applies.
 */
function setOnetConfig(string $key, mixed $value): void
{
    if ($value === null) {
        /** @var array<string, mixed> $settings */
        $settings = config('services.onet_web_services');
        unset($settings[$key]);
        Config::set('services.onet_web_services', $settings);

        return;
    }

    Config::set('services.onet_web_services.'.$key, $value);
}

/**
 * A service whose waits are recorded and spent on the test clock, not the wall clock.
 *
 * @param  list<int>  $pauses  Receives each wait, in microseconds
 * @param  (Closure(int): void)|null  $onPause  Runs after each wait is recorded
 */
function pausingOnetService(array &$pauses, ?Closure $onPause = null): OnetOccupationOverviewService
{
    $record = function (int $microseconds) use (&$pauses, $onPause): void {
        $pauses[] = $microseconds;
        Carbon::setTestNow(Carbon::now()->addMicroseconds(max($microseconds, 1)));

        if ($onPause instanceof Closure) {
            $onPause($microseconds);
        }
    };

    return new class($record) extends OnetOccupationOverviewService
    {
        public function __construct(private readonly Closure $record) {}

        protected function pause(int $microseconds): void
        {
            ($this->record)($microseconds);
        }
    };
}

/**
 * The rate limiter's cache key for the test API key.
 */
function onetOutboundKey(): string
{
    return 'onet_ws:outbound:v1:'.hash('sha256', 'test-api-key');
}

it('returns a warm cache entry without a request', function () {
    Http::fake();
    Cache::put('onet_ws:occupation_overview:v1:17-2051.00', ['title' => 'Cached Engineers'], 60);

    expect(app(OnetOccupationOverviewService::class)->overview('17-2051.00'))->toBe(['title' => 'Cached Engineers']);
    Http::assertNothingSent();
});

it('returns null for a warm cache entry that is not an array, without a request', function () {
    Http::fake();
    Cache::put('onet_ws:occupation_overview:v1:17-2051.00', 'Cached Engineers', 60);

    expect(app(OnetOccupationOverviewService::class)->overview('17-2051.00'))->toBeNull();
    Http::assertNothingSent();
});

it('stores a fetched overview under the code key for the configured TTL', function (mixed $configured, int $stored) {
    Config::set('services.onet_web_services.rate_limit_store', '');
    setOnetConfig('cache_ttl', $configured);
    Http::fake(['*' => Http::response(['title' => 'Civil Engineers'], 200)]);
    $cache = Cache::spy();

    app(OnetOccupationOverviewService::class)->overview('17-2051.00');

    $cache->shouldHaveReceived('put')->once()->withArgs(fn (mixed $key, mixed $value, mixed $ttl): bool => $key === 'onet_ws:occupation_overview:v1:17-2051.00'
        && $value === ['title' => 'Civil Engineers']
        && $ttl === $stored);
})->with([
    'a TTL' => [3600, 3600],
    'a one-second TTL' => [1, 1],
    'a numeric string' => ['3600', 3600],
    'the default when unset' => [null, 86400],
]);

it('stores nothing when the TTL is zero or below', function (mixed $configured) {
    Config::set('services.onet_web_services.rate_limit_store', '');
    setOnetConfig('cache_ttl', $configured);
    Http::fake(['*' => Http::response(['title' => 'Civil Engineers'], 200)]);
    $cache = Cache::spy();

    expect(app(OnetOccupationOverviewService::class)->overview('17-2051.00'))->toBe(['title' => 'Civil Engineers']);
    $cache->shouldNotHaveReceived('put');
})->with([
    'zero' => [0],
    'negative' => [-5],
    'not numeric' => ['abc'],
]);

it('stores nothing when the fetch fails', function () {
    Config::set('services.onet_web_services.rate_limit_store', '');
    Http::fake(['*' => Http::response([], 500)]);
    $cache = Cache::spy();

    expect(app(OnetOccupationOverviewService::class)->overview('17-2051.00'))->toBeNull();
    $cache->shouldNotHaveReceived('put');
});

it('sends one JSON GET with the api key and a 30-second timeout', function () {
    $timeouts = [];
    Http::globalMiddleware(function (callable $handler) use (&$timeouts): callable {
        return function ($request, array $options) use ($handler, &$timeouts) {
            $timeouts[] = $options['timeout'] ?? null;

            return $handler($request, $options);
        };
    });
    Http::fake(['*' => Http::response(['title' => 'Civil Engineers'], 200)]);

    app(OnetOccupationOverviewService::class)->overview('17-2051.00');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->header('X-API-Key') === ['test-api-key']
        && $request->header('Accept') === ['application/json']);
    expect($timeouts)->toBe([30]);
});

it('builds the request URL from the base URL, without a doubled slash', function (mixed $base, string $url) {
    setOnetConfig('base_url', $base);
    Http::fake(['*' => Http::response(['title' => 'Civil Engineers'], 200)]);

    app(OnetOccupationOverviewService::class)->overview('17-2051.00');

    Http::assertSent(fn (Request $request): bool => $request->url() === $url);
})->with([
    'a trailing slash' => ['https://onet.example.test/v2//', 'https://onet.example.test/v2/online/occupations/17-2051.00/'],
    'the default when unset' => [null, 'https://api-v2.onetcenter.org/online/occupations/17-2051.00/'],
    'a base URL that is not a string' => [8080, '8080/online/occupations/17-2051.00/'],
]);

it('returns null for a response it cannot use', function (int $status, array|string $body) {
    Http::fake(['*' => Http::response($body, $status, ['Content-Type' => 'application/json'])]);

    expect(app(OnetOccupationOverviewService::class)->overview('17-2051.00'))->toBeNull();
})->with([
    'an error status with a title' => [500, ['title' => 'Civil Engineers']],
    'a JSON string' => [200, '"Civil Engineers"'],
    'a title that is not a string' => [200, ['title' => 42]],
    'a blank title' => [200, ['title' => "  \n "]],
    'no title' => [200, ['description' => 'Perform engineering duties.']],
]);

it('trims the title and description and leaves out a description that is not text', function (mixed $description, array $expected) {
    Http::fake(['*' => Http::response(['title' => '  Civil Engineers  ', 'description' => $description], 200)]);

    expect(app(OnetOccupationOverviewService::class)->overview('17-2051.00'))->toBe($expected);
})->with([
    'padded text' => ['  Perform engineering duties.  ', ['title' => 'Civil Engineers', 'description' => 'Perform engineering duties.']],
    'blank text' => ["  \n ", ['title' => 'Civil Engineers']],
    'a number' => [42, ['title' => 'Civil Engineers']],
]);

it('pauses for the configured delay, in microseconds', function (mixed $configured, array $expected) {
    Config::set('services.onet_web_services.rate_limit_store', '');
    setOnetConfig('delay_ms', $configured);
    Http::fake(['*' => Http::response(['title' => 'Civil Engineers'], 200)]);
    $pauses = [];

    pausingOnetService($pauses)->overview('17-2051.00');

    expect($pauses)->toBe($expected);
})->with([
    'a delay' => [200, [200_000]],
    'a one-millisecond delay' => [1, [1_000]],
    'a fractional numeric string' => ['200.5', [200_000]],
    'no delay' => [0, []],
    'a negative delay' => [-5, []],
    'the default when unset' => [null, []],
]);

it('records one hit per request against the api key, for one second', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 18, 9, 0, 0));
    Http::fake(['*' => Http::response(['title' => 'Civil Engineers'], 200)]);

    app(OnetOccupationOverviewService::class)->overview('17-2051.00');

    expect(Cache::store('array')->get(onetOutboundKey()))->toBe(1)
        ->and(Cache::store('array')->get(onetOutboundKey().':timer'))->toBe(Carbon::now()->getTimestamp() + 1);
});

it('records no hit when the rate-limit store is not configured', function () {
    Config::set('services.onet_web_services.rate_limit_store', '');
    Http::fake(['*' => Http::response(['title' => 'Civil Engineers'], 200)]);

    app(OnetOccupationOverviewService::class)->overview('17-2051.00');

    expect(Cache::store('array')->has(onetOutboundKey()))->toBeFalse();
});

it('waits a full second once the per-second budget is spent', function (mixed $budget, int $free) {
    Carbon::setTestNow(Carbon::create(2026, 11, 18, 9, 0, 0));
    setOnetConfig('max_requests_per_second', $budget);
    Http::fake(['*' => Http::response(['title' => 'Civil Engineers'], 200)]);
    $pauses = [];
    $service = pausingOnetService($pauses);

    for ($i = 0; $i < $free; $i++) {
        $service->overview(sprintf('17-2051.%02d', $i));
    }

    expect($pauses)->toBe([]);

    $service->overview('17-2052.00');

    expect($pauses)->toBe([1_000_000]);
    Http::assertSentCount($free + 1);
})->with([
    'the default of 5' => [null, 5],
    'a budget of 2' => [2, 2],
    'a fractional numeric string' => ['2.5', 2],
    'a budget of 0, which allows one' => [0, 1],
    'a negative budget, which allows one' => [-3, 1],
]);

it('waits 50 milliseconds when the limiter says the slot is due now', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 18, 9, 0, 0));
    Http::fake(['*' => Http::response(['title' => 'Civil Engineers'], 200)]);
    $limiter = Cache::store('array');
    $limiter->put(onetOutboundKey(), 5, 60);
    $limiter->put(onetOutboundKey().':timer', Carbon::now()->getTimestamp(), 60);
    $pauses = [];

    pausingOnetService($pauses, function () use ($limiter): void {
        $limiter->forget(onetOutboundKey().':timer');
    })->overview('17-2051.00');

    expect($pauses)->toBe([50_000]);
    Http::assertSentCount(1);
});
