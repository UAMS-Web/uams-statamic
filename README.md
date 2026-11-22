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
10. Run `npx --no-install puppeteer browsers install chrome-headless-shell`. News release PDFs need this browser, and `npm` does not download it because `.npmrc` sets `ignore-scripts=true`. Without it a published `news_release` saves with no PDF and the reason is only in `storage/logs/laravel.log`. `node scripts/worktree-slots.mjs status` names the browser when it is missing.
11. Go to http://uams-statamic.test/ and click "Generate App Key"

## Vendor patches

The Composer vendor patches come from the `uams-web/patches-*` packages; see `patches/README.md`.

## Asset metadata (custom)

Asset rows live in SQLite and are mirrored to YAML under `content/models/assets/`. See [`docs/assets-metadata.md`](docs/assets-metadata.md) for architecture, sync commands, and deploy notes.

## Documentation

The [wiki](https://github.com/UAMS-Web/uams-statamic/wiki) holds the longer documentation: the [git submodules](https://github.com/UAMS-Web/uams-statamic/wiki/Git-submodules), [deploying](https://github.com/UAMS-Web/uams-statamic/wiki/Deploying), the [web server configuration](https://github.com/UAMS-Web/uams-statamic/wiki/Web-server-configuration), [working copies of `people` entries](https://github.com/UAMS-Web/uams-statamic/wiki/Working-copies-of-people-entries) and [troubleshooting](https://github.com/UAMS-Web/uams-statamic/wiki/Troubleshooting). Its pages are written in [`docs/wiki/`](docs/wiki) and change through pull requests.

## Git Submodules

Content, users, form configurations and assets live in five private git submodules. Clone with `--recurse-submodules`, or run `git submodule update --init --recursive` after cloning. [Git submodules](https://github.com/UAMS-Web/uams-statamic/wiki/Git-submodules) in the wiki lists them and says what a clone without access to them can still do.

## Deploying

The deploy scripts, the NGINX cache settings and what a deploy must keep in `storage/` are in the wiki: [Deploying](https://github.com/UAMS-Web/uams-statamic/wiki/Deploying), [Web server configuration](https://github.com/UAMS-Web/uams-statamic/wiki/Web-server-configuration) and [Working copies of `people` entries](https://github.com/UAMS-Web/uams-statamic/wiki/Working-copies-of-people-entries).

# Statamic Peak Starter Kit Information

See below for information about what was and wasn't added to the repository from the Statamic Peak starter kit.

* [Files Added and Modified](README_Statamic-Peak_added.md)
* [Files and Modifications Withheld](README_Statamic-Peak_withheld.md)
* [Alternate Changes Made](README_Statamic-Peak_alternate.md)

## Continuous integration

Remote CI runs on every pull request and on every push to `main`. A ruleset on `main` requires three checks: `CI`, which fails unless every other job in [`.github/workflows/ci.yml`](.github/workflows/ci.yml) succeeded (the manual-only `submodule-access` aside), plus `Attribution footer` and `Closing keywords`. `CI` runs its jobs on Linux, Windows and macOS; the other two read text and run on Linux. [`docs/runbooks/branch-protection.md`](docs/runbooks/branch-protection.md) records what `main` enforces.

## License

This repository is released under the [MIT License](LICENSE), copyright University of Arkansas for Medical Sciences.

The license does not cover the following paths, which hold third-party material under their owners' own terms:

- `patches/`: changes to third-party Composer packages, each under its package's own license.
- `resources/fonts/firago/`: the FiraGO typeface, under the SIL Open Font License 1.1 ([`OFL.txt`](resources/fonts/firago/OFL.txt)).
- `resources/svg/`: icon artwork.
- `resources/raster_images/`: photography and other raster images.
- `resources/js/uams_2020/source/genesis/`: a script from the Genesis framework by StudioPress.
- `lang/vendor/statamic/`: translations published from Statamic.

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
