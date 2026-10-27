<?php

declare(strict_types=1);

namespace App\Services\OnetWebServices;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches O*NET OnLine occupation overview (title and description) for a
 * single O*NET-SOC code via O*NET Web Services v2.
 *
 * @see https://services.onetcenter.org/reference/online/occupation#overview
 */
class OnetOccupationOverviewService
{
    /**
     * Prefix for Laravel cache keys storing successful overview payloads.
     */
    private const string CACHE_KEY_PREFIX = 'onet_ws:occupation_overview:v1:';

    /**
     * Matches the O*NET-SOC format validated by App\Rules\OnetSocOccupationCode.
     */
    private const string ONET_SOC_PATTERN = '/^\d{2}-\d{4}\.\d{2}$/';

    /**
     * Returns overview data for JSON-LD, or null when the key is missing,
     * the code is invalid, or the request/response is unusable.
     *
     * @param  string  $socCode  The SOC code to fetch the overview for
     * @return array{title: string, description?: string}|null The overview data for the SOC code, or null if the code is invalid or the request/response is unusable
     */
    public function overview(
        string $socCode
    ): ?array {
        // ---
        // Normalize and reject invalid SOC shapes before any network use.
        // ---

        // Trim the SOC code to remove any leading or trailing whitespace
        $code = trim($socCode);

        // Check if the SOC code is empty or invalid
        if (
            $code === ''
            || ! $this->isValidSocCode($code)
        ) {
            // Return null to signal that the SOC code is invalid
            return null;
        }

        // ---
        // Require API credentials for outbound calls.
        // ---

        // Get the API key from the configuration
        $apiKey = config('services.onet_web_services.api_key');

        // Check if the API key is empty or invalid
        if (
            ! \is_string($apiKey)
            || $apiKey === ''
        ) {
            // Return null to signal that the API key is invalid
            return null;
        }

        // ---
        // Respect configured TTL when caching successful responses per code.
        // ---

        // Get the TTL from the configuration
        /** @var scalar $ttlConfig */
        $ttlConfig = config(
            'services.onet_web_services.cache_ttl',
            86400
        );
        $ttl = max(
            0,
            (int) $ttlConfig
        );

        // Create a cache key for the SOC code
        $cacheKey = self::CACHE_KEY_PREFIX.$code;

        // ---
        // Return a warm cache entry when present.
        // ---

        // Check if the cache entry exists
        if (Cache::has($cacheKey)) {
            // Get the cached entry
            /** @var array{title: string, description?: string}|null $cached */
            $cached = Cache::get($cacheKey);

            // Return the cached entry or null if it's not an array
            return \is_array($cached) ? $cached : null;
        }

        // ---
        // Fetch, optionally store, and return fresh overview data.
        // ---

        // Fetch the overview data from the API
        $fetched = $this->fetchOverviewUncached(
            $code,
            $apiKey
        );

        // Check if the overview data is valid
        if (
            $fetched !== null
            && $ttl > 0
        ) {
            // Cache the overview data
            Cache::put(
                $cacheKey,
                $fetched,
                $ttl
            );
        }

        // Return the overview data or null if it's not valid
        return $fetched;
    }

