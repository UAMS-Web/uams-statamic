The site keeps its Statamic content, user accounts, form configurations and asset files in five git submodules, so each can be versioned and deployed on its own.

## The submodules

| Submodule | Path | Holds |
| --- | --- | --- |
| `UAMS-Web/aether-submodule-content` | `content/` | Statamic collections, taxonomies and globals |
| `UAMS-Web/aether-submodule-users` | `users/` | Statamic user accounts and groups |
| `UAMS-Web/aether-submodule-form-definitions` | `resources/forms/` | Statamic form configurations |
| `UAMS-Web/aether-submodule-assets-public` | `public/assets/` | Public asset containers (images, files) |
| `UAMS-Web/aether-submodule-assets-private` | `app/private/assets/` | Private asset containers (form uploads) |

## Cloning and updating

- **Clone with the submodules:** `git clone --recurse-submodules <repository-url>`
- **Fetch them into a clone made without them:** `git submodule update --init --recursive`
- **Move them to the commits the site repository records:** `git submodule update --init --recursive`. Both [deploy scripts](Deploying.md) run this after `git pull`, so a server stays in step with the site repository.

## A clone without access to the submodules

All five submodules are private repositories ([UAMS-Web/uams-statamic-archive#2654](https://github.com/UAMS-Web/uams-statamic-archive/issues/2654)). A clone made without access to them gets the submodule pointers but not their contents, so it cannot boot the site with real content or forms.

The test suite still runs in such a clone. A test that needs content checks for the `content/` submodule, and a test that reads the real form configurations checks for the `resources/forms/` submodule; each is skipped, not failed, when its submodule is absent ([`tests/Support/ContentSubmodule.php`](https://github.com/UAMS-Web/uams-statamic/blob/main/tests/Support/ContentSubmodule.php), [`tests/Support/FormsSubmodule.php`](https://github.com/UAMS-Web/uams-statamic/blob/main/tests/Support/FormsSubmodule.php)). Expect those tests to report as skipped.
