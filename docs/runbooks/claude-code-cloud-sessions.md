# Runbook: Claude Code cloud sessions

Cloud sessions run this repo on an Anthropic-managed Ubuntu 24.04 VM instead of your machine. They work well for the **issue and docs** half of this repo and poorly for **app work**, because a full PHP bootstrap needs a five-step chain that has to be re-established every session — and one link in it can never be fixed here (see [`phpstan/phpstan` is dist-only](#phpstanphpstan-is-dist-only-upstream) below).

Everything here was measured on **2026-07-31** across three probe sessions. Where something is inferred rather than run, it says so.

## What the stock image gives you

| | |
| --- | --- |
| OS / privileges | Ubuntu 24.04, running as **root** — `apt` needs no `sudo` |
| PHP | 8.4.19, Debian build, OPcache loaded. Only **`bcmath`** is missing from the set [`ci.yml`](../../.github/workflows/ci.yml) pins |
| Node | v22.22.2, npm 10.9.7 — **below the `engines.npm >= 11.10.0` floor**, so the setup script must upgrade npm before `npm ci` (UAMS-Web/uams-statamic-archive#1826) |
| Resources | 4 vCPU, 15 GiB RAM, ~30 GB free disk |
| Absent | `gh`, `vendor/`, `node_modules/`, `.env`, every submodule |

`composer` is present and its platform check passes without `bcmath` — no locked package hard-requires it, so install it only for parity with CI.

## Environment configuration

Network access stays **Trusted** and environment variables stay **empty** — there is no secrets store, and the dialog states values are readable by anyone using the environment. The setup script provisions the VM; it runs as root before Claude launches, and its result is snapshotted, so anything it installs is present at the start of later sessions:

```bash
#!/bin/bash
set -x
apt-get update -y || true
apt-get install -y gh php8.4-imagick || true
cd /home/user/uams-statamic 2>/dev/null || cd "$(ls -d /home/*/uams-statamic 2>/dev/null | head -1)" || exit 0
npm install -g npm@^11.10.0 || true
npm ci || echo 'WARNING: npm ci failed - node_modules is absent, so npm run spell will not work'
exit 0
```

**The npm pin is load-bearing, not hygiene.** `.npmrc` sets `engine-strict=true` against `engines.npm >= 11.10.0` (UAMS-Web/uams-statamic-archive#1826), and the VM ships npm 10.9.7 — so without the upgrade `npm ci` exits 1 with `EBADENGINE`. Under the old bare `npm ci || true` that failure was swallowed and the session simply had no `node_modules`, with nothing on screen to say why; hence the explicit warning rather than `|| true`.

`gh` installs from Ubuntu's own package (2.45.0). Do **not** use GitHub's install script — `cli.github.com` is not on the Trusted allowlist. Note that `deb.sury.org` and the main Ubuntu archive resolve, but `ppa.launchpadcontent.net` is refused, so keep the `|| true`.

`CLAUDE_PROJECT_DIR` is **unset** in the session environment, which is why the script locates the checkout by path rather than by variable.

## Two proxies, and why the difference matters

Almost every confusing failure below comes from conflating these:

- **The network allowlist** — what the environment's access level permits. Under **Trusted** this already includes `github.com`, `api.github.com`, `codeload.github.com`, `packagist.org`, and `registry.npmjs.org`.
- **The GitHub proxy** — a separate layer that holds the real credential outside the VM and scopes GitHub API requests to *repositories attached to the session*. It operates **independently of the access level**.

So a 403 from `api.github.com` is almost never an egress problem, and **raising the network access level will not fix it**. It means the repository is not attached. Git traffic is rewritten to a local proxy remote (`http://local_proxy@127.0.0.1:<port>/git/…`) via `insteadOf`; the port is session-specific.

`GH_TOKEN` and `GITHUB_TOKEN` read as the literal string `proxy-injected` unless you set one yourself. `gh` works with that; a script that reads the variable directly gets the placeholder.

## Attaching repositories

Session scope starts at the one repo the session was created from. Everything else (the private Composer package and all five submodules, including `uams-statamic-forms` at `resources/forms` since UAMS-Web/uams-statamic-archive#2818) needs `mcp__Claude_Code_Remote__add_repo`:

```json
{"owner": "UAMS-Web", "repo": "wordpress-importer", "access": "read"}
```

- `owner` and `repo` are **separate fields**, not an `owner/repo` string.
- Attachment is **session-scoped**. The response says so explicitly (`Session currently has N repo(s)`) and says nothing about persisting. A new session starts back at one repo. No setup script or `SessionStart` hook can pre-attach, because both run before Claude can call the tool.
- Use `access:"read"`. `access:"push"` was denied outright by the auto-mode classifier on one repo while succeeding on another.
- Attachment fixes **git transport as well as the REST API** — proven with a clean before/after on `git ls-remote` against the same URL.
- **One owner tier per session.** `add_repo` refuses a repo from a different owner than the session's existing sources:

  ```
  add_repo: cross-tier adds are not supported in v1: requested "phpstan/phpstan" but
  session already has repos from owner(s) [uams-web]. Start a new session with the
  requested repo as the initial source, or add a repo from the same owner as the
  existing sources
  ```

  The error's first suggestion is a dead end for our purposes: seeding a session with a non-`UAMS-Web` repo makes every `UAMS-Web` add cross-tier by the same rule. Practically, a cloud session reaches `UAMS-Web` repos or third-party repos, never both.

**The session credential must be able to see the repo in the first place.** Before the GitHub App's installation was extended to the sibling repos, `add_repo` on `uams-statamic-content` failed with `you don't have access to …` — a credential wall, not a scope wall, and a different message from the 403. Running `/web-setup` locally switches sessions to your account's OAuth token, which carries your full visibility; `gh api user --jq .login` returning a login (rather than 403) tells you that token is what the session is using.

## Failure signatures

| Symptom | Cause |
| --- | --- |
| `could not read Password for 'http://local_proxy@127.0.0.1:…'` | Repo not attached. Call `add_repo`. |
| `could not read Username for 'https://github.com'` | The proxy was never reached — an `insteadOf` rewrite terminated at real `github.com`. See below. |
| `Could not authenticate against github.com` (`AuthHelper.php` line 132) | **Not a credential problem.** Composer's fallback message after an HTTP 403 from the GitHub proxy. |
| `curl error 28 … Proxy CONNECT aborted due to timeout` | A `dist` download against `api.github.com`. Direct `curl` 403s instantly, but under CONNECT the same request *hangs* until the proxy gives up — a latency trap, not a fast failure. |
| `Numeric-ID repository paths (repositories/{id}/...) are not supported` | `gh api --paginate`. See [pagination](#pagination-truncates-at-page-1). |
| `Failed opening required '…/vendor/autoload.php'` | Composer never completed; every `artisan` command fails this way downstream. |

### `insteadOf` applies once, longest-match

Git resolves an `insteadOf` rewrite in a **single hop**. The proxy ships a rule mapping `https://github.com/` to the local proxy, so an SSH-form URL that maps to `https://github.com/` **stops there** and never reaches the proxy — which is exactly why the symptom is `could not read Username for 'https://github.com'` rather than a proxy error. Map the SSH forms straight to the proxy instead.

**`insteadOf` is multi-valued: always use `--add`.** A bare `git config --global url.<proxy>.insteadOf …` *replaces* the existing value and silently destroys the proxy's own `https://github.com/` mapping, breaking every HTTPS clone. An unguarded `--add`, meanwhile, appends a duplicate on every invocation — so a hook must be idempotent.

The three rules to add, mapping each URL form to the proxy in one hop:

```
url.<proxy>.insteadof git@github.com:
url.<proxy>.insteadof https://github.com/
url.<proxy>.insteadof ssh://git@github.com/
```

Two more will appear in `git config --get-regexp '^url\.'` mapping `git@github.com:` and `ssh://git@github.com/` to `https://github.com/`. Leave them alone — `--show-origin` reports them as `command line:`, injected per session through `GIT_CONFIG_COUNT` / `GIT_CONFIG_KEY_n`, so a hook writing `~/.gitconfig` **cannot** remove them and `--unset-all` only clears the global copy. The double mapping is harmless because git keeps the longest match and replaces only on a strictly longer one: on an equal-length tie the first-parsed wins, and files parse before the command line, so the file-based rule takes precedence. Correct, but precedence-fragile — it only holds while the rule lives in a file.

## `phpstan/phpstan` is dist-only upstream

Because `api.github.com` is unreachable for generic HTTP clients, the only workable Composer strategy is `composer config --global preferred-install source`, which routes installs over the permitted git transport. That covers 231 of 232 locked packages. It cannot cover `phpstan/phpstan`, which has **`"source": null`** in [`composer.lock`](../../composer.lock) — there is no git path for it to take.

This originates upstream and is not a lockfile defect. Packagist's p2 metadata for `phpstan/phpstan` 2.2.6 carries a `dist` block and no `source` block at all; Composer recorded what it was given. Across all 432 published versions, 393 carry a source block and **39 do not — and the 39 are the most recent 38 contiguous releases (2.1.30 → 2.2.7)**, plus four on the 1.12.x line.

The practical consequences:

**This is deliberate upstream behavior, and phpstan documented it.** The `phpstan/phpstan` repository is **~7.15 GB** — it commits a fresh `phpstan.phar` on every release — so a `--prefer-source` install meant cloning multiple gigabytes. [phpstan/phpstan#10921](https://github.com/phpstan/phpstan/issues/10921), reporting that the package is almost impossible to install because of its size, is the motivation. Their first fix, visible in [phpstan/phpstan#13617](https://github.com/phpstan/phpstan/issues/13617), redirected source installs to a small proxy repository, `phpstan/phpstan-phar-composer-source`, which just downloads the phar, warning:

```
Normally it'd lead to downloading multiple GBs but we made precautions to avoid that.
```

The maintainer then said in that thread, on the same day 2.1.30 published, that "the situation might change with the next release which changes the approach to this" — and the approach it changed to was **omitting the `source` block entirely**, leaving Composer no source option at all. The brief interleaving on the 1.12 line (1.12.30 has no source; 1.12.31, six minutes later, does) is that experimentation in flight.

The practical consequences:

- **Version changes do not help.** Every 2.x release from 2.1.30 onward is dist-only, including the newest. Pinning backward to buy a source block would mean 2.1.29.
- **Nothing to file upstream.** It is a considered fix to a real problem, and it only bites where git works and `api.github.com` does not — a property of this proxy, not of phpstan's packaging.
- **`add_repo` cannot rescue it.** Attaching the public `phpstan/phpstan` repo would resolve the 403, but `add_repo` refuses **cross-owner-tier adds** (see above), so a session already holding `uams-web` repos cannot attach it. Verified 2026-07-31.
- **The strategy is fragile by construction.** Any package that switches to dist-only publishing later breaks the cloud bootstrap the same way, silently, whenever its next release lands. Today it is 1 of 232; nothing holds it there.

**The consequence is structural: a cloud session cannot produce a complete `vendor/` for this repo.** `composer install --no-dev` succeeds and boots the app, but drops all 82 dev packages — including `pestphp/pest`, `phpunit/phpunit`, `laravel/pint`, and `larastan/larastan`. There is no test runner, no PHPStan and no Larastan in a cloud session, and no supported way to install every dev package except one. The formatting check is the one exception — see "Running the formatting check anyway" below.

Do **not** hand-seed a zip into Composer's dist cache to get past this. It is indistinguishable from routing around a policy denial, the auto-mode classifier blocks it, and `/root/.ccr/README.md` is explicit that organization policy denials are to be reported rather than retried.

## Running the formatting check anyway

The `vendor/` constraint above still holds — no Pest, no PHPUnit, no PHPStan, no Larastan — but **Pint is available**, and a cloud-authored PR has no excuse for arriving red on formatting (UAMS-Web/uams-statamic-archive#1880).

Pint is not really a Composer problem. It declares **no package dependencies** (only `php ^8.2` plus `ext-json`, `ext-mbstring`, `ext-tokenizer`, `ext-xml`) and ships a prebuilt ~22 MB phar at `builds/pint`; `vendor/bin/pint` is only a Composer-generated proxy to it. Composer is how we happen to deliver Pint, not something Pint needs. So it can be fetched over the same git transport that already carries 231 of the 232 packages:

```bash
node scripts/fetch-pint.mjs
php .tools/pint/builds/pint --test --config pint.json
```

`--test` exits **1** when a file needs formatting and **0** when clean, so it gates. Drop `--test` to apply the fixes.

Three things about [`scripts/fetch-pint.mjs`](../../scripts/fetch-pint.mjs) worth knowing:

- **The reference comes from `composer.lock`, never a constant in the script.** A pinned SHA in two places drifts silently, and the failure — formatting against a Pint the project does not use — is exactly the quiet wrongness the script exists to prevent.
- **It fetches ONE commit, not a shallow clone.** `clone --depth 1` takes the default branch tip, which is a *different* Pint from the locked one. Measured 2026-08-18: the tip gave 1.30.5 against a locked 1.30.3. They agreed on this repo that day, which is what would have made the drift invisible until it wasn't.
- **`.tools/` is git-ignored**, so nothing it writes can be staged.

**This does not extend to the other tools.** Pest, PHPUnit and Larastan all have real dependency graphs and need a resolved `vendor/`; PHPStan is the dist-only package that blocks the install in the first place. Pint is tractable *because* it is a dependency-free phar, and that property is what the mechanism rests on — not a general workaround.

**Verify the fetch in the session, not from memory.** Reachability of `github.com/laravel/pint` over git transport was verified locally, and is inferred for a cloud session from the fact that `preferred-install=source` works there. If the fetch fails, say so on the PR rather than pushing unformatted — the fallback is that whoever validates runs Pint.

## Pagination truncates at page 1

`gh api --paginate` cannot follow GitHub's `Link: rel="next"` header, because GitHub renders it in numeric-ID form (`https://api.github.com/repositories/<id>/issues?page=2`) and the proxy refuses that shape. `gh` writes page 1, then exits 1.

It is loud on stderr and in `$?` — but **silent through a pipe**: `gh api --paginate … | wc -l` discards the non-zero exit, so a truncated count reads as complete. Set `pipefail`, or walk pages explicitly with `&page=N`, which keeps every URL in the accepted `repos/{owner}/{repo}/…` form. Tracked in UAMS-Web/uams-statamic-archive#1823.

## Full app bootstrap (only if you need it)

Per session, in order — steps 1 and 2 cannot be pre-baked:

1. `add_repo` for `UAMS-Web/wordpress-importer`, `UAMS-Web/uams-statamic-content`, and `phpstan/phpstan`.
2. Add the SSH-form `insteadOf` rules with `--add` (derive the proxy URL from the existing config; the port changes per session).
3. `git submodule update --init --depth 1 content` — collection *configs* live here, so without it `Collection::findByHandle()` returns `null` and dozens of tests die on `entryClass() on null`.
4. `cp .env.example .env`. **This requires the `imagick` extension**, which the stock image lacks — without it every `artisan` command aborts at boot with `Imagick PHP extension must be installed to use this driver`. Install it (`apt-get install -y php8.4-imagick`, already in the setup script above) rather than switching the driver to `gd`: `App\Support\Imaging\SvgRasterizer` hard-codes the Imagick driver for brand-logo SVG rasterization independently of that config, so `gd` only moves the failure. `composer.json` declares `ext-imagick` (UAMS-Web/uams-statamic-archive#1830), so on an image without it the *install* now fails its platform check by name rather than deferring to this boot-time fatal — the setup script above provisions it, so the declared requirement is satisfied before step 5.
5. `composer config --global preferred-install source`, then `composer install --no-scripts --prefer-source`, `php artisan key:generate`, `composer run-script post-autoload-dump`.
5. `DB_DATABASE=:memory: APP_ENV=testing php artisan test:seed-stache` before any test run.

`php artisan tinker --execute 'echo Statamic\Facades\Collection::all()->count();'` returning non-zero is the success signal — it reported **19** on 2026-07-31 under `--no-dev`, with `clinical_*`, `calculators`, and `facilities` among the resolved handles. `vendor/uams-web/aether-wordpress-importer` installs correctly, so the private-package path works end to end.

**What a cloud session can and cannot do with the app:** it boots, resolves collections and entries, and renders — enough to inspect behavior or reproduce a report. It cannot run a test, Larastan, or Rector, because `--no-dev` is the only install that completes — but it *can* run Pint; see "Running the formatting check anyway" above.

## What does NOT help (measured — don't bother)

- **Raising network access to Full or Custom.** `api.github.com` and `codeload.github.com` are already on the Trusted allowlist; the 403 is the GitHub proxy, which ignores the access level.
- **`composer config use-github-api false`.** Fails faster (12.7s vs 14m28s) but not better — dist errors immediately and the source fallback still cannot authenticate.
- **`--prefer-dist`, the default.** Every dist URL is an `api.github.com` zipball, so all 230 packages fail their dist download and fall back to a git mirror anyway — after a per-package CONNECT timeout. That is where 14m28s of a 14.5-minute run went, producing nothing.
- ~~**Upgrading npm to restore `min-release-age`.**~~ **Superseded by UAMS-Web/uams-statamic-archive#1826** — it was fixed in both, as this bullet asked. CI now pins `npm@^11.10.0` in every job that runs `npm ci`, and the setup script above does the same, so cloud is no longer stricter or laxer than CI. The upgrade is now *required* in a cloud session rather than merely unhelpful: `engine-strict=true` makes `npm ci` refuse the VM's npm 10.9.7 outright.

## Measurements (2026-07-31)

| Step | Wall-clock |
| --- | --- |
| `git submodule update --init --depth 1 content` | 4s |
| `composer install --no-dev --prefer-source`, cold | 54s (150 prod packages; `vendor/` is **1.4 GB**, since source installs keep a git clone per package) |
| `composer install --prefer-source`, cold cache | 393s (231/232 packages) |
| `composer install --prefer-source`, warm VCS cache | 3.6s |
| `composer install --prefer-dist` (the trap) | 14m28s, then hard failure |
| `npm install -g npm@^11.10.0` | ~8s (required before `npm ci`; see UAMS-Web/uams-statamic-archive#1826) |
| `npm ci` | 13s |
| `npm run spell` | 19s, 2337 files |

The full Pest suite was never run — at 4 vCPUs against a suite that takes ~20 minutes on 12 workers, the gate stays [`local-ci`](../../.claude/skills/local-ci/SKILL.md) on a real machine.

## References

- Issues surfaced by this work: UAMS-Web/uams-statamic-archive#1823 (pagination), UAMS-Web/uams-statamic-archive#1824 (`afk-waves` forest null-prune), UAMS-Web/uams-statamic-archive#1825 (project-3/4 exclusion snapshot), UAMS-Web/uams-statamic-archive#1826 (`min-release-age`)
- [`.claude/rules/github-api-budget.md`](../../.claude/rules/github-api-budget.md) — REST vs GraphQL. Cloud sessions report a 15,000/hr REST limit, but GraphQL is **structurally unavailable**: the session credential serves only a pinned set of PR-review operations, so `gh project` and all of Projects V2 are refused. That makes UAMS-Web/uams-statamic-archive#1825 the workaround rather than an optimization.
- [`.claude/rules/worktrees.md`](../../.claude/rules/worktrees.md) — the local equivalent of this bootstrap, including the same `content/` submodule trap.
