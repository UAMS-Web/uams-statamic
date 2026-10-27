# Schema templates (JSON-LD)

This folder contains the **runtime pipeline** that turns `schema_objects` collection entries (and related Statamic entries/terms) into a single `<script type="application/ld+json">` document for the current request.

The hardest problems here are **(1)** choosing a stable, unique **`@id`** for every JSON-LD node, and **(2)** deciding when to emit a **full object** vs a compact **`{"@id":"…"}`** reference so the graph stays valid, small, and free of accidental merges.

---

## Main components (at a glance)

| Piece | Role |
|--------|------|
| [`SchemaOutputService`](SchemaOutputService.php) | Request entry: cascade-driven `@graph`, property templates, `@id`, dedupe, inflate. |
| [`SchemaTemplatesCascadeService`](SchemaTemplatesCascadeService.php) + **defaults repos** (see [Service catalog](#service-catalog)) | Site → section → blueprint → entry resolution for enabled flag and four slot entry ids. |
| [`SchemaJsonLdAtIdDeduplication`](SchemaJsonLdAtIdDeduplication.php) + [`SchemaJsonLdSchemaObjectEmissionCoordinator`](SchemaJsonLdSchemaObjectEmissionCoordinator.php) | Shared `@id` list policy, full-node bookkeeping, compact-reference fast path. |
| Emit pipeline | [`SchemaTemplateEmitContext`](SchemaTemplateEmitContext.php), [`SchemaTemplateEmitContextRuntimeStack`](SchemaTemplateEmitContextRuntimeStack.php), [`SchemaTemplateInstanceWatermarkBag`](SchemaTemplateInstanceWatermarkBag.php), [`SchemaTemplateNodeBuildStack`](SchemaTemplateNodeBuildStack.php) — nested Antlers + template instance suffixes + re-entrancy guard. |
| Relationship / refs | [`SchemaThingFromRelationshipResolver`](SchemaThingFromRelationshipResolver.php), [`SchemaThingRefsResolver`](SchemaThingRefsResolver.php). |
| CP / types / fragments | [`SchemaTemplatesFields`](SchemaTemplatesFields.php), repositories, [`SchemaTypeOptions`](SchemaTypeOptions.php), [`SchemaObjectFragmentBuilder`](SchemaObjectFragmentBuilder.php), [`SchemaJsonLdFragmentAtId`](SchemaJsonLdFragmentAtId.php). |

**Full file-by-file inventory** (every class below, with responsibilities and main APIs): [Service catalog](#service-catalog) and [Antlers tag catalog](#antlers-tag-catalog). **Runtime / CP config:** [Configuration](#configuration-config).

Author-facing usage for `{{ schema_ld_json }}` also lives in [`app/Tags/Schema/README.md`](../Tags/Schema/README.md).

---

## Configuration (`config/`)

| File | Purpose | Main keys / consumers |
|------|---------|------------------------|
| [`config/schema_templates.php`](../../config/schema_templates.php) | JSON-LD **output cache** and related CP/Artisan behavior. | **`json_ld_cache`**: `enabled` (`SCHEMA_JSON_LD_CACHE_ENABLED`), `ttl_seconds` (`SCHEMA_JSON_LD_CACHE_TTL`), `bust_cache_key` / `last_bump_meta_key` / `last_warm_meta_key` (used by [`SchemaJsonLdOutputCache`](SchemaJsonLdOutputCache.php)), **`invalidate_entry_collections`**, **`invalidate_global_set_handles`**, **`invalidate_taxonomies`** (listeners bump the bust counter so `SchemaOutputService::outputForCurrentContext` cache keys rotate), **`warm.*`** (`global_entry_fields_by_collection` with dotted `global_set.field` refs grouped by entry collection, `global_taxonomy_entry_traversals`, homepage URIs) for `schema-templates:warm-json-ld-cache` and CP warm actions. |
| [`config/schema_object.php`](../../config/schema_object.php) | **Schema Object** fieldtype, replicator metadata, and slot-oriented type policy. | **`slot_type_roots`** — per-slot Schema.org root types (`subject`, `website`, `webpage`, `main_content`); see file header for how this is meant to pair with type pickers / [`SchemaTypeOptions`](SchemaTypeOptions.php). **`entries_collection`** — collection handle for `schema_objects`-backed Thing rows ([`SchemaPropertyReplicatorSets`](../SchemaObject/SchemaPropertyReplicatorSets.php)). **`property_blacklist`** — `general` and per-`@type` **`specific`** lists of property **handles hidden in the CP** ([`SchemaObject`](../../Fieldtypes/SchemaObject.php) fieldtype, [`SchemaObjectReplicatorDataController`](../../Http/Controllers/CP/SchemaObjectReplicatorDataController.php)); blacklisting does not by itself strip values merged via [`SchemaClassificationsIdentifiersMerger`](SchemaClassificationsIdentifiersMerger.php). |

---

## End-to-end flow (current request)

1. **`SchemaOutputService::outputForCurrentContext()`**  
   Resolves the route **entity**, loads cascade config, optionally reads **`config('schema_templates.json_ld_cache')`** and `Cache::remember`, then calls **`composeJsonLdOutputScriptTagForResolvedSlots()`**.

2. **`composeJsonLdOutputScriptTagForResolvedSlots()`**  
   Builds **`$graphEntryIds`**: ordered list of non-empty slot entry ids (subject → website → webpage → main content).  
   Initializes **one shared**:
   - `$emittedEntryIds` — schema_objects entry ids that already received a **full** inline node in this pass (where tracked).
   - `$emittedNodeIds` — canonical `@id` → “full node already registered in this pass”.
   - `$materializedFullNodeAtIds` — `@id` keys whose **full** object survived merged output (used to gate **compact** references).
   - **`$templateInstanceWatermarkBag`** — **`SchemaTemplateInstanceWatermarkBag`**: monotonic instance suffixes for repeated template emits (see below).

3. **Per slot** it calls **`schemaObjectEntryToNode()`** for each slot’s `schema_objects` id with the **same** `$entity` (route context), **`$graphEntryIds`**, the maps above, and the **same watermark bag**.

4. **Dedupe passes**  
   After the graph is assembled, **`SchemaJsonLdAtIdDeduplication`** runs (shared map vs fresh map) so duplicate full nodes collapse where policy allows. See the class-level docblock in `SchemaJsonLdAtIdDeduplication.php` for the distinction between **shared pass**, **decode-first retention**, and **fresh pass**.

5. **`inflateBareAtIdReferencesMissingFullNodesInGraph()`**  
   Repairs trees that still contain bare `{"@id":…}` leaves when no matching full node exists anywhere in the graph (re-expands from `schema_objects` ids + optional instance suffix).

---

## `@id` construction (`entryIdToNodeId` and friends)

- **Root `@id` for a `schema_objects` node** is computed in **`entryIdToNodeId()`** from:
  - the template entry id,
  - **context entity** (entry/term used for Antlers / URL rules),
  - **`$graphEntryIds`** (which graph slots exist),
  - visit stack (cycle avoidance),
  - optional **template instance index** (suffix `-0`, `-1`, … on the fragment).

- **Canonical site / path** often comes from resolving the effective schema’s `url` property (or fallbacks); fragment-only vs absolute URL depends on that resolution.

- **`SchemaOutputService::getCanonicalAtIdForSchemaEntry()`** exposes canonical `@id` for an entry without building the full property tree (used where only identity matters).

### Template instance suffix (`-0`, `-1`, …)

Replicator rows often pass **`schema_template_instance_index`** (Antlers context key **`SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX`**) into nested **`{{ schema_template_node }}`** calls. Without coordination, every row could request index **0** and collide on the same fragment **`#uuid-0`**.

**`resolveEffectiveTemplateInstanceIndexForWatermark()`** (with a non-null **`SchemaTemplateInstanceWatermarkBag`**) applies:

\[
\text{effective} = \max(\text{requested},\ \text{lastCommitted} + 1)
\]

- **Peek** (commit `false`): used when previewing `@id` for compact checks — must not advance the bag.
- **Commit** (`true`): used when materializing a full node — advances **`lastIssuedByKey`** for that allocation key.

**The same row keeps its index.** A committed index is also remembered per **row identity** (the context entity id, the requested index and the replicator row's own `id`, in **`issuedByRowIdentity`**), and a later request from that row, peek or commit, returns it instead of bumping. So a row emitted more than once (the person template emits each certifications row under both `hasCertification` and `hasCredential`, and the person renders both as the Person node and as the WebSite's `accountablePerson`) keeps one `@id`: the first emit is the full node and the rest compact to `{"@id":…}` (UAMS-Web/uams-statamic-archive#3049). A row with no `id` of its own (none, empty, not a string, or equal to the context entity id that Antlers cascades from the outer scope) is not remembered, and each of its emits still takes a fresh suffix.

**Allocation key** = `schemaObjectsEntryId` + NUL + **allocation anchor**:

- **`templateInstanceWatermarkAllocationAnchor()`** prefers the **first non-empty** id in **`$graphEntryIds`** (typically the **subject** slot), so every nested emit under the **same graph** shares one monotonic series per template (e.g. multiple media contacts each get distinct `-0`, `-1`, … even when each row’s **context entity** is a different person).
- If **`$graphEntryIds`** is empty (standalone tag), anchor falls back to **`scopeEntityIdForTemplateInstanceWatermark($contextEntity)`** (the context entry/term id).

---

## Full node vs `{"@id":…}` (emission)

### Shared maps (by reference)

During **`schemaObjectEntryToNode` → `resolvePropertyValue` → `resolveSingleReplicatorRowToValue`**:

- **`$emittedNodeIds`** is updated when a **full** node (has `@type`, etc.) is recorded — **`SchemaJsonLdSchemaObjectEmissionCoordinator::recordFullSchemaObjectNode()`** / dedupe helpers.
- **`$materializedFullNodeAtIds`** is updated when merged output is known to contain a surviving full body for that `@id` — used so compact references are not emitted for `@id`s that were only speculative and later dropped.

### `schema_template_node` tag

The Antlers tag accepts a single **`id`** parameter (one uuid, pipe-separated uuids, arrays, collections, `Entry` models, or Statamic relationship **query builders** resolved with `get()`—as returned by `entries` fields without `max_items`). The separate **`ids`** parameter is no longer used.

**`SchemaOutputService::buildSchemaTemplateNodeJsonForTag()`**:

1. If **`SchemaTemplateEmitContext`** is present, it uses the **same** `emitted_*`, `materialized_*`, **`graphEntryIds`**, and **`templateInstanceWatermarkBag`** as the active graph build.
2. **`SchemaJsonLdSchemaObjectEmissionCoordinator::tryCompactReferenceIfSchemaObjectAlreadyFullyEmitted()`** may return **`{"@id":…}`** when the **preview** canonical `@id` (via **`previewCanonicalAtIdForSchemaObjectTemplate()`** with the **same watermark bag**, peek-only) is already in **`$materializedFullNodeAtIds`**.
3. Otherwise it builds a **full** node via **`schemaObjectEntryToNode()`** and runs dedupe retention.

Preview and full build **must** use the same watermark rules or compact would target the wrong fragment.

### `schema_thing_from_relationship` (and similar)

When **`SchemaThingFromRelationshipResolver`** runs with an emit context, it calls **`buildStandaloneSchemaObjectNodeSharingEmittedNodeIds()`** with:

- **`$emitContext->graphEntryIds`** and **`$emitContext->templateInstanceWatermarkBag`** overrides,

so nested person templates share the **graph anchor** and **allocator** instead of creating an isolated bag per target.

**`SchemaTemplateEmitContextRuntimeStack`**: **`resolveSingleReplicatorRowToValue()`** pushes the emit context **before** `Parse::template()` and pops in `finally`. Tags also **`peek()`** the stack first when resolving emit context, because Statamic’s tag runtime does not always propagate arbitrary Antlers variables.

### `schema_thing_refs` (`field` handles and fallbacks)

**[`SchemaThingRefsResolver`](SchemaThingRefsResolver.php)** reads the tag’s **`field`** parameter (after **[`SchemaTagFieldHandles`](SchemaTagFieldHandles.php)** splits **`a|b|c`** fallbacks). For each candidate handle it resolves the value from the **Antlers context snapshot** (`$this->context->all()` in **[`SchemaThingRefs`](../Tags/SchemaThingRefs.php)**).

**Dotted paths** (e.g. **`source_entry.work_location`**) are **not** resolved with Laravel **`data_get()`** alone: once a segment resolves to a Statamic **`Entry`**, the next segment must be read with **`Entry::value($handle)`** so blueprint fields behave like they do in Antlers. The resolver walks segments accordingly (unwraps **`Statamic\Fields\Value`**, steps through arrays and **`Illuminate\Support\Collection`** keys when present). Same underlying limitation as **`SchemaThingFromRelationshipResolver`** when it special-cases **`source_entry.{handle}`** — see **`readFirstNonEmptyFieldValueFromContext()`** there.

Use **`field="role_x|root_field|source_entry.root_field"`** when nested rows lack per-row data but the route entry (or **`source_entry`**) still carries the field.

**Modes.** **`terms`** (the default) reads taxonomy terms from the field and each term's entries field. **`linked_entries`** does the same through entries, for a vocabulary kept as a collection: the field's entry ids, then each linked entry's **`linked_entries_field`** (default **`schema_object`**). The `geographic_locations` schema-object templates use it (UAMS-Web/uams-statamic-archive#2597). **`entries`** reads schema_objects ids directly from the field, and **`global`** reads them from a global set.

### Antlers replicator rows and JSON fragments

For **`type: antlers`** rows, **`SchemaOutputService::resolveSingleReplicatorRowToValue()`** treats the trimmed parse output as JSON in a fixed order:

1. **Object or array** — starts with **`{`** or **`[`** → `json_decode` to an **array**, then **`SchemaJsonLdAtIdDeduplication::dedupeWithSharedEmitMapAndDecodeFirstFullRetention()`** so nested `@id` policy matches other decoded branches.
2. **JSON Text (string document)** — starts with **`"`** → `json_decode(..., JSON_THROW_ON_ERROR)`; when the top-level value is a **string**, that string is returned as the property value. This pairs with tags such as **`schema_pronounceable_text`**, which intentionally **`json_encode`** schema.org **Text** so the Antlers output is valid JSON. Without this step, the PHP value would still contain literal quote characters and the graph’s final **`json_encode`** would double-escape (e.g. `"givenName": "\"Kaitlin\""`). Non-string top-level JSON (e.g. a bare number) is **not** applied here — only a successful **string** result is unwrapped.
3. **`#schema_objects-id`** — bare fragment reference → **`{"@id":…}`** via **`entryIdToNodeId()`**.
4. Otherwise the trimmed string is returned unchanged (plain Antlers text, URLs, etc.).

---

## Antlers context keys (contract)

| Constant | Meaning |
|----------|---------|
| `SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX` | Non-negative int; suffixes repeated `schema_objects` `@id` for the same template in one build. |
| `SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT` | **`SchemaTemplateEmitContext`** instance; set on the Antlers variable bag during property template parse (and mirrored via the runtime stack). |
| `SchemaOutputService::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM` | When `schema_template_node` (or any caller) supplies a non-empty Antlers overlay after merge + slug hydration, the first overlay key in lexicographic order whose merged value is a term or non-empty term collection yields a **`Fluent`** flattening of that default-locale term (same pipeline as `buildFlattenedSchemaAntlersContextFromDataObject`) so **`schema_primary_related_taxonomy_term.<handle>`** dot paths and loops behave like normal entry Antlers. |

### `schema_primary_related_taxonomy_term` (primary related taxonomy term)

During **`SchemaOutputService::resolveSingleReplicatorRowToValue()`** Antlers property parsing, the service may set the context key **`schema_primary_related_taxonomy_term`** (constant **`SchemaOutputService::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM`**) to an **`Illuminate\Support\Fluent`** wrapping the flattened, terms-hydrated variable bag for the **default-locale** taxonomy term selected from the **caller overlay** (for example the live scope merged by **`schema_template_node`**). That happens **after** `array_merge($entryContext, $overlay)` and **after** `hydrateMergedContextTermsLikeFieldsIfRawSlugs()`, so overlay values that were raw slugs are already **collections of term models** where applicable. The value is **not** a live `LocalizedTerm` instance so `terms` / `terms_search_index` fields (for example `audience`) resolve consistently under dot paths and tag pairs like `schema_primary_related_taxonomy_term.audience`.

**How the term is chosen (no template author configuration):** only keys present on the overlay map are considered; they are sorted **lexicographically**; reserved keys (`schema_template_instance_index`, `schema_template_emit_context`, and `schema_primary_related_taxonomy_term` itself) are skipped. The **first** remaining key whose merged bag value is either a single taxonomy term or a **non-empty** term collection supplies **the first term** in that collection. If nothing matches, the key is **not set**—any Antlers that reference it render empty and JSON-LD properties that skip empty strings stay omitted.

#### Loop form: `{{ schema_primary_related_taxonomy_term }}{{ foo }}{{ /schema_primary_related_taxonomy_term }}`

**Intended use:** open a **term scope** so the body runs as if you were inside that taxonomy term (same idea as looping a terms field in page Antlers). Use this when you need **several** term fields, **conditionals**, **filters**, or multi-line text in one property template.

**Shape** ( **`foo`** = any field handle on the term blueprint, e.g. `title`, `slug` ):

```antlers
{{ schema_primary_related_taxonomy_term }}{{ foo }}{{ /schema_primary_related_taxonomy_term }}
```

You may split lines inside the pair for readability; Antlers ignores the extra whitespace between tags and field output. The inner **`{{ foo }}`** always refers to a field on the **related term**, not on the parent entry, while the pair is open.

**Placeholder scanning:** when assembling the parse context, `fillMissingSimpleTemplateVariablesWithNull` scans the template for simple `{{ handle }}` tokens and inserts missing keys (often `null`) so values do not “leak” from ambient Antlers scope. Handles that appear **only** inside this tag pair are **excluded** from that scan so they are not pre-filled from the parent entry—otherwise a handle like `audience` (on the certification term but not on the person entry) would become `audience: null` and **shadow** the term field of the same name, producing empty output.

**Looping `terms` / `terms_search_index` on the primary term:** use the **dot field** on the injected `Fluent`, with matching closing tags (same path as the opening tag):

```antlers
{{ schema_primary_related_taxonomy_term.audience }}
  {{ title }}
{{ /schema_primary_related_taxonomy_term.audience }}
```

`fillMissingSimpleTemplateVariablesWithNull` strips bodies of these `schema_primary_related_taxonomy_term.<field>` pairs so inner `{{ title }}` etc. are not pre-filled from the parent entry.

**Requirements:** the opening and closing tags for the **bare** term scope must use the **exact** same handle: `schema_primary_related_taxonomy_term`. For dot-scoped loops, the closing tag must repeat the **full** dotted path (for example `{{ /schema_primary_related_taxonomy_term.audience }}`).

**When to prefer this:** default choice for `schema_objects` property Antlers that must stay **agnostic** of which replicator field carried the term (avoid hardcoding `activity`, taxonomy handles, or term slugs in the schema template).

#### Dot form: `{{ schema_primary_related_taxonomy_term.foo }}`

**Intended use:** read **one** scalar (or stringifiable) field from the same injected term **inline**, without nesting.

**Shape** ( **`foo`** = any field handle on the term blueprint, e.g. `title`, `slug` ):

```antlers
{{ schema_primary_related_taxonomy_term.foo }}
```

Antlers resolves **`schema_primary_related_taxonomy_term`** first, then **`.foo`** on that **`Fluent`** value (same chaining idea as `{{ some_term.title }}` on a flattened data object elsewhere).

**When to prefer this:** short property values (a single line of text, one URL, one flag) where a loop would be noisy.

**Caveats:** if the term is missing or the field is empty, the expression is empty—same as any other Antlers path. Some fieldtypes output structures better inside the **loop** form (for example values that expect a scoped iterator or multi-segment markup). If dot access behaves oddly for a given field, switch to the loop form for that field.

#### Multiple term-like overlay keys

If the overlay includes **more than one** key whose merged values are terms or term collections, **lexicographic key order** decides which key wins. Design row-level overlays so only one such key appears when that ambiguity would matter, or accept the deterministic “first key wins” rule.

---

## Integrating new schema Antlers tags

The following steps keep **`@id`** assignment, **full-node vs compact reference** emits, and **dedupe** aligned with the graph build.

Use this checklist whenever a new tag emits JSON-LD that may overlap with the **same-request graph** (nested `schema_objects` templates, repeated occupations, relationship-driven nodes, etc.).

### 1. Decide how the tag participates in the graph

| Tag behavior | What to wire up |
|--------------|-----------------|
| Emits one or more **`schema_objects`** trees (same machinery as graph slots) | Resolve **`SchemaTemplateEmitContext`** and call **`SchemaOutputService::buildSchemaTemplateNodeJsonForTag()`** or **`buildStandaloneSchemaObjectNodeSharingEmittedNodeIds()`** with **shared** maps and bag (see below). |
| Delegates to **`SchemaThingFromRelationshipResolver`** (or similar) | **`resolve()`** needs **`$emitContext->graphEntryIds`** and **`$emitContext->templateInstanceWatermarkBag`** for **`buildStandaloneSchemaObjectNodeSharingEmittedNodeIds()`**. Prefer passing **`emit_context`** in params after your tag’s own stack-first / Antlers resolution (§2). If params omit it, **`resolve()`** uses **`SchemaTemplateEmitContextRuntimeStack::peek()`** when the call runs under **`SchemaOutputService`**’s nested parse — that often covers Antlers boundaries that drop the variable, but **do not rely on peek alone** for entry points that are not guaranteed to sit on that stack. |
| Emits **hand-built** nodes that still use canonical `@id` strings produced elsewhere | You still need the **same canonical `@id` rules** as `entryIdToNodeId` / `getCanonicalAtIdForSchemaEntry` if those nodes must dedupe against template output; otherwise you risk duplicate or orphan references. Prefer reusing **`SchemaOutputService`** for any `schema_objects`-backed fragment. |
| Truly isolated preview / CP-only output | A null emit context and **`buildStandaloneSchemaObjectNode()`** (fresh maps) may be correct — document why it must not share the graph. |

### 2. Resolve emit context: stack first, then Antlers

Statamic does not guarantee that arbitrary variables survive every tag boundary. **`SchemaOutputService`** pushes the active **`SchemaTemplateEmitContext`** onto **`SchemaTemplateEmitContextRuntimeStack`** around nested **`Parse::template()`** calls.

**Every tag that must share emit state should resolve context in this order:**

1. **`SchemaTemplateEmitContextRuntimeStack::peek()`** — if it returns **`SchemaTemplateEmitContext`**, use it.
2. Else read **`$this->context->get(SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT)`** and accept only an actual **`SchemaTemplateEmitContext`** instance.
3. Else **`null`** (tag ran outside an active schema property / graph build).

**Resolver vs tag:** **`SchemaThingFromRelationshipResolver::resolve()`** takes **`$params['emit_context']`** when present, otherwise falls back to **`SchemaTemplateEmitContextRuntimeStack::peek()`** (same type checks as above). That is a **safety net** for nested property parses, not a substitute for resolving emit context in the tag when you control the call site — other resolvers or future code paths may not duplicate that fallback.

Reference implementations: **`SchemaTemplateNode::resolveEmitContextFromAntlersScope()`**, **`SchemaThingFromRelationship::resolveSchemaTemplateEmitContextFromAntlersScope()`**, **`SchemaCountry::resolveEmitContextFromAntlersScope()`**, **`SchemaSubjectMatterKeywords::resolveSchemaTemplateEmitContextFromAntlersScope()`** (same stack-then-Antlers pattern; the keywords resolver path does not build `schema_objects` nodes, but the tag still resolves emit context consistently).

### 3. Thread maps and watermark bag by reference

**`SchemaTemplateEmitContext`** holds **by-reference** bindings to the graph’s **`$emittedEntryIds`**, **`$emittedNodeIds`**, **`$materializedFullNodeAtIds`**, plus **`graphEntryIds`**, **`contextEntity`**, and **`templateInstanceWatermarkBag`**.

When calling **`buildStandaloneSchemaObjectNodeSharingEmittedNodeIds()`** from new code:

- Pass **`&$emitContext->emittedNodeIds`** and **`&$emitContext->materializedFullNodeAtIds`** (or the same arrays the graph build uses).
- Pass **`$emitContext->graphEntryIds`** as **`$graphEntryIdsOverride`** so watermark allocation anchors match the **subject slot** (or first non-empty slot), not an arbitrary nested entity.
- Pass **`$emitContext->templateInstanceWatermarkBag`** as **`$templateInstanceWatermarkBagOverride`** so instance suffixes stay monotonic with **`schema_template_node`** and other nested emits.

**Never** construct a fresh **`SchemaTemplateInstanceWatermarkBag`** for a code path that is supposed to participate in the same graph unless you intentionally want isolated `-0` suffixes (that pattern caused duplicate `@id` bugs in the past).

### 4. Compact `{"@id":…}` vs full node

If your tag chooses between emitting a full **`schema_objects`** node and a reference:

- Use **`SchemaJsonLdSchemaObjectEmissionCoordinator::tryCompactReferenceIfSchemaObjectAlreadyFullyEmitted()`** (or the same policy **`SchemaOutputService`** already applies in **`buildSchemaTemplateNodeJsonForTag()`**).
- Any **preview** of the canonical `@id` for that decision must use **`previewCanonicalAtIdForSchemaObjectTemplate()`** with the **same** **`SchemaTemplateInstanceWatermarkBag`** and **peek** semantics as the eventual full build, or compact will point at the wrong fragment.

### 5. Template instance index in Antlers

When your tag or its Antlers invokes nested templates for **repeated rows**, merge a numeric **`schema_template_instance_index`** into the Antlers bag (key **`SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX`**) when you need explicit row identity; the service still **clamps** upward via the watermark bag when multiple rows would otherwise collide.

### 6. Recording emissions after custom merges

If your tag decodes JSON, merges arrays, or builds subgraphs manually:

- Reuse **`SchemaJsonLdAtIdDeduplication`** helpers so **shared pass**, **decode-first retention**, and **fresh pass** rules stay consistent (see that class’s docblock).
- When a full node is accepted into output, keep **`SchemaJsonLdSchemaObjectEmissionCoordinator::recordFullSchemaObjectNode()`** (and updates to **`materializedFullNodeAtIds`**) aligned with existing call sites in **`SchemaOutputService`** / **`SchemaThingFromRelationshipResolver`**.

### 7. Tests

Add or extend a **feature** test that renders a real entry whose template exercises the new tag **inside** a graph slot, and assert on **`@id`** uniqueness or compact-vs-full shape. If the request uses JSON-LD caching, follow **`SchemaJsonLdRealPersonEntriesTest`** and disable the cache for that test class so **`Cache::remember`** does not hide regressions.

---

## Caching

**`outputForCurrentContext()`** may wrap generation in **`Cache::remember`** keyed by site, locale, entity, slot fingerprint, and a **bust** counter (`SchemaJsonLdOutputCache`). Tune **`config/schema_templates.php`** → **`json_ld_cache`** (TTL, invalidation lists, env flags). Tests that assert on generated JSON-LD often **disable** that cache so earlier runs in the same PHPUnit process do not return stale payloads.

---

## Where to change behavior

| Goal | Likely touch points |
|------|---------------------|
| Change `@id` shape / URL rules | `entryIdToNodeId()`, `getCanonicalAtIdForSchemaEntry()`, effective schema `url` resolution. |
| Change when full vs compact | `SchemaJsonLdSchemaObjectEmissionCoordinator`, `SchemaJsonLdAtIdDeduplication`, `buildSchemaTemplateNodeJsonForTag()`. |
| Change instance suffix policy | `resolveEffectiveTemplateInstanceIndexForWatermark()`, `templateInstanceWatermarkAllocationAnchor()`. |
| New nested tag that needs graph dedupe | See **[Integrating new schema Antlers tags](#integrating-new-schema-antlers-tags)** — resolve emit context (stack + Antlers key), thread maps and watermark bag, reuse coordinator / dedupe. |
| Reading dotted field paths from Antlers context into **`schema_thing_refs`** (or similar) | Prefer **`SchemaThingRefsResolver`**’s **`getFieldValueFromAntlersContext()`** pattern ( **`Entry::value()`** per segment); do not assume **`data_get($context, …)`** crosses **`Entry`** boundaries. |
| JSON-LD cache keys / bust / warm | [`config/schema_templates.php`](../../config/schema_templates.php) → `json_ld_cache`; [`SchemaJsonLdOutputCache`](SchemaJsonLdOutputCache.php). |
| CP schema property visibility / `schema_objects` collection handle | [`config/schema_object.php`](../../config/schema_object.php) → `property_blacklist`, `entries_collection`, `slot_type_roots`. |

---

## Related tests

- Feature: `tests/Feature/SchemaJsonLdRealPersonEntriesTest.php` — full JSON-LD for real people entries (UAMS-Web/uams-statamic-archive#729 consolidated both one-test-per-file people tests here). Covers **Leslie Taylor** (multi-recipient occupation `@id` regression and **plain-string** `givenName` / `familyName` / `alternateName` on media recipients — guards JSON Text unwrap in **`resolveSingleReplicatorRowToValue()`**) and **C. Lowry Barnes** ( **`occupationLocation`** on **`Occupation`** nodes from **`schema_thing_refs`** when per-role locations are empty ( **`role_work_location|work_location|source_entry.work_location`** ); exercises **`SchemaThingRefsResolver`** context walking).
- Unit: `tests/Unit/Services/SchemaTemplates/SchemaOutputServiceTest.php`, `SchemaJsonLdSchemaObjectEmissionCoordinatorTest.php`, `SchemaThingFromRelationshipResolverTest.php`, tag tests under `tests/Unit/Tags/`.

When you add new emission paths, extend the **same** three maps + watermark bag threading, or deliberately document why a path must remain isolated.

---

## Service catalog

PHP modules under [`app/Services/SchemaTemplates/`](.) (excluding [`Mergers/`](Mergers/), [summarized below](#mergers-subfolder)). Internal `protected` / `private` methods are omitted unless they are the main concept (e.g. `composeJsonLdOutputScriptTagForResolvedSlots`).

| File | Responsibility | Principal APIs / notes |
|------|----------------|-------------------------|
| [`SchemaBlueprintDefaultsRepository`](SchemaBlueprintDefaultsRepository.php) | Read/write per-blueprint defaults (`enabled`, `inherit_template`, `parameter`) from collection/taxonomy cascade YAML under `inject.schema_templates.blueprint_defaults`. | `get($sectionHandle, $blueprintHandle, $type)`, `put(...)`. |
| [`SchemaSectionDefaultsRepository`](SchemaSectionDefaultsRepository.php) | Read/write per-section defaults (`enabled`, `inherit_template`, `template`) under `inject.schema_templates.section_defaults`. | `get($sectionHandle, $type)`, `put(...)`. |
| [`SchemaSiteDefaultsRepository`](SchemaSiteDefaultsRepository.php) | Read/write site-wide defaults (`enabled`, `template`, slot entry ids) in `resources/addons/schema-templates.yaml`. | `get($siteHandle)`, `put(...)`. |
| [`SchemaTemplatesCascadeService`](SchemaTemplatesCascadeService.php) | Merges site → section → blueprint → entry (and multisite global overrides) into one resolved shape for JSON-LD. | `resolve($entity)` for route entry or term → `enabled`, `subject_entry`, `website_entry`, `webpage_entry`, `main_content_entry`. |
| [`SchemaTemplatesFields`](SchemaTemplatesFields.php) | Blueprint **arrays** for CP Publish forms (Tools → Schema Templates): field definitions for site / section / blueprint defaults. | `siteDefaults()`, `sectionDefaults($siteDisabled)`, `blueprintDefaults($sectionDisabled)`, `toBlueprint($contents)`. Used by `Http\Controllers\CP\SchemaTemplates\*DefaultsController`. |
| [`SchemaTypeOptions`](SchemaTypeOptions.php) | Schema.org type picklists from `App\Enums\SchemaType` (cached per PHP process). | `schemaObjectTypes()` — consumed by `schema_objects` blueprint / [`SchemaObject`](../../Fieldtypes/SchemaObject.php) fieldtype. |
| [`SchemaOutputService`](SchemaOutputService.php) | Core runtime: graph composition, `schemaObjectEntryToNode`, Antlers property resolution, `entryIdToNodeId`, decode/merge/dedupe/inflate, emit context injection. | `outputForCurrentContext($tagParams)`, `buildSchemaTemplateNodeJsonForTag`, `buildStandaloneSchemaObjectNode`, `buildStandaloneSchemaObjectNodeSharingEmittedNodeIds`, `previewCanonicalAtIdForSchemaObjectTemplate`, `getCanonicalAtIdForSchemaEntry`, `getSlotEntryForCurrentContext`, `buildFlattenedSchemaAntlersContextFromDataObject` (helpers for nested context). |
| [`SchemaJsonLdOutputCache`](SchemaJsonLdOutputCache.php) | Bust counter and CP metadata for **`config('schema_templates.json_ld_cache')`** keys used by `outputForCurrentContext`. | `currentBust()`, `bump()`, related warm/meta helpers (see class). |
| [`SchemaJsonLdAtIdDeduplication`](SchemaJsonLdAtIdDeduplication.php) | Central `@id` policy: list dedupe, full-node registration, compact refs, shared vs fresh map passes, decode-first retention, materialized union sync. | `appendUniqueAtIdObject`, `shouldEmitUniqueAtIdNode`, `dedupeResolvedPropertyList`, `compactToReferenceIfFullNodeAlreadyEmitted`, `recordFullSchemaObjectNodeEmitted`, `dedupeWithSharedEmitMapAndDecodeFirstFullRetention`, `dedupeWithFreshEmitMap`, `dedupeNodeByAtId`, `recordMaterializedFullNodeAtIdsFromFragment`, `syncMaterializedFullNodeAtIdsToUnionOfTreeRoots`. |
| [`SchemaJsonLdSchemaObjectEmissionCoordinator`](SchemaJsonLdSchemaObjectEmissionCoordinator.php) | Thin coordinator over dedupe + `SchemaOutputService` for **`schema_objects`** compact vs full. | `tryCompactReferenceIfSchemaObjectAlreadyFullyEmitted`, `recordFullSchemaObjectNode`, `atIdReferenceObjectForSchemaObjectEntry`. |
| [`SchemaJsonLdFragmentAtId`](SchemaJsonLdFragmentAtId.php) | Builds canonical absolute `@id` = `rtrim(app.url,'/')` + `#` + URL-safe Base64 of a **root-relative path** (aligned with taxonomy term identity in the thing resolver). | `atIdFromRootRelativePath($path)`. Used by e.g. [`SchemaCountry`](../Tags/SchemaCountry.php). |
| [`SchemaObjectFragmentBuilder`](SchemaObjectFragmentBuilder.php) | Normalizes `@type`, coerces `properties` param to arrays, builds JSON object strings for small fragments (no `schema_objects` entry). | `normalizedType`, `coercePropertiesToArray`, `normalizedPropertiesForFragment`, `buildJson`, `normalizeJsonLdValuePublic`. Used by [`SchemaObject`](../Tags/SchemaObject.php), [`SchemaPlace`](../Tags/SchemaPlace.php). |
| [`SchemaTagFieldHandles`](SchemaTagFieldHandles.php) | Parses `field="a|b|c"` tag params into ordered handle lists (first non-empty wins in resolvers). | `listFromParam($field)`. |
| [`SchemaTemplateEmitContext`](SchemaTemplateEmitContext.php) | Holds **by-reference** `emittedEntryIds`, `emittedNodeIds`, `materializedFullNodeAtIds`, `graphEntryIds`, `contextEntity`, `templateInstanceWatermarkBag` for one graph build. | Public properties; constructed when entering property template resolution. |
| [`SchemaTemplateEmitContextRuntimeStack`](SchemaTemplateEmitContextRuntimeStack.php) | LIFO stack of active `SchemaTemplateEmitContext` around nested `Parse::template` so tags can `peek()` when the Antlers bag omits the emit key. | `push`, `pop`, `peek` (see implementation). |
| [`SchemaTemplateInstanceWatermarkBag`](SchemaTemplateInstanceWatermarkBag.php) | Tracks last **committed** template instance index per allocation key so repeated `-0`/`-1` suffixes stay unique within a graph. | Used from `SchemaOutputService` and emit context; **peek vs commit** when previewing `@id` vs materializing. |
| [`SchemaTemplateNodeBuildStack`](SchemaTemplateNodeBuildStack.php) | Detects re-entrant `schema_template_node` for the same `schema_objects` id while a node is still building. | `contains`, `push`, `pop` — avoids infinite recursion; yields `{"@id":…}` only. |
| [`SchemaClassificationsIdentifiersMerger`](SchemaClassificationsIdentifiersMerger.php) | After a node is built from properties, merges CP “classifications / identifiers” tab into schema.org-shaped `PropertyValue` / codes / etc. via [`Mergers/`](Mergers/). | `merge(Entry $entry, array $node): array`. |
| [`SchemaThingFromRelationshipResolver`](SchemaThingFromRelationshipResolver.php) | Large resolver: direct / secondary / tertiary / global relationship walks → JSON-LD nodes or refs; honors `emit_context` for shared `@id` behavior. | `resolve($context, $params)`, `resolveSubjectMatterKeywords` (keywords-only path for [`SchemaSubjectMatterKeywords`](../Tags/SchemaSubjectMatterKeywords.php)). |
| [`SchemaThingRefsResolver`](SchemaThingRefsResolver.php) | Emits JSON arrays of `{"@id":…}` for `schema_objects` targets resolved via taxonomy terms, entry fields, or globals — same canonical `@id` as graph. | `resolveFromTaxonomyTerms`, `resolveFromGlobalEntriesField`, `resolveFromEntryEntriesField`. Context field paths use **`getFieldValueFromAntlersContext()`** so dotted handles traverse **`Entry`** segments via **`value()`** (not plain **`data_get`**). Uses `SchemaOutputService`, `SchemaJsonLdAtIdDeduplication`, `SchemaJsonLdSchemaObjectEmissionCoordinator`. |

### `Mergers/` subfolder

[`Mergers/`](Mergers/) contains many small classes implementing [`SchemaIdentifierMergerInterface`](Mergers/SchemaIdentifierMergerInterface.php). [`SchemaClassificationsIdentifiersMerger`](SchemaClassificationsIdentifiersMerger.php) injects them to map stored identifier rows into JSON-LD. They are **not** part of the Antlers `@graph` emit path unless merged into a node via that merger.

---

## Antlers tag catalog

| File | Tag handle | Role in architecture |
|------|------------|----------------------|
| [`SchemaLdJson`](../Tags/SchemaLdJson.php) | `schema_ld_json` | **Request entry:** calls `SchemaOutputService::outputForCurrentContext()`; optional slot overrides. |
| [`SchemaTemplateNode`](../Tags/SchemaTemplateNode.php) | `schema_template_node` | Emits one or more `schema_objects` JSON fragments; uses **emit context** + `buildSchemaTemplateNodeJsonForTag`. |
| [`SchemaThingFromRelationship`](../Tags/SchemaThingFromRelationship.php) | `schema_thing_from_relationship` | Passes Antlers context + params (including **`emit_context`**) into `SchemaThingFromRelationshipResolver::resolve`; resolver also **`peek()`** the runtime stack when `emit_context` is missing from params. |
| [`SchemaSubjectMatterKeywords`](../Tags/SchemaSubjectMatterKeywords.php) | `schema_subject_matter_keywords` | Same resolver params as thing-from-relationship; `resolveSubjectMatterKeywords` → JSON **string array** only. |
| [`SchemaThingRefs`](../Tags/SchemaThingRefs.php) | `schema_thing_refs` | Canonical **`{"@id":…}`** lists via `SchemaThingRefsResolver` + `SchemaTagFieldHandles`; **`field`** supports **`\|`** fallbacks and dotted paths that cross **`Entry`** objects (see [`schema_thing_refs` (`field` handles and fallbacks)](#schema_thing_refs-field-handles-and-fallbacks)). |
| [`SchemaCountry`](../Tags/SchemaCountry.php) | `schema_country` | `Country` nodes with `SchemaJsonLdFragmentAtId`; when emit context exists, may emit compact refs consistent with dedupe maps. |
| [`SchemaPlace`](../Tags/SchemaPlace.php) | `schema_place` | `Place` + `PostalAddress` JSON via `SchemaObjectFragmentBuilder` (no `schema_objects` `@id` pipeline unless embedded in a template). |
| [`SchemaObject`](../Tags/SchemaObject.php) | `schema_object` | Arbitrary typed JSON-LD object from `type` + `properties` via `SchemaObjectFragmentBuilder` (inline fragments). |
| [`SchemaLanguage`](../Tags/SchemaLanguage.php) | `schema_language` | `Language` / Text JSON for BCP47 or ISO639 codes; output is decoded by `SchemaOutputService` like other property JSON. |
| [`SchemaPronounceableText`](../Tags/SchemaPronounceableText.php) | `schema_pronounceable_text` | Emits **`json_encode`** fragments (`PronounceableText` array, or plain **Text** as a JSON string document); uses `SchemaLanguage` for `inLanguage`. Plain Text rows are unwrapped when the property pipeline runs **`resolveSingleReplicatorRowToValue()`** (see [Antlers replicator rows and JSON fragments](#antlers-replicator-rows-and-json-fragments)). |
| [`SchemaPronouns`](../Tags/SchemaPronouns.php) | `schema_pronouns` | `PropertyValue` pronoun slots / plain name string. |
| [`Schema/SchemaPropertyValidator`](../Tags/Schema/SchemaPropertyValidator.php) | (PHP only, no Antlers tag) | Validates property names per `@type` using **spatie/schema-org** reflection + `SchemaType` enum; used by CP / field layers to guard schema property keys (including `@id` as reserved). |

**Emit integration:** tags that participate in the **same `@id` universe** as `schema_objects` must follow [Integrating new schema Antlers tags](#integrating-new-schema-antlers-tags). Tags that only emit small self-contained JSON fragments (`schema_language`, `schema_pronouns`, …) still interact with dedupe **when their output is JSON decoded** inside `SchemaOutputService` (`{` / `[` arrays and JSON **Text** string documents per [Antlers replicator rows and JSON fragments](#antlers-replicator-rows-and-json-fragments)), but they do not call `buildStandaloneSchemaObjectNodeSharingEmittedNodeIds` unless you add that.
