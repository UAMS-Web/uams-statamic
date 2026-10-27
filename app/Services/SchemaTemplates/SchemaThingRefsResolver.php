<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Term;
use Statamic\Fields\Value;
use Statamic\Globals\Variables;
use Statamic\Query\OrderedQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Builds JSON-LD @id reference objects for schema_objects entries resolved from
 * taxonomy terms or from a direct entries field, using the same canonical @id
 * rules as {@see SchemaOutputService::getCanonicalAtIdForSchemaEntry()}.
 */
class SchemaThingRefsResolver
{
    /**
     * Create a resolver that uses the schema output service for canonical @id
     * strings on schema_objects entries.
     *
     * @param  SchemaOutputService  $schemaOutputService  Service that computes canonical JSON-LD @id values for graph nodes
     * @param  SchemaJsonLdSchemaObjectEmissionCoordinator  $schemaObjectEmissionCoordinator  Shared @id reference builder for schema_objects
     */
    public function __construct(
        protected SchemaOutputService $schemaOutputService,
        protected SchemaJsonLdAtIdDeduplication $jsonLdAtIdDeduplication,
        protected SchemaJsonLdSchemaObjectEmissionCoordinator $schemaObjectEmissionCoordinator
    ) {}

    /**
     * Resolve schema_objects entry refs from an entry field that stores taxonomy
     * terms; each term's entries field (e.g., schema_object) supplies ids.
     *
     * @param  array<string, mixed>  $context  Antlers context (e.g., entry data->all())
     * @param  array<string, mixed>  $params  field, taxonomy, term_entries_field, term_limit, entry_limit
     * @return string JSON array of objects with @id keys for canonical URLs
     */
    public function resolveFromTaxonomyTerms(
        array $context,
        array $params
    ): string {
        // Build an ordered list of term field handles (supports `a|b|c` fallbacks)
        $handles = SchemaTagFieldHandles::listFromParam($params['field'] ?? null);

        // Check if no usable field handles were provided
        if ($handles === []) {
            // Return an empty JSON array when the field handle is unusable
            return $this->jsonEncodeRefs([]);
        }

        // Initialize the taxonomy handle from params with a project default
        $taxonomy = $params['taxonomy'] ?? 'geographic_locations';

        // Normalize the taxonomy handle to a non-empty string
        $taxonomy = \is_string($taxonomy) && $taxonomy !== ''
            ? $taxonomy
            : 'geographic_locations';

        // Initialize the term entries field handle with a default
        $termEntriesField = $params['term_entries_field'] ?? 'schema_object';

        // Normalize the term entries field handle to a non-empty string
        $termEntriesField = \is_string($termEntriesField) && $termEntriesField !== ''
            ? $termEntriesField
            : 'schema_object';

        // Parse the optional maximum number of terms to process
        $termLimit = $this->parseOptionalPositiveInt($params['term_limit'] ?? null);

        // Parse the optional maximum schema_objects ids to take per term
        $entryLimit = $this->parseOptionalPositiveInt($params['entry_limit'] ?? null);

        // Initialize the term list from the first field that has any terms
        $termItems = [];

        // Try each handle until one yields at least one term item
        foreach ($handles as $fieldHandle) {
            // Collect normalized term items from Antlers context for this handle
            $termItems = $this->collectTermItemsFromContext(
                $context,
                $fieldHandle,
                $termLimit
            );

            // ---
            // Stop at the first handle that resolves to any terms
            // ---

            // Check if the term items are not empty
            if ($termItems !== []) {
                // Break out of the loop if we have term items
                break;
            }
        }

        // Unset local variables to free memory
        unset(
            $fieldHandle
        );

        // Bail before walking terms when no term items were resolved
        if ($termItems === []) {
            unset(
                $entryLimit,
                $handles,
                $taxonomy,
                $termEntriesField,
                $termLimit
            );

            return $this->jsonEncodeRefs([]);
        }

        // Collect schema_objects entry ids in term order (ids per term, then next term)
        $allEntryIds = [];

        // Walk each term item and merge linked schema_objects entry ids
        foreach ($termItems as $item) {
            // Resolve the list item to a taxonomy Term instance when possible
            $term = $this->normalizeToTerm($item, $taxonomy);

            // Skip items that do not resolve to a term
            if (! $term instanceof TaxonomyTerm) {
                continue;
            }

            // Read schema_objects entry ids from the term's configured field
            $ids = $this->collectSchemaObjectEntryIdsFromTerm(
                $term,
                $termEntriesField,
                $entryLimit
            );

            // Append each id to the flat list for one batched entry load
            foreach ($ids as $entryId) {
                $allEntryIds[] = $entryId;
            }

            unset(
                $ids,
                $term
            );
        }

        unset($termItems);

        // Encode refs using a single repository query for all collected ids
        $out = $this->encodeRefsForSchemaObjectEntryIds($allEntryIds);

        unset(
            $allEntryIds,
            $entryLimit,
            $handles,
            $taxonomy,
            $termEntriesField,
            $termLimit
        );

        return $out;
    }

