# Schema.org JSON-LD value normalization

This document defines how raw values from the schema.org JSON-LD graph should be normalized before use in the enum generator (or any consumer). It is derived from the structure analysis in `schema-jsonld-structure-report.txt`.

**Scope:** The analysis treats the root as `@graph`; keys below are graph-node keys (e.g., `(root)[].@id` → key `@id`). Only keys that appear on graph nodes are listed.

**Conventions:**
- **Indexed array** = PHP list (numeric keys 0, 1, 2, …).
- **Associative array** = PHP key-value map (e.g., JSON object).
- **Reference** = object with only `@id` (e.g., `{"@id": "schema:Person"}`).
- **Value object** = object with `@value` and optional `@language` / `@type` (e.g., language-tagged string).

---

## Translation: source key → enum/attribute/field name

Use these names in PHP attributes, property bags, or generated code. Rule: replace `:` with `_`; for `@`-prefixed keys, use the part after `@` (so `id` and `type` are valid PHP identifiers).

| Source key (JSON-LD)     | Enum/attribute/field name |
|--------------------------|----------------------------|
| `@id`                    | `id`                       |
| `@type`                  | `type`                     |
| `rdfs:comment`           | `rdfs_comment`             |
| `rdfs:label`             | `rdfs_label`               |
| `rdfs:subClassOf`        | `rdfs_sub_class_of`        |
| `rdfs:subPropertyOf`     | `rdfs_sub_property_of`     |
| `rdfs:seeAlso`           | `rdfs_see_also`            |
| `schema:domainIncludes`  | `schema_domain_includes`   |
| `schema:rangeIncludes`   | `range_includes`    |
| `schema:contributor`     | `schema_contributor`       |
| `schema:source`          | `schema_source`            |
| `schema:inverseOf`       | `schema_inverse_of`        |
| `schema:isPartOf`        | `schema_is_part_of`        |
| `schema:supersededBy`    | `superseded_by`     |
| `schema:sameAs`          | `schema_same_as`           |
| `owl:equivalentClass`   | `owl_equivalent_class`     |
| `owl:equivalentProperty` | `owl_equivalent_property` |
| `owl:disjointWith`       | `owl_disjoint_with`        |
| `skos:exactMatch`        | `skos_exact_match`         |
| `skos:closeMatch`        | `skos_close_match`         |

**Rule for new keys:** `prefix:localName` → `prefix_local_name` (colon → underscore; preserve existing casing).

---

## OWL-implied range union (`range_includes` on `SchemaProperty`)

The published schema.org Turtle (`data/schema.ttl`) lists `schema:rangeIncludes` per property. The project’s generated `SchemaProperty` enum stores **effective** value types in `range_includes`, which may be a **superset** of those declarations.

**When:** For each property, after reading `schema:rangeIncludes` from the JSON-LD graph, if the property has **at least one** declared range type and **not every** declared range is one of the following seven local names, then **`Role`**, **`Text`**, and **`URL`** are merged in if missing:

`Boolean`, `Date`, `DateTime`, `Number`, `Float`, `Integer`, `Time`

**When not:** If the declared list is **empty**, leave it empty (do not invent ranges). If **every** declared range is from the seven-type list only, do **not** add `Role` / `Text` / `URL`.

