<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Asset;

/**
 * The filesystem root that flat-file content resolves under.
 *
 * Production reads the `content/` git submodule. The test env reads a
 * disposable mirror of it, so no test can write into the submodule — neither
 * the Stache stores (`config/statamic/stache.php`) nor the Eloquent
 * asset-metadata dual-write ({@see Asset}), which resolves its YAML
 * target outside the Stache entirely (UAMS-Web/uams-statamic-archive#1916).
 *
 * This exists so the two surfaces cannot drift apart. Re-rooting one without
 * the other is not a partial fix but a *destructive* one: `test:seed-stache`
 * wipes and rebuilds whatever it treats as the mirror, and a surface still
 * pointed at `content/` while the mirror moves is the mixed state UAMS-Web/uams-statamic-archive#1873
 * documents, where `ExistsAsFile::writeFile()` deletes the committed file
 * rather than merely writing to the wrong place. One function, two callers.
 *
 * **The environment is an argument, not a lookup.** Its first callers are config
 * files, which run before the container can answer `app()->environment()` — that
 * reads `config('app.env')`, which is the very thing being loaded. They pass
 * `env('APP_ENV')`, the one place an `env()` read is legitimate; runtime callers
 * pass `config('app.env')`, which is the same value and survives `config:cache`.
 * Taking it as a parameter is what keeps this class free of that distinction
 * (and of the `env()`-outside-config rule that enforces it).
 *
 * Under `config:cache` both the stores and this resolve from the same input at
 * the same moment, so they cannot disagree — and in production that input is not
 * `testing`, which is the correct production value.
 */
final class ContentRoot
{
    /**
     * The directory `content/`-relative paths resolve under.
     *
     * Absolute, with no trailing separator, so callers append their own.
     *
     * Typed as the union `env()` actually returns rather than `string`, so the
     * config callers need no cast. Anything that is not the string `testing` —
     * including the `bool` an `APP_ENV=true` would produce — is production.
     *
     * @param  string|bool|null  $environment  The app environment: `env('APP_ENV')`
     *                                         from a config file,
     *                                         `config('app.env')` at runtime
     */
    public static function path(string|bool|null $environment): string
    {
        return $environment === 'testing'
            ? storage_path('framework/testing/content-mirror')
            : base_path('content');
    }
}