    /**
     * Resolve schema_objects entry refs through linked entries: the context field
     * stores entry ids, and each linked entry's entries field (e.g. `schema_object`)
     * supplies the schema_objects ids (UAMS-Web/uams-statamic-archive#2597).
     *
     * The entries counterpart of {@see self::resolveFromTaxonomyTerms()}, for a
     * vocabulary kept as a collection, such as `geographic_locations`.
     *
     * @param  array<string, mixed>  $context  Antlers context (e.g., entry data->all())
     * @param  array<string, mixed>  $params  field, linked_entries_field, linked_limit, entry_limit
     * @return string JSON array of objects with @id keys for canonical URLs
     */
    public function resolveFromLinkedEntries(
        array $context,
        array $params
    ): string {
        // Build an ordered list of field handles (supports `a|b|c` fallbacks)
        $handles = SchemaTagFieldHandles::listFromParam($params['field'] ?? null);

        // Return an empty JSON array when the field handle is unusable
        if ($handles === []) {
            return $this->jsonEncodeRefs([]);
        }

        // Read the entries field on each linked entry, defaulting to schema_object
        $linkedField = $params['linked_entries_field'] ?? 'schema_object';
        $linkedField = \is_string($linkedField) && $linkedField !== ''
            ? $linkedField
            : 'schema_object';

        // Parse the optional caps on linked entries and on ids per linked entry
        $linkedLimit = $this->parseOptionalPositiveInt($params['linked_limit'] ?? null);
        $entryLimit = $this->parseOptionalPositiveInt($params['entry_limit'] ?? null);

        // Take the linked entry ids from the first handle that has any
        $linkedIds = [];

        foreach ($handles as $field) {
            // Read the raw field value and normalize it to entry ids
            $linkedIds = $this->collectEntryIdsFromRaw(
                $this->getFieldValueFromAntlersContext($context, $field)
            );

            // Stop at the first handle that resolves to any ids
            if ($linkedIds !== []) {
                break;
            }
        }

        // Cap the number of linked entries read
        if ($linkedLimit !== null) {
            $linkedIds = \array_slice($linkedIds, 0, $linkedLimit);
        }

        // Collect schema_objects ids in linked-entry order
        $allEntryIds = [];

        foreach ($linkedIds as $linkedId) {
            // Load the linked entry, skipping an id that resolves to nothing
            $linkedEntry = EntryFacade::find($linkedId);

            if (! $linkedEntry instanceof EntryModel) {
                continue;
            }

            // Read the linked entry's schema_objects ids
            $ids = $this->collectEntryIdsFromRaw($linkedEntry->value($linkedField));

            // Cap the ids taken from each linked entry
            if ($entryLimit !== null) {
                $ids = \array_slice($ids, 0, $entryLimit);
            }

            // Append them to the flat list for one batched entry load
            foreach ($ids as $entryId) {
                $allEntryIds[] = $entryId;
            }
        }

        // Encode refs using a single repository query for all collected ids
        return $this->encodeRefsForSchemaObjectEntryIds($allEntryIds);
    }

