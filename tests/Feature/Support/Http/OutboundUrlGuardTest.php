<?php

declare(strict_types=1);

use App\Support\Http\OutboundUrlGuard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// cspell:ignore fffa

uses(TestCase::class);

/**
 * Behavior of the SSRF egress guard (app/Support/Http/OutboundUrlGuard.php).
 *
 * The guard closes two DNS-name bypasses (UAMS-Web/uams-statamic-archive#1013) on top of the IP-literal
 * allow/deny logic introduced in UAMS-Web/uams-statamic-archive#931:
 *   - F1 (IPv6 AAAA-only): resolution covers BOTH A and AAAA, so a name with
 *     only an internal AAAA record is refused, not treated as "unresolvable".
 *   - F2 (DNS rebinding / TOCTOU): the connection is pinned via CURLOPT_RESOLVE
 *     to the exact vetted IP, so the address the guard validated is the address
 *     curl connects to. Redirects are followed manually so every hop is
 *     re-resolved, re-validated, and re-pinned to its own connected IP.
 *
 * Resolution is injected (OutboundUrlGuard::setResolver) so every case is
 * offline and deterministic — no real DNS, no real sockets.
 *
 * @command  php artisan test --compact tests/Unit/Support/Http/OutboundUrlGuardTest.php
 */
afterEach(function () {
    // Restore the real A + AAAA resolver and its lookups so injected state never leaks.
    OutboundUrlGuard::setResolver(null);
    OutboundUrlGuard::setDnsLookups(null, null);
});

it('blocks internal IP-literal hosts and non-http(s) schemes', function (string $url) {
    expect(OutboundUrlGuard::isAllowedUrl($url))->toBeFalse();
})->with([
    'loopback' => ['http://127.0.0.1/feed'],
    'loopback with redis port' => ['http://127.0.0.1:6379/'],
    'cloud metadata link-local' => ['http://169.254.169.254/latest/meta-data/'],
    'rfc1918 10.x' => ['http://10.0.0.5/internal'],
    'rfc1918 172.16.x (https)' => ['https://172.16.4.2/admin'],
    'rfc1918 192.168.x' => ['http://192.168.1.1/'],
    'ipv6 loopback' => ['http://[::1]/'],
    'ipv6 ula fd00' => ['http://[fd00::1]/'],
    'ipv6 ula fc00' => ['http://[fc00::1]/'],
    'ipv6 link-local fe80' => ['http://[fe80::1]/'],
    'ipv6 site-local fec0 (NO_RES_RANGE gap)' => ['http://[fec0::1]/'],
    'ipv6 multicast ff02 (NO_RES_RANGE gap)' => ['http://[ff02::1]/'],
    'ipv6 v4-mapped loopback' => ['http://[::ffff:127.0.0.1]/'],
    'ipv6 v4-mapped metadata' => ['http://[::ffff:169.254.169.254]/'],
    'ipv6 v4-mapped rfc1918 10.x' => ['http://[::ffff:10.0.0.5]/'],
    'ipv6 nat64 wrapping loopback' => ['http://[64:ff9b::7f00:1]/'],
    'ipv6 nat64 wrapping metadata' => ['http://[64:ff9b::a9fe:a9fe]/'],
    // The RFC 8215 local-use prefix 64:ff9b:1::/48 is refused whole (UAMS-Web/uams-statamic-archive#3006): the
    // first carries 127.0.0.1 in its low bits, the second 10.0.0.5 in the RFC 6052
    // /48 layout (v4 bytes around the reserved "u" octet), and the third a public
    // 8.8.8.8, which is refused all the same.
    'ipv6 local-use nat64 wrapping loopback' => ['http://[64:ff9b:1::7f00:1]/'],
    'ipv6 local-use nat64 wrapping rfc1918 10.x' => ['http://[64:ff9b:1:a00:0:500::]/'],
    'ipv6 local-use nat64 wrapping public 8.8.8.8' => ['http://[64:ff9b:1:808:8:800::]/'],
    'ipv6 6to4 wrapping loopback' => ['http://[2002:7f00:1::]/'],
    'ipv6 6to4 wrapping rfc1918 192.168' => ['http://[2002:c0a8:101::]/'],
    // Teredo (2001:0::/32) carrying a public server (8.8.8.8) but an OBFUSCATED
    // RFC1918 client (10.0.0.1 → bytes XOR 0xff = f5ff:fffe). The obfuscated
    // client must be de-obfuscated and refused.
    'ipv6 teredo wrapping rfc1918 client' => ['http://[2001:0:808:808::f5ff:fffe]/'],
    'ipv4 cgnat 100.64/10' => ['http://100.64.1.1/'],
    'ipv4 cgnat 100.127 upper' => ['http://100.127.255.254/'],
    'ipv4 benchmark 198.18/15' => ['http://198.18.0.1/'],
    'ipv4 benchmark 198.19 upper' => ['http://198.19.255.254/'],
    // Obfuscated-IPv4-literal hosts (decimal/hex/octal/short/zero) are covered by
    // a dedicated F6 test with an injected resolver so they stay offline and
    // mutation-tight — they are intentionally NOT in this no-resolver dataset
    // (without normalization they would fall to real DNS and be non-deterministic).
    'unspecified 0.0.0.0' => ['http://0.0.0.0/'],
    'ipv6 unspecified' => ['http://[::]/'],
    'file scheme' => ['file:///etc/passwd'],
    'gopher scheme' => ['gopher://127.0.0.1:6379/'],
    'bare path (no scheme/host)' => ['/just/a/path'],
    'empty string' => [''],
    'non-http scheme with a public host' => ['ftp://93.184.216.34/'],
]);

