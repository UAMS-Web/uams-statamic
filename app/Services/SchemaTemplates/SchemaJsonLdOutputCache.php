<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Site;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Service for generation-scoped JSON-LD output cache keys and CP-facing metadata.
 *
 * Maintains a monotonic bust counter so
 * `SchemaOutputService::outputForCurrentContext` keys rotate without scanning the
 * cache store; also stores last bump and warm summaries for the control panel.
 */
final class SchemaJsonLdOutputCache
{
    /**
     * Read the current bust generation from cache (defaults to zero when unset).
     *
     * @return int Non-negative integer generation used in cache key material
     */
    public function currentBust(): int
    {
        // Resolve the cache key name for the bust counter
        $key = $this->stringConfig('schema_templates.json_ld_cache.bust_cache_key');

        // Read the stored bust value (null when unset)
        $stored = Cache::get($key);

        // Coerce it to a non-negative integer, zero when unset or not numeric
        return \is_numeric($stored) ? max(0, (int) $stored) : 0;
    }

    /**
     * Increment the bust counter and record last-bump metadata for the CP utility.
     */
    public function bump(): void
    {
        // Resolve cache keys for the bust counter and last bump metadata
        $key = $this->stringConfig('schema_templates.json_ld_cache.bust_cache_key');
        $metaKey = $this->stringConfig('schema_templates.json_ld_cache.last_bump_meta_key');

        // Compute the next bust generation from the current value
        $next = $this->currentBust() + 1;

        // Persist the incremented bust counter
        Cache::forever(
            $key,
            $next
        );

        // Record when the bust happened for the control panel utility
        Cache::forever(
            $metaKey,
            [
                'bust' => $next,
                'at' => now()->toIso8601String(),
            ]
        );

        unset(
            $key,
            $metaKey,
            $next
        );
    }

    /**
     * Read last bump metadata for the control panel, or null when never bumped or
     * invalid.
     *
     * @return array{bust: int, at: string}|null Associative row with bust and ISO-8601 `at`, or null when cache is empty or
     *                                           malformed
     */
    public function lastBumpMeta(): ?array
    {
        // Resolve the cache key for last bump metadata
        $metaKey = $this->stringConfig('schema_templates.json_ld_cache.last_bump_meta_key');

        // Read the raw metadata payload from cache
        $raw = Cache::get($metaKey);

        // Check if the cached value is a usable array container. isset() below
        // already reads null for a non-array, so RemoveEarlyReturn changes no
        // result; PHPStan needs the guard to allow the offset reads.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! \is_array($raw)) {
            return null;
        }

        // Check if required bust and timestamp keys are present
        if (! isset($raw['bust'], $raw['at'])) {
            return null;
        }

        // Read the raw bust and timestamp values for normalization
        $bust = $raw['bust'];
        $at = $raw['at'];