    /**
     * Resolve schema_objects entry refs from an entries field on a global set
     * (e.g. default country of origin in site settings).
     *
     * @param  array<string, mixed>  $params  Keys: `global` (set handle), `field`,
     *                                        `site` (optional), `entry_limit` (optional)
     * @return string JSON array of objects with @id keys
     */
    public function resolveFromGlobalEntriesField(
        array $params
    ): string {
        // Initialize the global set handle from params
        $globalHandle = $params['global'] ?? null;

        // Check if the global handle is missing or not a non-empty string
        if (
            ! \is_string($globalHandle)
            || $globalHandle === ''
        ) {
            return $this->jsonEncodeRefs([]);
        }

        // Build an ordered list of entries field handles (supports `a|b|c` fallbacks)
        $handles = SchemaTagFieldHandles::listFromParam($params['field'] ?? null);

        // Check if no usable field handles were provided
        if ($handles === []) {
            // Return an empty JSON array when the field handle is unusable
            return $this->jsonEncodeRefs([]);
        }

        // Load the global set by handle
        $set = GlobalSet::findByHandle($globalHandle);

        // Check if the global set does not exist
        if ($set === null) {
            return $this->jsonEncodeRefs([]);
        }

        // Read optional site handle for localized global variables
        $siteRaw = $params['site'] ?? null;

        // Normalize site to a non-empty string or null for current site
        $site = \is_string($siteRaw) && $siteRaw !== ''
            ? $siteRaw
            : null;

        // Resolve variables for the requested site or the current site
        /** @var Variables $variables */
        $variables = $site !== null
            ? $set->in($site)
            : $set->inCurrentSite();

        // Parse the optional cap on how many entry ids to include
        $entryLimit = $this->parseOptionalPositiveInt($params['entry_limit'] ?? null);

        // Initialize the id list from the first global field that has any entries
        $ids = [];

        // Try each handle until one yields at least one schema_objects entry id
        foreach ($handles as $field) {
            // Read the raw field value from the global variables
            $raw = $variables->get($field);

            // ---
            // Unwrap Statamic Value objects to their underlying stored value
            // ---

            // Check if the raw value is a Value instance
            if ($raw instanceof Value) {
                // Unwrap the Value instance to the underlying stored value
                $raw = $raw->value();
            }

            // Normalize the raw value to a flat list of entry id strings
            $ids = $this->collectEntryIdsFromRaw($raw);

            // Stop at the first handle that resolves to any ids
            if ($ids !== []) {
                break;
            }
        }

        // Unset local variables to free memory
        unset(
            $field,
            $raw
        );

        // ---
        // Return an empty JSON array when no handle produced entry ids
        // ---

        // Check if the id list is empty
        if ($ids === []) {
            // Unset local variables to free memory
            unset(
                $entryLimit,
                $globalHandle,
                $handles,
                $ids,
                $site,
                $siteRaw,
                $set,
                $variables
            );

            // Return an empty JSON array when no handle produced entry ids
            return $this->jsonEncodeRefs([]);
        }

        // Truncate the id list when an entry limit is configured
        if ($entryLimit !== null) {
            $ids = \array_slice($ids, 0, $entryLimit);
        }

        // Encode deduplicated JSON-LD @id refs for the schema_objects ids
        $out = $this->encodeRefsForSchemaObjectEntryIds($ids);

        unset(
            $entryLimit,
            $globalHandle,
            $handles,
            $ids,
            $site,
            $siteRaw,
            $set,
            $variables
        );

        return $out;
    }

    /**
     * Resolve schema_objects entry refs from an entry field that stores entry ids
     * directly (entries fieldtype targeting schema_objects).
     *
     * @param  array<string, mixed>  $context  Antlers context
     * @param  array<string, mixed>  $params  field, entry_limit
     * @return string JSON array of objects with @id keys
     */
    public function resolveFromEntryEntriesField(
        array $context,
        array $params
    ): string {
        // Build an ordered list of entries field handles (supports `a|b|c` fallbacks)
        $handles = SchemaTagFieldHandles::listFromParam($params['field'] ?? null);

        // Check if no usable field handles were provided
        if ($handles === []) {
            // Return an empty JSON array when the field handle is unusable
            return $this->jsonEncodeRefs([]);
        }

        // Parse the optional cap on how many entry ids to include
        $entryLimit = $this->parseOptionalPositiveInt($params['entry_limit'] ?? null);

        // Initialize the id list from the first context field that has any entries
        $ids = [];

        // Try each handle until one yields at least one schema_objects entry id
        foreach ($handles as $field) {
            // Read the raw field value from Antlers context by handle
            $raw = $this->getFieldValueFromAntlersContext(
                $context,
                $field
            );

            // ---
            // Unwrap Statamic Value objects to their underlying stored value
            // ---

            // Check if the raw value is a Value instance
            if ($raw instanceof Value) {
                // Unwrap the Value instance to the underlying stored value
                $raw = $raw->value();
            }

            // Normalize the raw value to a flat list of entry id strings
            $ids = $this->collectEntryIdsFromRaw($raw);

            // ---
            // Stop at the first handle that resolves to any ids
            // ---

            // Check if the id list is not empty
            if ($ids !== []) {
                // Break out of the loop if we have entry ids
                break;
            }
        }

        // Unset local variables to free memory
        unset(
            $field,
            $raw
        );

        // ---
        // Return an empty JSON array when no handle produced entry ids
        // ---

        // Check if the id list is empty
        if ($ids === []) {
            // Unset local variables to free memory
            unset(
                $entryLimit,
                $handles,
                $ids,
            );

            // Return an empty JSON array when no handle produced entry ids
            return $this->jsonEncodeRefs([]);
        }

        // Check if an entry limit is configured
        if ($entryLimit !== null) {
            // Truncate the id list to the entry limit
            $ids = \array_slice($ids, 0, $entryLimit);
        }

        // Encode deduplicated JSON-LD @id refs for the schema_objects ids
        $out = $this->encodeRefsForSchemaObjectEntryIds($ids);

        // Unset local variables to free memory
        unset(
            $entryLimit,
            $handles,
            $ids
        );

        // Return the encoded JSON-LD @id refs
        return $out;
    }

