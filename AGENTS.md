# Agent instructions for uams-statamic

**This is the live repository.** New work, issues and pull requests go here. The history before the snapshot of `UAMS-Web/uams-statamic-archive` at `fc1031537`, and the issues and pull requests filed until then, are in [`UAMS-Web/uams-statamic-archive`](https://github.com/UAMS-Web/uams-statamic-archive); a reference written `UAMS-Web/uams-statamic-archive#N` points there, and the open issues move here (UAMS-Web/uams-statamic-archive#2661, UAMS-Web/uams-statamic-archive#2659).

This file is the single source of agent instructions for this repository (UAMS-Web/uams-statamic-archive#2539). Cursor and Claude Code both read it directly: the repository has no `CLAUDE.md` since UAMS-Web/uams-statamic-archive#2833, and Claude Code reads `AGENTS.md` itself when there is none. Edit instructions here, and do not add a `CLAUDE.md`.

The Laravel Boost block below, between its opening and closing tags, is generated from `.ai/guidelines/`. Edit its source there, not the block. Never write that block's tag name literally anywhere else in this file: Boost finds the block by a pattern that spans from the first occurrence of the opening tag, so a mention above the block makes the next regeneration overwrite everything in between. `boost:update --ignore-skills` rewrites the block in place and leaves the rest of this file alone. Both of Boost's agents write this file (`config/boost.php` points Claude Code's `guidelines_path` here), so a regeneration never creates a `CLAUDE.md`.

<!-- uams-claude-skills:begin -->
## Shared skills and rules

The files listed here are synced from [UAMS-Web/uams-claude-skills](https://github.com/UAMS-Web/uams-claude-skills) (`shared/`) and are edited there, never here; a sync pull request brings changes into this repository. This section is generated; everything outside it belongs to this repository.

Each rule below is a standing instruction for every task in this repository. Its full text is delivered three times: `.claude/rules/<name>.md`, which Claude Code loads in every session; `.cursor/rules/<name>.mdc`, which Cursor always applies; and the skill `.agents/skills/rule-<name>/`, which Codex selects by its description. Before work a rule governs, read its full text. Skills are in `.claude/skills/` and, as the same files, in `.agents/skills/`, and load when their description matches the task.

Profiles for this repository: core, php, pest, laravel, laravel-host, statamic, statamic-app, tests, quality, local-ci, tailwindcss, frontend.

### Contributing

Follow [`CONTRIBUTING.md`](CONTRIBUTING.md) for the issue-to-merge path: a branch from `main` linked to the issue and a pull request to `main`. It also maps each branch to the environment its pull request's test plan is run on. Start each pull request body from [`.github/pull_request_template.md`](.github/pull_request_template.md).

### Rules

- `adversarial-review`: Adversarially verify before it reaches anyone else. Full text: [`.claude/rules/adversarial-review.md`](.claude/rules/adversarial-review.md).
- `american-english-prose`: American English for prose, and a dictionary entry that overrides it needs a stated reason. Full text: [`.claude/rules/american-english-prose.md`](.claude/rules/american-english-prose.md).
- `an-empty-result-is-not-evidence`: A result is not evidence until the instrument has been shown to tell the two answers apart. Full text: [`.claude/rules/an-empty-result-is-not-evidence.md`](.claude/rules/an-empty-result-is-not-evidence.md).
- `characterization-test-inversion`: A characterization test a sibling package will invert names it as `@invertsOn`. Full text: [`.claude/rules/characterization-test-inversion.md`](.claude/rules/characterization-test-inversion.md).
- `closing-a-ticket`: Finish a ticket where it is visible, not just where it builds. Full text: [`.claude/rules/closing-a-ticket.md`](.claude/rules/closing-a-ticket.md).
- `coordination-plumbing-stays-out-of-artifacts`: Keep session-coordination plumbing out of GitHub artifacts and documentation. Full text: [`.claude/rules/coordination-plumbing-stays-out-of-artifacts.md`](.claude/rules/coordination-plumbing-stays-out-of-artifacts.md).
- `cutting-releases`: Cut a release each business day at 10:00 Central when there is unreleased work. Full text: [`.claude/rules/cutting-releases.md`](.claude/rules/cutting-releases.md).
- `design-decision-forks`: Presenting design-decision forks (lead with a compare/contrast). Full text: [`.claude/rules/design-decision-forks.md`](.claude/rules/design-decision-forks.md).
- `filing-defects-across-repos`: File a defect in the repository that owns it, and claim it first when a race is likely. Full text: [`.claude/rules/filing-defects-across-repos.md`](.claude/rules/filing-defects-across-repos.md).
- `github-api-budget`: Spend the REST quota to preserve the GraphQL one. Full text: [`.claude/rules/github-api-budget.md`](.claude/rules/github-api-budget.md).
- `impersonal-voice-in-github-artifacts`: Write GitHub artifacts in an impersonal voice. Full text: [`.claude/rules/impersonal-voice-in-github-artifacts.md`](.claude/rules/impersonal-voice-in-github-artifacts.md).
- `long-running-commands`: Bound every long-running command, and resolve what you kill. Full text: [`.claude/rules/long-running-commands.md`](.claude/rules/long-running-commands.md).
- `measurement-parity`: Mirror the *job*, not the command, before comparing to a baseline. Full text: [`.claude/rules/measurement-parity.md`](.claude/rules/measurement-parity.md).
- `no-em-dashes`: No em dashes in prose, docs, comments, or strings. Full text: [`.claude/rules/no-em-dashes.md`](.claude/rules/no-em-dashes.md).
- `no-emoji-in-durable-records`: No emoji in durable records. Full text: [`.claude/rules/no-emoji-in-durable-records.md`](.claude/rules/no-emoji-in-durable-records.md).
- `pre-merge-check`: Re-verify at the merge, not only at the build. Full text: [`.claude/rules/pre-merge-check.md`](.claude/rules/pre-merge-check.md).
- `reading-exit-status`: Read the result, not the exit code. Full text: [`.claude/rules/reading-exit-status.md`](.claude/rules/reading-exit-status.md).
- `running-boost-update`: Run `boost:update` only as `--ignore-skills`, deliberately, never mid-ticket. Full text: [`.claude/rules/running-boost-update.md`](.claude/rules/running-boost-update.md).
- `supported-platforms`: MacOS and Windows are both supported; name the platform, write paths repo-relative. Full text: [`.claude/rules/supported-platforms.md`](.claude/rules/supported-platforms.md).
- `sync-pr-branch`: Sync every PR branch from `origin/main` before the gating run. Full text: [`.claude/rules/sync-pr-branch.md`](.claude/rules/sync-pr-branch.md).
- `verify-claims-before-a-third-party-post`: Verify every claim three independent ways before a post to a third-party tracker. Full text: [`.claude/rules/verify-claims-before-a-third-party-post.md`](.claude/rules/verify-claims-before-a-third-party-post.md).
- `worktrees`: Work from stable, pre-bootstrapped worktree slots (branch work never happens in the primary checkout). Full text: [`.claude/rules/worktrees.md`](.claude/rules/worktrees.md).

Skills: [`code-quality`](.claude/skills/code-quality/SKILL.md), [`decision-log`](.claude/skills/decision-log/SKILL.md), [`evidence-behind-rules`](.claude/skills/evidence-behind-rules/SKILL.md), [`github-wiki`](.claude/skills/github-wiki/SKILL.md), [`handed-back-label`](.claude/skills/handed-back-label/SKILL.md), [`laravel-best-practices`](.claude/skills/laravel-best-practices/SKILL.md), [`pcov-setup`](.claude/skills/pcov-setup/SKILL.md), [`pest-testing`](.claude/skills/pest-testing/SKILL.md), [`php-coding-standards`](.claude/skills/php-coding-standards/SKILL.md), [`php-documentation`](.claude/skills/php-documentation/SKILL.md), [`phpunit-to-pest`](.claude/skills/phpunit-to-pest/SKILL.md), [`repository-settings`](.claude/skills/repository-settings/SKILL.md), [`security-audit`](.claude/skills/security-audit/SKILL.md), [`statamic-app-conventions`](.claude/skills/statamic-app-conventions/SKILL.md), [`statamic-conventions`](.claude/skills/statamic-conventions/SKILL.md), [`synced-files`](.claude/skills/synced-files/SKILL.md), [`tailwindcss-development`](.claude/skills/tailwindcss-development/SKILL.md), [`tests`](.claude/skills/tests/SKILL.md), [`update-dependencies-ticket`](.claude/skills/update-dependencies-ticket/SKILL.md), [`upstream-patch-lifecycle`](.claude/skills/upstream-patch-lifecycle/SKILL.md), [`wcag-contrast`](.claude/skills/wcag-contrast/SKILL.md), [`writing-comments`](.claude/skills/writing-comments/SKILL.md), [`writing-commits`](.claude/skills/writing-commits/SKILL.md), [`writing-issues`](.claude/skills/writing-issues/SKILL.md), [`writing-pull-requests`](.claude/skills/writing-pull-requests/SKILL.md), [`writing-release-notes`](.claude/skills/writing-release-notes/SKILL.md).
<!-- uams-claude-skills:end -->

<laravel-boost-guidelines>
=== .ai/uams-static-analysis rules ===

# Static Analysis

- **And run `composer stan`. `phpstan` is gated by the file *extension*, not by the directory** — [`phpstan.neon.dist`](phpstan.neon.dist) lists `tests` alongside `app`, so a **`tests/`-only diff gates it too** (UAMS-Web/uams-statamic-archive#1344). Scoping it out on "no `app/**` changed" is how PHPStan-red has reached `main` before. The gate table in the [`local-ci`](.claude/skills/local-ci/SKILL.md) skill is the authority on which jobs a diff needs; this line exists because that skill belongs to the validator and a build lane has no occasion to open it.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation

- Use `search-docs` before changing code that touches a package API. It returns version-specific docs for the installed packages, which is more reliable than memory for framework and Statamic behavior.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- Use `search-docs` for version-specific Inertia documentation and code examples.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scrolling (merging props + `WhenVisible`), lazy loading on scroll, polling, prefetching.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

Every test in `tests/Unit`, `tests/Feature`, and `tests/Browser` is Pest function-style, and Pest is the suite's runner — the CI `pest` job invokes `vendor/bin/pest`. PHPUnit is only the engine Pest is built on.

- Write all tests in Pest. Create them with `php artisan make:test --pest {name}`. The `{name}` argument should not include the test suite directory — use `php artisan make:test --pest SomeFeatureTest`, not `php artisan make:test --pest Feature/SomeFeatureTest`.
- Class-based PHPUnit tests (`class …Test extends TestCase`) are not used in this suite.
- `tests/Browser` is a dedicated Pest browser-smoke suite (headless Control Panel tests via `pestphp/pest-plugin-browser`, bootstrapped by `tests/Pest.php`). It is **not** registered as a PHPUnit testsuite, so `php artisan test` does not run it — run it explicitly with `vendor/bin/pest tests/Browser`. Add browser smoke tests here as Pest (`it(...)`) using the shared `tests/Support/CpSmoke.php` helpers.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- `toContain()` and `toContainEqual()` take **needles only** — both are variadic (`toContain(mixed ...$needles)`), and there is no message parameter. A string passed in that position becomes an **additional required substring**, so a well-meant explanation silently makes the assertion stricter and the failure output then presents your own prose as the expectation. The sibling `toContainOnlyInstancesOf(string $class, string $message = '')` *does* take a message, and PHPUnit's `assertContains($needle, $haystack, $message)` does too, which is why the mistake is easy to make. Put the explanation in a comment above the assertion or in the test name instead.
- A **characterization test** that a `wordpress-importer` ticket will invert tags itself `@invertsOn UAMS-Web/wordpress-importer#N` — see [`.claude/rules/characterization-test-inversion.md`](.claude/rules/characterization-test-inversion.md).

</laravel-boost-guidelines>

## Repository rules

Each line below summarizes a standing rule for this repository. Its full text is in [`docs/agent-instructions.md`](docs/agent-instructions.md), and the section linked from each line is the one to read before work it governs.

- **[Running `boost:update`](docs/agent-instructions.md#running-boostupdate)**: run it only as `php artisan boost:update --ignore-skills`, as its own reviewed change, never mid-ticket.
- **[Supported development platforms](docs/agent-instructions.md#supported-development-platforms)**: macOS and Windows are both supported. Name the platform a claim was verified on, and write paths repo-relative.
- **[Rector on changed paths](docs/agent-instructions.md#rector-on-changed-paths)**: after changing PHP under `app/`, run `vendor/bin/rector --dry-run` on the changed `app/` paths only, alongside Pint.
- **[Closing a ticket](docs/agent-instructions.md#closing-a-ticket)**: never push to `main`; tick acceptance criteria as they are met, move the board card with the branch, and give residue its own ticket.
- **[Local CI runner](docs/agent-instructions.md#local-ci-runner)**: this repository keeps its own `scripts/local-ci.mjs`, which no longer gates a merge: the required remote checks do. Do not adopt the shared runner.
- **[Pre-merge check](docs/agent-instructions.md#pre-merge-check)**: a green chain is not a merge decision; work the check in `.claude/rules/pre-merge-check.md` before any merge.
- **[WordPress source corpora](docs/agent-instructions.md#wordpress-source-corpora)**: ground importer, blueprint and migration claims in the local WordPress install and the exports corpus, per `docs/wordpress-source-corpora.md`.
- **[Adversarial review](docs/agent-instructions.md#adversarial-review)**: review a non-trivial change adversarially, fix what it finds, then run the gate.
- **[Worktree slots](docs/agent-instructions.md#worktree-slots)**: branch work happens in the slots (`../uams-statamic-a`, `../uams-statamic-b`), never in the primary checkout, which holds `main`. Refresh a slot before trusting a check in it, and release it when done.
- **[Sync PR branches before the gating run](docs/agent-instructions.md#sync-pr-branches-before-the-gating-run)**: bring a branch current with `origin/main` before the gating run and before opening its pull request.
- **[Impersonal voice in GitHub artifacts](docs/agent-instructions.md#impersonal-voice-in-github-artifacts)**: pull requests, issues and comments carry no narrator and no vouching stance.
- **[American English, and the dictionary that overrides it](docs/agent-instructions.md#american-english-and-the-dictionary-that-overrides-it)**: first-party prose is American English; a `project-words.txt` entry needs a stated reason.
- **[Decision log](docs/agent-instructions.md#decision-log)**: consult `docs/DECISIONS.md` before acting, and record a closed decision there and on its issue.
- **[Measurement parity](docs/agent-instructions.md#measurement-parity)**: reproduce a baseline's whole job, `php artisan route:cache` included, before comparing a timing to it.
- **[Cutting releases](docs/agent-instructions.md#cutting-releases)**: one `--prerelease` tag a business day at 10:00 Central when there is unreleased work.
- **[Filing defects across repositories](docs/agent-instructions.md#filing-defects-across-repositories)**: file a defect in the repository that owns it, and claim it first when a race is likely.
- **[Claude Code cloud sessions](docs/agent-instructions.md#claude-code-cloud-sessions)**: read `docs/runbooks/claude-code-cloud-sessions.md` before a bootstrap, and never run `composer install --prefer-dist` there.

## Where the rest of the instructions live

Most of this repository's guidance is not in this file, and an agent that reads only this file misses it. **It governs every agent working here, Cursor included**: there is no separate set of Cursor style rules (UAMS-Web/uams-statamic-archive#2750).

- **Standing rules: `.claude/rules/`.** Each applies to all work here. The shared section at the top of this file names every one and links its full text; read the ones that bear on a task before starting it.
- **Repository rules: [`docs/agent-instructions.md`](docs/agent-instructions.md)**, summarized above.
- **Skills: [`.claude/skills/`](.claude/skills).** Each `.claude/skills/<name>/SKILL.md` is a procedure for a kind of task, such as writing an issue, running local CI or cutting a release, and its front-matter `description` says when it applies. Claude Code loads these as skills; any other agent should open the matching `SKILL.md` before doing that kind of task. Every skill there is synced from [`UAMS-Web/uams-claude-skills`](https://github.com/UAMS-Web/uams-claude-skills), so edit a skill in that repository and let the next sync pull request bring it here (UAMS-Web/uams-statamic-archive#2839).
