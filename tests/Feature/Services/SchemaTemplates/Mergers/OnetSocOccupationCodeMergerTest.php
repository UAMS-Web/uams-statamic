<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\Mergers\OnetSocOccupationCodeMerger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for OnetSocOccupationCodeMerger (merges the O*NET-SOC occupation code
 * classification into a schema node, augmenting CategoryCode with API-backed
 * name and description when the overview is available).
 *
 * Mutation: not recorded. An earlier figure here (100% on App\Services\SchemaTemplates\Mergers\OnetSocOccupationCodeMerger,
 * 52 mutants, --covered-only) named no command or revision and was removed (UAMS-Web/uams-statamic-archive#2915).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/Mergers/OnetSocOccupationCodeMergerTest.php
 */
beforeEach(function () {
    Cache::flush();
    Config::set('services.onet_web_services.api_key', 'test-api-key');
    Config::set('services.onet_web_services.base_url', 'https://api-v2.onetcenter.org');
    Config::set('services.onet_web_services.cache_ttl', 0);
    Config::set('services.onet_web_services.delay_ms', 0);
    Config::set('services.onet_web_services.rate_limit_store', 'array');
    Config::set('services.onet_web_services.max_requests_per_second', 5);
});

it('adds name and description when the overview is available', function () {
    Http::fake([
        'api-v2.onetcenter.org/online/occupations/17-2051.00/*' => Http::response([
            'title' => 'Civil Engineers',
            'description' => 'Perform engineering duties.',
        ], 200),
    ]);

    $merger = app(OnetSocOccupationCodeMerger::class);
    $fragment = $merger->buildFragment('17-2051.00');

    $cat = $fragment['occupationalCategory'];
    expect(data_get($cat, '@type'))->toBe('CategoryCode');
    expect(data_get($cat, 'codeValue'))->toBe('17-2051.00');
    expect(data_get($cat, 'name'))->toBe('Civil Engineers');
    expect(data_get($cat, 'description'))->toBe('Perform engineering duties.');
});

it('omits name and description when the overview is unavailable', function () {
    Http::fake([
        'api-v2.onetcenter.org/*' => Http::response([], 500),
    ]);

    $merger = app(OnetSocOccupationCodeMerger::class);
    $fragment = $merger->buildFragment('17-2051.00');

    $cat = $fragment['occupationalCategory'];
    expect($cat)->not->toHaveKey('name');
    expect($cat)->not->toHaveKey('description');
});