    /**
     * Build a JSON array string of @id reference objects from schema_objects entry
     * ids, deduplicated by canonical @id.
     *
     * Loads entries with one `whereInId` query instead of per-id `find` calls.
     *
     * @param  array<int, string>  $ids  Entry id strings (may include duplicates or empties)
     * @return string JSON list of reference objects
     */
    private function encodeRefsForSchemaObjectEntryIds(
        array $ids
    ): string {
        // Initialize the list of unique @id reference objects
        $refs = [];

        // Track canonical @id strings already added to avoid duplicates
        $seen = [];

        // Collect non-empty id strings in encounter order
        $orderedIds = [];

        foreach ($ids as $entryId) {
            // Keep only non-empty string ids
            if ($entryId !== '') {
                $orderedIds[] = $entryId;
            }
        }

        // Return early when there is nothing to resolve
        if ($orderedIds === []) {
            unset(
                $refs,
                $seen
            );

            return $this->jsonEncodeRefs([]);
        }

        // Deduplicate ids for a single repository round-trip (order not required for the query)
        $uniqueIds = array_values(array_unique($orderedIds));

        // Load all requested entries in one query (preserves order when mapping back)
        $entries = EntryFacade::whereInId($uniqueIds);

        // Build a map of schema_objects entries keyed by id for O(1) lookups
        $schemaObjectEntriesById = [];

        foreach ($entries as $entry) {
            // Skip unexpected entry types
            if (! $entry instanceof EntryModel) {
                unset($entry);

                continue;
            }

            // Skip entries outside the schema_objects collection
            if ($entry->collectionHandle() !== 'schema_objects') {
                unset($entry);

                continue;
            }

            // Index the entry by its Statamic id string
            $entryId = $entry->id();
            $schemaObjectEntriesById[is_scalar($entryId) ? (string) $entryId : ''] = $entry;

            unset($entry);
        }

        unset(
            $entries,
            $uniqueIds
        );

        // Walk ids in original encounter order so output order matches the previous per-find behavior
        foreach ($orderedIds as $entryId) {
            // Resolve the loaded entry when this id is a schema_objects row
            $entry = $schemaObjectEntriesById[$entryId] ?? null;

            // Skip ids that did not load or are not schema_objects
            if (! $entry instanceof Entry) {
                continue;
            }

            // Build the JSON-LD @id reference for this entry
            $ref = $this->schemaObjectEmissionCoordinator->atIdReferenceObjectForSchemaObjectEntry(
                $entry,
                $this->schemaOutputService
            );

            // Skip when the coordinator cannot produce a usable @id
            if ($ref === null) {
                unset($entry);

                continue;
            }

            // Append the ref when its canonical @id is new
            $this->jsonLdAtIdDeduplication->appendUniqueAtIdObject(
                $refs,
                $seen,
                $ref
            );

            unset(
                $entry,
                $ref
            );
        }

        unset(
            $entryId,
            $orderedIds,
            $schemaObjectEntriesById
        );

        // Serialize refs for Antlers output
        $json = $this->jsonEncodeRefs($refs);

        // Unset local variables to free memory
        unset(
            $refs,
            $seen
        );

        // Return the encoded JSON-LD @id refs
        return $json;
    }

