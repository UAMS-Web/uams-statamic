# Consuming `role_title` field data (Antlers, GraphQL, APIs)

The `role_title` field stores an associative array. Always branch on the **`mode`** key:

- **`structured`** — Composed one-line title is **not** persisted as `value`; editors fill configuration-specific subfields (`configuration`, radios, checkboxes, `scope`, etc.). At read time, `augment()` / CP `preProcess()` add a virtual **`value`** string (composed title) and optional schema-driven output keys from `config/role_title.php`.
- **`unstructured`** — **`value`** is persisted as the free-text job title. **`occupation`** and **`occupational_field`** hold list-shaped metadata (`occupation.same_as`, `occupation.category_code.isco_08`, `occupation.category_code.onet_soc`, `occupational_field.same_as`). CP-only `__cp_role_title_unstructured_*` keys exist only in publish payloads and are stripped on save.

## Legacy content

Entries saved before this feature may omit **`mode`**. Treat missing `mode` as **`structured`** when `enabled_title_structures` was never set on the blueprint (default is structured-only). After editors save, `mode` will be present.

## Antlers

Guard templates that assumed only structured keys:

```antlers
{{ if (title | get:mode) == 'unstructured' }}
  {{ title:value }}
{{ else }}
  {{ title:value }}{{* augmented composed line *}}
{{ /if }}
```

## GraphQL

If you expose `role_title` as JSON, clients must accept the unstructured shape above. If you expose flattened fields, map `mode` first, then either the composed virtual `value` (structured) or stored `value` (unstructured).
