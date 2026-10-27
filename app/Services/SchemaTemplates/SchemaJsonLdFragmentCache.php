<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Listeners\InvalidateSchemaJsonLdCache;
use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Site;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Cross-request cache for page-invariant JSON-LD fragment nodes (issue UAMS-Web/uams-statamic-archive#937).
 *
 * Relationship-driven standalone builds whose targets come from a global set
 * with `merge_source_context="false"` (e.g. `founder`, `accountable_person`,
 * `media_contact`) are pure functions of the schema template, the target
 * entity, and the site content generation — yet they dominate cold compose
 * time and are rebuilt identically on every page. This cache stores the built
 * base node per (bust generation, site, template id, target) so only the first
 * compose after a content change pays the build cost.
 *
 * Keys embed the {@see SchemaJsonLdOutputCache} bust generation, so the same
 * events that invalidate cached page output (watched entry collections, the
 * multisite global set, `cache:clear`) invalidate fragments; the page-output
 * TTL bounds staleness for anything else.
 */
final readonly class SchemaJsonLdFragmentCache
{
    /**
     * Create the fragment cache with the generation-scoped bust helper.
     *
     * @param  SchemaJsonLdOutputCache  $jsonLdOutputCache  Source of the bust generation
     */
    public function __construct(
        private SchemaJsonLdOutputCache $jsonLdOutputCache
    ) {}

    /**
     * Whether fragment caching is enabled (the master switch gates it too).
     *
     * `schema_templates.json_ld_cache.enabled` is the **master switch** for every
     * JSON-LD cache layer, so setting it to false disables fragments whatever
     * `fragments_enabled` says. It is deliberately not a prerequisite for bust
     * rotation: {@see InvalidateSchemaJsonLdCache} bumps the
     * generation on every watched entry, global-set and term save without reading
     * this flag, so rotation stays meaningful either way (UAMS-Web/uams-statamic-archive#1842).
     *
     * To switch off page output alone — which is what a test building nodes for
     * in-memory fixtures wants — use `output_enabled`, not this flag. Doing it
     * with the master switch is what made 19 of the 20 `SchemaJsonLd*` suites
     * silently profile a fragments-off regime production does not run, and it is
     * the regime UAMS-Web/uams-statamic-archive#1797 / UAMS-Web/uams-statamic-archive#1800 / UAMS-Web/uams-statamic-archive#1801 all measured. On one location node (UAMS-Web/uams-statamic-archive#1801):
     * fragments off, a second page costs 1206 Antlers renders; on, 73 — and the
     * emitted JSON-LD is byte-identical either way.
     */
    public function enabled(): bool
    {
        // Honor the master switch for every JSON-LD cache layer
        if (! (bool) config('schema_templates.json_ld_cache.enabled', true)) {
            return false;
        }

        // Read the fragment-specific switch
        return (bool) config('schema_templates.json_ld_cache.fragments_enabled', true);
    }

    /**
     * Stable cache key for a (template id, target entity) fragment, or null when
     * the target kind has no stable identity.
     *
     * @param  string  $templateEntryId  schema_objects template entry id
     * @param  mixed  $targetEntity  Resolved relationship target
     * @return string|null Colon-delimited cache key, or null when not cacheable
     */
    public function keyFor(
        string $templateEntryId,
        mixed $targetEntity
    ): ?string {
        // Resolve a stable identity segment for the target entity
        $targetSegment = $this->targetIdentitySegment($targetEntity);

        // Reject targets without stable identity
        if ($targetSegment === null) {
            return null;
        }

        // Resolve the current site for its handle segment
        /** @var \Statamic\Sites\Site $currentSite */
        $currentSite = Site::current();
        $handle = $currentSite->handle();
        $currentSiteHandle = \is_scalar($handle) ? (string) $handle : '';

        // Assemble the generation-scoped fragment key
        return implode(
            ':',
            [
                'schema_templates',
                'json_ld_fragment',
                'v'.$this->jsonLdOutputCache->currentBust(),
                $currentSiteHandle,
                $templateEntryId,
                $targetSegment,
            ]
        );
    }

    /**
     * Read a cached fragment node, or null when absent.
     *
     * @param  string  $key  Key from {@see keyFor()}
     * @return array<string, mixed>|null Cached node array, or null
     */
    public function get(string $key): ?array
    {
        // Read the raw cached payload
        $raw = Cache::get($key);

        // Return only well-formed node arrays
        if (\is_array($raw) && isset($raw['@type'])) {
            /** @var array<string, mixed> $raw */
            return $raw;
        }

        return null;
    }

    /**
     * Store a built fragment node under the generation-scoped key.
     *
     * @param  string  $key  Key from {@see keyFor()}
     * @param  array<string, mixed>  $node  Built base node (must include @type)
     */
    public function put(
        string $key,
        array $node
    ): void {
        // Skip malformed nodes so get() never returns unusable payloads
        if (! isset($node['@type'])) {
            return;
        }

        // Reuse the page-output TTL so fragment staleness matches page staleness
        $ttlConfig = config('schema_templates.json_ld_cache.ttl_seconds', 3600);
        $ttlSeconds = max(
            60,
            \is_numeric($ttlConfig) ? (int) $ttlConfig : 3600
        );

        // Persist the node for reuse by later composes
        Cache::put(
            $key,
            $node,
            $ttlSeconds
        );
    }

    /**
     * Stable identity segment for supported target kinds, or null.
     *
     * @param  mixed  $targetEntity  Resolved relationship target
     */
    private function targetIdentitySegment(mixed $targetEntity): ?string
    {
        // Identify entries by id
        if ($targetEntity instanceof EntryModel || $targetEntity instanceof EntryContract) {
            // Every concrete entry implements id(); the contract does not declare it.
            /** @var EntryModel $targetEntity */
            $id = $targetEntity->id();

            return \is_string($id) && $id !== '' ? 'entry-'.$id : null;
        }

        // Identify localized terms by their underlying term id
        if ($targetEntity instanceof LocalizedTerm) {
            $term = $targetEntity->term();

            return $term instanceof TaxonomyTerm && \is_string($term->id())
                ? 'term-'.$term->id()
                : null;
        }

        // Identify taxonomy terms by id
        if ($targetEntity instanceof TaxonomyTerm || $targetEntity instanceof TermContract) {
            // Every concrete term implements id(); the contract does not declare it.
            /** @var TaxonomyTerm $targetEntity */
            $id = $targetEntity->id();

            return \is_string($id) && $id !== '' ? 'term-'.$id : null;
        }

        // Reject other target kinds (assets, raw URLs, globals)
        return null;
    }
}