    /**
     * Performs delay, shared rate limit, HTTP GET, and response shaping.
     *
     * @param  string  $code  The SOC code to fetch the overview for
     * @param  string  $apiKey  The API key to use for the request
     * @return array{title: string, description?: string}|null The overview data for the SOC code, or null if the code is invalid or the request/response is unusable
     */
    private function fetchOverviewUncached(
        string $code,
        string $apiKey
    ): ?array {
        // ---
        // Optional spacing between requests (single-process throttle).
        // ---

        // Get the delay from the configuration
        /** @var scalar $delayMsConfig */
        $delayMsConfig = config(
            'services.onet_web_services.delay_ms',
            0
        );
        $delayMs = max(
            0,
            (int) $delayMsConfig
        );

        // Check if the delay is greater than 0
        if ($delayMs > 0) {
            // Sleep for the delay
            usleep($delayMs * 1000);
        }

        // ---
        // Shared cap across workers (Redis-backed store when configured).
        // ---

        // Acquire an outbound slot
        $this->acquireOutboundSlot($apiKey);

        // Get the base URL from the configuration
        /** @var scalar $baseConfig */
        $baseConfig = config(
            'services.onet_web_services.base_url',
            'https://api-v2.onetcenter.org'
        );
        $base = rtrim(
            (string) $baseConfig,
            '/'
        );

        // Create the URL for the API request
        $url = sprintf('%s/online/occupations/%s/', $base, $code);

        // Attempt to fetch the overview data from the API
        try {
            // Fetch the overview data from the API
            $response = Http::acceptJson()
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                ])
                ->timeout(30)
                ->get(
                    $url
                );
        } catch (Throwable) {
            // Return null to signal that the API request failed
            return null;
        }

        // Check if the API request was successful
        if (! $response->successful()) {
            // Return null to signal that the API request was not successful
            return null;
        }

        // Get the data from the API response
        $data = $response->json();

        // Check if the data is an array
        if (! \is_array($data)) {
            // Return null to signal that the data is not an array
            return null;
        }

        // Get the title from the data
        $title = $data['title'] ?? null;

        // Check if the title is a string
        if (! \is_string($title)) {
            // Return null to signal that the title is not a string
            return null;
        }

        // Trim the title
        $title = trim($title);

        // Check if the title is empty
        if ($title === '') {
            // Return null to signal that the title is empty
            return null;
        }

        // Create the output array
        $out = ['title' => $title];

        // Get the description from the data
        $description = $data['description'] ?? null;

        // Check if the description is a string
        if (\is_string($description)) {
            // Trim the description
            $description = trim($description);

            // Check if the description is not empty
            if ($description !== '') {
                // Add the description to the output array
                $out['description'] = $description;
            }
        }

        // Return the output array
        return $out;
    }

    /**
     * Block until an outbound O*NET HTTP call fits the shared per-second
     * budget (O*NET ToS). Uses Illuminate cache store
     * `services.onet_web_services.rate_limit_store` (env
     * ONET_WEB_SERVICES_RATE_LIMIT_STORE, default redis); empty store disables
     * limiting.
     *
     * @param  string  $apiKey  The API key to use for the request
     */
    private function acquireOutboundSlot(
        string $apiKey
    ): void {
        // Get the store name from the configuration
        $storeName = config(
            'services.onet_web_services.rate_limit_store'
        );

        // Check if the store name is empty or invalid
        if (
            ! \is_string($storeName)
            || $storeName === ''
        ) {
            // Return to signal that the store name is empty or invalid
            return;
        }

        // Get the maximum attempts from the configuration
        /** @var scalar $maxAttemptsConfig */
        $maxAttemptsConfig = config(
            'services.onet_web_services.max_requests_per_second',
            5
        );
        $maxAttempts = max(
            1,
            (int) $maxAttemptsConfig
        );

        // ---
        // RateLimiter shares counters via the chosen cache driver (e.g. Redis).
        // ---

        // Create a new rate limiter
        $limiter = new RateLimiter(
            Cache::store($storeName)
        );

        // Create a hash of the API key
        $keyHash = hash(
            'sha256',
            $apiKey
        );

        // Create a cache key for the API key
        $key = 'onet_ws:outbound:v1:'.$keyHash;

        // Set the decay seconds
        $decaySeconds = 1;

        // Wait until the rate limiter has fewer than the maximum attempts
        while (
            $limiter->tooManyAttempts(
                $key,
                $maxAttempts
            )
        ) {
            // Get the available in seconds
            $waitSeconds = $limiter->availableIn($key);

            // Check if the wait seconds is greater than 0
            if ($waitSeconds > 0) {
                // Sleep for the wait seconds
                sleep($waitSeconds);
            } else {
                // Sleep for 50 milliseconds
                usleep(50_000);
            }
        }

        // Reserve one outbound slot before Http::get()
        $limiter->hit(
            $key,
            $decaySeconds
        );
    }

    /**
     * Whether the string matches the canonical O*NET-SOC code pattern.
     *
     * @param  string  $code  The SOC code to check
     * @return bool True if the code matches the canonical O*NET-SOC code pattern, false otherwise
     */
    private function isValidSocCode(
        string $code
    ): bool {
        // Return true if the code matches the canonical O*NET-SOC code pattern
        return preg_match(
            self::ONET_SOC_PATTERN,
            $code
        ) === 1;
    }
}
