---
name: statamic-app-conventions
description: >-
  Conventions for a Laravel + Statamic application that boots on its own, a repository with `artisan`,
  `bootstrap/app.php` and a Herd-served `.test` URL: Laravel Boost MCP usage (`search-docs`,
  `list-artisan-commands`, `tinker`, `database-query`, `browser-logs`, `get-absolute-url`),
  `php artisan make:*` for new files, Eloquent / N+1 / Form Request / casts() / route() guidance,
  `env()` only inside config files (test-only values included), the streamlined Laravel structure
  (`bootstrap/app.php`, no `Http\Kernel.php`), migrations, Vite and frontend bundling, Pest
  function-style tests, the `tests/Browser` suite and `php artisan test`. Activate together with
  `statamic-conventions` whenever working in the application: new controllers, models, jobs,
  console commands, routes, migrations, Statamic computed properties, fieldtypes, CP controllers,
  or any test/Artisan/build workflow.
---
<!--
  Synced from UAMS-Web/uams-claude-skills: shared/.claude/skills/statamic-app-conventions/SKILL.md
  Edit it there, not here; the next sync overwrites this copy.
  Delivered to this repository through the profile(s): statamic-app.
-->
# Statamic application conventions

The conventions for a Statamic site that boots as a Laravel application. They hold alongside the
`statamic-conventions` skill, which carries everything that is the same in an add-on package;
activate both.

## 1. Skills you must activate alongside this one

- [`tailwindcss-development`](../tailwindcss-development/SKILL.md): any styling / CSS / Tailwind /
  UI work. Tailwind's conventions live there, not here.

## 2. Laravel Boost MCP: use it

Laravel Boost is an MCP server with tools designed for this application. Reach for these before
falling back to general approaches.

### `search-docs` (critically important)

- Use **before** making code changes when working with Laravel or Laravel-ecosystem packages.
  The tool returns **version-specific** docs for the installed packages, so it already scopes to
  the versions `composer.json` resolves.
- Use multiple, broad, **simple, topic-based queries** at once:
  `['rate limiting', 'routing rate limiting', 'routing']`.
- **Do not** include package names in queries (`test resource table`, not
  `filament 4 test resource table`). The tool already filters by installed package.
- Search syntax: auto-stemming on simple words; AND across multiple words; `"quoted phrases"`
  for exact-position match; mix the two; pass `queries=["a", "b"]` for OR.

### Other Boost tools

- `list-artisan-commands`: double-check available parameters before calling an Artisan command.
- `get-absolute-url`: generate any project URL you share with the user (correct scheme/host/
  port). The app is served by **Laravel Herd** at `https?://[kebab-case-project-dir].test`. Do
  not run commands to make the site available over HTTP(S); Herd already does that.
- `tinker`: execute PHP to debug code or query Eloquent models directly. Do not open a tinker
  session to verify what a test already covers.
- `database-query`: read-only DB access when you don't need to run PHP.
- `browser-logs`: read browser logs, errors, exceptions. Only recent logs are useful.

## 3. Doing things the Laravel way

