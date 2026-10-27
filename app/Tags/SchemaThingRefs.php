<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaTagFieldHandles;
use App\Services\SchemaTemplates\SchemaThingRefsResolver;
use Statamic\Fields\Value;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * JSON-LD @id references for schema_objects entries, for Antlers schema property
 * templates (same canonical @id rules as Thing/Place sets).
 *
 * Terms mode (default): read taxonomy term ids from an entry field, then read
 * schema_objects entry ids from each term's entries field.
 *
 * Entries mode: read schema_objects entry ids directly from an entries field.
 *
 * Linked-entries mode: read entry ids from an entry field, then read
 * schema_objects entry ids from each linked entry's entries field (UAMS-Web/uams-statamic-archive#2597), as
 * terms mode does for taxonomy terms.
 *
 * Global mode: read schema_objects entry ids from an entries field on a named
 * global set (optional `site` for localized globals).
 *
 * Usage (terms):
 *   {{ schema_thing_refs field="geographic_location" }}
 *   {{ schema_thing_refs field="geographic_location_content" taxonomy="geographic_locations" term_entries_field="schema_object" }}
 *   {{ schema_thing_refs field="geographic_location" term_limit="1" entry_limit="1" }}
 *
 * Usage (linked entries, e.g. geographic_locations entries):
 *   {{ schema_thing_refs mode="linked_entries" field="geographic_location" linked_entries_field="schema_object" }}
 *
 * Usage (direct entries field on the entry):
 *   {{ schema_thing_refs mode="entries" field="related_places" }}
 *
 * Usage (entries field on a global set):
 *   {{ schema_thing_refs mode="global" global="uams_multisite_settings" field="country_of_origin" }}
 *   {{ schema_thing_refs mode="global" global="uams_multisite_settings" field="country_of_origin" site="health" }}
 *
 * Parameters:
 *   - mode: `terms` (default), `entries`, `linked_entries`, or `global`
 *   - global: global set handle (global mode only)
 *   - site: site handle for the global variables (global mode; default current site)
 *   - field: field handle (terms field, entries field, or global entries field per mode);
 *     use `|`-separated handles for fallbacks (first field with content wins); dotted paths
 *     traverse arrays as usual and resolve Statamic {@see Entry}
 *     segments via {@see Entry::value()} (not plain `data_get`)
 *   - taxonomy: taxonomy handle for term ids (terms mode; default geographic_locations)
 *   - term_entries_field: entries field on each term (terms mode; default schema_object)
 *   - term_limit: max terms to read from the field (optional)
 *   - linked_entries_field: entries field on each linked entry (linked_entries
 *     mode; default schema_object)
 *   - linked_limit: max linked entries to read from the field (optional)
 *   - entry_limit: max schema_objects ids per term or linked entry, or overall
 *     (entries / global modes)
 *
 * Output: JSON array string, e.g. [{"@id":"https://example.test/#uuid"}]
 *
 * Canonical `{"@id":…}` objects use `SchemaJsonLdSchemaObjectEmissionCoordinator`
 * and `SchemaOutputService::getCanonicalAtIdForSchemaEntry()`.
 */
class SchemaThingRefs extends Tags
{
    /**
     * Registered Antlers tag handle so templates use `{{ schema_thing_refs }}`
     *
     * @var string
     */
    protected static $handle = 'schema_thing_refs';

