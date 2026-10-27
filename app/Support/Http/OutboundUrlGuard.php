<?php

declare(strict_types=1);

namespace App\Support\Http;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Egress guard for editor-controlled outbound HTTP fetches (SSRF defense).
 *
 * Several features fetch a URL supplied by a CP editor — the RSS/Atom feed
 * block, the `remote_file_meta` modifier, the feed-URL validation rules. Each
 * previously validated only the scheme (or nothing), letting an editor point
 * the server at internal infrastructure (`http://127.0.0.1:6379/`,
 * `http://169.254.169.254/…` cloud metadata, RFC1918 hosts). This guard
 * enforces an http/https allowlist and rejects any URL whose host resolves to a
 * loopback, link-local, or private/reserved address.
 *
 * It closes two DNS-name bypasses (see UAMS-Web/uams-statamic-archive#1013):
 *
 *   - **IPv6 AAAA-only hosts.** Resolution covers *both* address families the
 *     client may use (A and AAAA), so a name with only an internal AAAA record
 *     ({@see https://www.rfc-editor.org/rfc/rfc4193 ULA}, link-local, `::1`)
 *     is refused rather than treated as "unresolvable → allowed".
 *
 *   - **DNS rebinding / TOCTOU.** {@see fetch()} pins the connection to the
 *     exact vetted IP via curl's `CURLOPT_RESOLVE`, so the address the guard
 *     validated is the address the client connects to — a low-TTL attacker
 *     resolver cannot answer the guard with a public IP and curl with an
 *     internal one. Redirects are followed manually so every hop is re-resolved,
 *     re-validated, and re-pinned to its own connected IP.
 *
 * Hosts that resolve to *no* public address are refused (an unroutable host is
 * not a safe fetch target); a name that does not resolve at all is allowed only
 * for the pre-flight {@see isAllowedUrl()} check, while {@see fetch()} — the
 * code that actually connects — refuses any host without a vetted public IP.
 *
 * **One narrow, opt-in exception (UAMS-Web/uams-statamic-archive#1768).** {@see fetch()} accepts a list of
 * *private-network hosts*: exact host names a caller may reach even though they
 * resolve to a private address. It exists for the link checker, whose content
 * links to intranet hosts such as `inside.uams.edu` that resolve to RFC1918
 * space on the network production runs on. Every other caller passes nothing
 * and keeps the guard exactly as strict as before. The exception is bounded on
 * three sides:
 *
 *   - **Exact names only.** A listed host matches itself, case-insensitively and
 *     ignoring one trailing root dot — never a subdomain, a suffix, or a pattern.
 *     An IP literal is never matched, whatever the list says.
 *   - **Default port only.** The exception holds on the scheme's own port (80
 *     for http, 443 for https, implicit or explicit). A listed host named with
 *     any other port is judged like every other host, so content cannot turn
 *     the link checker into a port scanner against the intranet address.
 *   - **Private-network space only.** A listed host may resolve to RFC1918 IPv4
 *     or IPv6 ULA (`fc00::/7`), and to nothing else non-public: loopback,
 *     link-local (cloud metadata), CGNAT, reserved, multicast and embedded-v4
 *     forms are still refused, so a listed name whose DNS is poisoned toward
 *     `169.254.169.254` gains nothing.
 *   - **Per hop.** Each redirect hop is judged by its own host, so a listed host
 *     cannot hand the connection to an unlisted private one, and the connection
 *     is still pinned to the address that was vetted.
 */
final class OutboundUrlGuard
{
    /**
     * Resolver used to look up a host's A + AAAA addresses. Overridable in tests
     * to inject a deterministic, offline resolver. Receives the bare host and
     * returns a list of IP-literal strings (IPv4 and/or IPv6).
     *
     * @var (Closure(string): list<string>)|null
     */
    private static ?Closure $resolver = null;

    /**
     * The two DNS lookups the real resolver makes, overridable in tests so the way
     * their answers are combined can be checked offline: `$recordLookup` stands in
     * for `dns_get_record()` and `$addressLookup` for `gethostbynamel()`. Null uses
     * the real function.
     *
     * @var (Closure(string, int): (list<array<string, mixed>>|false))|null
     */
    private static ?Closure $recordLookup = null;

    /**
     * @var (Closure(string): (list<string>|false))|null
     */
    private static ?Closure $addressLookup = null;

    /**
     * Whether a pre-flight check permits an outbound fetch to this URL.
     *
     * Returns false for a non-http(s) scheme, a missing host, or any host that
     * resolves to a non-public address. A host that does not resolve at all is
     * allowed here (it poses no connect-time SSRF risk for this advisory check,
     * and blocking it would reject legitimately-unresolvable feeds and reserved
     * doc/test domains); the actual connection in {@see fetch()} additionally
     * refuses any host that yields no vetted public address.
     */
    public static function isAllowedUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        // A loose comparison of two non-numeric strings is a strict one in PHP 8
        // @pest-mutate-ignore: TrueToFalse
        if (! \in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // parse_url() returns false rather than an empty host, so this is a backstop
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if (! \is_string($host) || $host === '') {
            return false;
        }

        return self::isAllowedHost($host);
    }

    /**
     * Whether a host (IP literal or DNS name) is safe to fetch: it must not
     * resolve to any loopback, link-local, or private/reserved address in either
     * address family (A or AAAA).
     *
     * A host that does not resolve is allowed here for the advisory pre-flight
     * check (no connection can be established, and blocking it would reject
     * legitimately-unresolvable feeds and reserved doc/test domains). The guard
     * only refuses targets that actually resolve to a non-public address.
     */
    public static function isAllowedHost(string $host): bool
    {
        $host = trim($host, '[]');

        return array_all(self::resolveHostToIps($host), fn (string $ip): bool => self::isPublicIp($ip));
    }

    /**
     * Fetch a URL through the egress guard with the connection pinned to a vetted
     * public IP, following redirects manually so every hop is re-validated and
     * re-pinned to its own connected address.
     *
     * The connection is pinned via curl's `CURLOPT_RESOLVE`, so the IP the guard
     * vetted is the IP curl connects to — closing the DNS-rebinding TOCTOU. The
     * original `Host` header / SNI / TLS verification are preserved (curl still
     * sees the hostname; only its address lookup is overridden).
     *
     * @param  string  $method  HTTP verb ('GET' or 'HEAD').
     * @param  string  $url  The editor-supplied URL.
     * @param  (Closure(PendingRequest): PendingRequest)|null  $configure  Hook to
     *                                                                     apply caller-specific timeouts before the request is sent.
     * @param  int  $maxRedirects  Maximum redirect hops to follow.
     * @param  list<string>  $privateNetworkHosts  Exact host names that may resolve to
     *                                             private-network space (UAMS-Web/uams-statamic-archive#1768). Empty for
     *                                             every caller but the link checker.
     *
     * @throws \RuntimeException When a host (initial or any redirect hop) does
     *                           not resolve to a vetted public address, the
     *                           scheme is not http(s), or the redirect limit is
     *                           exceeded.
     */
    public static function fetch(
        string $method,
        string $url,
        ?Closure $configure = null,
        int $maxRedirects = 5,
        array $privateNetworkHosts = []
    ): Response {
        // Follow up to $maxRedirects hops, re-validating and re-pinning each one.
        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            // Pin this hop's connection to its own vetted public IP. Throws when
            // the host does not resolve to a public address (closing F1) and
            // guarantees curl connects to the validated address (closing F2).
            $curlOptions = self::pinnedCurlOptions($url, $privateNetworkHosts);

            // Build the request: a fresh client, the curl pin, redirects handled
            // by us (not Guzzle) so each hop is independently vetted and pinned.
            $request = Http::withoutRedirecting()
                ->withOptions(['curl' => $curlOptions]);

            // Apply caller-specific configuration (timeouts) when provided.
            if ($configure instanceof Closure) {
                $request = $configure($request);
            }

            // Send the request for the verb the caller asked for.
            $response = $method === 'HEAD'
                ? $request->head($url)
                : $request->get($url);

            // A non-redirect response (or a redirect with no Location) is final.
            if (! self::isRedirect($response) || $response->header('Location') === '') {
                return $response;
            }

            // Resolve the redirect target against the current URL and loop to
            // re-validate + re-pin the next hop.
            $url = self::resolveRedirectLocation($url, $response->header('Location'));
        }

        // More than $maxRedirects hops: refuse rather than follow indefinitely.
        throw new \RuntimeException('Too many redirects following: '.$url);
    }

    /**
     * Whether a response is an HTTP redirect (3xx).
     */
    private static function isRedirect(Response $response): bool
    {
        $status = $response->status();

        return $status >= 300 && $status < 400;
    }

    /**
     * Resolve a (possibly relative) redirect Location against the current URL,
     * producing an absolute URL for the next hop.
     */
    private static function resolveRedirectLocation(string $currentUrl, string $location): string
    {
        // An absolute URL (has a scheme) is used as-is.
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $parts = parse_url($currentUrl);
        $scheme = $parts['scheme'] ?? 'http';
        // The current URL was vetted for a host before it was requested, so the
        // default is never read
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $base = $scheme.'://'.$host.$port;

        // A scheme-relative ("//host/path") or host-relative ("/path") Location.
        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $base.$location;
        }

        // A path-relative Location: resolve against the current path's directory.
        $path = $parts['path'] ?? '/';
        // A parsed path always starts with a slash, so strrpos() never returns false
        // @pest-mutate-ignore: RemoveIntegerCast
        $dir = substr($path, 0, (int) strrpos($path, '/') + 1);

        return $base.$dir.$location;
    }

    /**
     * Build the curl options pinning a URL's connection to its vetted IPs.
     *
     * This is the exact `curl` option array {@see fetch()} hands to the HTTP
     * client (`withOptions(['curl' => …])`): a single `CURLOPT_RESOLVE` entry
     * whose value is the vetted `host:port:addr` pin map. It is `public` so the
     * connection-pin wiring — the security-critical line that makes curl connect
     * to the validated address — is directly assertable in tests.
     *
     * @param  list<string>  $privateNetworkHosts  See {@see fetch()}.
     * @return array<int, mixed>
     *
     * @throws \RuntimeException When the host resolves to no vetted public IP.
     */
    public static function pinnedCurlOptions(string $url, array $privateNetworkHosts = []): array
    {
        return [\CURLOPT_RESOLVE => self::resolveMapFor($url, $privateNetworkHosts)];
    }

    /**
     * Build the `CURLOPT_RESOLVE` pin map for a URL's host, vetting every
     * resolved address. Returns a list of `host:port:addr` entries (IPv6 addrs
     * bracketed, per curl's syntax) — one per vetted public IP — so curl connects
     * to exactly the address the guard validated rather than re-resolving the name.
     *
     * This is the proof that the validated address is the connected address: the
     * map curl uses to bypass its own connect-time DNS is built solely from
     * addresses this guard classified as public, closing the DNS-rebinding TOCTOU
     * (F2) and the AAAA-only internal-host window (F1).
     *
     * An IP-literal host needs no pin (curl never resolves a literal — it connects
     * to exactly the vetted address), so an empty map is returned for it after the
     * literal is validated; an internal literal still throws below.
     *
     * A host named in `$privateNetworkHosts` may additionally resolve to
     * private-network space (RFC1918 / ULA) and is pinned to that address; every
     * other non-public class is refused for it exactly as for any other host.
     *
     * @param  list<string>  $privateNetworkHosts  See {@see fetch()}.
     * @return list<string>
     *
     * @throws \RuntimeException When the scheme is not http(s), the host is
     *                           missing, or the host resolves to no public IP
     *                           (or to any non-public IP).
     */
    public static function resolveMapFor(string $url, array $privateNetworkHosts = []): array
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        // A loose comparison of two non-numeric strings is a strict one in PHP 8
        // @pest-mutate-ignore: TrueToFalse
        if (! \in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('Refusing non-http(s) URL: '.$url);
        }

        $host = parse_url($url, PHP_URL_HOST);

        // parse_url() returns false rather than an empty host, so this is a backstop
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if (! \is_string($host) || $host === '') {
            throw new \RuntimeException('Refusing URL with no host: '.$url);
        }

        // Strip IPv6-literal brackets for resolution and classification.
        $bareHost = trim($host, '[]');

        // Resolve to every address family the client may use (A + AAAA). An IP
        // literal resolves to itself.
        $ips = self::resolveHostToIps($bareHost);

        // Determine the port curl will connect to (scheme default when implicit).
        $port = parse_url($url, PHP_URL_PORT);
        $port = \is_int($port) ? $port : ($scheme === 'https' ? 443 : 80);

        // A listed intranet host may also resolve to private-network space, on its
        // scheme's default port only. The list is matched against the exact host
        // name, and never an IP literal.
        $admitsPrivateNetwork = $port === ($scheme === 'https' ? 443 : 80)
            && self::isListedHost($bareHost, $privateNetworkHosts);

        // Keep only addresses proven safe to connect to; any other IP (or none at
        // all) makes the whole host unsafe to connect to.
        // The list is either every address or the host is refused below, so the
        // filter never leaves a gap in the keys
        // @pest-mutate-ignore: UnwrapArrayValues
        $publicIps = array_values(array_filter(
            $ips,
            static fn (string $ip): bool => self::isPublicIp($ip)
                || ($admitsPrivateNetwork && self::isPrivateNetworkIp($ip))
        ));

        // A genuinely unresolvable host (no A or AAAA records) has no address to
        // pin and nothing to pivot to — curl's own lookup simply fails to connect
        // — so allow it unpinned, matching isAllowedUrl()'s "unresolvable is
        // allowed" pre-flight and keeping reserved doc/test domains and
        // transiently-unresolvable feeds working.
        // Without this return, an empty list passes the count check and maps to the
        // same empty pin
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($ips === []) {
            return [];
        }

        // The host resolves, but at least one resolved address is non-public.
        // Refuse: this covers an internal-only host (including an AAAA-only
        // internal host, closing F1) and a rebinding resolver mixing a public and
        // an internal record (closing F2 — the internal record must not slip past).
        if (\count($publicIps) !== \count($ips)) {
            throw new \RuntimeException('Refusing host that resolves to a non-public address: '.$bareHost);
        }

        // An IP-literal host is already the exact connect target — curl performs
        // no name lookup for it — so no pin is needed (and curl's CURLOPT_RESOLVE
        // host field cannot represent a colon-bearing IPv6 literal cleanly).
        if (filter_var($bareHost, FILTER_VALIDATE_IP) !== false) {
            return [];
        }

        // Pin host:port → each vetted IP. curl still uses $host for Host/SNI/TLS;
        // only the address lookup is overridden, so it connects to the vetted IP.
        // IPv6 addresses are bracketed so curl's host:port:addr parse is unambiguous.
        return array_map(
            static function (string $ip) use ($bareHost, $port): string {
                $addr = str_contains($ip, ':') ? '['.$ip.']' : $ip;

                return sprintf('%s:%d:%s', $bareHost, $port, $addr);
            },
            $publicIps
        );
    }

    /**
     * Whether an IP literal is a public, routable address (not private and not
     * reserved — which together cover loopback, link-local, RFC1918, and the
     * IPv6 equivalents), with explicit hardening for IPv6 reserved ranges that
     * PHP's `FILTER_FLAG_NO_RES_RANGE` does not cover and for IPv6 forms that
     * embed an IPv4 address (the embedded v4 is extracted and re-classified, so
     * an internal v4 cannot hide inside a v4-mapped, NAT64, 6to4, or Teredo
     * address).
     */
    private static function isPublicIp(string $ip): bool
    {
        $packed = @inet_pton($ip);

        // Anything inet_pton() cannot read is not an address, and the filter below
        // would refuse it as well; refusing it here lets every check below read the
        // packed form without testing it again
        if ($packed === false) {
            return false;
        }

        // An IPv6 address that embeds an IPv4 address (v4-mapped ::ffff:0:0/96,
        // NAT64 64:ff9b::/96, 6to4 2002::/16, Teredo 2001:0::/32) must be judged
        // SOLELY by its EMBEDDED v4 — otherwise an internal v4 (127.0.0.1, 10.x,
        // the 169.254.169.254 metadata host) hides inside a syntactically-public
        // IPv6 literal, AND a legitimately-public embedded v4 (e.g. ::ffff:8.8.8.8)
        // is wrongly refused by FILTER_FLAG_NO_RES_RANGE (which treats every
        // v4-mapped address as reserved). The embedded v4(s) are authoritative:
        // public only when EVERY extracted endpoint is itself public.
        if (\strlen($packed) === 16) {
            $embedded = self::embeddedIpv4s($packed);

            if ($embedded !== []) {
                return array_all($embedded, fn (string $v4): bool => self::isPublicIp($v4));
            }
        }

        // PHP's NO_PRIV_RANGE / NO_RES_RANGE flags reject loopback, link-local,
        // RFC1918, IPv4-mapped private/reserved, ::1, fe80::/10, and fc00::/7.
        if (
            filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false
        ) {
            return false;
        }

        // FILTER_FLAG_NO_PRIV_RANGE / NO_RES_RANGE miss two IPv4 ranges that are
        // nonetheless internal/special and reachable: CGNAT (RFC 6598) and the
        // IETF benchmarking range (RFC 2544). Reject them explicitly.
        if (\strlen($packed) === 4) {
            $first = \ord($packed[0]);
            $second = \ord($packed[1]);

            // 100.64.0.0/10 — RFC 6598 shared CGNAT space.
            if ($first === 100 && ($second & 0xC0) === 0x40) {
                return false;
            }

            // 198.18.0.0/15 — RFC 2544 benchmarking.
            if ($first === 198 && ($second & 0xFE) === 18) {
                return false;
            }
        }

        // FILTER_FLAG_NO_RES_RANGE misses several non-global IPv6 ranges that are
        // still locally reachable; reject them explicitly. Normalize to the
        // packed binary form so shorthand/expanded notations compare equally.
        if (\strlen($packed) === 16) {
            $first = \ord($packed[0]);
            $second = \ord($packed[1]);

            // ff00::/8 — multicast.
            if ($first === 0xFF) {
                return false;
            }

            // fec0::/10 — deprecated site-local (still routes on internal nets).
            if ($first === 0xFE && ($second & 0xC0) === 0xC0) {
                return false;
            }

            // 64:ff9b:1::/48: the RFC 8215 local-use NAT64 prefix. A gateway that
            // translates it delivers to whatever IPv4 address the bits carry, and
            // no public feed or link needs it, so the whole prefix is refused
            // rather than decoded (UAMS-Web/uams-statamic-archive#3006).
            if ($packed[0] === "\x00" && $packed[1] === "\x64" && $packed[2] === "\xFF"
                && $packed[3] === "\x9B" && $packed[4] === "\x00" && $packed[5] === "\x01") {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a host is named, exactly, in a caller's private-network host list.
     *
     * Case-insensitive, and one trailing root dot is ignored on both sides, since
     * `inside.uams.edu.` and `inside.uams.edu` are the same DNS name. Nothing else
     * is normalized: no wildcard, suffix or subdomain match. A host that is an IP
     * literal never matches, including the obfuscated IPv4 forms curl would parse
     * as one (`0x0a.0.0.1`, `167772161`): the exception is for named intranet
     * hosts, and a literal would make it an exception for an address instead. A
     * literal entry in the list therefore matches nothing.
     *
     * Public so the link checker classifies a URL as an intranet one by the same rule the guard
     * applies to it, rather than by a second copy of that rule that could drift.
     *
     * @param  list<string>  $listedHosts
     */
    public static function isListedHost(string $host, array $listedHosts): bool
    {
        $host = self::withoutRootDot(strtolower(trim($host, '[]')));

        if (filter_var($host, FILTER_VALIDATE_IP) !== false || self::normalizeObfuscatedIpv4($host) !== null) {
            return false;
        }

        return array_any($listedHosts, fn (string $listed): bool => $host !== '' && self::withoutRootDot(strtolower(trim($listed))) === $host);
    }

    /**
     * Drop exactly one trailing root dot, so `inside.uams.edu.` names the same host
     * as `inside.uams.edu` and `inside.uams.edu..` does not.
     */
    private static function withoutRootDot(string $host): string
    {
        return str_ends_with($host, '.') ? substr($host, 0, -1) : $host;
    }

    /**
     * Whether an IP literal is in private-network space: RFC1918 IPv4
     * (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`) or IPv6 unique-local
     * (`fc00::/7`).
     *
     * Deliberately narrower than "not public". Loopback, link-local (the
     * `169.254.169.254` metadata host), CGNAT, reserved and multicast space are
     * NOT private-network, and neither is an IPv6 form embedding a v4 address, so
     * none of them is admitted for a listed host.
     */
    private static function isPrivateNetworkIp(string $ip): bool
    {
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        $first = \ord($packed[0]);
        $second = \ord($packed[1]);

        if (\strlen($packed) === 4) {
            return $first === 10
                || ($first === 172 && ($second & 0xF0) === 16)
                || ($first === 192 && $second === 168);
        }

        return ($first & 0xFE) === 0xFC;
    }

    /**
     * Extract every IPv4 address an IPv6 address embeds, as dotted-quad strings,
     * for re-classification by {@see isPublicIp()}.
     *
     * Recognized embeddings, each detected by its fixed prefix in the packed
     * 16-byte form:
     *   - IPv4-mapped `::ffff:0:0/96` — v4 in bytes 12-15.
     *   - NAT64 `64:ff9b::/96` (RFC 6052 well-known prefix) — v4 in bytes 12-15.
     *   - 6to4 `2002::/16` (RFC 3056) — v4 in bytes 2-5.
     *   - Teredo `2001:0000::/32` (RFC 4380) — the server v4 in bytes 4-7 and an
     *     OBFUSCATED client v4 in bytes 12-15 (each byte XOR `0xFF`); both are
     *     returned so an internal endpoint on either side is refused.
     *
     * @param  string  $packed  The 16-byte packed IPv6 address from `inet_pton`.
     * @return list<string> Zero or more dotted-quad IPv4 strings.
     */
    private static function embeddedIpv4s(string $packed): array
    {
        $byte = static fn (int $i): int => \ord($packed[$i]);
        $quad = static fn (int $o): string => sprintf(
            '%d.%d.%d.%d',
            \ord($packed[$o]),
            \ord($packed[$o + 1]),
            // Every IPv4 range this class or PHP's filter refuses is decided by the
            // first two octets, so which bytes fill the last two never matters
            // @pest-mutate-ignore: PlusToMinus, IncrementInteger, DecrementInteger
            \ord($packed[$o + 2]),
            // (an offset of $o + 4 reads past the end, so that mutant is left to be killed)
            // @pest-mutate-ignore: PlusToMinus, DecrementInteger
            \ord($packed[$o + 3])
        );

        // IPv4-mapped ::ffff:0:0/96 — bytes 0-9 zero, bytes 10-11 == 0xffff.
        $isV4Mapped = $byte(10) === 0xFF && $byte(11) === 0xFF;

        for ($i = 0; $i < 10 && $isV4Mapped; $i++) {
            if ($byte($i) !== 0x00) {
                $isV4Mapped = false;
            }
        }

        if ($isV4Mapped) {
            return [$quad(12)];
        }

        // NAT64 64:ff9b::/96 — bytes 0-3 == 00 64 ff 9b, bytes 4-11 zero.
        $isNat64 = $byte(0) === 0x00 && $byte(1) === 0x64
            && $byte(2) === 0xFF && $byte(3) === 0x9B;

        for ($i = 4; $i < 12 && $isNat64; $i++) {
            if ($byte($i) !== 0x00) {
                $isNat64 = false;
            }
        }

        if ($isNat64) {
            return [$quad(12)];
        }

        // 6to4 2002::/16 — v4 in bytes 2-5.
        if ($byte(0) === 0x20 && $byte(1) === 0x02) {
            return [$quad(2)];
        }

        // Teredo 2001:0000::/32 — server v4 in bytes 4-7; obfuscated client v4 in
        // bytes 12-15 (each byte XOR 0xFF). Return both endpoints.
        if ($byte(0) === 0x20 && $byte(1) === 0x01 && $byte(2) === 0x00 && $byte(3) === 0x00) {
            $client = implode('.', array_map(static fn (int $i): int => $byte($i) ^ 0xFF, range(12, 15)));

            return [$quad(4), $client];
        }

        return [];
    }

    /**
     * Resolve a host to its IP addresses across *both* address families the HTTP
     * client may use. An IP literal resolves to itself; a DNS name is resolved
     * via A (IPv4) and AAAA (IPv6) record lookups. Returns an empty array when
     * the host does not resolve.
     *
     * @return list<string>
     */
    private static function resolveHostToIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        // An obfuscated IPv4 literal — decimal (2130706433), hex (0x7f.0.0.1),
        // octal (0177.0.0.1), short (127.1), or 0 — is not recognized by
        // FILTER_VALIDATE_IP, so it would otherwise fall through to DNS and be
        // re-parsed by curl/inet_aton as the underlying IPv4 (e.g. 127.0.0.1),
        // bypassing the guard. Normalize it to canonical dotted-quad and resolve
        // to that single literal so the IPv4 classifier judges the real target.
        $normalized = self::normalizeObfuscatedIpv4($host);

        if ($normalized !== null) {
            return [$normalized];
        }

        // Use the injected resolver in tests; otherwise resolve A + AAAA for real.
        if (self::$resolver instanceof Closure) {
            return (self::$resolver)($host);
        }

        return self::resolveBothFamilies($host);
    }

    /**
     * Normalize an obfuscated IPv4 literal host to canonical dotted-quad, or
     * return null when the token is not confidently an IPv4 literal (so it is
     * left to DNS resolution and real hostnames are not broken).
     *
     * Mirrors the permissive `inet_aton` grammar that curl/glibc apply when
     * connecting — 1 to 4 dot-separated parts, each decimal, octal (`0` prefix),
     * or hex (`0x` prefix), with the final part absorbing the low-order bytes.
     * Any token whose every part is not one of those numeric forms is treated as
     * a DNS name (returns null), so a legitimate hostname is never misread.
     *
     * @return string|null Canonical dotted-quad (e.g. `127.0.0.1`), or null.
     */
    private static function normalizeObfuscatedIpv4(string $host): ?string
    {
        $parts = explode('.', $host);
        $count = \count($parts);

        if ($count > 4) {
            return null;
        }

        $values = [];

        foreach ($parts as $part) {
            // A part that is not hex (`0x`), octal (`0`) or decimal means this is a DNS
            // name, not an IPv4 literal; an empty host is one empty part, so it lands here
            if (preg_match('/^(0[xX][0-9a-fA-F]+|0[0-7]*|[1-9]\d*)$/', $part) !== 1) {
                return null;
            }

            // Base 0 reads the same three prefixes the pattern admits
            $values[] = \intval($part, 0);
        }

        // Each leading part must fit one byte; the final part absorbs the rest of
        // the 32-bit address (so `127.1` → 127.0.0.1, `2130706433` → 127.0.0.1).
        $maxFinal = [1 => 0xFFFFFFFF, 2 => 0xFFFFFF, 3 => 0xFFFF, 4 => 0xFF];

        for ($i = 0; $i < $count - 1; $i++) {
            if ($values[$i] > 0xFF) {
                return null;
            }
        }

        if ($values[$count - 1] > $maxFinal[$count]) {
            return null;
        }

        $address = match ($count) {
            1 => $values[0],
            2 => ($values[0] << 24) | $values[1],
            3 => ($values[0] << 24) | ($values[1] << 16) | $values[2],
            4 => ($values[0] << 24) | ($values[1] << 16) | ($values[2] << 8) | $values[3],
        };

        return long2ip($address & 0xFFFFFFFF);
    }

    /**
     * Resolve a DNS name's A (IPv4) and AAAA (IPv6) records.
     *
     * Primary source is `dns_get_record($host, DNS_A | DNS_AAAA)`. `gethostbynamel()`
     * supplements the A records (it can succeed where `dns_get_record` returns a
     * partial answer), so an IPv4 target is never missed.
     *
     * Residual (UAMS-Web/uams-statamic-archive#1013 F9): the AAAA half depends entirely on `dns_get_record`.
     * In a hardened container where `dns_get_record` is `disable_function`-ed (or
     * the underlying `res_*` calls fail), an AAAA-only public host loses its IPv6
     * pin — `gethostbynamel` answers A records only and cannot supply AAAA. Such a
     * host then resolves to `[]` here and is treated as "unresolvable → allowed
     * unpinned" by {@see resolveMapFor()}, which re-opens the (narrow) rebinding
     * window for that one host class. This is an accepted gap: the alternative —
     * refusing every host when `dns_get_record` is unavailable — would break all
     * AAAA-reachable feeds. Deployments that need the pin must keep
     * `dns_get_record` enabled.
     *
     * @return list<string>
     */
    private static function resolveBothFamilies(string $host): array
    {
        $ips = [];
        $types = DNS_A | DNS_AAAA;

        // A + AAAA via dns_get_record: ['ip'] for A records, ['ipv6'] for AAAA.
        $records = self::$recordLookup instanceof Closure
            ? (self::$recordLookup)($host, $types)
            : @dns_get_record($host, $types);

        if (\is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip']) && \is_string($record['ip'])) {
                    $ips[] = $record['ip'];
                }

                if (isset($record['ipv6']) && \is_string($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        // Supplement A records via gethostbynamel (it may answer when the above
        // does not), so an IPv4 address is never silently dropped.
        $ipv4 = self::$addressLookup instanceof Closure
            ? (self::$addressLookup)($host)
            : @gethostbynamel($host);

        if (\is_array($ipv4)) {
            foreach ($ipv4 as $ip) {
                $ips[] = $ip;
            }
        }

        // Every caller re-indexes the list or reads it as a set, so the gap in the
        // keys that array_unique() leaves is never seen
        // @pest-mutate-ignore: UnwrapArrayValues
        return array_values(array_unique($ips));
    }

    /**
     * Override the host resolver (test seam). Pass `null` to restore the real
     * A + AAAA lookup. The resolver receives a bare host and returns IP literals.
     *
     * @param  (Closure(string): list<string>)|null  $resolver
     */
    public static function setResolver(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Override the two DNS lookups behind the real resolver (test seam). Pass `null`
     * for either to restore the real function. They apply only while no resolver is
     * injected through {@see setResolver()}.
     *
     * @param  (Closure(string, int): (list<array<string, mixed>>|false))|null  $records  Stands in for `dns_get_record()`.
     * @param  (Closure(string): (list<string>|false))|null  $addresses  Stands in for `gethostbynamel()`.
     */
    public static function setDnsLookups(?Closure $records, ?Closure $addresses): void
    {
        self::$recordLookup = $records;
        self::$addressLookup = $addresses;
    }
}
