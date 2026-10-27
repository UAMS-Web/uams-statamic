<?php

declare(strict_types=1);

use App\Support\Http\OutboundUrlGuard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/**
 * HTTP coverage for the GPS picker's server-side Nominatim address-search proxy
 * (App\Http\Controllers\CP\Gps\NominatimSearchController, UAMS-Web/uams-statamic-archive#984).
 *
 * Proves the proxy: gates on CP auth; normalizes the upstream geocoding response
 * into the picker's { lat, lng, label } shape (the geocode-result -> coordinate
 * update path); identifies itself with a descriptive User-Agent; enforces the
 * Nominatim usage policy's one-request-per-second cap; routes the outbound fetch
 * through the SSRF egress guard; and degrades to an empty result set on upstream
 * failure. The external service and DNS resolution are both stubbed so the test
 * stays offline (Http::fake + OutboundUrlGuard::setResolver, mirroring
 * tests/Feature/Rules/FeedUrlSsrfTest).
 *
 * @command  php artisan test --compact tests/Feature/Http/Controllers/CP/Gps/NominatimSearchControllerTest.php
 */
uses(TestCase::class);

const NOMINATIM_SEARCH_TEST_EMAIL = 'nominatim-search-test@example.test';

const NOMINATIM_SEARCH_RATE_LIMITER_KEY = 'maps:nominatim:outbound';

beforeEach(function (): void {
    // Pin a known, policy-shaped config so assertions are deterministic.
    config()->set('maps.nominatim.endpoint', 'https://nominatim.openstreetmap.org/search');
    config()->set('maps.nominatim.min_query_length', 3);
    config()->set('maps.nominatim.max_requests_per_second', 1);
    config()->set('maps.nominatim.user_agent', 'UAMS Statamic Test Agent');

    // Reset the app-wide limiter so each test starts with a fresh allowance.
    RateLimiter::clear(NOMINATIM_SEARCH_RATE_LIMITER_KEY);

    // Resolve any DNS name to a public IP so the egress guard permits the fetch
    // without real DNS; IP-literal hosts (the SSRF case) bypass this and are
    // classified on their own.
    OutboundUrlGuard::setResolver(fn (string $host): array => ['8.8.8.8']);
});

afterEach(function (): void {
    OutboundUrlGuard::setResolver(null);
    RateLimiter::clear(NOMINATIM_SEARCH_RATE_LIMITER_KEY);

    /** @var Statamic\Auth\File\User|null $user */
    $user = User::findByEmail(NOMINATIM_SEARCH_TEST_EMAIL);
    if ($user !== null) {
        $user->delete();
    }
});

/**
 * Persist a throwaway file-backed super user and authenticate as them.
 */
function actAsNominatimSearchUser(): void
{
    /** @var Statamic\Auth\File\User $user */
    $user = User::make();
    $user->id((string) Str::uuid());
    $user->email(NOMINATIM_SEARCH_TEST_EMAIL);
    $user->password('password');
    $user->makeSuper();
    $user->save();

    actingAs($user);
}

it('rejects a guest before any outbound request', function (): void {
    Http::fake();

    /** @var string $url */
    $url = cp_route('gps.nominatim-search', ['q' => 'Little Rock']);
    $response = getJson($url);

    $response->assertUnauthorized();
    Http::assertNothingSent();
});

it('geocodes an address into normalized coordinate results with an identifying user-agent', function (): void {
    actAsNominatimSearchUser();

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '34.7492719', 'lon' => '-92.3198281', 'display_name' => 'UAMS, 4301 W Markham St, Little Rock, AR'],
            ['lat' => '34.7465', 'lon' => '-92.2896', 'display_name' => 'Little Rock, Pulaski County, AR'],
        ], 200),
    ]);

    /** @var string $url */
    $url = cp_route('gps.nominatim-search', ['q' => '4301 W Markham St, Little Rock']);
    $response = getJson($url);

    $response->assertOk();
    $response->assertExactJson([
        'results' => [
            ['lat' => 34.7492719, 'lng' => -92.3198281, 'label' => 'UAMS, 4301 W Markham St, Little Rock, AR'],
            ['lat' => 34.7465, 'lng' => -92.2896, 'label' => 'Little Rock, Pulaski County, AR'],
        ],
    ]);

    Http::assertSent(function (Request $request): bool {
        return str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/search')
            && str_contains($request->url(), 'format=jsonv2')
            && str_contains($request->url(), 'q=')
            && $request->header('User-Agent') === ['UAMS Statamic Test Agent'];
    });
});