    /**
     * Build a JSON array string of `{"@id":…}` objects from the current tag
     * context and parameters.
     *
     * @return string JSON list of reference objects, or `[]` when nothing resolves
     */
    public function index(): string
    {
        // Narrow the untyped Tags parameters bag to its concrete type
        /** @var Parameters $tagParams */
        $tagParams = $this->params;

        // Read the mode parameter with `terms` as the default
        $modeRaw = $this->unwrapAntlersParam(
            $tagParams->get('mode', 'terms')
        );

        // Normalize mode to a lowercase string for comparisons
        $mode = \is_string($modeRaw) ? strtolower(trim($modeRaw)) : 'terms';

        // Map tag parameters into a plain array for the resolver
        $params = [
            'field' => $this->unwrapAntlersParam($tagParams->get('field')),
            'taxonomy' => $this->unwrapAntlersParam(
                $tagParams->get('taxonomy', 'geographic_locations')
            ),
            'term_entries_field' => $this->unwrapAntlersParam(
                $tagParams->get('term_entries_field', 'schema_object')
            ),
            'term_limit' => $this->unwrapAntlersParam($tagParams->get('term_limit')),
            'linked_entries_field' => $this->unwrapAntlersParam(
                $tagParams->get('linked_entries_field', 'schema_object')
            ),
            'linked_limit' => $this->unwrapAntlersParam($tagParams->get('linked_limit')),
            'entry_limit' => $this->unwrapAntlersParam($tagParams->get('entry_limit')),
            'global' => $this->unwrapAntlersParam($tagParams->get('global')),
            'site' => $this->unwrapAntlersParam($tagParams->get('site')),
        ];

        // Bail before resolving the service when no field handles can match
        if (SchemaTagFieldHandles::listFromParam($params['field']) === []) {
            unset(
                $mode,
                $modeRaw,
                $params
            );

            return '[]';
        }

        // Bail early in global mode when the global set handle is missing
        if ($mode === 'global') {
            // Read the normalized global set handle from params
            $globalHandle = $params['global'];

            // Check if the global handle is missing or not a non-empty string
            if (
                ! \is_string($globalHandle)
                || $globalHandle === ''
            ) {
                unset(
                    $globalHandle,
                    $mode,
                    $modeRaw,
                    $params
                );

                return '[]';
            }

            unset($globalHandle);
        }

        // Resolve the shared service that builds canonical @id references
        $resolver = app(SchemaThingRefsResolver::class);

        // Global mode reads globals only; avoid materializing full Antlers context
        if ($mode === 'global') {
            // Resolve refs from an entries field on the named global set
            $out = $resolver->resolveFromGlobalEntriesField($params);

            // Unset local variables to free memory
            unset(
                $mode,
                $modeRaw,
                $params,
                $resolver
            );

            // Return the JSON array string from entries mode
            return $out;
        }

        // Narrow the untyped Tags context bag to its concrete type
        /** @var Context $tagContext */
        $tagContext = $this->context;

        // Snapshot Antlers context for entries and terms modes
        /** @var array<string, mixed> $context */
        $context = $tagContext->all();

        // Branch to direct entries resolution when mode is `entries`
        if ($mode === 'entries') {
            // Resolve refs from an entries field on the context entry
            $out = $resolver->resolveFromEntryEntriesField(
                $context,
                $params
            );

            // Unset local variables to free memory
            unset(
                $context,
                $mode,
                $modeRaw,
                $params,
                $resolver
            );

            // Return the JSON array string from global mode
            return $out;
        }

        // Branch to linked-entries resolution when mode is `linked_entries`
        if ($mode === 'linked_entries') {
            // Resolve refs through the entries the context field links to
            $out = $resolver->resolveFromLinkedEntries(
                $context,
                $params
            );

            // Unset local variables to free memory
            unset(
                $context,
                $mode,
                $modeRaw,
                $params,
                $resolver
            );

            // Return the JSON array string from linked-entries mode
            return $out;
        }

        // Resolve refs from taxonomy terms on the context entry (default path)
        $out = $resolver->resolveFromTaxonomyTerms(
            $context,
            $params
        );

        // Unset local variables to free memory
        unset(
            $context,
            $mode,
            $modeRaw,
            $params,
            $resolver
        );

        // Return the JSON array string from terms mode
        return $out;
    }

    /**
     * Unwrap a Statamic Value when a tag parameter is field-bound in Antlers.
     *
     * @param  mixed  $raw  Raw parameter from `$this->params->get()`
     * @return mixed Unwrapped value, or the original argument when not a Value
     */
    private function unwrapAntlersParam(mixed $raw): mixed
    {
        return $raw instanceof Value ? $raw->value() : $raw;
    }
}