    /**
     * JSON-encode a list of @id reference objects for Antlers schema output.
     *
     * @param  array<int, array<string, string>>  $refs  List of maps with @id keys
     * @return string Encoded array, or `[]` when encoding fails
     */
    private function jsonEncodeRefs(
        array $refs
    ): string {
        // Serialize refs with unescaped slashes and Unicode for readable JSON-LD
        $json = json_encode(
            $refs,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // Fall back to an empty JSON array if encoding fails
        return $json !== false ? $json : '[]';
    }

    /**
     * Read a dotted field path from Antlers context for schema refs.
     *
     * Laravel {@see data_get()} fails once a path segment resolves to a
     * {@see EntryContract}: blueprint field handles are not reliably visible
     * through {@see Arr::exists()} / array access (same
     * limitation noted on
     * {@see SchemaThingFromRelationshipResolver::readFirstNonEmptyFieldValueFromContext()}).
     *
     * This walker unwraps {@see Value}, traverses plain arrays, steps into
     * {@see Collection} keys when present, and reads nested segments from
     * entries via {@see EntryContract::value()}.
     *
     * @param  array<string, mixed>  $context  Antlers context snapshot
     */
    private function getFieldValueFromAntlersContext(
        array $context,
        string $fieldHandle
    ): mixed {
        if ($fieldHandle === '') {
            return null;
        }

        $segments = explode('.', $fieldHandle);
        $cursor = $context;

        foreach ($segments as $segment) {
            if ($cursor instanceof Value) {
                $cursor = $cursor->value();
            }

            if (\is_array($cursor)) {
                if (! \array_key_exists($segment, $cursor)) {
                    return null;
                }

                $cursor = $cursor[$segment];

                continue;
            }

            if (
                $cursor instanceof EntryContract
                && \is_callable([$cursor, 'value'])
            ) {
                $cursor = $cursor->value($segment);

                continue;
            }

            if ($cursor instanceof Collection) {
                if (! $cursor->has($segment)) {
                    return null;
                }

                $cursor = $cursor->get($segment);

                continue;
            }

            return null;
        }

        return $cursor;
    }

    /**
     * Read a terms field from context and return a sequential list of raw term
     * items (slugs, augmented arrays, LocalizedTerm, etc.) for further resolution.
     *
     * @param  array<string, mixed>  $context  Antlers context (entry data)
     * @param  string  $fieldHandle  Entry field handle for the terms field
     * @param  ?int  $termLimit  Maximum items to return, or null for all
     * @return array<int, mixed> Normalized list of term representations
     */
    private function collectTermItemsFromContext(
        array $context,
        string $fieldHandle,
        ?int $termLimit
    ): array {
        // Read the field value from context using dot notation when needed
        $geographicLocation = $this->getFieldValueFromAntlersContext(
            $context,
            $fieldHandle
        );

        // Check if the geographic location is a Value instance
        if ($geographicLocation instanceof Value) {
            // Unwrap the Value instance to the underlying stored value
            $geographicLocation = $geographicLocation->value();
        }

        // Check if the geographic location is null (the field is absent)
        if ($geographicLocation === null) {
            // Return an empty array of items
            return [];
        }

        // Check if the geographic location is an OrderedQueryBuilder instance
        if ($geographicLocation instanceof OrderedQueryBuilder) {
            // Materialize the ordered terms query into a plain PHP array of items
            /** @var Collection<int, mixed> $orderedTerms */
            $orderedTerms = $geographicLocation->get();
            $geographicLocation = $orderedTerms->all();
        }

        // Check if the geographic location is a Collection instance
        if ($geographicLocation instanceof Collection) {
            // Convert the Collection to a zero-indexed array of items
            $geographicLocation = $geographicLocation->values()->all();
        }

        // Check if the geographic location is not an array (single scalar value or object item)
        if (! \is_array($geographicLocation)) {
            // ---
            // Wrap the scalar value or object item in an array so downstream code always sees
            // a list
            // ---
            $geographicLocation = [$geographicLocation];
        }

        // Check if a term limit is configured
        if ($termLimit !== null) {
            // Truncate the list to the term limit to apply the optional term cap
            $geographicLocation = \array_slice($geographicLocation, 0, $termLimit);
        }

        // Reindex the list so iteration order is explicit
        return array_values($geographicLocation);
    }

    /**
     * Normalize a single terms field item to a Statamic Term, or null when the
     * shape is not supported or Term::find returns nothing.
     *
     * @param  mixed  $item  Slug string, augmented array, LocalizedTerm, or Term
     * @param  string  $taxonomy  Taxonomy handle used for `taxonomy::slug` lookups
     * @return TaxonomyTerm|null Concrete term instance, or null when unresolved
     */
    private function normalizeToTerm(
        mixed $item,
        string $taxonomy
    ): ?TaxonomyTerm {
        // Check if the item is a Value instance
        if ($item instanceof Value) {
            // Unwrap the Value instance to the underlying stored value
            $item = $item->value();
        }

        // Check if the item is a LocalizedTerm instance
        if ($item instanceof LocalizedTerm) {
            // Resolve the term from the localized term
            $term = $item->term();

            // Check if the term is a TaxonomyTerm instance
            return $term instanceof TaxonomyTerm ? $term : null;
        }

        // Check if the item is a TaxonomyTerm instance
        if ($item instanceof TaxonomyTerm) {
            // Return the term to accept an already-materialized taxonomy Term instance
            return $item;
        }

        // Check if the item is augmented data with a usable slug key
        if (
            \is_array($item)
            && \is_string($item['slug'] ?? null)
            && $item['slug'] !== ''
        ) {
            // Resolve the term from taxonomy handle and slug string
            return $this->findTaxonomyTermBySlug(
                $taxonomy,
                $item['slug']
            );
        }

        // Check if the item is a plain slug string for this taxonomy
        if (
            \is_string($item)
            && $item !== ''
        ) {
            // Resolve the term from taxonomy handle and slug string
            return $this->findTaxonomyTermBySlug(
                $taxonomy,
                $item
            );
        }

        // Return null to signal that no supported representation matched
        return null;
    }

    /**
     * Load a term by `taxonomy::slug` and return the concrete Term model.
     *
     * `Term::find` may return a LocalizedTerm; callers need the underlying
     * TaxonomyTerm for `inDefaultLocale()` and blueprint field access.
     *
     * @param  string  $taxonomy  Taxonomy handle
     * @param  string  $slug  Term slug without the taxonomy prefix
     * @return TaxonomyTerm|null Concrete term, or null when not found or not a Term
     */
    private function findTaxonomyTermBySlug(
        string $taxonomy,
        string $slug
    ): ?TaxonomyTerm {
        // Look up the term id in Statamic using the taxonomy handle and slug
        $found = Term::find(sprintf('%s::%s', $taxonomy, $slug));

        // Check if Statamic has no term for that id
        if ($found === null) {
            // Return null when no term is found
            return null;
        }

        // Check if the found term is a localized facade
        if ($found instanceof LocalizedTerm) {
            // Unwrap the localized facade to the shared taxonomy Term instance
            $term = $found->term();

            // Unset local variable to free memory
            unset(
                $found
            );

            // Return the concrete Term model or null if not a TaxonomyTerm
            return $term instanceof TaxonomyTerm ? $term : null;
        }

        // Check if it is already a concrete Term model
        if ($found instanceof TaxonomyTerm) {
            // Return the concrete Term model
            return $found;
        }

        // Unset local variable to free memory
        unset(
            $found
        );

        // Reject unexpected types from Term::find
        return null;
    }

    /**
     * Read an entries field on a taxonomy term and return schema_objects entry ids
     * as strings, optionally capped per term.
     *
     * @param  TaxonomyTerm  $term  Term whose fields are read in the default locale
     * @param  string  $schemaFieldHandle  Entries field handle on the term blueprint
     * @param  ?int  $entryLimit  Max ids to return for this term, or null for all
     * @return array<int, string> Entry id strings from the field
     */
    private function collectSchemaObjectEntryIdsFromTerm(
        TaxonomyTerm $term,
        string $schemaFieldHandle,
        ?int $entryLimit
    ): array {
        // Read the raw entries field value, tolerating blueprint access failures
        try {
            /** @var LocalizedTerm $localizedTerm */
            $localizedTerm = $term->inDefaultLocale();
            $raw = $localizedTerm->value($schemaFieldHandle);
        } catch (\Throwable $throwable) {
            // Unset local variable to free memory
            unset(
                $throwable
            );

            // Return an empty array to signal that the field is absent
            return [];
        }

        // Flatten the raw value to entry id strings
        $ids = $this->collectEntryIdsFromRaw($raw);

        // Check if an entry limit is configured
        if ($entryLimit !== null) {
            // Truncate the list to the entry limit to apply the optional entry cap
            $ids = \array_slice($ids, 0, $entryLimit);
        }

        // Unset local variable to free memory
        unset(
            $raw
        );

        // Return the collected entry ids
        return $ids;
    }

    /**
     * Normalize Statamic entries field raw values to a flat list of entry id
     * strings (single id, array of ids, Entry models, or entry queries).
     *
     * @param  mixed  $raw  Stored or augmented entries field value
     * @return array<int, string> Non-empty entry id strings only
     */
    private function collectEntryIdsFromRaw(
        mixed $raw
    ): array {
        // Check if the raw value is a Value instance
        if ($raw instanceof Value) {
            // Unwrap the Value instance to the underlying stored value
            $raw = $raw->value();
        }

        // Check if the raw value is null or an empty string
        if (
            $raw === null
            || $raw === ''
        ) {
            // Unset local variable to free memory
            unset(
                $raw
            );

            // Return an empty array when the raw value is null or an empty string
            return [];
        }

        // Check if the raw value is an Entry model
        if ($raw instanceof EntryModel) {
            // Read the entry id from the Entry model
            $id = $raw->id();

            // Return a single entry id when the raw value is an Entry model
            return \is_string($id) && $id !== '' ? [$id] : [];
        }

        // ---
        // Materialize an entries query builder to a plain PHP array of entries
        // ---

        // Check if the raw value is an OrderedQueryBuilder instance
        if ($raw instanceof OrderedQueryBuilder) {
            // Materialize the entries query builder to a plain PHP array of entries
            /** @var Collection<int, mixed> $entries */
            $entries = $raw->get();

            // Initialize the id list
            $ids = [];

            // Loop through each entry in the entries query builder
            foreach ($entries as $entry) {
                // Check if the entry is an Entry model
                if ($entry instanceof EntryModel) {
                    // Read the entry id from the Entry model
                    $eid = $entry->id();

                    // Check if the entry id is a string and not empty
                    if (
                        \is_string($eid)
                        && $eid !== ''
                    ) {
                        // Append the entry id to the id list
                        $ids[] = $eid;
                    }
                }
            }

            // Unset local variable to free memory
            unset(
                $entries,
                $entry,
                $eid,
                $raw
            );

            // Return the collected entry ids
            return $ids;
        }

        // Check if the raw value is a string
        if (\is_string($raw)) {
            // Return a single entry id when the raw value is a non-empty string
            return [$raw];
        }

        // Check if the raw value is not an array
        if (! \is_array($raw)) {
            // Return an empty array to reject non-array values
            return [];
        }

        // Initialize the id list
        $ids = [];

        // Loop through each item in the raw value
        foreach ($raw as $v) {
            // Check if the item is an Entry model
            if ($v instanceof EntryModel) {
                // Read the entry id from the Entry model
                $eid = $v->id();

                // Check if the entry id is a string and not empty
                if (
                    \is_string($eid)
                    && $eid !== ''
                ) {
                    // Append the entry id to the id list
                    $ids[] = $eid;
                }

                // Unset local variable to free memory
                unset(
                    $eid
                );
            } elseif (
                \is_string($v)
                && $v !== ''
            ) { // Check if the item is a string and not empty
                // Append the entry id to the id list
                $ids[] = $v;
            }
        }

        // Unset local variable to free memory
        unset(
            $v
        );

        // Return the collected entry ids
        return $ids;
    }

    /**
     * Parse a positive integer limit from tag parameters, or null when unlimited
     * or invalid.
     *
     * @param  mixed  $value  Raw tag parameter (int, numeric string, or empty)
     * @return int|null Positive integer, or null when absent or not valid
     */
    private function parseOptionalPositiveInt(
        mixed $value
    ): ?int {
        // Check if the value is null or an empty string
        if (
            $value === null
            || $value === ''
        ) {
            // Return null to signal that the value is null or an empty string
            return null;
        }

        // Check if the value is an integer
        if (\is_int($value)) {
            // Return the value if it is a strictly positive integer
            return $value > 0 ? $value : null;
        }

        // Check if the value is a string and a digit-only string
        if (
            \is_string($value)
            && ctype_digit($value)
        ) {
            // Convert the value to an integer
            $i = (int) $value;

            // Return the integer if it is a strictly positive integer
            return $i > 0 ? $i : null;
        }

        // Return null to reject other scalar types and non-digit strings
        return null;
    }
}
