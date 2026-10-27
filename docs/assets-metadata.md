# Custom asset metadata (Eloquent + SQLite + YAML)

This project extends Statamic’s **Eloquent asset driver** so asset metadata is stored in a **dedicated SQLite database**, mirrored to **versioned YAML** under the content tree. The control panel and front-end still use Statamic’s normal asset APIs; the custom pieces are persistence and Git-friendly exports.

## Why

- **Git**: Blueprint field values and technical meta (size, `mime_type`, `duration`, etc.) live in `content/models/assets/{id}.yml` so they can ship with the `content/` submodule (or repo) like other Statamic content.
- **Runtime**: Statamic’s Eloquent driver reads/writes rows in SQLite (`assets_meta`) for speed and to match the driver’s expectations.
- **CP stability**: Custom code ensures `meta.last_modified` is always a numeric Unix timestamp so listings and `Carbon::createFromTimestamp()` do not break when meta comes from imports or YAML without disk-backed files.

## Architecture

| Piece | Role |
| ----- | ---- |
| `config/statamic/eloquent-driver.php` | Sets `assets.model` to `App\Models\Asset` and `assets.asset` to `App\Statamic\Eloquent\Assets\Asset`. |
| `App\Models\Asset` | Eloquent model: `asset_metadata` connection, `assets_meta` table; normalizes `meta` on save; dual-writes YAML on save/delete. |
| `App\Statamic\Eloquent\Assets\Asset` | Statamic asset class: safe `lastModified()`, richer `generateMeta()` (e.g., bitrate for audio/video). |
| `content/models/assets/*.yml` | One file per row; filename is the numeric primary key (`{id}.yml`). The directory is `config('asset_metadata.yaml_directory')` — see *Where the YAML lives* below. |
| `php artisan assets:sync-from-yaml` | Pushes YAML back into SQLite (e.g., after `git pull`). |
| `App\Listeners\DeleteYamlAssetMetadata` | On `Statamic\Events\AssetDeleting`, removes the matching `{id}.yml` so Git stays aligned when an asset is deleted from the CP. |
| ~~`App\Listeners\BumpSchemaJsonLdCacheWhenAssetSaved`~~ | **Removed in UAMS-Web/uams-statamic-archive#937.** An asset save no longer bumps the schema JSON-LD generation — see *Caches and static output when assets change* below. |

## Where the YAML lives

The directory is configuration, not a `base_path()` literal: `config/asset_metadata.php` resolves `yaml_directory`, and every writer and reader — the model's dual-write, `App\Listeners\DeleteYamlAssetMetadata`, `assets:sync-from-yaml`, `assets:prune-logo-metadata` — reads it at call time. In production it is `content/models/assets/`, unchanged.

