# `app/Support`

**Small support types** that do not warrant a full service: focused helpers, formatters, and thin wrappers tied to Laravel or Statamic behavior.

## Use this directory for

- **Stateless or nearly stateless helpers** (e.g., formatting, simple transforms).
- **Framework integration glue** (e.g., custom augmented types, helpers used from providers or hooks).

## Prefer elsewhere

- **Larger coordinated behavior** used across features → `app/Services`
- **HTTP concerns** → `app/Http`
- **Config, routes, providers** → their usual Laravel locations

Keep files here **single-purpose** and easy to discover by name. If a type grows into a real dependency graph or domain workflow, move it under `app/Services` (or a feature namespace) and reserve `Support` for the genuinely small pieces.
