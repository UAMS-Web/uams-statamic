# `app/Services`

Application **domain and workflow logic**: classes that coordinate behavior, own non-trivial rules, or sit behind multiple callers (controllers, commands, listeners, jobs).

## Use this directory for

- **Named operations** you might inject or resolve from the container (resolvers, generators, sanitizers, mergers, cascade builders).
- **Feature-sized code** grouped by area, e.g. schema templates, enum generation, Statamic patch generation.
- **Shared business logic** that is larger than a one-off helper and is not purely HTTP or view concerns.

## Prefer elsewhere

- **Thin HTTP layer** → `app/Http`
- **Eloquent models** → `app/Models`
- **Framework bootstrap / bindings** → `app/Providers`
- **Small stateless helpers and CMS glue** → `app/Support`

Subfolders here (e.g., `SchemaTemplates`, `SchemaObject`, `EnumGeneration`) group related services; add new subfolders when a clear bounded context emerges rather than growing a single flat list indefinitely.
