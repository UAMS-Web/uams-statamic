<?php

declare(strict_types=1);

use App\Services\LinkHealth\ExternalProber;
use App\Services\LinkHealth\ProbeOutcome;
use App\Support\Http\OutboundUrlGuard;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Coverage for {@see ExternalProber} — probing external targets through the guard (UAMS-Web/uams-statamic-archive#1766).
 *
 * Resolution is injected via `OutboundUrlGuard::setResolver()` and every response is faked, so
 * the whole file is offline and deterministic: no real DNS, no real sockets. That matters more
 * here than convenience, because the cases worth testing are the ones where a request MUST NOT
 * leave the machine.
 *
 * @command  php artisan test --compact tests/Feature/Services/LinkHealth/ExternalProberTest.php
 */
uses(TestCase::class);

/** A public address, so the guard permits the host. */
const XP_PUBLIC = '93.184.216.34';

/** Loopback — the guard must refuse a host resolving here. */
const XP_LOOPBACK = '127.0.0.1';

/** The cloud metadata endpoint, the SSRF target this guard exists for. */
const XP_METADATA = '169.254.169.254';

beforeEach(function (): void {
    OutboundUrlGuard::setResolver(fn (string $host): array => [XP_PUBLIC]);
});

afterEach(function (): void {
    // Restore the real resolver so injected state never leaks into a sibling file.
    OutboundUrlGuard::setResolver(null);
});

it('records a success status as healthy', function (): void {
    Http::fake(['*' => Http::response('', 200)]);

    expect((new ExternalProber)->probe('https://example.com/ok'))->toBe(ProbeOutcome::Healthy);
});

it('records a 404 and a 500 as broken', function (int $status): void {
    Http::fake(['*' => Http::response('', $status)]);

    expect((new ExternalProber)->probe('https://example.com/gone'))->toBe(ProbeOutcome::Broken);
})->with([404, 500]);

// --- the guard boundary -----------------------------------------------------
//
// AC2 and AC3. These are the cases the slice exists for: a refusal is its OWN outcome, never
// `Broken`. Reporting an internal-only host as broken would send an editor after a link they
// cannot fix, while concealing that the checker never reached anything.

it('records a host resolving to private space as refused, not broken', function (string $address): void {
    OutboundUrlGuard::setResolver(fn (string $host): array => [$address]);
    Http::fake(['*' => Http::response('', 200)]);

    $outcome = (new ExternalProber)->probe('https://internal.example/thing');

    expect($outcome)->toBe(ProbeOutcome::Refused)
        ->and($outcome)->not->toBe(ProbeOutcome::Broken)
        ->and($outcome->isBroken())->toBeFalse();
})->with([
    'loopback' => [XP_LOOPBACK],
    'cloud metadata link-local' => [XP_METADATA],
    'private class A' => ['10.0.0.5'],
    'private class C' => ['192.168.1.10'],
]);

it('refuses a non-http scheme rather than reporting it broken', function (): void {
    expect((new ExternalProber)->probe('ftp://example.com/file'))->toBe(ProbeOutcome::Refused);
});

// AC3. The hop-three case the ticket names: hop one is public, the redirect target is not.
it('refuses a redirect that pivots into private space, without following it', function (): void {
    OutboundUrlGuard::setResolver(fn (string $host): array => $host === 'evil.example'
        ? [XP_PUBLIC]
        : [XP_METADATA]);

    Http::fake([
        'evil.example/*' => Http::response('', 302, ['Location' => 'http://metadata.internal/latest']),
        '*' => Http::response('', 200),
    ]);

    expect((new ExternalProber)->probe('https://evil.example/start'))->toBe(ProbeOutcome::Refused);
});

// --- the HEAD -> GET retry --------------------------------------------------

it('retries with GET on a status where a HEAD rejection proves nothing', function (int $status): void {
    // Many servers refuse HEAD outright. Treating that as broken manufactures false positives on
    // sites that are perfectly fine, so the verdict waits for the GET.
    Http::fakeSequence()->push('', $status)->push('', 200);

    expect((new ExternalProber)->probe('https://example.com/head-hostile'))->toBe(ProbeOutcome::Healthy);
})->with([400, 403, 405, 406, 501]);

it('keeps the broken verdict when the GET retry also fails', function (): void {
    Http::fakeSequence()->push('', 403)->push('', 404);

    expect((new ExternalProber)->probe('https://example.com/really-gone'))->toBe(ProbeOutcome::Broken);
});

it('does not retry a status that is already conclusive', function (): void {
    // A 404 from HEAD is evidence. A second request would be a wasted round trip against every
    // broken link in the corpus.
    Http::fakeSequence()->push('', 404)->push('', 200);

    expect((new ExternalProber)->probe('https://example.com/gone'))->toBe(ProbeOutcome::Broken);
});

// --- fan-out ----------------------------------------------------------------

it('caches an outcome across direct probe calls, not only inside probeAll', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $prober = new ExternalProber;

    // `probeAll()` de-duplicates before it ever calls `probe()`, so the two guards mask each
    // other and only a direct repeat exercises the cache itself.
    $prober->probe('https://example.com/same');
    $prober->probe('https://example.com/same');

    Http::assertSentCount(1);
    expect($prober->attemptCount())->toBe(1);
});

