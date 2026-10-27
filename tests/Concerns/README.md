# Test concerns

Shared traits mixed into test classes — reusable guards and helpers, not tests
themselves. Nothing here is discovered by PHPUnit; these only take effect when a
test `use`s them.

## What lives here

| Trait | Purpose |
|---|---|
| [`RequiresContentSubmodule`](RequiresContentSubmodule.php) | Skip a content-dependent test gracefully when the `content/` submodule is absent, instead of erroring out of an unresolved collection/entry. |
| [`IsolatesStacheCache`](IsolatesStacheCache.php) | Give a Stache-isolated test its own in-memory Stache cache, so the index it builds from a throwaway fixture root cannot reach the worker's shared one. |

## `RequiresContentSubmodule`

Some `Feature/` tests render real committed content — a specific person, a
populated taxonomy group, a media-contact term — that lives only in the
`content/` submodule. The canonical environments always initialize it: the
`phpunit` job in [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) and
[`scripts/local-ci.mjs`](../../scripts/local-ci.mjs) both
`git submodule update --init … content` before running, and a normal local
checkout has it too. So these guards never trip there.

A **bare worktree** or a `local-ci --skip-submodule` run, however, has no
`content/`. Without it `Collection::find('people')` returns `null` and the test
either trips a hard assertion or throws out of the Antlers render — noisy
`FAIL`/`ERROR` output that masks real regressions. The trait converts that into a
clear `SKIP` with a reason naming the missing dependency.

```php
use Tests\Concerns\RequiresContentSubmodule;

class SomePartialTest extends TestCase
{
    use RequiresContentSubmodule;

    public function test_renders_something(): void
    {
        // Skips with "Requires the content/ submodule (people collection)."
        // when content/ is absent; returns the collection otherwise.
        $collection = $this->requireContentCollection('people');

        // ...
    }
}
```

### Helpers

Two shapes, and picking the right one matters (UAMS-Web/uams-statamic-archive#1738). The `require*` pair
guards a lookup that **must** resolve, so a `null` is only forgiven when
`content/` is genuinely absent; `skipWithoutQualifyingContentEntry()` guards a
*data* precondition the content set is free not to satisfy, so it always skips.

- `requireContentCollection(string $handle): Collection` — resolve a collection
  that lives in the submodule. Skips when `content/` is uninitialized; **fails**
  when it is present and the Stache still cannot find the handle.
- `requireContentEntry(?Entry $entry, string $description): Entry` — assert a
  real committed entry resolved (e.g. from a `slug` query). Skips when `content/`
  is uninitialized; **fails** when it is present and the entry did not resolve.
- `skipWithoutQualifyingContentEntry(?Entry $entry, string $description): Entry`
  — skip when no committed entry satisfies an *editorial* precondition ("a
  published topic that has a published descendant article"). Use this only where
  `null` is a content fact, never where it would mean the content is broken.

The discriminator is the filesystem, not the lookup: `Tests\Support\ContentSubmodule`
treats `content/collections/*.yaml` being populated as proof the submodule is
there. Before UAMS-Web/uams-statamic-archive#1738 all three shapes skipped on any `null`, which reported a
starved Stache worker or a renamed handle as a benign skip while the band passed.

Guard **before** rendering: a partial that runs a `collection:*` tag throws a
`CollectionNotFoundException` as soon as it renders, so resolve the dependency
first (in `setUp()` or at the top of the test) and let the skip short-circuit it.

> This is the *graceful-skip* direction (see UAMS-Web/uams-statamic-archive#856). Where a test renders only
> synthetic data and merely needs a collection *definition*, full
> content-independence (stubbing the collection in `setUp`) is an alternative —
> but most of these tests need real committed content that can't be reasonably
> synthesized, so they skip instead.

## `IsolatesStacheCache`

Mixed into the two Stache-isolated base cases —
[`StacheIsolatedTestCase`](../Support/StacheIsolatedTestCase.php) and
[`SchemaFixtureStacheTestCase`](../Support/SchemaFixtureStacheTestCase.php) — and
called once from each `setUp()`. You should not need it anywhere else: if a test
re-roots the Stache at a throwaway directory, it belongs on one of those bases.

Statamic's `PreventsSavingStacheItemsToDisk` re-roots what the Stache reads **from
disk**. It leaves the **cache store** those stores index into pointing at the
worker's default `file` store — the same one the per-process Stache seed in
[`CreatesApplication`](../CreatesApplication.php) fills with the real `content/`
index. That let an isolated test both erase the seeded index (via its
`Stache::clear()`) and write its own fixture view of a real collection handle in
its place, which the next test in that worker then read as authoritative (UAMS-Web/uams-statamic-archive#1747).

Pointing the Stache at an `array` store closes both directions: the isolated test
can neither read nor write the shared cache, and the store dies with the test's
application instance, so nothing needs restoring. The store serializes its values,
because Laravel's `ArrayStore` otherwise hands back the *same object instance* and
the `file` store it stands in for returns a fresh clone — without that, an entry
and the stored original a save compares it against are one object and every
dirty-state check reads clean.

[`StacheIsolationCacheStoreTest`](../Feature/StacheIsolationCacheStoreTest.php)
pins both directions.