it('allows public IP-literal http(s) hosts', function (string $url) {
    expect(OutboundUrlGuard::isAllowedUrl($url))->toBeTrue();
})->with([
    'public ipv4' => ['http://93.184.216.34/'],
    'public ipv4 (https)' => ['https://8.8.8.8/resolve'],
    'public ipv6 literal' => ['http://[2606:2800:220:1:248:1893:25c8:1946]/'],
    // v4-mapped of a PUBLIC v4 must be allowed — FILTER_FLAG_NO_RES_RANGE wrongly
    // treats every v4-mapped address as reserved; the embedded-v4 classifier fixes
    // that regression so a legitimate ::ffff:<public-v4> AAAA is reachable again.
    'v4-mapped public 8.8.8.8' => ['http://[::ffff:8.8.8.8]/'],
    'nat64 wrapping public 8.8.8.8' => ['http://[64:ff9b::808:808]/'],
    // Neighbors of the refused local-use prefix 64:ff9b:1::/48 (UAMS-Web/uams-statamic-archive#3006) stay allowed:
    // each differs from the prefix in exactly one of its six bytes
    'local-use nat64 neighbor, byte 0' => ['http://[164:ff9b:1::808:808]/'],
    'local-use nat64 neighbor, byte 1' => ['http://[65:ff9b:1::808:808]/'],
    'local-use nat64 neighbor, byte 2' => ['http://[64:fe9b:1::808:808]/'],
    'local-use nat64 neighbor, byte 3' => ['http://[64:ff9a:1::808:808]/'],
    'local-use nat64 neighbor, byte 4' => ['http://[64:ff9b:101::808:808]/'],
    'local-use nat64 neighbor, byte 5' => ['http://[64:ff9b:2::808:808]/'],
    '6to4 wrapping public 8.8.8.8' => ['http://[2002:808:808::]/'],
    'teredo wrapping public server+client' => ['http://[2001:0:808:808::fefd:fcfb]/'],
]);

it('refuses an AAAA-only internal host before any request (F1)', function (string $aaaa) {
    // A name that resolves only to an internal IPv6 address must be refused both
    // by the pre-flight check and by the connecting code — gethostbynamel (A-only)
    // would have seen [] and wrongly allowed it.
    OutboundUrlGuard::setResolver(fn (string $host): array => [$aaaa]);

    expect(OutboundUrlGuard::isAllowedUrl('http://aaaa-only.internal/'))->toBeFalse();
    expect(fn () => OutboundUrlGuard::resolveMapFor('http://aaaa-only.internal/'))
        ->toThrow(RuntimeException::class);
})->with([
    'ula fd00' => ['fd00::1'],
    'link-local fe80' => ['fe80::1'],
    'loopback ::1' => ['::1'],
    'site-local fec0' => ['fec0::dead'],
]);

it('pins the connection to the exact vetted IP (F2: validated == connected)', function (string $url, string $ip, string $expectedEntry) {
    // The CURLOPT_RESOLVE map curl uses to bypass its own connect-time DNS is
    // built solely from the address the guard classified as public, so the
    // address validated is provably the address connected to.
    OutboundUrlGuard::setResolver(fn (string $host): array => [$ip]);

    expect(OutboundUrlGuard::resolveMapFor($url))->toBe([$expectedEntry]);
})->with([
    'public A, default http port' => ['http://example.com/feed', '93.184.216.34', 'example.com:80:93.184.216.34'],
    'public A, default https port' => ['https://example.com/doc.pdf', '93.184.216.34', 'example.com:443:93.184.216.34'],
    'public A, explicit port' => ['http://example.com:8080/feed', '93.184.216.34', 'example.com:8080:93.184.216.34'],
    'public AAAA (addr bracketed for curl)' => ['http://ipv6.example.com/', '2606:2800:220:1:248:1893:25c8:1946', 'ipv6.example.com:80:[2606:2800:220:1:248:1893:25c8:1946]'],
]);

it('refuses a rebinding host that mixes a public and an internal address (F2)', function () {
    // A low-TTL attacker resolver returning BOTH a public and an internal record
    // must not let the internal one slip through: any non-public address makes
    // the whole host unsafe.
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34', '127.0.0.1']);

    expect(fn () => OutboundUrlGuard::resolveMapFor('http://rebind.example/'))
        ->toThrow(RuntimeException::class);
});

it('allows a genuinely unresolvable host unpinned but refuses one resolving to a non-public address', function () {
    // A genuinely unresolvable name has no address to pivot to, so the connecting
    // code allows it unpinned (curl's own lookup simply fails to connect) — this
    // matches isAllowedUrl()'s pre-flight and keeps reserved/test domains working.
    OutboundUrlGuard::setResolver(fn (string $host): array => []);
    expect(OutboundUrlGuard::isAllowedUrl('http://nonexistent.invalid./'))->toBeTrue();
    expect(OutboundUrlGuard::resolveMapFor('http://nonexistent.invalid./'))->toBe([]);

    // A host that DOES resolve but to a non-public address (an internal-only host,
    // incl. AAAA-only internal, or a rebinding resolver mixing public + internal)
    // is refused by the connecting code — closing F1/F2.
    OutboundUrlGuard::setResolver(fn (string $host): array => ['10.0.0.5']);
    expect(fn () => OutboundUrlGuard::resolveMapFor('http://rebind.example/'))
        ->toThrow(RuntimeException::class);
});

it('emits no pin for a public IP-literal host (curl connects to the validated literal)', function () {
    // curl performs no name lookup for a literal, so no CURLOPT_RESOLVE entry is
    // needed — but the literal is still validated, so an internal literal throws.
    expect(OutboundUrlGuard::resolveMapFor('http://93.184.216.34/file'))->toBe([]);
    expect(OutboundUrlGuard::resolveMapFor('http://[2606:2800:220:1:248:1893:25c8:1946]/file'))->toBe([]);
    expect(fn () => OutboundUrlGuard::resolveMapFor('http://127.0.0.1/file'))
        ->toThrow(RuntimeException::class);
    expect(fn () => OutboundUrlGuard::resolveMapFor('http://[::1]/file'))
        ->toThrow(RuntimeException::class);
});

