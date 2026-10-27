# EntriesBySchemaType scope and related pieces

This document explains how the EntriesBySchemaType Statamic query scope works and how it ties together the scope alias cache, enum generation, bootstrap, and Artisan commands.

---

## What EntriesBySchemaType does

`EntriesBySchemaType` is a Statamic query scope that filters entries by Schema.org type. It:

- Exposes **dynamic aliases** so each Schema type gets a scope handle, keyed by `Str::snake` of the type name (e.g., `entries_by_schema_type_thing`, `entries_by_schema_type_person`, `entries_by_schema_type_administrative_area`).
- Is a **single dual-mode scope** (the UAMS-Web/uams-statamic-archive#1408 merge of the former `EntriesBySchemaType` + `EntriesByResolvedSchemaType`). `apply()` constrains whichever schema-type field the invoking collection stores; a query is scoped to one collection, so the other clause matches nothing:
  - `schema_objects`: the flat resolved leaf on `schema->type`, expanded to the type **and its descendants** (via the `SchemaType` enum).
  - clinical collections: the chained fieldtype's stored `schema_type->selections` path, matched on the leaf type (the path already carries every ancestor, so the type and all descendants match for free).
- Resolves type from the scope handle by matching `Str::snake(<TypeName>)`. Using `Str::snake` (not the enum's `slug` attribute) is what makes multi-word handles like `administrative_area` resolve — the slug alias was `administrativearea`, which matched no blueprint handle and silently no-op'd.

Because the alias list is derived from `SchemaType::cases()`, it can be large and is expensive to build during **scope discovery**, which runs early in the Laravel bootstrap (before app service providers). So the alias list is cached.

---

## Files and how they relate

### `app/Scopes/EntriesBySchemaType.php`

- **Scope class.** Defines handle `entries_by_schema_type`, implements `aliases()` and `apply()`.
- **`aliases()`** returns the list of scope handles (one per Schema type slug). It uses:
  1. In-process static cache (per request/process).
  2. `ScopeAliasCache` when bound (preferred: available before Laravel cache).
  3. Laravel `Cache::rememberForever` when `cache` is bound.
  4. Otherwise builds the list with `buildAliases()` (no persistence).
- **`buildAliases()`** builds the list from `SchemaType::cases()`, keyed by `Str::snake($case->value)`.
- **`clearAliasesCache()`** / **`warmAliasesCache(array $typeNames)`** clear or warm both `ScopeAliasCache` and Laravel cache so the alias list stays in sync with the enum (e.g., after regenerating it).

### `app/Cache/ScopeAliasCache.php`

- **Cache for scope alias lists** that works even when Laravel’s `cache` is not bound yet.
- **Currently used only by** `EntriesBySchemaType`. **Intended for any scope** that implements `aliases()` and needs to persist alias lists; such scopes should use a scope-specific cache key and this singleton’s `rememberForever()`, `put()`, and `forget()`.
- When `cache` is bound, delegates to Laravel Cache; otherwise uses a file store under `storage/framework/cache/data/...` so alias discovery can persist across requests and CLI runs when scope discovery runs before `CacheServiceProvider`.

### `bootstrap/app.php`

- Registers the **`ScopeAliasCache` singleton** via Laravel 12’s `beforeBootstrapping(LoadEnvironmentVariables::class, ...)`.
- That hook runs at the start of the bootstrap phase, **before** any kernel bootstrapper (and before `RegisterProviders`). So when Statamic discovers scopes and calls `EntriesBySchemaType::aliases()`, `ScopeAliasCache` is already bound. Without this early binding, `aliases()` would sometimes run when neither `ScopeAliasCache` nor `cache` was bound and would fall back to building the list every time.

### `app/Console/Commands/ScopeAliasCache.php`

- **Artisan command** `scope-alias-cache` with actions: `clear`, `warm`, `refresh`.
- **clear** – Calls `EntriesBySchemaType::clearAliasesCache()` (clears both ScopeAliasCache and Laravel cache).
- **warm** – Builds type names from `SchemaType::cases()` and calls `EntriesBySchemaType::warmAliasesCache($typeNames)`.
- **refresh** – clear then warm.
- Usage: `php artisan scope-alias-cache clear|warm|refresh` (or `php please scope-alias-cache ...`).

### `app/Console/Commands/GenerateSchemaTypeEnum.php`

- **Artisan command** `generate:schema-type-enum`. Runs `SchemaTypeEnumGenerator::generate()` to fetch schema.org JSON-LD and write `SchemaType`, `SchemaProperty`, and `SchemaDataType` enums.
- After writing the enum files, the generator calls `EntriesBySchemaType::warmAliasesCache($typeNames)` so the scope alias cache is populated with the new type list without requiring a separate warm step. The command reports “Warmed EntriesBySchemaType alias cache.” when that succeeds.

### `app/Services/EnumGeneration/Schema/SchemaTypeEnumGenerator.php`

- **Generator** that fetches schema.org vocabulary, parses it, and writes the three enum files.
- At the end of `generate()`, if `EntriesBySchemaType` exists and has `warmAliasesCache`, it calls `warmAliasesCache($typeNames)` with the list of type names just written to the enum. That keeps the scope alias list in sync with the generated `SchemaType` enum; the cache is cleared manually (e.g., `scope-alias-cache clear`) or by operations that wipe the cache directory (e.g., `stache:warm`), and can be re-warmed by the generator or by `scope-alias-cache warm`.

---

## Flow summary

1. **Bootstrap** – `bootstrap/app.php` registers `ScopeAliasCache` before any bootstrapper runs.
2. **Scope discovery** – Statamic calls `EntriesBySchemaType::aliases()`. The scope uses `ScopeAliasCache` (or Laravel cache or in-process build) so the alias list is fast and persistent.
3. **Regenerating the enum** – `php artisan generate:schema-type-enum` writes the enums and warms the alias cache via the generator.
4. **Manual cache control** – `php artisan scope-alias-cache clear|warm|refresh` clears or warms the alias cache when needed (e.g., after a full cache clear or to fix a stale list).

---

## Cache keys and storage

- **Scope key:** `EntriesBySchemaType::CACHE_KEY_ALIASES` (`entries_by_schema_type_aliases_v2`). The `_v2` suffix invalidates any list persisted in the former slug format so the alias list rebuilds in the `Str::snake` format on deploy (UAMS-Web/uams-statamic-archive#1408).
- **ScopeAliasCache** file store: under `storage/framework/cache/data/` (subdirectory and filenames are implementation detail). Laravel’s `cache:clear` does not remove this store; `stache:warm` may clear the cache data directory and thus invalidate the file-backed alias cache, which will be rebuilt on next scope discovery or after `scope-alias-cache warm`.
