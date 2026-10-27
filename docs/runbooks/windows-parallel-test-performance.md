# Runbook: Windows parallel test-suite performance (Defender exclusion)

On Windows, the parallel ([`paratest`](../../scripts/local-ci.mjs)) test suite runs materially slower than on Linux. A controlled A/B/A experiment (2026-07-14, UAMS-Web/uams-statamic-archive#1388) attributed **~24%** of that cost to **Windows Defender real-time scanning** of the repo tree. This runbook covers the one durable, measured mitigation you can apply on a developer machine: a single recursive Defender path exclusion.

The remaining ~75% is **inherent** per-test cost — a code-side concern a Defender exclusion does not touch. UAMS-Web/uams-statamic-archive#1537 attributed it (see [The inherent cost, attributed](#the-inherent-cost-attributed-2026-07-16-1537) below): the two dominant buckets are the per-worker Stache warm (50%) and the per-test app boot (45%). The warm is addressed by the shared seed (UAMS-Web/uams-statamic-archive#1581); the boot by a per-process sites cache (UAMS-Web/uams-statamic-archive#1582).

## Symptoms

- `php artisan test`, `vendor/bin/pest --parallel`, or the local-CI `phpunit` job runs far slower than the few minutes it should take — tens of minutes on the full suite, on an otherwise-idle machine.
- All `php.exe` worker processes are CPU-busy, yet throughput is low (~4 CPU-seconds per test on the feature suite).
- The slowdown is **Windows-only** — the same suite on the Linux CI image is fast.

## Cause

Windows Defender's real-time protection scans every file the workers touch as they run: the per-worker tokenized cache writes (`storage/framework/cache/data/<TEST_TOKEN>-<pid>/…`), the `stache/keys/*` files, the `content/` submodule reads during Stache warm, and the `vendor/` autoload. With 12 workers churning the filesystem in parallel, that scan overhead compounds.

This is **not** the transient `Permission denied` cache-write flake (that is a different failure, fixed in UAMS-Web/uams-statamic-archive#1383 via [`app/Support/Cache/RetryingFileStore.php`](../../app/Support/Cache/RetryingFileStore.php)) — it is steady scan latency on every file access.

## Fix — the measured mitigation

Add **one recursive path exclusion** for the Herd projects root that contains this repo, in an **elevated** PowerShell:

```powershell
# `C:\Users\<you>\Herd` is the Herd root that holds the primary checkout,
# every worktree under it, AND the local-CI runner's `../uams-statamic-ci` worktree.
Add-MpPreference -ExclusionPath "$env:USERPROFILE\Herd"

# verify
Get-MpPreference | Select-Object -ExpandProperty ExclusionPath   # → C:\Users\<you>\Herd
```

One recursive path is all that is needed. It covers, in a single entry:

- the primary checkout (`…\Herd\uams-statamic`),
- every session worktree (`…\Herd\uams-statamic\.claude\worktrees\*`), and
- the local-CI runner's default worktree (`…\Herd\uams-statamic-ci` — see [`scripts/local-ci.mjs`](../../scripts/local-ci.mjs) `--worktree-path`, default `../uams-statamic-ci`).

**Keep the local-CI worktree under the excluded root.** The default (`../uams-statamic-ci`) already sits under `…\Herd`, so the actual gating runs benefit. If you point `--worktree-path` elsewhere, add that path to the exclusion too, or the ~24% is lost on the runs that matter.

## What does NOT help (measured — don't bother)

- **Sub-path exclusions** (`…\Herd\uams-statamic-ci`, `…\Herd\uams-statamic`) — **no-ops.** Defender path exclusions are recursive, so these are subsumed by the `…\Herd` root exclusion.
- **The `php.exe` process exclusion** (`Add-MpPreference -ExclusionProcess php.exe`) — **no measurable effect** in the UAMS-Web/uams-statamic-archive#1388 experiment. Do not add it.

## The measurement (2026-07-14, `tests/Feature/Services --parallel --processes=12`, reported `Duration`)

A drift-controlled A/B/A on an idle 12-core machine:

| Phase | Defender | Runs (s) | Mean |
| --- | --- | --- | --- |
| ON baseline | `…\Herd` excluded | 381, 419 | ~400s |
| OFF | no exclusion (tree scanned) | ~~607~~\*, 453, 544, 522 | ~506s |
| ON-recheck | `…\Herd` re-excluded | 436, 423 | ~429s |

\* First OFF run — one-time Defender scan of the newly-exposed tree; excluded from the mean.

The excluded and scanned bands **do not overlap** (slowest excluded run 436s < fastest scanned run 453s), measured at two separate times, so the ~24% is a real effect and not session drift.

## The inherent cost, attributed (2026-07-16, UAMS-Web/uams-statamic-archive#1537)

Defender is only ~24%. The larger, code-side cost was attributed on the same `tests/Feature/Services --parallel --processes=12` probe (554 tests), bucketing each worker's wall into boot / stache-warm / churn / test-body (drift-validated: boot-bench 330s START / 329s END). Absolute wall drifts with machine state between sessions (this session's baseline was 507s), but the bucket proportions are stable:

| bucket | % of parallel CPU | solo | under 12-way contention |
| --- | ---: | ---: | ---: |
| stache-warm | 50.1% | 21s/proc | ~168s/proc (8×) |
| app-boot | 45.2% | 1.5s/test | 3.4s/test (2.3×) |
| per-test churn (isolated) | 1.4% | — | — |
| test-body | 3.3% | — | — |

warm + boot = 95% of the cost. `stache-warm` is I/O-bound — 12 workers reading the `content/` submodule at once saturate the disk — and is a *fixed* startup burst paid once per worker (`paratest` does not recycle workers at the default `max-batch-size=0`). Its "the pre-run warm doesn't help the parallel job" behavior is a path mismatch: the pre-warm writes the default `storage/framework/cache/data/`, workers read a per-worker `data/<TEST_TOKEN>/` ([`config/cache.php`](../../config/cache.php), UAMS-Web/uams-statamic-archive#1136). `app-boot` is paid per test method, so it dominates the *full-suite* wall while warm dominates the fast *per-probe* cycle.

**The warm fix — shared seed (UAMS-Web/uams-statamic-archive#1581).** [`test:seed-stache`](../../app/Console/Commands/SeedTestStache.php) warms once, before the suite, and snapshots the cache; each worker copies the snapshot into its own per-worker dir instead of re-warming ([`tests/CreatesApplication.php`](../../tests/CreatesApplication.php)). Wired into the `phpunit` job in `ci.yml` and [`scripts/local-ci.mjs`](../../scripts/local-ci.mjs), mirroring the `route:cache` pre-step. Cuts the probe 507s → 345s (−32%). `app-boot` is addressed by the per-process sites cache below (UAMS-Web/uams-statamic-archive#1582); per-test churn and body are inherent and not worth addressing.

**The boot fix — per-process sites cache (UAMS-Web/uams-statamic-archive#1582).** Most of the per-test `app-boot` is one place: Statamic re-hydrates the 255-site `resources/sites.yaml` on every fresh per-test app (`Stache\ServiceProvider::boot()` resolves `Site::all()`, whose first resolution parses the YAML and Antlers-resolves every site URL) — ~280ms/boot, re-paid per test method. [`Tests\Support\CachedSites`](../../tests/Support/CachedSites.php) hydrates it once per process and injects the resolved collection on every later boot; it is rebound onto `Sites::class` in a [`tests/CreatesApplication.php`](../../tests/CreatesApplication.php) `booting()` callback (which fires after Statamic binds the singleton and before the Stache boot resolves it). Only the hydrated *collection* is cached, not the `Sites` instance, so per-request state (`$current`) stays fresh per app and isolation is preserved — the sites are read-only global config, identical for every test (`config('app.url')` is fixed per process, so every app resolves byte-identical URLs). Drift-controlled interleaved measurement: ~236ms saved per boot (~33% of the boot); the hydration is deterministically 1 per process, guarded by `TestBootstrapCostTest`. Unlike the warm seed there is no pre-run artifact — the cache is purely in-process, so it needs no `ci.yml` / `local-ci.mjs` step.

## Caveats

- **Per-machine, does not travel via git.** This is a local Defender setting; each developer must apply it on their own Windows machine. It is not something the repo can enforce.
- **Irrelevant to CI.** GitHub Actions runs on Linux (no Defender), and remote CI is disabled anyway — local CI on Windows is the gate this helps.
- **An AV-coverage tradeoff.** You are excluding an entire dev tree from real-time scanning. That is a security posture decision for your own machine.
- **Only ~24%.** The majority of the slowness is inherent (UAMS-Web/uams-statamic-archive#1537); do not expect this exclusion alone to make the suite fast.

## References

- UAMS-Web/uams-statamic-archive#1388 — the Defender decision this runbook records (spun off from UAMS-Web/uams-statamic-archive#1381).
- UAMS-Web/uams-statamic-archive#1381 / UAMS-Web/uams-statamic-archive#1383 — the Windows cache-write flake and its `RetryingFileStore` fix (a different symptom).
- UAMS-Web/uams-statamic-archive#1521 — the investigation that exonerated UAMS-Web/uams-statamic-archive#1504 and reproduced this slowdown on an idle machine.
- UAMS-Web/uams-statamic-archive#1537 — the inherent (non-Defender) ~75% parallel-suite cost, attributed above.
- UAMS-Web/uams-statamic-archive#1581 — the shared-seed Stache fix for the warm bucket; UAMS-Web/uams-statamic-archive#1582 — the per-process sites-hydration cache for the boot bucket.
- [`config/cache.php`](../../config/cache.php) — the per-worker `TEST_TOKEN` cache path.
- [`app/Console/Commands/SeedTestStache.php`](../../app/Console/Commands/SeedTestStache.php) — the `test:seed-stache` seed builder.
- [`tests/Support/CachedSites.php`](../../tests/Support/CachedSites.php) — the per-process sites-hydration cache.
- [`scripts/local-ci.mjs`](../../scripts/local-ci.mjs) — the local-CI runner and its worktree location.