it('fetch() follows a public→public redirect, re-pinning each hop', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);

    Http::fake([
        'first.example/*' => Http::response('', 302, ['Location' => 'http://second.example/final']),
        'second.example/*' => Http::response('final-body', 200),
    ]);

    $response = OutboundUrlGuard::fetch('GET', 'http://first.example/start');

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('final-body');

    // Both hops were sent (redirect followed manually).
    Http::assertSentCount(2);
});

it('fetch() blocks a redirect hop to an internal host against the connected IP (F1/F2 on hops)', function () {
    // The first host is public; the redirect target re-resolves to loopback. The
    // hop is refused by re-resolving + re-pinning, never by trusting the name.
    OutboundUrlGuard::setResolver(
        fn (string $host): array => $host === 'pub.example' ? ['93.184.216.34'] : ['127.0.0.1']
    );

    Http::fake([
        'pub.example/*' => Http::response('', 302, ['Location' => 'http://evil.internal/secret']),
        'evil.internal/*' => Http::response('SHOULD-NOT-REACH', 200),
    ]);

    expect(fn () => OutboundUrlGuard::fetch('GET', 'http://pub.example/start'))
        ->toThrow(RuntimeException::class);

    // Only the public first hop was sent; the internal hop was refused before connect.
    Http::assertSentCount(1);
});

it('fetch() refuses an AAAA-only internal host before any request (F1)', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['fd00::1']);

    Http::fake();

    expect(fn () => OutboundUrlGuard::fetch('GET', 'http://aaaa-only.internal/'))
        ->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});

it('fetch() returns a public host response with no redirect', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);

    Http::fake(['*' => Http::response('ok', 200)]);

    $response = OutboundUrlGuard::fetch('GET', 'http://public.example/feed');

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('ok');
    Http::assertSentCount(1);
});

it('wires the vetted IP into the CURLOPT_RESOLVE pin handed to curl (F5/F13)', function () {
    // The pin map is wired into curl as exactly [CURLOPT_RESOLVE => <map>]. This
    // is the security-critical line that makes curl connect to the validated
    // address; deleting/mutating it must turn this assertion red (it was a
    // mutation survivor — the pin was untested end to end).
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);

    expect(OutboundUrlGuard::pinnedCurlOptions('http://example.com/feed'))
        ->toBe([CURLOPT_RESOLVE => ['example.com:80:93.184.216.34']]);

    // Multiple vetted public IPs all land in the pin so curl may try each.
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34', '8.8.8.8']);

    expect(OutboundUrlGuard::pinnedCurlOptions('https://example.com/doc'))
        ->toBe([CURLOPT_RESOLVE => [
            'example.com:443:93.184.216.34',
            'example.com:443:8.8.8.8',
        ]]);

    // An internal address still throws through the pin builder (no map leaks).
    OutboundUrlGuard::setResolver(fn (string $host): array => ['10.0.0.5']);

    expect(fn () => OutboundUrlGuard::pinnedCurlOptions('http://internal.example/'))
        ->toThrow(RuntimeException::class);
});

it('fetch() re-pins each redirect hop to its OWN vetted IP (per-hop pin, F5/F13)', function () {
    // Each hop must be pinned to the IP of THAT hop's host, not the first hop's.
    // A resolver spy returns a distinct IP per host and records what it resolved,
    // and the pin for each hop is rebuilt from the redirect target's URL — so a
    // mutant that pins every hop to the first host's IP (or skips re-pinning the
    // second hop) is caught.
    $resolved = [];

    OutboundUrlGuard::setResolver(function (string $host) use (&$resolved): array {
        $resolved[] = $host;

        return match ($host) {
            'first.example' => ['93.184.216.34'],
            'second.example' => ['8.8.8.8'],
            default => [],
        };
    });

    // Each hop's pin is built from its own host:ip — proven directly.
    expect(OutboundUrlGuard::pinnedCurlOptions('http://first.example/start'))
        ->toBe([CURLOPT_RESOLVE => ['first.example:80:93.184.216.34']]);
    expect(OutboundUrlGuard::pinnedCurlOptions('http://second.example/final'))
        ->toBe([CURLOPT_RESOLVE => ['second.example:80:8.8.8.8']]);

    // Reset the spy so it records only what fetch() resolves, not the pre-checks.
    $resolved = [];

    Http::fake([
        'first.example/*' => Http::response('', 302, ['Location' => 'http://second.example/final']),
        'second.example/*' => Http::response('final-body', 200),
    ]);

    $response = OutboundUrlGuard::fetch('GET', 'http://first.example/start');

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('final-body');

    // Both hops were resolved (and therefore re-pinned), in order.
    expect($resolved)->toBe(['first.example', 'second.example']);
    Http::assertSentCount(2);
});

it('classifies an obfuscated IPv4 literal host by its real address (F6)', function (string $url, bool $allowed) {
    // Decimal / hex / octal / short IPv4 literals are not recognized by
    // FILTER_VALIDATE_IP and would otherwise reach DNS and be re-parsed by curl
    // as the underlying IPv4. They are normalized and classified up front.
    //
    // The resolver is injected to return a PUBLIC IP for ANYTHING it is asked, so
    // this test is offline AND proves the normalizer — not DNS — decides the
    // verdict: if normalization were skipped, every obfuscated host would fall to
    // this public resolver and be ALLOWED, flipping the refused cases (a mutant
    // that drops normalization is therefore killed, not silently survived).
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);

    expect(OutboundUrlGuard::isAllowedUrl($url))->toBe($allowed);
})->with([
    'decimal loopback refused' => ['http://2130706433/', false],
    'hex loopback refused' => ['http://0x7f.0.0.1/', false],
    'octal loopback refused' => ['http://0177.0.0.1/', false],
    'short loopback refused' => ['http://127.1/', false],
    'decimal rfc1918 refused' => ['http://167772165/', false],
    'zero host refused' => ['http://0/', false],
    'decimal public allowed' => ['http://134744072/', true],   // 8.8.8.8
    'hex public allowed' => ['http://0x8.8.8.8/', true],        // 8.8.8.8
]);

