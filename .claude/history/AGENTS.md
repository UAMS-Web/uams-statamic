# History: Agent instructions for uams-statamic

The incident history behind [`AGENTS.md`](../../AGENTS.md): the measurements, observations and worked examples that justify its instructions. It was moved here from the auto-loaded file by UAMS-Web/uams-statamic-archive#2693, and each section matches the heading it came from. The wording and dates are unchanged.

## Running `boost:update` (project rule)

Bare, it rewrites the harness skill trees as a side effect of a guideline refresh — replacing `pest-testing` and `tailwindcss-development` with `.ai/skills/` **symlinks** (the UAMS-Web/uams-statamic-archive#2170 regression a default Windows clone turns into a vanished skill, gated by [`tests/Feature/Config/SkillMirrorsTest.php`](../../tests/Feature/Config/SkillMirrorsTest.php)) and adding the declined `infer-conventions` skill — all landing silently in whatever diff is open (UAMS-Web/uams-statamic-archive#2168 reverted it twice).

## Supported development platforms (project rule)

An unqualified claim reads as portable, which is how a `bound()` idiom reasoned from "macOS ships no `timeout(1)`" shipped as general advice and turned out to be a silent no-op under Git Bash, and how [`public/favicon/README.md`](../../public/favicon/README.md) stated a macOS-Herd nginx quirk as though Windows Herd shared it.

## Pre-merge check (project rule)

The reason it has to be written down is measured rather than assumed: `main` here is protected, force-pushes and deletions are refused, and **nothing requires a pull request, a review, or a passing chain**, so the corpus observed voluntarily is the entire gate.

## Claude Code cloud sessions (project rule)

The limit is structural rather than a preference: `api.github.com` is unreachable for generic HTTP clients there, so Composer needs `preferred-install=source`, and `phpstan/phpstan` publishes dist-only with no source path — leaving `--no-dev` as the only install that completes, which drops Pest, PHPUnit, Pint, and Larastan.