- Use `php artisan make:*` to create new files (migrations, controllers, models, jobs, etc.).
  For a generic PHP class: `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands so they run without user input. Pass the
  correct `--options`.

### Database / Eloquent

- Always use proper Eloquent relationship methods with return type hints. Prefer relationships
  over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw DB queries.
- **Avoid `DB::`**; prefer `Model::query()`. Leverage Laravel's ORM rather than bypassing it.
- Prevent N+1 with eager loading.
- Use the query builder only for very complex DB operations.

### Models

- When creating new models, also create useful factories and seeders. Ask the user before
  adding anything else, using `list-artisan-commands` for the available options.
- Casts likely belong in a `casts()` method on the model rather than the `$casts` property.
  Follow existing conventions from other models.

### APIs

- Default to Eloquent API Resources and API versioning unless existing API routes diverge: then follow existing convention.

### Validation

- Always create **Form Request** classes for validation rather than inline validation in
  controllers. Include both rules and custom error messages.
- Check sibling Form Requests to see whether the application uses array- or string-based rules.

### Auth

- Use Laravel's built-in auth/authorization (gates, policies, Sanctum, etc.).

### URLs

- Prefer named routes and the `route()` function for generating links.

### Queues

- Use queued jobs with the `ShouldQueue` interface for time-consuming operations.

### Configuration

- Use `env()` **only inside config files**. Never call `env()` from app code; call
  `config('app.name')` instead of `env('APP_NAME')`.
- This applies to **test code too**. For a test-only, env-backed value (e.g. a path to external
  fixtures), add it to a dedicated config file (`config/testing.php` returning
  `['gf_audit_fixtures_dir' => env('GF_AUDIT_FIXTURES_DIR')]`) and read it with
  `config('testing.gf_audit_fixtures_dir')`. This keeps the test green under `config:cache`
  (where bare `env()` returns `null`) and avoids committing machine-specific defaults in code.
  Document the variable as a commented, per-developer entry in `.env.example` (no shared default
  when the value is a personal absolute path).

## 4. Laravel streamlined structure

- Middleware is **not** registered in `app/Http/Kernel.php` (that file no longer exists).
- Configure middleware declaratively in `bootstrap/app.php` via
  `Application::configure()->withMiddleware(...)`.
- `bootstrap/app.php` registers middleware, exceptions, and routing files.
- `bootstrap/providers.php` lists app-specific service providers.
- `app\Console\Kernel.php` no longer exists: use `bootstrap/app.php` or `routes/console.php`
  for console configuration.
- Console commands in `app/Console/Commands/` are auto-registered.

### Migrations

- When modifying a column, the migration must include all attributes previously defined on the
  column. Otherwise they will be dropped.

### Eager-load limits

- Laravel supports limiting eagerly-loaded records natively:
  `$query->latest()->limit(10);` (no external package required).

### Vite error

- "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" → run
  `npm run build`, or ask the user to run `npm run dev` / `composer run dev`.

## 5. Frontend bundling

If the user doesn't see a frontend change in the UI, they may need to run `npm run build`,
`npm run dev`, or `composer run dev`. Ask.

## 6. Tests

The test-enforcement rules (every change tested, the paths to cover, self-skipping tests) are in
`statamic-conventions`. In the application:

- **Every test is Pest function-style** (`php artisan make:test --pest {name}`; the `{name}`
  omits the suite dir, e.g. `SomeFeatureTest`, not `Feature/SomeFeatureTest`). Class-based
  `…Test extends TestCase` files are not used in this suite.
- **`tests/Browser` is a dedicated Pest suite** (headless CP browser-smoke tests, issue #852,
  via `pestphp/pest-plugin-browser`, bootstrapped by `tests/Pest.php`). It is **not** registered
  as a PHPUnit testsuite, so `php artisan test` never runs it: run it explicitly with
  `vendor/bin/pest tests/Browser`. Add browser smoke tests here as `it(...)`/`beforeEach(...)`
  using the shared `tests/Support/CpSmoke.php` helpers.
- **A test that depends on a resource outside the repo** resolves its path or flag from config
  (env-backed, per the Configuration rule above) before skipping when it is unset or missing:
  `$this->markTestSkipped("… set GF_AUDIT_FIXTURES_DIR")`.

### Running tests

- All tests: `php artisan test --compact`.
- One file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- Filter: `php artisan test --compact --filter=testName`.
- Browser smoke suite (Pest, not part of `php artisan test`): `vendor/bin/pest tests/Browser`.

### Test data

- Use **model factories** when creating models for tests. Check for **custom states** before
  manually setting up a model.
- Most tests should be feature tests; pass `--unit` to `php artisan make:test` only when you
  want a unit test.