it('leaves a real hostname (non-numeric label) to DNS resolution, never misread as IPv4 (F6)', function () {
    // A normal hostname must NOT be normalized as an obfuscated literal; the
    // injected resolver still fires for it.
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);

    expect(OutboundUrlGuard::isAllowedUrl('http://feeds.example.com/rss'))->toBeTrue();
    expect(OutboundUrlGuard::resolveMapFor('http://feeds.example.com/rss'))
        ->toBe(['feeds.example.com:80:93.184.216.34']);
});

it('classifies an embedded-v4 IPv6 reached via an AAAA record, not just a literal (F2/F8)', function (string $aaaa, bool $allowed) {
    // The embedded-v4 extraction must also protect a DNS name whose AAAA record
    // is an internal-v4-embedding IPv6 (rebinding via a crafted AAAA), and must
    // NOT over-block a public-v4-embedding AAAA.
    OutboundUrlGuard::setResolver(fn (string $host): array => [$aaaa]);

    expect(OutboundUrlGuard::isAllowedUrl('http://aaaa.example/'))->toBe($allowed);
})->with([
    'v4-mapped loopback AAAA refused' => ['::ffff:127.0.0.1', false],
    'nat64 metadata AAAA refused' => ['64:ff9b::a9fe:a9fe', false],
    'local-use nat64 loopback AAAA refused' => ['64:ff9b:1::7f00:1', false],
    'local-use nat64 rfc1918 AAAA refused' => ['64:ff9b:1:a00:0:500::', false],
    '6to4 rfc1918 AAAA refused' => ['2002:c0a8:101::', false],
    'teredo rfc1918-client AAAA refused' => ['2001:0:808:808::f5ff:fffe', false],
    'v4-mapped public AAAA allowed' => ['::ffff:8.8.8.8', true],
]);

// --- UAMS-Web/uams-statamic-archive#1768: private-network hosts, the link checker's intranet exception ----------------------
//
// `inside.uams.edu` resolves to a private address on the UAMS network. The guard admits a private
// address only for a host named EXACTLY in the list a caller passes, only in RFC1918 / ULA space,
// and judges every redirect hop by its own host. Every case below is offline: the resolver is
// injected, so none of it depends on the network the suite runs on.

/** A placeholder RFC1918 address standing in for the private address `inside.uams.edu` resolves to on-network (UAMS-Web/uams-statamic-archive#1768). It is not a measured value (UAMS-Web/uams-statamic-archive#2780). */
const OUG_INTRANET_IP = '10.1.2.3';

it('pins a listed host to its private-network address instead of refusing it', function (string $ip, string $entry) {
    OutboundUrlGuard::setResolver(fn (string $host): array => [$ip]);

    expect(OutboundUrlGuard::resolveMapFor('https://inside.uams.edu/page', ['inside.uams.edu']))
        ->toBe([$entry]);
})->with([
    'rfc1918 10/8, the intranet placeholder' => [OUG_INTRANET_IP, 'inside.uams.edu:443:10.1.2.3'],
    'rfc1918 172.16/12 lower edge' => ['172.16.0.1', 'inside.uams.edu:443:172.16.0.1'],
    'rfc1918 172.16/12 upper edge' => ['172.31.255.254', 'inside.uams.edu:443:172.31.255.254'],
    'rfc1918 192.168/16' => ['192.168.1.10', 'inside.uams.edu:443:192.168.1.10'],
    'ipv6 ula fd00::/8' => ['fd00::1', 'inside.uams.edu:443:[fd00::1]'],
    'ipv6 ula fc00::/8' => ['fc00::1', 'inside.uams.edu:443:[fc00::1]'],
]);

it('refuses the same private-network host when the caller lists nothing', function () {
    // The control for the case above: the exception exists only where a caller passes a list.
    OutboundUrlGuard::setResolver(fn (string $host): array => [OUG_INTRANET_IP]);

    expect(fn () => OutboundUrlGuard::resolveMapFor('https://inside.uams.edu/page'))
        ->toThrow(RuntimeException::class, 'Refusing host that resolves to a non-public address');
});

it('still refuses a listed host resolving outside private-network space', function (string $ip) {
    // A listed name whose DNS points at loopback or the metadata host gains nothing: the
    // exception is for private networks, not for every non-public address.
    OutboundUrlGuard::setResolver(fn (string $host): array => [$ip]);

    expect(fn () => OutboundUrlGuard::resolveMapFor('https://inside.uams.edu/page', ['inside.uams.edu']))
        ->toThrow(RuntimeException::class);
})->with([
    'loopback' => ['127.0.0.1'],
    'cloud metadata link-local' => ['169.254.169.254'],
    'cgnat 100.64/10' => ['100.64.1.1'],
    'benchmarking 198.18/15' => ['198.18.0.1'],
    'this-network 0.0.0.0' => ['0.0.0.0'],
    'ipv6 loopback' => ['::1'],
    'ipv6 link-local fe80' => ['fe80::1'],
    'ipv6 site-local fec0' => ['fec0::1'],
    'ipv6 v4-mapped rfc1918' => ['::ffff:10.1.2.3'],
    'ipv6 v4-mapped metadata' => ['::ffff:169.254.169.254'],
]);

it('refuses a listed host whose records mix a private-network and a loopback address', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => [OUG_INTRANET_IP, '127.0.0.1']);

    expect(fn () => OutboundUrlGuard::resolveMapFor('https://inside.uams.edu/page', ['inside.uams.edu']))
        ->toThrow(RuntimeException::class);
});