**Under test it is not** (UAMS-Web/uams-statamic-archive#1916). The test env roots flat-file content at a disposable mirror of `content/`, and this directory follows, because it is the one asset-metadata surface no Stache store re-root can reach: the dual-write hangs off Eloquent's `saved` event and never touches the Stache. It has to move, because the metadata database is `:memory:` under test, so its autoincrement restarts at `1` in every process and the first asset any test creates lands on the id a real image already owns — replacing that image's alt text, caption and photographer credit with a fixture's (UAMS-Web/uams-statamic-archive#1919). `App\Models\Asset::$skipYamlSync` is still armed by default in `Tests\TestCase::setUp()`; the two are complementary, not redundant. The flag keeps the write from happening at all, and the path makes a *forgotten* flag land on a copy.

A test that needs its own directory sets the config key (`Config::set('asset_metadata.yaml_directory', $tmp)`) — a per-app override, and the reason this is a config key rather than a constant. It replaced `App::setBasePath()`, which several suites used to redirect this one directory and which moves every other `base_path()` caller with it.

## Database

- **Connection**: `asset_metadata` in `config/database.php` (SQLite by default).
- **Path**: `ASSET_METADATA_DATABASE` in `.env`, or default `storage/framework/cache/asset-metadata.sqlite`.
- **Table**: `assets_meta` (see migration `database/migrations/2026_03_18_100000_create_asset_metadata_table.php`).

Each row is keyed in Statamic by **`container` + `folder` + `basename`** (unique index). The **numeric `id`** is the YAML filename and must stay stable when updating an existing row so `{id}.yml` does not drift.

## YAML file shape

Each `content/models/assets/{id}.yml` typically includes:

- Identity: `id`, `container`, `folder`, `basename`, `filename`, `extension`, `path`
- `meta`: JSON string (or structure after parse) holding Statamic’s meta blob, including:
  - `data`: blueprint field values (e.g., `title`, `series`, `transcript`)
  - Technical keys: `size`, `last_modified`, `mime_type`, `duration`, etc.
- `created_at` / `updated_at`

Editing YAML by hand is possible; run **`php artisan assets:sync-from-yaml`** so SQLite matches before relying on the CP.

## Commands

```bash
# Apply all YAML files under content/models/assets/ to SQLite
php artisan assets:sync-from-yaml

# Report how many files would sync (no writes)
php artisan assets:sync-from-yaml --dry-run
```

During sync, `Asset::$skipYamlSync` is set so loading/saving rows does not rewrite every YAML file.

## Dual-write behavior

- **Save in CP (or API)**: Eloquent `saved` on `App\Models\Asset` writes/overwrites `content/models/assets/{id}.yml` unless `$skipYamlSync` is true.
- **Delete in CP**: Eloquent `deleted` removes the YAML file (when not skipping); `DeleteYamlAssetMetadata` also deletes YAML keyed off the Statamic asset’s path/model lookup on `AssetDeleting`.

## Deploy / local workflow

After updating the `content/` tree (including `content/models/assets/*.yml`), run:

```bash
php artisan assets:sync-from-yaml
```

so each environment’s SQLite file reflects committed YAML. Consider adding this to deploy scripts **after** `git pull` / submodule update if metadata is edited in Git.

If the CP shows stale or missing field values after a pull, confirm:

1. Sync has been run on **that** environment.
2. Web and CLI use the same `ASSET_METADATA_DATABASE` path (same app root and `.env`).
3. Clear Statamic caches if needed (`php artisan statamic:stache:clear`).

## Caches and static output when assets change

- **Schema JSON-LD (Laravel cache):** Composed JSON-LD can include asset URLs and
  metadata. Cached payloads use a generation counter (`bump`), not per-asset
  keys. `Statamic\Events\AssetSaved` deliberately does **not** bump that counter:
  the `BumpSchemaJsonLdCacheWhenAssetSaved` listener that once did was removed in
  UAMS-Web/uams-statamic-archive#937, because a single import or CP upload cold-busted every page at ~67s per
  compose against a 60s `max_execution_time` — a site-wide 504.
  `AssetSavedDoesNotBustSchemaJsonLdCacheTest` pins that. So a CP asset editor
  save (title, alt, focal, blueprint fields) can leave a stale script tag until a
  watched entry/global/term save rotates the generation, bounded by `ttl_seconds`
  in `config/schema_templates.php`.
  The bump path that *does* run — `App\Listeners\InvalidateSchemaJsonLdCache`, on
  watched entry, global-set and term saves — reads none of the cache switches, so
  the generation rotates whatever `json_ld_cache.enabled`, `output_enabled` or
  `fragments_enabled` are set to (UAMS-Web/uams-statamic-archive#1842). Use the CP JSON-LD cache utility or
  `schema-templates:warm-json-ld-cache` after large busts if you want to avoid
  cold composes on first visits.

- **Statamic static caching:** When a static strategy is enabled, Statamic’s own
  invalidation runs on asset saves; this is separate from the JSON-LD Laravel
  cache above.

- **SSG (`statamic/ssg`):** HTML under `storage/app/static` is a build artifact.
  Changing asset metadata in the CP does not regenerate those files; run your
  SSG / deploy pipeline again when static output must reflect new asset fields
  or derivatives.

- **Glide / CDN:** Image derivatives and caches outside this app may still need
  operational clearing when manipulations or focal points change in ways URLs do
  not reflect.

## Related files (reference)

- `app/Models/Asset.php` — model, `last_modified` normalization, YAML I/O, `setAttribute` for string `meta`/`data`.
- `app/Statamic/Eloquent/Assets/Asset.php` — Statamic-side `lastModified` / `generateMeta` / bitrate.
- `app/Console/Commands/AssetsSyncFromYaml.php` — YAML → SQLite sync.
- `app/Listeners/DeleteYamlAssetMetadata.php` — YAML cleanup on asset delete.
- `app/Listeners/InvalidateSchemaJsonLdCache.php` — the JSON-LD bump path that does run: watched entry, global-set and term saves, gated on no cache switch.
- `app/Providers/EventServiceProvider.php` — registers `DeleteYamlAssetMetadata` on `AssetDeleting`; records why `AssetSaved` deliberately does not bust the JSON-LD cache.