it('does not retry a guard refusal with GET', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $prober = new ExternalProber;

    // AC2: a refusal is terminal. The guard throws before reaching the transport, so the request
    // count cannot see a second attempt and `attemptCount()` is the only observable.
    expect($prober->probe('http://127.0.0.1/internal'))->toBe(ProbeOutcome::Refused)
        ->and($prober->attemptCount())->toBe(1);

    Http::assertNothingSent();
});

it('probes a repeated URL once and reuses the outcome', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $prober = new ExternalProber;

    $outcomes = $prober->probeAll(array_fill(0, 12, 'https://example.com/shared'));

    // AC5 is a claim about REQUESTS, so it is asserted against the transport. `probedCount()`
    // counts distinct URLs cached, which is 1 here however many requests went out — it cannot
    // see a missing cache and must not be the assertion that stands for the fan-out.
    Http::assertSentCount(1);

    expect($outcomes)->toBe(['https://example.com/shared' => ProbeOutcome::Healthy])
        ->and($prober->probedCount())->toBe(1);
});

it('keeps distinct URLs distinct', function (): void {
    Http::fake([
        'example.com/ok' => Http::response('', 200),
        'example.com/gone' => Http::response('', 404),
    ]);
    $prober = new ExternalProber;

    $outcomes = $prober->probeAll([
        'https://example.com/ok',
        'https://example.com/gone',
        'https://example.com/ok',
    ]);

    expect($outcomes)->toBe([
        'https://example.com/ok' => ProbeOutcome::Healthy,
        'https://example.com/gone' => ProbeOutcome::Broken,
    ])->and($prober->probedCount())->toBe(2);
});

// --- UAMS-Web/uams-statamic-archive#1768: configured intranet hosts --------------------------------------------------------
//
// `inside.uams.edu` resolves to private space on the UAMS network, where production scans. A
// listed host is FETCHED past the guard's private-address refusal; if it still cannot be reached
// it is `Unverifiable` with its reason, and if it answers with a failure it is still `Broken`.

/** A placeholder RFC1918 address standing in for the private address `inside.uams.edu` resolves to on-network. It is not a measured value (UAMS-Web/uams-statamic-archive#2780). */
const XP_INTRANET = '10.1.2.3';

it('fetches a configured intranet host resolving to private space', function (): void {
    OutboundUrlGuard::setResolver(fn (string $host): array => [XP_INTRANET]);
    Http::fake(['*' => Http::response('', 200)]);

    $prober = new ExternalProber(['inside.uams.edu']);

    expect($prober->probe('https://inside.uams.edu/page'))->toBe(ProbeOutcome::Healthy)
        ->and($prober->reasonFor('https://inside.uams.edu/page'))->toBeNull();

    Http::assertSentCount(1);
});

it('keeps a genuine failure on a reachable intranet host broken', function (int $status): void {
    // The unverifiable path must not swallow real failures: the host answered, so this is news.
    OutboundUrlGuard::setResolver(fn (string $host): array => [XP_INTRANET]);
    Http::fake(['*' => Http::response('', $status)]);

    $outcome = (new ExternalProber(['inside.uams.edu']))->probe('https://inside.uams.edu/gone');

    expect($outcome)->toBe(ProbeOutcome::Broken)
        ->and($outcome->isBroken())->toBeTrue();
})->with([404, 500]);

it('reports a configured intranet host that never answers as unverifiable, with the reason', function (): void {
    // What a scan run OFF the UAMS network sees: the name does not resolve for it.
    OutboundUrlGuard::setResolver(fn (string $host): array => []);
    Http::fake(['*' => Http::failedConnection('cURL error 6: Could not resolve host: inside.uams.edu')]);

    $prober = new ExternalProber(['inside.uams.edu']);
    $outcome = $prober->probe('https://inside.uams.edu/page');

    expect($outcome)->toBe(ProbeOutcome::Unverifiable)
        ->and($outcome->isBroken())->toBeFalse()
        ->and($prober->reasonFor('https://inside.uams.edu/page'))->toContain('Could not resolve host');

    // HEAD, then the GET retry a transport failure earns, before the verdict.
    expect($prober->attemptCount())->toBe(2);
});

it('reports a configured intranet host the guard still refuses as unverifiable, with the refusal', function (): void {
    // A listed name pointed at loopback is refused by the guard; it is still not a broken link.
    OutboundUrlGuard::setResolver(fn (string $host): array => [XP_LOOPBACK]);
    Http::fake(['*' => Http::response('', 200)]);

    $prober = new ExternalProber(['inside.uams.edu']);

    expect($prober->probe('https://inside.uams.edu/page'))->toBe(ProbeOutcome::Unverifiable)
        ->and($prober->reasonFor('https://inside.uams.edu/page'))->toStartWith('Refusing host')
        ->and($prober->attemptCount())->toBe(1);

    Http::assertNothingSent();
});