it('matches a listed host exactly, never by suffix, subdomain or parent', function (string $url) {
    OutboundUrlGuard::setResolver(fn (string $host): array => [OUG_INTRANET_IP]);

    expect(fn () => OutboundUrlGuard::resolveMapFor($url, ['inside.uams.edu']))
        ->toThrow(RuntimeException::class);
})->with([
    'a subdomain' => ['https://evil.inside.uams.edu/'],
    'a longer label ending the same way' => ['https://notinside.uams.edu/'],
    'the parent domain' => ['https://uams.edu/'],
    'the listed name as a prefix of another domain' => ['https://inside.uams.edu.evil.example/'],
    'a sibling host' => ['https://outside.uams.edu/'],
]);

it('treats letter case and one trailing root dot as the same host', function (string $url, string $listed) {
    OutboundUrlGuard::setResolver(fn (string $host): array => [OUG_INTRANET_IP]);

    expect(OutboundUrlGuard::resolveMapFor($url, [$listed]))->toHaveCount(1);
})->with([
    'upper-case url' => ['https://INSIDE.UAMS.EDU/', 'inside.uams.edu'],
    'upper-case entry' => ['https://inside.uams.edu/', 'Inside.UAMS.edu'],
    'root dot on the url' => ['https://inside.uams.edu./', 'inside.uams.edu'],
    'root dot on the entry' => ['https://inside.uams.edu/', 'inside.uams.edu.'],
    'whitespace around the entry' => ['https://inside.uams.edu/', '  inside.uams.edu '],
]);

it('never matches an IP literal, whichever side of the comparison it is on', function () {
    // Listing the address itself must not turn the exception into one for an address.
    expect(fn () => OutboundUrlGuard::resolveMapFor('http://'.OUG_INTRANET_IP.'/', [OUG_INTRANET_IP]))
        ->toThrow(RuntimeException::class);
    expect(fn () => OutboundUrlGuard::resolveMapFor('http://[fd00::1]/', ['fd00::1', '[fd00::1]']))
        ->toThrow(RuntimeException::class);

    // The obfuscated IPv4 forms curl parses as a literal are literals here too, so listing one
    // cannot smuggle an address past the "names only" rule.
    OutboundUrlGuard::setResolver(fn (string $host): array => ['10.0.0.1']);
    expect(fn () => OutboundUrlGuard::resolveMapFor('http://0x0a.0.0.1/', ['0x0a.0.0.1']))
        ->toThrow(RuntimeException::class);
    expect(fn () => OutboundUrlGuard::resolveMapFor('http://167772161/', ['167772161']))
        ->toThrow(RuntimeException::class);

    expect(OutboundUrlGuard::isListedHost(OUG_INTRANET_IP, [OUG_INTRANET_IP]))->toBeFalse()
        ->and(OutboundUrlGuard::isListedHost('0x0a.0.0.1', ['0x0a.0.0.1']))->toBeFalse()
        ->and(OutboundUrlGuard::isListedHost('[fd00::1]', ['fd00::1']))->toBeFalse()
        ->and(OutboundUrlGuard::isListedHost('inside.uams.edu', ['', ' ']))->toBeFalse()
        ->and(OutboundUrlGuard::isListedHost('', ['', ' ']))->toBeFalse()
        ->and(OutboundUrlGuard::isListedHost('.', ['.']))->toBeFalse()
        ->and(OutboundUrlGuard::isListedHost('inside.uams.edu', []))->toBeFalse()
        ->and(OutboundUrlGuard::isListedHost('inside.uams.edu', ['other.example', 'inside.uams.edu']))->toBeTrue();
});

it('leaves the pre-flight isAllowedUrl() check strict for a listed host', function () {
    // isAllowedUrl() takes no list, so no caller's pre-flight can be widened by the exception.
    OutboundUrlGuard::setResolver(fn (string $host): array => [OUG_INTRANET_IP]);

    expect(OutboundUrlGuard::isAllowedUrl('https://inside.uams.edu/'))->toBeFalse();
});

it('fetch() reaches a listed host, pinned to its private-network address', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => [OUG_INTRANET_IP]);
    Http::fake(['inside.uams.edu/*' => Http::response('intranet', 200)]);

    $response = OutboundUrlGuard::fetch('GET', 'https://inside.uams.edu/page', null, 5, ['inside.uams.edu']);

    expect($response->status())->toBe(200);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://inside.uams.edu/page';
    });
    Http::assertSentCount(1);
});

it('fetch() without a list refuses a listed host before any request', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => [OUG_INTRANET_IP]);
    Http::fake();

    expect(fn () => OutboundUrlGuard::fetch('GET', 'https://inside.uams.edu/page'))
        ->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});

it('fetch() refuses a listed host redirecting to an UNlisted private host (per hop)', function () {
    // The listed host is reached; the hop it hands off to is judged by its own name.
    OutboundUrlGuard::setResolver(fn (string $host): array => $host === 'inside.uams.edu'
        ? [OUG_INTRANET_IP]
        : ['10.0.0.9']);

    Http::fake([
        'inside.uams.edu/*' => Http::response('', 302, ['Location' => 'http://payroll.internal/admin']),
        'payroll.internal/*' => Http::response('SHOULD-NOT-REACH', 200),
    ]);

    expect(fn () => OutboundUrlGuard::fetch('GET', 'https://inside.uams.edu/start', null, 5, ['inside.uams.edu']))
        ->toThrow(RuntimeException::class, 'payroll.internal');

    Http::assertSentCount(1);
});

it('fetch() refuses a listed host redirecting to the metadata host (per hop)', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => [OUG_INTRANET_IP]);

    Http::fake([
        'inside.uams.edu/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        '*' => Http::response('SHOULD-NOT-REACH', 200),
    ]);

    expect(fn () => OutboundUrlGuard::fetch('GET', 'https://inside.uams.edu/start', null, 5, ['inside.uams.edu']))
        ->toThrow(RuntimeException::class);

    Http::assertSentCount(1);
});

