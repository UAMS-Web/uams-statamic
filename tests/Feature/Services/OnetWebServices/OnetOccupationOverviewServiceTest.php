<?php

declare(strict_types=1);

use App\Services\OnetWebServices\OnetOccupationOverviewService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Feature tests for OnetOccupationOverviewService (O*NET OnLine occupation
 * overview fetch, caching, and shared rate-limit behavior).
 *
 * Mutation: 100% on App\Services\OnetWebServices\OnetOccupationOverviewService (89 mutants, --covered-only).
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

    $service = app(OnetOccupationOverviewService::class);

    expect($service->overview('17-2051.00'))->toBeNull();
    Http::assertNothingSent();
});

it('returns null for an invalid soc code', function () {
    $service = app(OnetOccupationOverviewService::class);

    expect($service->overview('invalid'))->toBeNull();
    Http::assertNothingSent();
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
