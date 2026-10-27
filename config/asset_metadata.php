<?php

declare(strict_types=1);

use App\Support\ContentRoot;

/*
|--------------------------------------------------------------------------
| Asset metadata YAML
|--------------------------------------------------------------------------
|
| `App\Models\Asset` keeps asset metadata in a dedicated SQLite database (the
| `asset_metadata` connection in config/database.php) and dual-writes every
| save to one YAML file per row, so the metadata is version-controlled
| alongside the assets it describes. `assets:sync-from-yaml` reads the same
| directory back in the other direction after a deploy, and
| `assets:prune-logo-metadata` audits it.
|
| The path is configuration rather than a `base_path()` literal so the test env
| can point it at the disposable `content/` mirror (UAMS-Web/uams-statamic-archive#1916). That is not merely
| tidier: the dual-write hangs off Eloquent's `saved` event and resolves its
| target outside the Stache, so no store re-root reaches it, and a test that
| saves an asset lands on whichever committed `{id}.yml` the `:memory:`
| autoincrement hands it — replacing a real image's alt text, caption and
| photographer credit with a fixture's (UAMS-Web/uams-statamic-archive#1919). Being a config key also means a
| test can point it somewhere of its own with `config()`, which a store
| directory cannot do (those are built at provider registration).
|
| Resolved through `App\Support\ContentRoot` rather than repeated, so this and
| the Stache stores in config/statamic/stache.php move together — see that
| class for why a half-moved state is destructive rather than partial.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | YAML directory
    |--------------------------------------------------------------------------
    |
    | Absolute path to the directory holding one `{id}.yml` per asset-metadata
    | row. Read at call time, so an override applies to the rest of the request.
    |
    */

    'yaml_directory' => ContentRoot::path(env('APP_ENV')).'/models/assets',

];
