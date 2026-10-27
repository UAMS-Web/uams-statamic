<?php

declare(strict_types=1);

use App\Services\PubMed\PubMedEsummaryService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

/**
 * E-utilities client identification and the application-wide rate limiter (UAMS-Web/uams-statamic-archive#2409).
 *
 * NCBI asks every client to send `tool` and `email` so they can make contact
 * before throttling or blocking it, and an `api_key` raises the ceiling from
 * three requests per second to ten. Before UAMS-Web/uams-statamic-archive#2409 every request was anonymous,
 * and the only pacing was a sleep inside a single call — which bounded one call
 * rather than the application, so two warming workers plus Control-Panel
 * validation could exceed the ceiling while each paced itself politely.
 *
 * @command  php artisan test --compact tests/Feature/Services/PubMed/PubMedIdentificationTest.php
 */
beforeEach(function () {
    // Both the summary cache and the shared limiter live in the cache store, so
    // one flush isolates both. Without it a limiter counter survives into the
    // next test and its first request waits on a window it cannot see.
    Cache::flush();

    // The limiter's window is 1 second of wall-clock time, and the cache decides
    // whether a hit has expired by reading the clock. On a loaded machine a window
    // filled by a test can empty before the service first checks it, or two
    // requests can straddle a boundary (UAMS-Web/uams-statamic-archive#2582). A frozen clock keeps every hit in
    // one window, so these tests observe slots taken, not elapsed time.
    $this->freezeTime();

    Http::fake([
        'eutils.ncbi.nlm.nih.gov/*' => Http::response([
            'result' => ['123' => ['uid' => '123', 'title' => 'Example']],
        ], 200),
    ]);
});

/**
 * Query parameters of the single request the fake recorded.
 *
 * Read from the request URL rather than from the service, so the assertion
 * describes what NCBI would actually receive.
 *
 * @return array<string, string>
 */
function recordedPubMedQuery(): array
{
    $parameters = [];

    Http::assertSent(function (Request $request) use (&$parameters): bool {
        $query = \parse_url($request->url(), PHP_URL_QUERY);
        \parse_str(\is_string($query) ? $query : '', $parameters);

        return true;
    });

    /** @var array<string, string> $parameters */
    return $parameters;
}

it('sends tool and the configured contact email on every request', function () {
    config()->set('pubmed.tool', 'uams-statamic');
    config()->set('pubmed.email', 'testing@example.com');

    (new PubMedEsummaryService)->pmidExists('123');

    $query = recordedPubMedQuery();

    expect($query['tool'])->toBe('uams-statamic')
        ->and($query['email'])->toBe('testing@example.com')
        // The pre-existing parameters must survive the move to a query array
        ->and($query['db'])->toBe('pubmed')
        ->and($query['retmode'])->toBe('json')
        ->and($query['id'])->toBe('123');
});

it('sends api_key when one is configured', function () {
    config()->set('pubmed.api_key', 'abc123');

    (new PubMedEsummaryService)->pmidExists('123');

    expect(recordedPubMedQuery())->toHaveKey('api_key', 'abc123');
});

it('omits api_key entirely when none is configured, rather than sending it empty', function () {
    config()->set('pubmed.api_key', null);

    (new PubMedEsummaryService)->pmidExists('123');

    // NCBI treats an empty api_key as a malformed one, so an empty-string value
    // would be worse than the absence this asserts.
    expect(recordedPubMedQuery())->not->toHaveKey('api_key');
});

it('treats a whitespace-only api_key as unconfigured', function () {
    config()->set('pubmed.api_key', '   ');

    $service = new PubMedEsummaryService;
    $service->pmidExists('123');

    expect(recordedPubMedQuery())->not->toHaveKey('api_key')
        ->and($service->maxRequestsPerSecond())->toBe(3);
});

it('refuses to query anonymously when no contact email is configured', function () {
    config()->set('pubmed.email', null);

    expect(fn () => (new PubMedEsummaryService)->pmidExists('123'))
        ->toThrow(RuntimeException::class, 'PUBMED_CONTACT_EMAIL');

    // The refusal must prevent the request, not merely report it afterwards
    Http::assertNothingSent();
});

