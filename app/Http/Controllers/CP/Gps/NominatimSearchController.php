<?php

declare(strict_types=1);

namespace App\Http\Controllers\CP\Gps;

use App\Support\Http\OutboundUrlGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Server-side proxy for the GPS / map fieldtype's OpenStreetMap (Nominatim)
 * address search.
 *
 * The picker's address-search box geocodes a typed street address to a marker
 * by calling this Control-Panel endpoint rather than hitting Nominatim from the
 * editor's browser. Proxying server-side is what lets the request honor the
 * Nominatim Usage Policy and the app's egress hardening:
 *
 *   - a descriptive, identifying `User-Agent` (a browser cannot set one, and an
 *     anonymous request is policy-non-compliant);
 *   - one centrally-enforced, app-wide rate limit targeting the policy's
 *     one-request-per-second ceiling (a best-effort throttle across editors and
 *     tabs — not a per-tab limit); and
 *   - the outbound fetch routed through {@see OutboundUrlGuard} (SSRF defense,
 *     UAMS-Web/uams-statamic-archive#931/UAMS-Web/uams-statamic-archive#1013), so the configurable endpoint cannot be pointed at internal
 *     infrastructure.
 *
 * Authorization is inherited from {@see CpController}: only an authenticated
 * Control-Panel user can reach the route.
 *
 * @see config/maps.php (the `nominatim` section)
 */
class NominatimSearchController extends CpController
{
    /**
     * The app-wide rate-limiter key throttling outbound Nominatim requests toward
     * the policy's one-request-per-second ceiling across every editor and tab
     * (not scoped per user). The check-then-hit is best-effort: a rare concurrent
     * burst may momentarily admit a second request before the first increments —
     * acceptable for a courtesy throttle to a public service.
     */
    private const string RATE_LIMITER_KEY = 'maps:nominatim:outbound';

    /**
     * Geocode an address query through Nominatim and return normalized results.
     *
     * Returns `{ results: [{ lat, lng, label }] }`. An empty query (below the
     * configured minimum length) yields an empty result set without an outbound
     * request; an exhausted rate limit yields `429`; an egress-guard rejection,
     * a transport error, or an unsuccessful upstream response yields `502` with
     * an empty result set so the picker degrades gracefully.
     *
     * @return JsonResponse The normalized geocoding results
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Validate the query is a present, bounded string before any work.
        $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        // Read it back through the typed string accessor (the validation above
        // guarantees a string) so the value is narrowed, not cast from mixed.
        $query = $request->string('q')->trim()->toString();

        // Skip short queries: they geocode poorly and waste a rate-limited slot.
        if (mb_strlen($query) < $this->minQueryLength()) {
            return response()->json(['results' => []]);
        }

        // Throttle outbound requests toward the policy's one-per-second ceiling
        // app-wide. A blocked request returns 429 with Retry-After.
        if (RateLimiter::tooManyAttempts(self::RATE_LIMITER_KEY, $this->maxRequestsPerSecond())) {
            return response()->json(
                ['results' => [], 'message' => __('statamic::fieldtypes.gps.field.address_search_error')],
                JsonResponse::HTTP_TOO_MANY_REQUESTS,
            )->header('Retry-After', (string) RateLimiter::availableIn(self::RATE_LIMITER_KEY));
        }

        RateLimiter::hit(self::RATE_LIMITER_KEY, 1);

        $url = $this->buildSearchUrl($query);

        // Refuse before connecting if the configured endpoint resolves to an
        // internal/loopback/private target (SSRF defense, UAMS-Web/uams-statamic-archive#931/UAMS-Web/uams-statamic-archive#1013).
        if (! OutboundUrlGuard::isAllowedUrl($url)) {
            return response()->json(['results' => []], JsonResponse::HTTP_BAD_GATEWAY);
        }

        // Fetch through the egress guard with an identifying User-Agent/Referer
        // and a bounded timeout, per the Nominatim Usage Policy.
        try {
            $response = OutboundUrlGuard::fetch(
                'GET',
                $url,
                fn (PendingRequest $pendingRequest): PendingRequest => $pendingRequest
                    ->timeout($this->timeout())
                    ->connectTimeout(3)
                    ->withHeaders([
                        'User-Agent' => $this->userAgent(),
                        'Referer' => $this->stringConfig('app.url'),
                        'Accept' => 'application/json',
                    ]),
            );
        } catch (\Throwable $throwable) {
            unset($throwable);

            return response()->json(['results' => []], JsonResponse::HTTP_BAD_GATEWAY);
        }

        if (! $response->successful()) {
            return response()->json(['results' => []], JsonResponse::HTTP_BAD_GATEWAY);
        }

        return response()->json(['results' => $this->normalizeResults($response->json())]);
    }

    /**
     * Build the Nominatim search URL with policy-compliant query parameters.
     *
     * @param  string  $query  The editor-supplied address query
     * @return string The fully-qualified Nominatim search URL
     */
    private function buildSearchUrl(string $query): string
    {
        // Drop empty optional parameters so they are not sent as blanks.
        $params = array_filter(
            [
                'q' => $query,
                'format' => 'jsonv2',
                'addressdetails' => '1',
                'limit' => (string) $this->limit(),
                'countrycodes' => $this->stringConfig('maps.nominatim.country_codes'),
                'email' => $this->stringConfig('maps.nominatim.email'),
            ],
            static fn (string $value): bool => $value !== '',
        );

        return $this->endpoint().'?'.http_build_query($params);
    }

    /**
     * Normalize Nominatim's response into the picker's `{ lat, lng, label }` shape.
     *
     * Rows without a numeric latitude/longitude pair are dropped so the picker
     * never receives an unplaceable result.
     *
     * @param  mixed  $json  The decoded Nominatim response body
     * @return array<int, array{lat: float, lng: float, label: string}> The normalized results
     */
    private function normalizeResults($json): array
    {
        if (! \is_array($json)) {
            return [];
        }

        $results = [];

        foreach ($json as $row) {
            // Skip non-object rows and rows missing a usable coordinate pair.
            if (! \is_array($row)) {
                continue;
            }

            if (! is_numeric($row['lat'] ?? null)) {
                continue;
            }

            if (! is_numeric($row['lon'] ?? null)) {
                continue;
            }

            $label = $row['display_name'] ?? ($row['name'] ?? '');

            $results[] = [
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lon'],
                'label' => \is_string($label) ? $label : '',
            ];
        }

        unset($row, $label);

        return $results;
    }

    /**
     * The descriptive User-Agent identifying this application to Nominatim.
     *
     * Falls back to the app name and URL when no explicit value is configured,
     * so the request is never sent without an identifying agent.
     */
    private function userAgent(): string
    {
        $configured = $this->stringConfig('maps.nominatim.user_agent');

        if ($configured !== '') {
            return $configured;
        }

        $name = $this->stringConfig('app.name');

        return sprintf('%s (%s)', $name !== '' ? $name : 'Statamic', $this->stringConfig('app.url'));
    }

    /**
     * The configured Nominatim search endpoint.
     */
    private function endpoint(): string
    {
        $endpoint = $this->stringConfig('maps.nominatim.endpoint');

        return $endpoint !== '' ? $endpoint : 'https://nominatim.openstreetmap.org/search';
    }

    /**
     * The minimum query length before a search is issued (at least 1).
     */
    private function minQueryLength(): int
    {
        return max(1, $this->intConfig('maps.nominatim.min_query_length', 3));
    }

    /**
     * The maximum number of results requested (at least 1).
     */
    private function limit(): int
    {
        return max(1, $this->intConfig('maps.nominatim.limit', 5));
    }

    /**
     * The policy-capped maximum outbound requests per second (at least 1).
     */
    private function maxRequestsPerSecond(): int
    {
        return max(1, $this->intConfig('maps.nominatim.max_requests_per_second', 1));
    }

    /**
     * The outbound request timeout in seconds (at least 1).
     */
    private function timeout(): int
    {
        return max(1, $this->intConfig('maps.nominatim.timeout', 5));
    }

    /**
     * Read a config value as an int, falling back to the default for non-numerics.
     *
     * @param  string  $key  The dotted config key
     * @param  int  $default  The value used when the config entry is non-numeric
     * @return int The narrowed integer value
     */
    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Read a config value as a trimmed string, treating non-scalars as empty.
     *
     * @param  string  $key  The dotted config key
     * @return string The trimmed string value, or an empty string
     */
    private function stringConfig(string $key): string
    {
        $value = config($key);

        return \is_scalar($value) ? trim((string) $value) : '';
    }
}