it('drops upstream rows without a usable coordinate pair', function (): void {
    actAsNominatimSearchUser();

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '34.75', 'lon' => '-92.32', 'display_name' => 'Good'],
            ['lat' => 'not-a-number', 'lon' => '-92.32', 'display_name' => 'Bad latitude'],
            ['display_name' => 'No coordinates at all'],
            'not even an object',
        ], 200),
    ]);

    /** @var string $url */
    $url = cp_route('gps.nominatim-search', ['q' => 'Little Rock']);
    $response = getJson($url);

    $response->assertOk();
    $response->assertExactJson([
        'results' => [
            ['lat' => 34.75, 'lng' => -92.32, 'label' => 'Good'],
        ],
    ]);
});

it('does not issue an outbound request for a query below the minimum length', function (): void {
    actAsNominatimSearchUser();
    Http::fake();

    /** @var string $url */
    $url = cp_route('gps.nominatim-search', ['q' => 'ab']);
    $response = getJson($url);

    $response->assertOk();
    $response->assertExactJson(['results' => []]);
    Http::assertNothingSent();
});

it('requires a query parameter', function (): void {
    actAsNominatimSearchUser();
    Http::fake();

    /** @var string $url */
    $url = cp_route('gps.nominatim-search');
    $response = getJson($url);

    $response->assertStatus(422);
    Http::assertNothingSent();
});

it('enforces the Nominatim one-request-per-second policy cap', function (): void {
    actAsNominatimSearchUser();

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([], 200),
    ]);

    // Freeze time around both requests. The limiter's timer key decays after one
    // real second (RateLimiter::hit(..., 1)); under heavy parallel load the
    // wall-clock gap between these two requests can exceed that, expiring the timer
    // so the second request is admitted (a flaky 200 instead of 429). Frozen time
    // pins the limiter's "now" so the window cannot elapse mid-test.
    /** @var TestResponse<Response> $second */
    $second = $this->freezeTime(function (): TestResponse {
        /** @var string $firstUrl */
        $firstUrl = cp_route('gps.nominatim-search', ['q' => 'Little Rock']);
        getJson($firstUrl)->assertOk();

        /** @var string $secondUrl */
        $secondUrl = cp_route('gps.nominatim-search', ['q' => 'Conway']);

        return getJson($secondUrl);
    });

    $second->assertStatus(429);
    $second->assertHeader('Retry-After');
    // Only the first request reached the network; the second was throttled.
    Http::assertSentCount(1);
});

it('degrades to an empty 502 response when the upstream fails', function (): void {
    actAsNominatimSearchUser();

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response('upstream error', 500),
    ]);

    /** @var string $url */
    $url = cp_route('gps.nominatim-search', ['q' => 'Little Rock']);
    $response = getJson($url);

    $response->assertStatus(502);
    $response->assertExactJson(['results' => []]);
});

it('refuses to fetch when the configured endpoint targets an internal host (SSRF guard)', function (): void {
    actAsNominatimSearchUser();
    config()->set('maps.nominatim.endpoint', 'http://169.254.169.254/search');
    Http::fake();

    /** @var string $url */
    $url = cp_route('gps.nominatim-search', ['q' => 'Little Rock']);
    $response = getJson($url);

    $response->assertStatus(502);
    $response->assertExactJson(['results' => []]);
    Http::assertNothingSent();
});
