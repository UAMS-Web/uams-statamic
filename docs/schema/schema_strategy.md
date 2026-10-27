# Schema Strategy

The `schema_objects` collection entries are used to define the JSON-LD schema output via the `{{ schema_ld_json }}` Antlers tag.

Use those collection entries to define schema properties for data that is either not otherwise emitted on the front end or would only be conditionally emitted.

Use schema microdata in the relevant Antlers templates to define schema properties for data that is unconditionally emitted. Somehow associate the `<article>` HTML element (or perhaps `<main>` element) with the main content schema object, likely in some way related to the `@id` value.

In Schema Templates:
- define the site default for each slot (website subject, website, webpage) using `schema_objects` collection entries.
- as needed, define the section defaults for each slot (website subject, website, webpage, main content) for each collection and taxonomy using `schema_objects` collection entries.
- as needed, define the blueprint defaults for each slot (website subject, website, webpage, main content) using `schema_objects` collection entries.

In the `uams_multisite_settings` global set, define the `schema_objects` collection entries to use for the website subject website and website slot. We may later determine that defining the website slot in the `uams_multisite_settings` global set may not be necessary if we can dynamically define the properties for the schema object in that slot.

Define some mechanism for overriding and/or appending values to the schema object in the main content slot at the level of the collection entry / taxonomy term. The schema type should probably remain static at the entry level, though there may be a scenario where granting the ability to define more specific subtypes of the base type would be useful.