**Why:** This mirrors schema.org’s own OWL vocabulary export (`software/util/sdoowl.py`: `DATATYPES` vs `DEFAULTRANGES`), which unions `schema:Text`, `schema:URL`, and `schema:Role` into `rdfs:range` for the same class of properties. It aligns with the [Data model → Conformance](https://schema.org/docs/datamodel.html) note that `Role` and `URL` (and, in practice, textual values) are used flexibly. Official examples (e.g. `OrganizationRole` qualifying `alumniOf` on [`https://schema.org/Role`](https://schema.org/Role)) follow that pattern even when Turtle `rangeIncludes` omits `Role`; see [schemaorg#2787](https://github.com/schemaorg/schemaorg/issues/2787) for vocabulary-vs-example discussion.

**Implementation:** `App\Services\EnumGeneration\Schema\SchemaTypeEnumGenerator::mergeOwlImpliedRangeIncludes()`.

---

## Role-wrappable relationship properties (domain/type property lists)

Schema.org’s `Role` / `OrganizationRole` pattern is commonly used to qualify *relationship edges* with metadata that doesn’t belong solely to either endpoint entity. These qualifiers are typically `startDate`, `endDate`, and `roleName` (plus subtype-specific fields like `numberedPosition`, `characterName`, or compensation-related fields), while still stating the underlying relationship using the **same relationship predicate**.

In practice, publishers and consumers treat certain relationship properties as idiomatically acceptable to “wrap” with `Role`/`OrganizationRole`, especially for memberships, affiliations, participation, credits, and similar connections.

For authoring/tooling in this project, we treat the following properties as Role-wrappable by:

- Adding `Role` to each property’s generated `schema_domain_includes` (even though the core vocabulary does not always declare `domainIncludes Role` for them); and
- Ensuring `SchemaType::Role` lists these property names (within `property_names`).

Properties:

`alumniOf`, `affiliation`, `member`, `members`, `memberOf`, `worksFor`, `founder`, `performer`, `performers`, `organizer`, `attendee`, `attendees`, `participant`, `author`, `creator`, `contributor`, `producer`, `publisher`, `creditedTo`, `translator`

**Implementation:** `App\Services\EnumGeneration\Schema\SchemaTypeEnumGenerator::ROLE_WRAPPABLE_PROPERTIES`, `mergeRoleWrappablePropertyDomains()`, and `mergeRoleWrappableTypeProperties()`.

---

## 1. Node identifier

| Key   | Observed value types | Normalize to |
|-------|----------------------|--------------|
| `@id` | string               | **string** (unchanged). Optionally resolve to local name (e.g., `schema:Person` → `Person`) in one place so downstream code sees a single convention. |

**Report data:** Use **@id prefix distribution** to filter (e.g., schema.org-only: keep nodes whose `@id` has prefix `schema:` or `https://schema.org/`). The full list of every `@id` is in `schema-jsonld-all-ids.txt` (one per line, sorted).

---

## 2. Type list

| Key    | Observed value types              | Normalize to |
|--------|-----------------------------------|--------------|
| `@type` | string, array (indexed) of strings | **list of strings.** If value is a string, treat as `[value]`. Enables uniform checks (e.g., `in_array('rdfs:Class', $types, true)`). |

**Report data:** **Distinct @type values** lists every type string seen in the graph (e.g., `rdfs:Class`, `rdf:Property`, `schema:DataType`, and many `schema:*` enumeration types). Use this set when branching on node kind (class vs property vs datatype vs enumeration) instead of hardcoding. **Keys per node type** shows which keys appear on each of these types.

---

## 3. Text (comment, label)

| Key           | Observed value types                                   | Normalize to |
|---------------|--------------------------------------------------------|--------------|
| `rdfs:comment` | string, array (associative) with `@language` and `@value` | **string.** If value is a string, use it. If it is an object with `@value`, use `$value['@value']`. If it is an array (e.g., `[lang, text]`), use the text element (e.g., index `1`). Prefer a single language (e.g., `en`) if multiple are present; otherwise first available. Empty/missing → `''` or omit. |
| `rdfs:label`    | string, array (associative) with `@language` and `@value` | **string.** Same rules as `rdfs:comment`. |

**Report data:** **Language tags seen** lists which `@language` values appear (e.g., `en`); prefer that language when normalizing. Per-key **max_string_length** and **string_contains_double_quote** / **string_contains_newline** inform truncation and escaping in generated code (e.g., for PHP attribute strings).

---

## 4. Single reference (one @id)

These keys appear as either a **single reference object** `{"@id": "…"}` (associative array) or, in a few cases, an **indexed array of one reference**. Normalize so downstream always sees one reference.

| Key                    | Observed value types                          | Normalize to |
|------------------------|------------------------------------------------|--------------|
| `schema:inverseOf`     | array (associative) — reference                | **string** — the single `@id` value. If value is reference object, use `$value['@id']`. If indexed array of one reference, use `$value[0]['@id']`. Missing → `null` or omit. |
| `schema:isPartOf`      | array (associative) — reference                | **string** — the single `@id` value (e.g., `https://pending.schema.org`). |
| `schema:supersededBy`  | array (associative) — reference                | **string** — the single `@id` value. |
| `owl:disjointWith`     | array (associative) — reference                | **string** — the single `@id` value. |
| `skos:exactMatch`      | array (associative) — reference                | **string** — the single `@id` value. |
| `rdfs:seeAlso`         | array (associative) — reference                | **string** — the single `@id` value. |
| `schema:sameAs`        | array (associative) — reference                | **string** — the single `@id` value. |

**Note:** If any of these ever appear as an indexed array of multiple references, decide whether to take the first, concatenate, or keep as list (see §5).

**Report data:** **Keys per node type** shows which node types (e.g., `rdf:Property`, `rdfs:Class`) carry each of these keys and with what frequency; use to validate expectations when reading a single reference.

---

## 5. List of references (one or more @id)

These keys appear as either a **single reference object** or an **indexed array of reference objects**. Normalize so downstream always sees a **list of @id strings**.

| Key                      | Observed value types                                      | Normalize to |
|--------------------------|-----------------------------------------------------------|--------------|
| `rdfs:subClassOf`        | array (associative), array (indexed) of reference objects | **list of strings** — each element is an `@id` (e.g., `['schema:CreativeWork']`). If value is one reference object, use `[$value['@id']]`. If indexed array, use `array_map(fn ($r) => $r['@id'], $value)`. |
| `rdfs:subPropertyOf`     | array (associative), array (indexed) of reference objects | **list of strings** — same as `rdfs:subClassOf`. |
| `schema:domainIncludes`  | array (associative), array (indexed) of reference objects | **list of strings** — list of `@id` values (e.g., `['schema:Person', 'schema:Organization']`). |
| `schema:rangeIncludes`   | array (associative), array (indexed) of reference objects | **list of strings** — same as `schema:domainIncludes`. |
| `schema:contributor`     | array (associative), array (indexed) of reference objects | **list of strings** — list of `@id` values. |
| `schema:source`          | array (associative), array (indexed) of reference objects | **list of strings** — list of `@id` values. |
| `owl:equivalentClass`    | array (associative), array (indexed) of reference objects | **list of strings** — list of `@id` values. |
| `owl:equivalentProperty` | array (associative), array (indexed) of reference objects | **list of strings** — list of `@id` values. |
| `skos:closeMatch`        | array (associative), array (indexed) of reference objects | **list of strings** — list of `@id` values. |

**Generic rule:** For any key whose value is either (a) a single reference object or (b) an indexed array of reference objects, normalize to **list of @id strings**: if it’s a single reference, wrap in a one-element list; if it’s already a list, map each element to its `@id`.

**Report data:** Per-key **array_length: min .. max** gives the actual range (e.g., 1–12 for `schema:domainIncludes`); use for sizing or deciding inline vs reference in generated code. **Keys per node type** shows these keys on `rdf:Property` (e.g., `schema:domainIncludes`, `schema:rangeIncludes`) vs `rdfs:Class` (e.g., `rdfs:subClassOf`).

---

## 6. Optional local-name normalization

After normalizing reference lists or single references, you may optionally map each `@id` string to a **local name** (e.g., strip `schema:` prefix or take the fragment after the last `/`). Example:

- `schema:Person` → `Person`
- `https://schema.org/Person` → `Person`
- `eli:passed_by` → keep as `eli:passed_by` or normalize per vocabulary.

Do this in one place so the rest of the generator consistently sees either full `@id` strings or local names.

**Report data:** **@id prefix distribution** shows which namespaces appear (e.g., `schema:`, `bibo:`, `eli:`); use to define which prefixes to strip or keep when building local names.

---

## 7. Summary table

| Key                     | Raw shape(s)                    | Normalized type   |
|-------------------------|---------------------------------|-------------------|
| `@id`                   | string                          | string            |
| `@type`                 | string \| indexed array         | list of strings   |
| `rdfs:comment`          | string \| value object          | string            |
| `rdfs:label`            | string \| value object          | string            |
| `rdfs:subClassOf`       | single ref \| list of refs      | list of strings   |
| `rdfs:subPropertyOf`    | single ref \| list of refs      | list of strings   |
| `schema:domainIncludes` | single ref \| list of refs      | list of strings   |
| `schema:rangeIncludes`  | single ref \| list of refs      | list of strings   |
| `schema:contributor`    | single ref \| list of refs      | list of strings   |
| `schema:source`         | single ref \| list of refs      | list of strings   |
| `schema:inverseOf`      | single ref                      | string            |
| `schema:isPartOf`       | single ref                      | string            |
| `schema:supersededBy`   | single ref                      | string            |
| `schema:sameAs`         | single ref                      | string            |
| `owl:equivalentClass`   | single ref \| list of refs      | list of strings   |
| `owl:equivalentProperty` | single ref \| list of refs    | list of strings   |
| `owl:disjointWith`      | single ref                      | string            |
| `rdfs:seeAlso`          | single ref                      | string            |
| `skos:exactMatch`       | single ref                      | string            |
| `skos:closeMatch`       | single ref \| list of refs      | list of strings   |

---

## 8. Implementation notes

- **Single reference vs list:** In the report, “array (associative)” with “reference(@id only)” means one object `{"@id": "…"}`. “array (indexed)” with “array_element_types: array (associative)” means a list of such objects. A single reference can be treated as a list of one for uniformity, then the consumer takes `[0]` when only one is needed.
- **Missing keys:** Omitted keys can be represented as `null`, empty string, or empty list depending on the normalized type; document the choice in the generator.
- **Source of truth:** Re-run `app/Services/EnumGeneration/Schema/SchemaJsonLdStructureAnalyzer.php` on the latest `schemaorg-current-https.jsonld` in that directory and update this document if new keys or value shapes appear.

---

## 9. Using the structure report

When implementing normalization or the enum generator, use the report and all-ids file as follows:

| Step | Report section / file | Use |
|------|------------------------|-----|
| Filter nodes (e.g., schema.org only) | **@id prefix distribution**; `schema-jsonld-all-ids.txt` | Keep nodes with prefix `schema:` or `https://schema.org/`; enumerate from graph or from all-ids file. |
| Branch on node kind (class vs property vs datatype) | **Distinct @type values**; **Keys per node type** | Use distinct types as the set for `in_array($type, …)`; use keys per type to know which keys to read per node. |
| Normalize @type | **Distinct @type values** | Expect only these strings; normalize string → list of one. |
| Normalize comment/label | **Language tags seen**; per-key **max_string_length**, **string_contains_*** | Prefer listed language(s); truncate/escape using length and quote/newline flags. |
| Normalize single reference | **Keys per node type** | Confirm key appears on expected node type; take single `@id`. |
| Normalize list of references | **Keys per node type**; per-key **array_length: min .. max** | Confirm key on node type; use length range for sizing. |
| Local-name from @id | **@id prefix distribution** | Define which prefixes to strip (e.g., `schema:` → local name). |
| Order of list values | **Ordering** (note in report) | Preserve array order; schema.org does not define significance. |

---

## 10. What the structure report provides (gaps addressed)

The analyzer (`SchemaJsonLdStructureAnalyzer.php`) now includes all of the following. The report and the optional `schema-jsonld-all-ids.txt` file are the source of truth.

| Gap | Description | Use for enum generator? |
|-----|-------------|--------------------------|
| **Keys per node type** | Which keys appear on which kind of node (e.g., only on `rdfs:Class` vs only on `rdf:Property`). The report merges all 3187 nodes, so e.g. `rdfs:subClassOf` is “943 occurrences” but we don’t see “only on Class nodes.” | **Yes.** Generator can branch by type (Class vs Property) and only read keys that apply (e.g., `rdfs:subClassOf` for types, `schema:domainIncludes` for properties). |
| **@id namespace / prefix distribution** | Counts of nodes by `@id` prefix (e.g., `schema:` vs `bibo:` vs `eli:`). The report shows examples like `bibo:issue`, `schema:legislationPassedBy` but not “N nodes with schema:, M with bibo:”. | **Yes.** Deciding “schema.org only” (filter `@id` by `schema:` or `https://schema.org/`) and estimating enum size. |
| **Distinct values for small domains** | For keys with a small value set (e.g., `@type`), the list of all distinct values seen. Report gives types and examples but not “all @type values: rdfs:Class, rdf:Property, schema:DataType”. | **Useful.** Knowing the exact set of `@type` values avoids hardcoding and handles new types (e.g., `schema:DataType`) safely. |
| **List length for array-valued keys** | Min/max or distribution of list length for keys like `schema:domainIncludes`, `rdfs:subClassOf`. Report gives count of occurrences, not “this key’s value has 1–12 elements.” | **Optional.** Helps size buffers or choose “inline vs reference” for large lists; not required for correctness. |
| **Language tags in value objects** | Distinct `@language` values for `rdfs:comment` / `rdfs:label` when they are value objects (e.g., only `en`). Report mentions `@language` and `@value` but doesn’t list languages. | **Low.** If you always take the first or prefer `en`, you don’t need a full list. |
| **Ordering semantics** | Whether order of elements in arrays is significant. | **Low.** Report includes an ordering note: array order is preserved; schema.org does not define order for these predicates. |
| **String length / escaping** | Max length of strings (e.g., `rdfs:comment`) or whether they contain quotes/newlines. | **Optional.** Report includes per-key `max_string_length`, `string_contains_double_quote`, `string_contains_newline` where applicable. |
| **List of all @id values** | Full list of every `@id` in the graph. | **Yes.** Written to `schema-jsonld-all-ids.txt` (one id per line, sorted); report states total count. |

**Summary:** The report and `schema-jsonld-all-ids.txt` cover the above. Section 9 above maps each implementation step to the report data to use.
