# Assets Fieldtype (Core + Custom PHP)

The app uses Statamic’s **core** Assets fieldtype in the CP. No custom Vue component is registered for `assets-fieldtype`. File type restrictions and behavior are provided by the PHP fieldtype and query scopes.

## Behavior

- **CP UI**: Statamic’s default Assets fieldtype component (no fork).
- **File type restrictions**: Configured in the blueprint (File Type Restrictions section). Enforced **on save** via server-side validation in `App\Fieldtypes\Assets`.
- **Asset browser**: When file type restrictions are set, the field’s preload adds the `assets_by_file_type` query scope and params. The browser only filters by type when those scope params are passed with the request (e.g. by a future Statamic mechanism). Until then, the browser shows all assets; validation still applies on save.
- **Upload file picker / drag-drop**: No `accept` attribute or client-side file-type validation. Users can select or drop any file; invalid types are rejected on save.

## Related code

- **PHP**: `app/Fieldtypes/Assets.php` — config (file type restrictions, attach_to_email in form context), validation (mime_types, mime_categories, min/max file size), preload (query_scopes and query_scope_params for the scope).
- **Scope**: `app/Scopes/AssetsByFileType.php` — asset query scope `assets_by_file_type`; filters by `categories`, `mime_types`, or `extensions` when params are provided.

## Documentation

- `app/Fieldtypes/Assets_docs.md` — full PHP fieldtype documentation.