        // Return a normalized associative row for callers
        return [
            'bust' => \is_scalar($bust) ? (int) $bust : 0,
            'at' => \is_scalar($at) ? (string) $at : '',
        ];
    }

    /**
     * Persist the most recent warm-run summary blob for display in the CP utility.
     *
     * @param  array<string, mixed>  $summary  Aggregated warm statistics and context fields
     */
    public function storeLastWarmSummary(
        array $summary
    ): void {
        // Store the warm summary under the configured metadata key
        Cache::forever(
            $this->stringConfig('schema_templates.json_ld_cache.last_warm_meta_key'),
            $summary
        );
    }

    /**
     * Read the last persisted warm-run summary for the control panel, or null when
     * absent.
     *
     * @return array<string, mixed>|null Decoded warm summary array, or null when unset
     */
    public function lastWarmSummary(): ?array
    {
        // Read the raw warm summary payload from cache
        $raw = Cache::get($this->stringConfig('schema_templates.json_ld_cache.last_warm_meta_key'));

        // Bail out when the payload is not an array container
        if (! \is_array($raw)) {
            return null;
        }

        // Return the cached warm summary as a string-keyed associative row
        /** @var array<string, mixed> $raw */
        return $raw;
    }

    /**
     * Build a deterministic cache key for the JSON-LD script tag payload in the
     * current context, or null when this context must not be cached at all.
     *
     * **Why an id-less entity must not be cached rather than key-able (UAMS-Web/uams-statamic-archive#1909).**
     * This used to fall back to `spl_object_hash($contextEntity)` when the
     * entity carried no persisted id. That is a recycled memory address, not an
     * identity: PHP hands a freed object's hash straight to the next object
     * allocated, verified directly —
     *
     *     $a = new A; $h1 = spl_object_hash($a); unset($a);
     *     $b = new A; $h2 = spl_object_hash($b);   // $h1 === $h2
     *
     * — so two unrelated id-less entities in one process could compute the
     * SAME key, and the second was served the first's JSON-LD. Production never
     * reaches it (routed entries always carry an id), but any process
     * composing output for in-memory entities does, which is why four
     * `Feature/Views` and `Feature/Http` tests failed only under `--parallel`:
     * distribution changes which fixtures share a process, and so which hashes
     * get recycled. A key that can collide is worse than no key, so there is
     * none. This matches the sibling fragment cache, whose
     * {@see SchemaJsonLdFragmentCache::keyFor()} already returns null without a
     * real string id.
     *
     * @param  EntryContract|Term  $contextEntity  Route-bound entry or term providing locale and id
     * @param  array{subject?: mixed, website?: mixed, webpage?: mixed, main_content?: mixed}  $tagParams  Raw tag overrides from the template
     * @param  string|null  $subjectEntryId  Normalized subject slot entry id
     * @param  string|null  $websiteEntryId  Normalized website slot entry id
     * @param  string|null  $webpageEntryId  Normalized webpage slot entry id
     * @param  string|null  $mainContentEntryId  Normalized main content slot entry id
     * @return string|null Colon-delimited cache key, or null when the context entity has no persisted id
     */
    public function cacheKey(
        EntryContract|Term $contextEntity,
        array $tagParams,
        ?string $subjectEntryId,
        ?string $websiteEntryId,
        ?string $webpageEntryId,
        ?string $mainContentEntryId
    ): ?string {
        // Resolve a stable identifier for the context entity. Without one there
        // is no key that can safely distinguish this entity from another, so
        // refuse to build one at all — see the note above.
        $contextId = $this->resolveContextEntityId($contextEntity);

        if ($contextId === null) {
            return null;
        }

        // Read the current bust generation for key segregation
        $bust = $this->currentBust();

        // Resolve the active site handle for the request context
        /** @var \Statamic\Sites\Site $currentSite */
        $currentSite = Site::current();
        $handle = $currentSite->handle();
        // implode() stringifies a scalar the same way, so the cast changes no key.
        // @pest-mutate-ignore: RemoveStringCast
        $siteHandle = is_scalar($handle) ? (string) $handle : '';

        // Resolve the locale segment from the route-bound entity
        $locale = $this->resolveLocaleForContextEntity($contextEntity);

        // Fingerprint normalized tag overrides so distinct CP values do not collide
        $overrideFingerprint = $this->tagOverrideFingerprint($tagParams);

        // Fingerprint normalized slot entry ids as a single digest
        $slotsFingerprint = hash(
            'xxh128',
            json_encode(
                [
                    $subjectEntryId,
                    $websiteEntryId,
                    $webpageEntryId,
                    $mainContentEntryId,
                ],
                JSON_THROW_ON_ERROR
            )
        );

        // Assemble the colon-delimited cache key segments
        return implode(
            ':',
            [
                'schema_templates',
                'json_ld',
                'v'.$bust,
                $siteHandle,
                $locale,
                $contextId,
                $slotsFingerprint,
                $overrideFingerprint,
            ]
        );
    }

    /**
     * Hash normalized tag override parameters so distinct CP overrides do not collide.
     *
     * @param  array{subject?: mixed, website?: mixed, webpage?: mixed, main_content?: mixed}  $tagParams  Raw tag overrides from the template
     * @return string xxh128 digest of normalized slot override strings
     */
    private function tagOverrideFingerprint(
        array $tagParams
    ): string {
        // Build a stable associative map of stringified override slot values
        $normalized = [
            'subject' => $this->stringifyTagParam($tagParams['subject'] ?? null),
            'website' => $this->stringifyTagParam($tagParams['website'] ?? null),
            'webpage' => $this->stringifyTagParam($tagParams['webpage'] ?? null),
            'main_content' => $this->stringifyTagParam($tagParams['main_content'] ?? null),
        ];

        // Hash the normalized map as JSON for a stable fingerprint
        return hash(
            'xxh128',
            json_encode(
                $normalized,
                JSON_THROW_ON_ERROR
            )
        );
    }

    /**
     * Normalize a single tag parameter to a stable string for fingerprinting.
     *
     * @param  mixed  $value  Raw Antlers tag argument (scalar, array, or null)
     * @return string Stable string token; empty when absent or not representable
     */
    private function stringifyTagParam(
        mixed $value
    ): string {
        // Coerce array tag arguments using the first non-empty scalar element
        if (\is_array($value)) {
            // Read the first element for a simple list-like tag argument
            $first = reset($value);

            // Return the first element as a string when it is scalar
            return \is_scalar($first) ? (string) $first : '';
        }

        // Coerce scalar values to string for fingerprinting
        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * Resolve the locale string for cache key segregation from the context entity.
     *
     * @param  EntryContract|Term  $contextEntity  Route-bound entry or term
     * @return string Locale or site language string used in cache keys
     */
    private function resolveLocaleForContextEntity(
        EntryContract|Term $contextEntity
    ): string {
        // Use the entry locale when the context is a Statamic entry model
        if ($contextEntity instanceof EntryModel) {
            // Read the entry locale value for stringification
            $entryLocale = $contextEntity->locale();

            return \is_scalar($entryLocale) ? (string) $entryLocale : '';
        }

        // Use the localized term locale when the context is a localized term
        if ($contextEntity instanceof LocalizedTerm) {
            // Read the localized term locale value for stringification
            $termLocale = $contextEntity->locale();

            return \is_scalar($termLocale) ? (string) $termLocale : '';
        }

        // Fall back to the current site language for other context types
        /** @var \Statamic\Sites\Site $currentSite */
        $currentSite = Site::current();

        // Read the site language value for stringification
        $siteLang = $currentSite->lang();

        return \is_scalar($siteLang) ? (string) $siteLang : '';
    }

    /**
     * Resolve a stable identifier for the context entity for cache key segregation.
     *
     * Returns null when the entity carries no persisted id, which makes the
     * whole entry must not be cached — see {@see cacheKey()} for why that is the only
     * safe answer.
     *
     * @param  EntryContract|Term  $contextEntity  Route-bound entry or term
     * @return string|null Entry or term id, or null when the entity has no stable identity
     */
    private function resolveContextEntityId(
        EntryContract|Term $contextEntity
    ): ?string {
        // Attempt to read a string id from the context entity when available
        if (method_exists($contextEntity, 'id')) {
            try {
                // Read the identifier value from the entity
                $id = $contextEntity->id();

                // Check if the identifier is a non-empty string
                if (\is_string($id) && $id !== '') {
                    return $id;
                }
            } catch (\Throwable) {
                // Partial test doubles may omit id(); treat as no stable identity
            }
        }

        // No persisted id: the caller must not cache this entity's output.
        return null;
    }

    /**
     * Read a configuration value and coerce it to a string for cache key material.
     *
     * @param  string  $key  Dot-notated configuration key
     * @return string Stringified configuration value, or empty string when not scalar
     */
    private function stringConfig(
        string $key
    ): string {
        // Read the raw configuration value
        $value = config($key);

        // Coerce scalar configuration values to string, otherwise use an empty token
        return \is_scalar($value) ? (string) $value : '';
    }
}
