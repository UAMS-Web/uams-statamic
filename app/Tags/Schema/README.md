# Schema (JSON-LD) Tag and Schema Templates

The `{{ schema_ld_json }}` tag outputs JSON-LD for the current entry/term when enabled via **Schema Templates** (Tools → Schema Templates). It uses **four slots** (Subject/site subject, Website, Webpage, Main content), each filled by a **schema_objects entry** chosen in the cascade or overridden by tag parameters.

## Usage

In your layout (e.g., `<head>`):

```antlers
{{ schema_ld_json }}
```

With optional overrides (schema_objects entry id per slot):

```antlers
{{ schema_ld_json subject="entry-id" website="entry-id" webpage="entry-id" main_content="entry-id" }}
```

The tag resolves the current entry/term from the route, runs the Schema Templates cascade (site → section → blueprint → entry) to get **enabled** and the **entry id** for each slot. If a tag parameter is present (`subject`, `website`, `webpage`, `main_content`), it overrides the cascade for that slot. If enabled, it outputs `<script type="application/ld+json">…</script>` with a single `@graph` built from the selected schema_objects entries.

Configure **enabled** and the **schema entry** for each slot (Subject, Website, Webpage, Main Content) in the Control Panel under **Tools → Schema Templates** (Site Defaults, Section Defaults, Blueprint Defaults). Each slot uses an entries field for the `schema_objects` collection (max one entry per slot). Section and Blueprint levels can override or inherit the Webpage and Main Content entries.

## How each graph node is built

For each selected schema_objects entry, the tag builds one JSON-LD node from that entry's **front matter**:

- **`@id`** – `#` plus the entry id (e.g. `#58185f50-22a4-48e7-9575-b86a1fdffef9`).
- **`@type`** – From `schema.type`: one value → string (e.g. `"Organization"`); multiple values → array of strings.
- **Properties** – From `schema.properties`. Each value is resolved (replicator rows are reduced to their effective string). Properties that are empty or resolve to empty are omitted.

Fields such as `title`, `blueprint`, `updated_by`, and `updated_at` are not used in the output.