it('admits a listed host on its scheme default port only', function (string $url, bool $admitted) {
    // Content could otherwise name any port on the intranet address and read back which ones
    // answer: the exception covers the web port a link to the host would actually use.
    OutboundUrlGuard::setResolver(fn (string $host): array => [OUG_INTRANET_IP]);

    $resolve = fn () => OutboundUrlGuard::resolveMapFor($url, ['inside.uams.edu']);

    if ($admitted) {
        expect($resolve())->toHaveCount(1);
    } else {
        expect($resolve)->toThrow(RuntimeException::class);
    }
})->with([
    'https implicit' => ['https://inside.uams.edu/', true],
    'https explicit 443' => ['https://inside.uams.edu:443/', true],
    'http implicit' => ['http://inside.uams.edu/', true],
    'http explicit 80' => ['http://inside.uams.edu:80/', true],
    'https on 80' => ['https://inside.uams.edu:80/', false],
    'http on 443' => ['http://inside.uams.edu:443/', false],
    'redis port' => ['http://inside.uams.edu:6379/', false],
    'ssh port' => ['https://inside.uams.edu:22/', false],
    'alternate web port' => ['https://inside.uams.edu:8443/', false],
]);

it('strips one trailing root dot and no more', function () {
    expect(OutboundUrlGuard::isListedHost('inside.uams.edu..', ['inside.uams.edu']))->toBeFalse()
        ->and(OutboundUrlGuard::isListedHost('inside.uams.edu', ['inside.uams.edu..']))->toBeFalse()
        ->and(OutboundUrlGuard::isListedHost('inside.uams.edu.', ['inside.uams.edu']))->toBeTrue();
});

it('cannot be subclassed to reach the exception by another name', function () {
    expect((new ReflectionClass(OutboundUrlGuard::class))->isFinal())->toBeTrue();
});

it('refuses a URL with no host, and reads the scheme in any case', function (string $url, bool $allowed) {
    expect(OutboundUrlGuard::isAllowedUrl($url))->toBe($allowed);
})->with([
    // parse_url() reads these as an http URL with only a path
    'no host' => ['http:/feed', false],
    'no slashes' => ['http:feed', false],
    // parse_url() cannot read this at all
    'unparseable' => ['http:///feed', false],
    'upper-case scheme' => ['HTTPS://8.8.8.8/', true],
]);

it('refuses to build a pin for a non-http(s) URL or one with no host', function (string $url, string $message) {
    expect(fn () => OutboundUrlGuard::resolveMapFor($url))->toThrow(RuntimeException::class, $message);
})->with([
    'another scheme' => ['ftp://files.example/feed', 'Refusing non-http(s) URL: ftp://files.example/feed'],
    'no host' => ['http:/feed', 'Refusing URL with no host: http:/feed'],
]);

it('refuses a listed host whose resolver answers with something that is not an address', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['not-an-address']);

    expect(fn () => OutboundUrlGuard::resolveMapFor('http://inside.uams.edu/', ['inside.uams.edu']))
        ->toThrow(RuntimeException::class, 'Refusing host that resolves to a non-public address: inside.uams.edu');
});

it('reads an IPv6 address as v4-mapped or NAT64 only when every other prefix byte is zero', function (string $ip, bool $allowed) {
    expect(OutboundUrlGuard::isAllowedUrl("http://[{$ip}]/"))->toBe($allowed);
})->with([
    'v4-mapped rfc1918 refused' => ['::ffff:a00:5', false],
    'a nonzero first byte is not v4-mapped' => ['1::ffff:a00:5', true],
    'a nonzero tenth byte is not v4-mapped' => ['::1:ffff:a00:5', true],
    'nat64 rfc1918 refused' => ['64:ff9b::a00:5', false],
    'a nonzero twelfth byte is not the /96 nat64 prefix' => ['64:ff9b::1:0:a00:5', true],
]);

it('reads an embedded IPv4 address only behind its exact prefix', function (string $ip) {
    // Each address carries 10.0.0.5 where an embedding would put it, behind a
    // prefix one byte away from a real one, so reading it as embedded refuses it
    expect(OutboundUrlGuard::isAllowedUrl("http://[{$ip}]/"))->toBeTrue();
})->with([
    'v4-mapped with only byte 10 set' => ['::ff00:a00:5'],
    'v4-mapped with only byte 11 set' => ['::ff:a00:5'],
    'v4-mapped with a nonzero first byte' => ['100::ffff:a00:5'],
    'nat64 with only a zero first byte' => ['0:1::a00:5'],
    'nat64 with only bytes 2 and 3 matching' => ['1:ff9b::a00:5'],
    'nat64 with only byte 3 matching' => ['0:9b::a00:5'],
    'nat64 with a nonzero fifth byte' => ['64:ff9b:100::a00:5'],
    'nat64 with a nonzero twelfth byte' => ['64:ff9b::1:a00:5'],
    'teredo with a nonzero third byte' => ['2001:100:808:808::f5ff:fffa'],
    'teredo with a nonzero fourth byte' => ['2001:1:808:808::f5ff:fffa'],
]);

it('reads a Teredo client from bytes 12 to 15, each XOR 0xFF', function () {
    // The client is 192.168.1.1; the port byte before it and the bytes after its
    // first two octets each decode to a public octet, so reading the wrong byte allows it
    expect(OutboundUrlGuard::isAllowedUrl('http://[2001:0:808:808:0:f7f7:3f57:fefe]/'))->toBeFalse();
});