it('names the config key as well as the environment variable when refusing', function () {
    config()->set('pubmed.email', '   ');

    expect(fn () => (new PubMedEsummaryService)->pmidExists('123'))
        ->toThrow(RuntimeException::class, 'pubmed.email');
});

it('raises the permitted rate from three per second to ten when an api_key is set', function () {
    $service = new PubMedEsummaryService;

    config()->set('pubmed.api_key', null);
    expect($service->maxRequestsPerSecond())->toBe(3);

    config()->set('pubmed.api_key', 'abc123');
    expect($service->maxRequestsPerSecond())->toBe(10);
});

it('paces against a limiter shared across instances rather than per instance', function () {
    // Two instances stand in for the live callers that each resolve their own:
    // the warming job, the Pmid validation rule, and the computed field. Before
    // UAMS-Web/uams-statamic-archive#2409 each paced itself and nothing counted across them.
    (new PubMedEsummaryService)->pmidExists('123');
    (new PubMedEsummaryService)->pmidExists('456');

    // A per-instance limiter would leave this at 1
    expect(RateLimiter::attempts('pubmed_api_requests'))->toBe(2);
});

it('consumes no limiter slot when the cache already holds the row', function () {
    (new PubMedEsummaryService)->pmidExists('123');

    $afterFirst = RateLimiter::attempts('pubmed_api_requests');

    // Same PMID, now warm: no HTTP and so no slot taken from a worker that
    // needs one (the UAMS-Web/uams-statamic-archive#865 cache contract, restated against the limiter)
    (new PubMedEsummaryService)->pmidExists('123');

    expect(RateLimiter::attempts('pubmed_api_requests'))->toBe($afterFirst);
    Http::assertSentCount(1);
});

it('waits for a limiter slot rather than dropping the request when the window is full', function () {
    // Sleep is faked, so the window never actually clears and the wait runs to
    // its bound. That is the point: it proves the code WAITS instead of
    // returning early, which `RateLimiter::attempt()` would have done.
    Sleep::fake();

    // Fill the unauthenticated window
    config()->set('pubmed.api_key', null);
    for ($i = 0; $i < 3; $i++) {
        RateLimiter::hit('pubmed_api_requests', 1);
    }

    expect(fn () => (new PubMedEsummaryService)->pmidExists('123'))
        ->toThrow(RuntimeException::class, 'rate limit did not clear');

    // It waited rather than dropping the request silently, and gave up after a
    // bounded number of waits rather than hanging the worker forever. The exact
    // count pins RATE_LIMIT_MAX_WAITS, so raising the bound has to be deliberate.
    Sleep::assertSleptTimes(30);

    // And it never sent an over-ceiling request
    Http::assertNothingSent();
});

it('does not consume a limiter slot when it gives up waiting', function () {
    Sleep::fake();

    config()->set('pubmed.api_key', null);
    for ($i = 0; $i < 3; $i++) {
        RateLimiter::hit('pubmed_api_requests', 1);
    }

    $before = RateLimiter::attempts('pubmed_api_requests');

    try {
        (new PubMedEsummaryService)->pmidExists('123');
    } catch (RuntimeException) {
        // expected
    }

    // A refusal that still consumed a slot would starve the workers it was
    // queued behind, which is the opposite of what the bound is protecting
    expect(RateLimiter::attempts('pubmed_api_requests'))->toBe($before);
});

it('allows ten in a window when an api_key is configured, where three would be refused', function () {
    Sleep::fake();
    config()->set('pubmed.api_key', 'abc123');

    // Four in the window: over the anonymous ceiling, inside the keyed one
    for ($i = 0; $i < 4; $i++) {
        RateLimiter::hit('pubmed_api_requests', 1);
    }

    // Proceeds without waiting at all
    expect((new PubMedEsummaryService)->pmidExists('123'))->toBeTrue();
    Sleep::assertNeverSlept();
});

it('does not refuse a cache-only read when no contact email is configured', function () {
    (new PubMedEsummaryService)->pmidExists('123');

    // Misconfiguration must not break reads that never reach the network,
    // otherwise an unset address takes down request-time rendering too
    config()->set('pubmed.email', null);

    expect((new PubMedEsummaryService)->cachedSummaryRow('123'))->toBeArray();
});
