# Upstream watch rows

Each `*.json` file here is one row for [`watch-upstream.yml`](../workflows/watch-upstream.yml). The workflow loads them all with [`scripts/load-upstream-watches.mjs`](../../scripts/load-upstream-watches.mjs) and, for each open tracking issue, comments when the upstream issue or pull request moves (UAMS-Web/uams-statamic-archive#2673).

They are separate files so that two pull requests that each add a row never edit the same line. When every row lived in one JSON string in the workflow, any two such pull requests conflicted, and a resolution that kept one side dropped the other side's rows without anything noticing.

## Adding a row

Add one file named `<tracker>-<owner>-<repo>-<number>.json`, holding one object:

```json
{
    "tracker": 1729,
    "repo": "pestphp/pest-plugin-mutate",
    "kind": "pr",
    "number": 38,
    "composer": "pestphp/pest-plugin-mutate"
}
```

- `tracker` is the issue in this repository that the comments go on. Closing it turns its watch off.
- `repo`, `kind` (`issue` or `pr`) and `number` name the upstream thread.
- `composer` is optional. It also reports a Packagist release newer than the version in `composer.lock`. Leave it out for a package that releases every week or so, because it would notify on every release.
- `released` is optional, and only for a `pr` row: `"released": true`. Once the pull request has merged, the watch also comments once when the newest stable tag on the pull request's base-branch line contains the merge commit, so it reports the release that carries the fix rather than every release (UAMS-Web/uams-statamic-archive#2727). A base of `13.x` reads `v13.*` tags only; any other base reads every stable tag. Use it on a row whose follow-up waits on a release, such as dropping a local patch.

Then add a note below saying why the row earns its place and why it tracks the ticket it does. The loader rejects a file that does not parse, has an unknown key, has the wrong shape, or does not match its own name. It also fails the run when there are no row files at all, so a wrong path can never pass as "nothing to watch". When the last row is retired, disable the workflow in the Actions tab rather than leaving the directory empty.

## Retiring a row

Delete its file and its note once the signal has fired and the tracker records the outcome (UAMS-Web/uams-statamic-archive#1898).

## What is watched today

A row earns its place only if a terminal signal would make us do something. A row stays here only when it watches an upstream that no `uams-web/patches-*` repository patches; a row for a patched upstream lives in the `patches-*` repository that carries the patch (#667). So `pestphp/pest-plugin-mutate#43`, which tracked #227, is now watched from `UAMS-Web/patches-pest-plugin-mutate`, and its copy in `tests/Fixtures/upstream-watches/` keeps the `composer` signal under test. If a second repository starts watching the same upstream item, revisit where its row lives.

- **`192-pestphp-pest-1820.json`** tracks `pestphp/pest#1820`, which resolves the git origin in linked worktrees and so changes Tia's cache key (UAMS-Web/uams-statamic-archive#1924). Whichever way it lands, #192 has to record the outcome and retire this watch. No `composer` key: `pestphp/pest` ships roughly twice a week (six stable tags between v5.0.1 and v5.1.1 in a fortnight), so a release-polling row would post about a hundred notifications a year, and the hazard it would poll for is one #192 already removed by pinning the cache to `tests/.pest/tia`.
- **`193-pestphp-pest-1813.json`** tracks `pestphp/pest#1813`, which replaces the Symfony Finder query in `Fingerprint::isTrackedByGit()` with `git check-ignore` and so fixes the structural fingerprint reading `null` for every tracked file in an ephemeral worktree (UAMS-Web/uams-statamic-archive#1996). A merge means `composer test:tia`'s blindness guard can be retired, which is #193's whole scope. It tracks #193 rather than UAMS-Web/uams-statamic-archive#1996 deliberately: UAMS-Web/uams-statamic-archive#1996 closes when its own pull request merges, which would retire this row before its signal could arrive. No `composer` key, for the same reason as the row above.
- **`229-statamic-docs-2003.json`** is an ISSUE row. It tracks `statamic/docs#2003`, filed from UAMS-Web/uams-statamic-archive#1498, which asks for documentation of how a config-field Vue component reads its blueprint siblings, the undocumented `suggestableConditionFieldsProvider` inject that UAMS-Web/uams-statamic-archive#1393's `DateFieldSelectFieldtype.vue` relies on. A close means Statamic has answered, and acting on the answer is #229's scope: move the consumer to a supported alternative if one is named, or record the outcome. It tracks #229, not UAMS-Web/uams-statamic-archive#1498, because UAMS-Web/uams-statamic-archive#1498 closed once the request was posted (UAMS-Web/uams-statamic-archive#2723). No `composer` key: `statamic/docs` is not a package this site installs.
- **`7-statamic-ideas-1490.json`** is an ISSUE row. It tracks `statamic/ideas#1490`, filed from UAMS-Web/uams-statamic-archive#1396, which asks Statamic to export the Assets fieldtype's selector, browser and uploader components so a multi-container fieldtype can be built from them. #7, the multi-container fieldtype itself, is already blocked by that request and is what acts on the answer, so the row tracks #7 rather than UAMS-Web/uams-statamic-archive#1396, which closed once the request was posted (UAMS-Web/uams-statamic-archive#2723). No `composer` key: `statamic/ideas` is a request tracker, not a package.
- **`17-statamic-ideas-1491.json`** is an ISSUE row. It tracks `statamic/ideas#1491`, filed from UAMS-Web/uams-statamic-archive#1397, which asks for an asset container's file-type rules to be applied in the uploader before upload. #17, restoring the upload picker's `accept` and drag-drop validation, is already blocked by that request and acts on the answer, so the row tracks #17 rather than UAMS-Web/uams-statamic-archive#1397 (UAMS-Web/uams-statamic-archive#2723). No `composer` key, for the same reason.
- **`9-statamic-ideas-140.json`** is an ISSUE row. It tracks `statamic/ideas#140`, the existing request for per-row collapse on Grid fields that UAMS-Web/uams-statamic-archive#1398 added this site's case to. #9, adding collapse to the extended Grid fieldtype, is already blocked by that request and acts on the answer, so the row tracks #9 rather than UAMS-Web/uams-statamic-archive#1398 (UAMS-Web/uams-statamic-archive#2723). No `composer` key, for the same reason.
- **`659-pestphp-pest-2024.json`** is an ISSUE row. It tracks `pestphp/pest#2024`, which reports that `brianium/paratest` `v7.28.0` crashes Pest's parallel runner at `WrapperRunner.php:646`, the breakage that made #658 pin `brianium/paratest` to `~7.27.5` and ignore it in `.github/dependabot.yml`. A close means upstream has answered, and #659 then names the Pest release that carries the fix, raises Pest, and removes the pin, the `ignore` entry and this row. It tracks #659 rather than #658, because #658 closes when the pin merges, which would retire this row before its signal could arrive. No `composer` key: `pestphp/pest` releases about twice a week, as the `192` row records, so a release-polling row would notify on every release.
