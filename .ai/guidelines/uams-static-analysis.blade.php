# Static Analysis

- **And run `composer stan`. `phpstan` is gated by the file *extension*, not by the directory** — [`phpstan.neon.dist`](phpstan.neon.dist) lists `tests` alongside `app`, so a **`tests/`-only diff gates it too** (UAMS-Web/uams-statamic-archive#1344). Scoping it out on "no `app/**` changed" is how PHPStan-red has reached `main` before. The gate table in the [`local-ci`](.claude/skills/local-ci/SKILL.md) skill is the authority on which jobs a diff needs; this line exists because that skill belongs to the validator and a build lane has no occasion to open it.
