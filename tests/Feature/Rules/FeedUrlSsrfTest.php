<?php

declare(strict_types=1);

use App\Modifiers\RemoteFileMeta;
use App\Rules\AtomFeedUrl;
use App\Rules\RssFeedUrl;
use App\Support\Http\OutboundUrlGuard;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Wiring tests proving the SSRF egress guard (App\Support\Http\OutboundUrlGuard)
 * is applied at each editor-controlled outbound fetch: the RSS/Atom feed-URL
 * validation rules and the RemoteFileMeta modifier short-circuit
 * internal/loopback/link-local/private targets BEFORE any HTTP request, and a
 * DNS name that resolves only to an internal address (including an IPv6 AAAA-only
 * record — UAMS-Web/uams-statamic-archive#1013 F1) is likewise refused before connecting.
 *
 * Scope: this is a focused SSRF-wiring test (the only test exercising these three
 * classes). The guard's own allow/deny + pinning logic is covered by
 * tests/Unit/Support/Http/OutboundUrlGuardTest. Resolution is injected
 * (OutboundUrlGuard::setResolver) so the DNS-name cases stay offline.
 *
 * @command  php artisan test --compact tests/Feature/Rules/FeedUrlSsrfTest.php
 */
afterEach(function () {
    // Restore the real A + AAAA resolver so injected state never leaks.
    OutboundUrlGuard::setResolver(null);
});

it('rejects an internal host for the RSS feed rule without fetching', function () {
    Http::fake();

    expect((new RssFeedUrl)->isValid('http://169.254.169.254/latest/meta-data/'))->toBeFalse();

    // The guard short-circuits before any HTTP request is attempted.
    Http::assertNothingSent();
});

it('rejects an internal host for the Atom feed rule without fetching', function () {
    Http::fake();

    expect((new AtomFeedUrl)->isValid('http://127.0.0.1:6379/'))->toBeFalse();

    Http::assertNothingSent();
});

it('rejects an AAAA-only internal DNS name for the RSS feed rule without fetching (F1)', function () {
    // gethostbynamel (A-only) would have seen no record and wrongly allowed this;
    // the dual-family resolver sees the internal AAAA and refuses it.
    OutboundUrlGuard::setResolver(fn (string $host): array => ['fd00::1']);
    Http::fake();

    expect((new RssFeedUrl)->isValid('http://aaaa-only.internal/feed'))->toBeFalse();

    Http::assertNothingSent();
});

it('skips the HEAD request in RemoteFileMeta for an internal host', function () {
    Http::fake();

    $meta = (new RemoteFileMeta)->index('http://10.0.0.5/secret.pdf', [], []);

    // No request is made, and the full fallback shape is the url plus the
    // path-derived extension with every header-derived field null — pinning the
    // whole array (not a 3-key subset) so a regression that drops a fallback key
    // is caught.
    Http::assertNothingSent();
    expect($meta)->toBe([
        'duration' => null,
        'height' => null,
        'width' => null,
        'created_at' => null,
        'updated_at' => null,
        'size' => null,
        'mime_type' => null,
        'extension' => 'pdf',
        'url' => 'http://10.0.0.5/secret.pdf',
        'title' => null,
        'description' => null,
    ]);
});

it('skips the HEAD request in RemoteFileMeta for an AAAA-only internal DNS name (F1)', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['fd00::1']);
    Http::fake();

    $meta = (new RemoteFileMeta)->index('http://aaaa-only.internal/secret.pdf', [], []);

    // Refused before any request; only the URL + path-derived extension survive.
    Http::assertNothingSent();
    expect($meta['url'])->toBe('http://aaaa-only.internal/secret.pdf');
    expect($meta['extension'])->toBe('pdf');
    expect($meta['size'])->toBeNull();
    expect($meta['mime_type'])->toBeNull();
});

it('still requests a public host (IP literal) in RemoteFileMeta', function () {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '1024',
            'Content-Type' => 'application/pdf',
        ]),
    ]);

    $meta = (new RemoteFileMeta)->index('https://93.184.216.34/doc.pdf', [], []);

    // The public host is fetched and its headers populate the meta.
    Http::assertSentCount(1);
    expect($meta['size'])->not->toBeNull();
    expect($meta['mime_type'])->not->toBeNull();
});

it('still requests a public AAAA DNS host in RemoteFileMeta (no regression)', function () {
    // A normal public IPv6 host resolves and its metadata is returned unchanged.
    OutboundUrlGuard::setResolver(fn (string $host): array => ['2606:2800:220:1:248:1893:25c8:1946']);
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '2048',
            'Content-Type' => 'application/pdf',
        ]),
    ]);

    $meta = (new RemoteFileMeta)->index('https://ipv6.example.com/doc.pdf', [], []);

    Http::assertSentCount(1);
    expect($meta['size'])->not->toBeNull();
    expect($meta['mime_type'])->toBe('application/pdf');
});

// UAMS-Web/uams-statamic-archive#1768. The link checker may reach a configured intranet host in private space. These callers
// must not: with `inside.uams.edu` listed in config and resolving to its on-network address,
// each still refuses it before any request.
it('keeps refusing a configured intranet host for every editor-facing fetch', function (Closure $check) {
    config(['link_health.intranet_hosts' => ['inside.uams.edu']]);
    OutboundUrlGuard::setResolver(fn (string $host): array => ['10.1.2.3']);
    Http::fake(['*' => Http::response('<rss><channel></channel></rss>', 200)]);

    $check();

    Http::assertNothingSent();
})->with([
    'RSS feed rule' => [fn () => expect((new RssFeedUrl)->isValid('https://inside.uams.edu/feed'))->toBeFalse()],
    'Atom feed rule' => [fn () => expect((new AtomFeedUrl)->isValid('https://inside.uams.edu/feed'))->toBeFalse()],
    'remote_file_meta modifier' => [fn () => expect((new RemoteFileMeta)->index('https://inside.uams.edu/file.pdf', [], []))
        ->toMatchArray(['size' => null, 'mime_type' => null, 'url' => 'https://inside.uams.edu/file.pdf'])],
]);