it('reads only well-formed obfuscated IPv4 hosts as addresses, leaving the rest to the resolver', function (string $host, bool $allowed) {
    // The injected resolver answers public for anything, so a refusal means the host was read as an address
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);

    expect(OutboundUrlGuard::isAllowedHost($host))->toBe($allowed);
})->with([
    'three parts, loopback' => ['127.0.1', false],
    'three parts, a 16-bit final part' => ['10.0.256', false],
    'two parts, a 24-bit final part' => ['10.16777215', false],
    'a leading part above 255' => ['256.0.0.1', true],
    'a final part above 255 in four parts' => ['1.0.0.256', true],
    'a final part above 24 bits in two parts' => ['1.16777216', true],
    'five parts' => ['1.2.3.4.5', true],
]);

it('refuses a Teredo address whose server is private, whatever its client', function () {
    // The server 192.168.1.1 sits in bytes 4 to 7 and the client decodes to 8.8.8.8, so
    // dropping the server, or reading it one byte late (168.1.1.0), allows the address
    expect(OutboundUrlGuard::isAllowedUrl('http://[2001:0:c0a8:101::f7f7:f7f7]/'))->toBeFalse();
});

it('pins an obfuscated IPv4 host to the exact address it spells', function (string $host) {
    // The resolver answers an address one below the spelled one, so a host misread as a
    // name, a part shifted to the wrong byte, or a dropped low bit pins something else
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);

    expect(OutboundUrlGuard::resolveMapFor("http://{$host}/"))->toBe(["{$host}:80:93.184.216.35"]);
})->with([
    'one decimal part' => ['1572395043'],
    'one hex part' => ['0x5db8d823'],
    'one hex part with an upper-case prefix' => ['0X5DB8D823'],
    'two parts' => ['93.0xb8d823'],
    'three parts' => ['93.184.0xd823'],
    'four hex parts' => ['0x5d.0xb8.0xd8.0x23'],
    'four octal parts' => ['0135.0270.0330.043'],
]);

it('reads each part of an obfuscated IPv4 host up to its exact limit', function (string $host, ?string $pinned) {
    // A host past a limit is a name, so the resolver's 93.184.216.34 is pinned for it;
    // a null expectation is an address the guard refuses
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);

    if ($pinned === null) {
        expect(fn () => OutboundUrlGuard::resolveMapFor("http://{$host}/"))
            ->toThrow(RuntimeException::class, 'Refusing host that resolves to a non-public address: '.$host);

        return;
    }

    expect(OutboundUrlGuard::resolveMapFor("http://{$host}/"))->toBe(["{$host}:80:{$pinned}"]);
})->with([
    'one part at 32 bits' => ['4294967295', null],
    'one part past 32 bits' => ['4294967296', '93.184.216.34'],
    'two parts at 24 bits' => ['1.16777215', '1.255.255.255'],
    'two parts past 24 bits' => ['1.16777216', '93.184.216.34'],
    'three parts at 16 bits' => ['1.2.65535', '1.2.255.255'],
    'three parts past 16 bits' => ['1.2.65536', '93.184.216.34'],
    'four parts at 8 bits' => ['01.2.3.255', '1.2.3.255'],
    'four parts past 8 bits' => ['01.2.3.256', '93.184.216.34'],
    'a first part past 8 bits' => ['256.1', '93.184.216.34'],
    'a leading part at 8 bits' => ['1.255.1', '1.255.0.1'],
    'a leading part past 8 bits' => ['1.256.1', '93.184.216.34'],
    'an empty host part' => ['1..1', '93.184.216.34'],
    'a hex prefix with no digits' => ['0x.1', '93.184.216.34'],
    'a decimal part with a leading zero' => ['08.1', '93.184.216.34'],
]);

it('combines the A and AAAA records with the addresses gethostbynamel answers, once each', function () {
    $asked = [];
    OutboundUrlGuard::setDnsLookups(
        function (string $host, int $types) use (&$asked): array {
            $asked[] = [$host, $types];

            // A record and AAAA record shapes, plus two whose address is not text
            return [
                ['type' => 'A', 'ip' => '93.184.216.34'],
                ['type' => 'AAAA', 'ipv6' => '2606:2800:220:1:248:1893:25c8:1946'],
                ['type' => 'A', 'ip' => 42],
                ['type' => 'AAAA', 'ipv6' => false],
            ];
        },
        fn (string $host): array => ['93.184.216.34', '93.184.216.35'],
    );

    expect(OutboundUrlGuard::resolveMapFor('http://dual.example/'))->toBe([
        'dual.example:80:93.184.216.34',
        'dual.example:80:[2606:2800:220:1:248:1893:25c8:1946]',
        'dual.example:80:93.184.216.35',
    ])
        ->and($asked)->toBe([['dual.example', DNS_A | DNS_AAAA]]);
});

it('takes the addresses gethostbynamel answers when the record lookup fails', function () {
    OutboundUrlGuard::setDnsLookups(
        fn (string $host, int $types): false => false,
        fn (string $host): array => ['93.184.216.34'],
    );

    expect(OutboundUrlGuard::resolveMapFor('http://a-only.example/'))->toBe(['a-only.example:80:93.184.216.34']);
});

it('takes the records when gethostbynamel fails, and allows a name neither lookup answers', function () {
    OutboundUrlGuard::setDnsLookups(
        fn (string $host, int $types): array => $host === 'aaaa-only.example' ? [['type' => 'AAAA', 'ipv6' => '2606:2800:220:1:248:1893:25c8:1946']] : [],
        fn (string $host): false => false,
    );

    expect(OutboundUrlGuard::resolveMapFor('http://aaaa-only.example/'))->toBe(['aaaa-only.example:80:[2606:2800:220:1:248:1893:25c8:1946]'])
        ->and(OutboundUrlGuard::resolveMapFor('http://nowhere.example/'))->toBe([]);
});

it('resolves a name itself when no resolver is injected', function () {
    // localhost resolves from the hosts file on every supported platform; the DNS record
    // lookup that runs first comes back empty for it
    OutboundUrlGuard::setResolver(null);

    expect(OutboundUrlGuard::isAllowedHost('localhost'))->toBeFalse();
});

