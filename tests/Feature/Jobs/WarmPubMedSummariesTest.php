<?php

declare(strict_types=1);

use App\Jobs\WarmPubMedSummaries;
use App\Services\PubMed\PubMedEsummaryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#865: the warming job populates the E-summary cache out of band so the
 * request-time computed field can read cache-only.
 */
beforeEach(function () {
    Cache::flush();
    Sleep::fake();

    // The shared limiter's window is 1 second of wall-clock time, and the cache
    // decides whether a hit has expired by reading the clock. On a loaded machine
    // two fetches can straddle a window boundary and the counter restarts (UAMS-Web/uams-statamic-archive#2582).
    // A frozen clock keeps every hit in one window, so a count reflects the
    // fetches that took a slot rather than how long they took.
    $this->freezeTime();
});

it('warms the cache for each pmid so the request path can read it (#865)', function () {
    Http::fake([
        'eutils.ncbi.nlm.nih.gov/*' => Http::response([
            'result' => ['456' => ['title' => 'A Study']],
        ], 200),
    ]);

    (new WarmPubMedSummaries(['456']))->handle(new PubMedEsummaryService);

    // The cache-only request path now finds the row without any further HTTP.
    expect((new PubMedEsummaryService)->cachedSummaryRow('456'))->toBeArray();
    Http::assertSentCount(1);
});

it('does not re-fetch a pmid that is already cached (#865)', function () {
    Http::fake([
        'eutils.ncbi.nlm.nih.gov/*' => Http::response([
            'result' => ['456' => ['title' => 'A Study']],
        ], 200),
    ]);

    $service = new PubMedEsummaryService;
    $service->rememberSummaryRow('456'); // pre-warm (1 HTTP)

    (new WarmPubMedSummaries(['456']))->handle($service);

    Http::assertSentCount(1);
});

it('paces every uncached network fetch against the shared limiter (#865 review, #2409)', function () {
    Http::fake([
        'eutils.ncbi.nlm.nih.gov/*' => Http::response([
            'result' => ['1' => ['title' => 'x']],
        ], 200),
    ]);

    (new WarmPubMedSummaries(['111', '222']))->handle(new PubMedEsummaryService);

    // One limiter slot per uncached fetch — never skip NCBI rate-limit pacing.
    //
    // This asserted `Sleep::assertSleptTimes(2)` until UAMS-Web/uams-statamic-archive#2409. The intent is
    // unchanged; the mechanism is not. A per-call sleep bounded one call and
    // nothing else, so two warming workers each sleeping politely still
    // exceeded the ceiling together. Counting slots asserts what the sleep was
    // standing in for, and does so across every caller rather than this one.
    expect(RateLimiter::attempts('pubmed_api_requests'))->toBe(2);
});

it('isolates a failing pmid and still warms the rest (#865 review)', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'id=111')) {
            throw new ConnectionException('NCBI timed out');
        }

        return Http::response(['result' => ['222' => ['title' => 'Ok']]], 200);
    });

    // The failure must be logged (operators rely on it to spot a cold PMID).
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $context['pmid'] === '111' && isset($context['message']);
        });

    $service = new PubMedEsummaryService;

    // A poison PMID must not throw out of the job (which, under the sync queue,
    // would abort the whole pubmed:warm command).
    (new WarmPubMedSummaries(['111', '222']))->handle($service);

    // The good PMID after the failing one is still warmed.
    expect($service->cachedSummaryRow('222'))->toBeArray();
    expect($service->cachedSummaryRow('111'))->toBeNull();
});
