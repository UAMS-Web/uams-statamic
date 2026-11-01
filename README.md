# Getting Started

## Installation instructions

1. Install [Laravel Herd](https://herd.laravel.com/)
2. Configure "Herd paths" in Laravel Herd's general settings
3. [Install Imagick](README_Imagick.md) (if on Windows)
4. Clone this repository into the Herd path.
    1. If you clone without `--recurse-submodules`, run `git submodule update --init --recursive` to fetch the `content`, `users`, `resources/forms` and assets submodules.
5. Create a copy of the `.env.example` file as `.env`.
6. Add any sensitive data to the relevant variables in `.env` (e.g., `GOOGLE_MAPS_API_KEY`).
7. Run `composer install`
8. Run `php please make:user`
9. Run `npm i` && `npm run dev:with-cp`
10. Go to http://uams-statamic.test/ and click "Generate App Key"

## Statamic vendor patch workflow

For the Composer patch generation process used for Statamic vendor overrides,
see `patches/README.md`.

## Asset metadata (custom)

Asset rows live in SQLite and are mirrored to YAML under `content/models/assets/`. See [`docs/assets-metadata.md`](docs/assets-metadata.md) for architecture, sync commands, and deploy notes.

## Troubleshooting

- **Public form renders without fields / Alpine never starts (`504 Outdated Optimize Dep` in DevTools).** Vite's optimize-dep cache went stale (common after `npm ci`, lockfile-touching branch switches, or dep upgrades while the dev server is running). See [`docs/runbooks/vite-stale-optimize-dep-cache.md`](docs/runbooks/vite-stale-optimize-dep-cache.md) for symptoms, cause, and fix.

## Git Submodules

This repository uses Git submodules for Statamic content, users, form configurations and asset storage so those can be versioned and deployed separately.

| Submodule                               | Path                 | Purpose                                       |
| --------------------------------------- | -------------------- | --------------------------------------------- |
| `UAMS-Web/uams-statamic-content`        | `content/`           | Statamic collections, taxonomies, globals     |
| `UAMS-Web/uams-statamic-users`          | `users/`             | Statamic user accounts and groups             |
| `UAMS-Web/uams-statamic-forms`          | `resources/forms/`   | Statamic form configurations                  |
| `UAMS-Web/uams-statamic-assets-public`  | `public/assets/`     | Public asset containers (images, files, etc.) |
| `UAMS-Web/uams-statamic-assets-private` | `app/private/assets/`| Private asset containers (form uploads, etc.) |

- **Clone with submodules:**  
  `git clone --recurse-submodules <repo-url>`
- **If you already cloned without submodules:**  
  `git submodule update --init --recursive`
- **Update submodules to latest committed refs:**  
  `git submodule update --init --recursive`  
  (Deploy scripts run this after `git pull` so production stays in sync.)

All five submodules are private repositories (UAMS-Web/uams-statamic-archive#2654). A clone without access to them gets the submodule pointers but not their contents, so it cannot boot the site with real content or forms. The test suite still runs: a test that needs content checks for the `content/` submodule, and a test that reads the real form configurations checks for the `resources/forms/` submodule, and each is skipped, not failed, when its submodule is absent (`tests/Support/ContentSubmodule.php`, `tests/Support/FormsSubmodule.php`). Expect those tests to report as skipped in such a clone.

# Statamic Peak Starter Kit Information

See below for information about what was and wasn't added to the repository from the Statamic Peak starter kit.

* [Files Added and Modified](README_Statamic-Peak_added.md)
* [Files and Modifications Withheld](README_Statamic-Peak_withheld.md)
* [Alternate Changes Made](README_Statamic-Peak_alternate.md)

## NGINX config

Add the following to your NGINX config __inside the server block__ enable static resource caching:
```
expires $expires;
```

And this __outside the server block__:
```
map $sent_http_content_type $expires {
    default    off;
    text/css    max;
    ~image/    max;
    application/javascript    max;
    application/octet-stream    max;
}
```

## Deploy script Ploi

```bash
if [[ {COMMIT_MESSAGE} =~ "[BOT]" ]] && [[ {DEPLOYMENT_SOURCE} == "quick-deploy" ]]; then
    echo "Automatically committed on production. Nothing to deploy."
    {DO_NOT_NOTIFY}
    exit 0
fi

cd {SITE_DIRECTORY}
git pull origin {BRANCH}
git submodule update --init --recursive
{SITE_COMPOSER} install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# `.npmrc` sets engine-strict against `engines.npm >= 11.10.0` (UAMS-Web/uams-statamic-archive#1826), and Node 22
# bundles npm 10.9.x — so `npm ci` fails with EBADENGINE unless npm is pinned first.
npm install -g npm@^11.10.0
npm ci
npm run build

{RELOAD_PHP_FPM}

{SITE_PHP} artisan cache:clear
{SITE_PHP} artisan config:cache
{SITE_PHP} artisan route:cache
{SITE_PHP} artisan statamic:stache:warm
{SITE_PHP} artisan queue:restart
{SITE_PHP} artisan statamic:search:update --all
{SITE_PHP} artisan statamic:static:clear
{SITE_PHP} artisan statamic:static:warm --queue

echo "Application deployed!"
```

## Deploy script Forge

```bash
if [[ $FORGE_QUICK_DEPLOY == 1 ]]; then
    if [[ $FORGE_DEPLOY_MESSAGE =~ "[BOT]" ]]; then
        echo "Automatically committed on production. Nothing to deploy."
        exit 0
    fi
fi

cd $FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH
git submodule update --init --recursive
$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# `.npmrc` sets engine-strict against `engines.npm >= 11.10.0` (UAMS-Web/uams-statamic-archive#1826), and Node 22
# bundles npm 10.9.x — so `npm ci` fails with EBADENGINE unless npm is pinned first.
npm install -g npm@^11.10.0
npm ci
npm run build

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

$FORGE_PHP artisan cache:clear
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan statamic:stache:warm
$FORGE_PHP artisan queue:restart
$FORGE_PHP artisan statamic:search:update --all
$FORGE_PHP artisan statamic:static:clear
$FORGE_PHP artisan statamic:static:warm --queue
```

## Working copies of `people` entries (revisions)

A change to a published `people` entry is saved as a working copy and goes live only when an administrator publishes it (UAMS-Web/uams-statamic-archive#3127, UAMS-Web/uams-statamic-archive#3184). That needs `STATAMIC_REVISIONS_ENABLED=true` in the environment's `.env`; `people` is the only collection with `revisions: true`, so no other collection changes. With the setting off, a change to a published `people` entry is saved to the entry directly, as before.

Working copies and revision history are stored under `storage/statamic/revisions` (`config/statamic/revisions.php`), which is git-ignored and outside the content repository. So:

- **They survive a deploy only if `storage/` does.** Both deploy scripts above pull into the same directory, so `storage/` stays in place. A deploy that builds each release in a fresh directory (zero-downtime deployment) must share `storage/statamic/revisions` across releases, or every pending change is lost on the next deploy.
- **They are not in the content repository's history.** Include `storage/statamic/revisions` in the server's backups; nothing else holds a change that is waiting to be published.

## Continuous integration

Remote CI runs on every pull request and on every push to `main`. A ruleset on `main` requires three checks: `CI`, which fails unless every job in [`.github/workflows/ci.yml`](.github/workflows/ci.yml) succeeded, plus `Attribution footer` and `Closing keywords`. All three run on Linux only until UAMS-Web/uams-statamic#348 adds Windows and macOS. [`docs/runbooks/branch-protection.md`](docs/runbooks/branch-protection.md) records what `main` enforces.

## License

This repository is released under the [MIT License](LICENSE), copyright University of Arkansas for Medical Sciences.

The license does not cover the following paths, which hold third-party material under their owners' own terms:

- `patches/`: changes to third-party Composer packages, each under its package's own license.
- `resources/fonts/firago/`: the FiraGO typeface, under the SIL Open Font License 1.1 ([`OFL.txt`](resources/fonts/firago/OFL.txt)).
- `resources/svg/`: icon artwork.
- `resources/raster_images/`: photography and other raster images.
- `resources/js/uams_2020/source/genesis/`: a script from the Genesis framework by StudioPress.
- `lang/vendor/statamic/`: translations published from Statamic.
- `app/Services/EnumGeneration/Schema/schemaorg-current-https.jsonld`: the Schema.org vocabulary, under the terms Schema.org publishes it under.

# General Statamic Information

<p align="center"><img src="https://statamic.com/assets/branding/Statamic-Logo+Wordmark-Rad.svg" width="400" alt="Statamic Logo" /></p>

## About Statamic

Statamic is the flat-first, Laravel + Git powered CMS designed for building beautiful, easy to manage websites.

> [!NOTE]
> This repository contains the code for a fresh Statamic project that is installed via the Statamic CLI tool.
>
> The code for the Statamic Composer package itself can be found at the [Statamic core package repository][cms-repo].


## Learning Statamic

Statamic has extensive [documentation][docs]. We dedicate a significant amount of time and energy every day to improving them, so if something is unclear, feel free to open issues for anything you find confusing or incomplete. We are happy to consider anything you feel will make the docs and CMS better.

## Support

We provide official developer support on [Statamic Pro](https://statamic.com/pricing) projects. Community-driven support is available on the [forum](https://statamic.com/forum) and in [Discord][discord].


## Contributing

Thank you for considering contributing to Statamic! We simply ask that you review the [contribution guide][contribution] before you open issues or send pull requests.


## Code of Conduct

In order to ensure that the Statamic community is welcoming to all and generally a rad place to belong, please review and abide by the [Code of Conduct](https://github.com/statamic/cms/wiki/Code-of-Conduct).


## Important Links

- [Statamic Main Site](https://statamic.com)
- [Statamic Documentation][docs]
- [Statamic Core Package Repo][cms-repo]
- [Statamic Migrator](https://github.com/statamic/migrator)
- [Statamic Discord][discord]

[docs]: https://statamic.dev/
[discord]: https://statamic.com/discord
[contribution]: https://github.com/statamic/cms/blob/master/CONTRIBUTING.md
[cms-repo]: https://github.com/statamic/cms