it('keeps a failure after a listed host answered, rather than calling it unverifiable', function (string $case): void {
    // "Unverifiable" means the listed host itself never answered. Once it has answered — here with
    // a redirect — the host was reached, and what failed afterwards is judged like any other link.
    OutboundUrlGuard::setResolver(fn (string $host): array => match ($host) {
        'inside.uams.edu' => [XP_INTRANET],
        'payroll.internal' => ['10.0.0.9'],
        default => [],
    });

    Http::fake(match ($case) {
        'redirect to an unlisted private host' => [
            'inside.uams.edu/*' => Http::response('', 302, ['Location' => 'https://payroll.internal/admin']),
        ],
        'redirect to a dead public host' => [
            'inside.uams.edu/*' => Http::response('', 302, ['Location' => 'https://gone.example/page']),
            'gone.example/*' => Http::failedConnection(),
        ],
        'redirect loop' => [
            'inside.uams.edu/*' => Http::response('', 302, ['Location' => 'https://inside.uams.edu/loop']),
        ],
        default => throw new InvalidArgumentException('Unknown case: '.$case),
    });

    $prober = new ExternalProber(['inside.uams.edu']);
    $outcome = $prober->probe('https://inside.uams.edu/start');

    expect($outcome)->not->toBe(ProbeOutcome::Unverifiable)
        ->and($outcome)->toBe($case === 'redirect to an unlisted private host' ? ProbeOutcome::Refused : ProbeOutcome::Unreachable)
        ->and($prober->reasonFor('https://inside.uams.edu/start'))->not->toBeNull();
})->with([
    'redirect to an unlisted private host',
    'redirect to a dead public host',
    'redirect loop',
]);

it('leaves an unlisted private host refused and an unlisted dead host unreachable', function (): void {
    // The exception is for listed hosts only; nothing else changes meaning.
    OutboundUrlGuard::setResolver(fn (string $host): array => $host === 'internal.example' ? [XP_INTRANET] : []);
    Http::fake(['*' => Http::failedConnection()]);

    $prober = new ExternalProber(['inside.uams.edu']);

    expect($prober->probe('https://internal.example/x'))->toBe(ProbeOutcome::Refused)
        ->and($prober->probe('https://dead.example/x'))->toBe(ProbeOutcome::Unreachable)
        ->and($prober->probe('https://evil.inside.uams.edu/x'))->toBe(ProbeOutcome::Unreachable);
});

it('reads the intranet hosts from config when none are passed', function (): void {
    config(['link_health.intranet_hosts' => ['intra.example']]);
    OutboundUrlGuard::setResolver(fn (string $host): array => [XP_INTRANET]);
    Http::fake(['*' => Http::response('', 200)]);

    $prober = new ExternalProber;

    expect($prober->isIntranetUrl('https://intra.example/a'))->toBeTrue()
        ->and($prober->isIntranetUrl('https://inside.uams.edu/a'))->toBeFalse()
        ->and($prober->probe('https://intra.example/a'))->toBe(ProbeOutcome::Healthy);
});

it('prefers an explicitly passed host list over the configured one', function (): void {
    // Config lists inside.uams.edu by default; an explicit list replaces it rather than adding.
    $prober = new ExternalProber(['other.example']);

    expect($prober->isIntranetUrl('https://other.example/a'))->toBeTrue()
        ->and($prober->isIntranetUrl('https://inside.uams.edu/a'))->toBeFalse();
});

it('lists inside.uams.edu by default', function (): void {
    expect(config('link_health.intranet_hosts'))->toBe(['inside.uams.edu'])
        ->and((new ExternalProber)->isIntranetUrl('https://inside.uams.edu/'))->toBeTrue();
});

it('ignores a malformed intranet-hosts config rather than widening the guard', function (mixed $configured): void {
    config(['link_health.intranet_hosts' => $configured]);
    OutboundUrlGuard::setResolver(fn (string $host): array => [XP_INTRANET]);
    Http::fake(['*' => Http::response('', 200)]);

    expect((new ExternalProber)->probe('https://inside.uams.edu/page'))->toBe(ProbeOutcome::Refused);
})->with([
    'a bare string' => ['inside.uams.edu'],
    'null' => [null],
    'non-string entries' => [[1, true, ['inside.uams.edu']]],
]);

it('explains every outcome to an editor, and says an unverifiable link is not broken', function (): void {
    foreach (ProbeOutcome::cases() as $case) {
        expect($case->explanation())->not->toBe('');
    }

    expect(ProbeOutcome::Unverifiable->explanation())->toContain('not reported as broken')
        ->and(ProbeOutcome::Refused->explanation())->toContain('not permitted');
});