it('fetch() sends a HEAD request through the caller\'s configuration', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);
    Http::fake(['*' => Http::response('', 200)]);

    OutboundUrlGuard::fetch('HEAD', 'http://public.example/feed', fn ($request) => $request->withHeaders(['X-Probe' => 'yes']));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'HEAD' && $request->hasHeader('X-Probe', 'yes'));
    Http::assertSentCount(1);
});

it('fetch() returns a redirect that names no location', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);
    Http::fake(['*' => Http::response('', 301)]);

    expect(OutboundUrlGuard::fetch('GET', 'http://public.example/feed')->status())->toBe(301);
    Http::assertSentCount(1);
});

it('fetch() resolves relative, root-relative and scheme-relative redirects against the current URL', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);
    $locations = [
        'https://a.example:8443/dir/page' => 'next',
        'https://a.example:8443/dir/next' => '/abs?x=1',
        'https://a.example:8443/abs?x=1' => '//b.example/other',
        'https://b.example/other' => 'http://c.example',
        'http://c.example' => 'rel',
    ];
    Http::fake(fn (Request $request) => isset($locations[$request->url()])
        ? Http::response('', 302, ['Location' => $locations[$request->url()]])
        : Http::response('done', 200));

    $response = OutboundUrlGuard::fetch('GET', 'https://a.example:8443/dir/page', maxRedirects: 10);

    expect($response->body())->toBe('done')
        ->and(Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->all())->toBe([
            'https://a.example:8443/dir/page',
            'https://a.example:8443/dir/next',
            'https://a.example:8443/abs?x=1',
            'https://b.example/other',
            'http://c.example',
            'http://c.example/rel',
        ]);
});

it('fetch() follows at most the given number of redirects', function (int $maxRedirects, int $sent) {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);
    Http::fake(fn (Request $request) => Http::response('', 302, ['Location' => $request->url().'/more']));

    expect(fn () => OutboundUrlGuard::fetch('GET', 'http://loop.example/a', maxRedirects: $maxRedirects))
        ->toThrow(RuntimeException::class, 'Too many redirects following: http://loop.example/a'.str_repeat('/more', $sent));
    Http::assertSentCount($sent);
})->with([
    'none' => [0, 1],
    'two' => [2, 3],
]);

it('hands the pin to the HTTP client it sends with', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);
    Http::fake(['*' => Http::response('ok', 200)]);
    // An object, since the configure callback is an arrow function and copies what it captures
    $sentCurl = new ArrayObject;

    OutboundUrlGuard::fetch('GET', 'http://public.example/feed', fn ($request) => $request->beforeSending(
        function ($sent, array $options) use ($sentCurl): void {
            $sentCurl[] = $options['curl'] ?? null;
        }
    ));

    expect($sentCurl->getArrayCopy())->toBe([[CURLOPT_RESOLVE => ['public.example:80:93.184.216.34']]]);
});

it('follows a 3xx status only', function (int $status, int $sent) {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);
    Http::fake(fn (Request $request) => str_ends_with($request->url(), '/next')
        ? Http::response('next', 200)
        : Http::response('', $status, ['Location' => '/next']));

    OutboundUrlGuard::fetch('GET', 'http://public.example/start');

    Http::assertSentCount($sent);
})->with([
    '299' => [299, 1],
    '300' => [300, 2],
    '399' => [399, 2],
    '400' => [400, 1],
]);

it('builds a pin from a scheme in any case, and refuses an unparseable URL as a non-http(s) one', function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);

    expect(OutboundUrlGuard::resolveMapFor('HTTPS://feeds.example.com/rss'))->toBe(['feeds.example.com:443:93.184.216.34'])
        ->and(fn () => OutboundUrlGuard::resolveMapFor('http:///feed'))->toThrow(RuntimeException::class, 'Refusing non-http(s) URL: http:///feed');
});

it('refuses CGNAT and benchmarking space only inside their ranges', function (string $ip, bool $allowed) {
    expect(OutboundUrlGuard::isAllowedUrl("http://{$ip}/"))->toBe($allowed);
})->with([
    'just below CGNAT' => ['100.63.255.255', true],
    'just above CGNAT' => ['100.128.0.1', true],
    'a CGNAT second octet under another first octet' => ['8.64.0.1', true],
    'just below benchmarking' => ['198.17.0.1', true],
    'just above benchmarking' => ['198.20.0.1', true],
    'a benchmarking second octet under another first octet' => ['8.18.0.1', true],
]);

it('refuses deprecated site-local IPv6 across fec0::/10 only', function (string $ip, bool $allowed) {
    expect(OutboundUrlGuard::isAllowedUrl("http://[{$ip}]/"))->toBe($allowed);
})->with([
    'fe00::/9 below it' => ['fe00::1', true],
    'fe40:: below it' => ['fe40::1', true],
    'a site-local second byte under another first byte' => ['2fc0::1', true],
    'fec1::, inside it' => ['fec1::1', false],
    'fee0::, high inside it' => ['fee0::1', false],
]);

it('reads a bracketed host the same as a bare one when matching the list', function () {
    expect(OutboundUrlGuard::isListedHost('[inside.uams.edu]', ['inside.uams.edu']))->toBeTrue();
});

it('admits a listed host to 192.168/16 only as a pair of octets, not to any x.168 address', function () {
    // Loopback with a second octet of 168: refused for everyone, listed or not
    OutboundUrlGuard::setResolver(fn (string $host): array => ['127.168.0.1']);

    expect(fn () => OutboundUrlGuard::resolveMapFor('http://inside.uams.edu/', ['inside.uams.edu']))
        ->toThrow(RuntimeException::class, 'Refusing host that resolves to a non-public address: inside.uams.edu');
});
