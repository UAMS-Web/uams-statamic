# `tests/Feature/`

Tests in this directory **boot Laravel and Statamic** and exercise behavior at higher levels of integration: HTTP endpoints, validation rules that walk into taxonomy lookups, blueprint resolution, listeners that react to entry / asset events, computed fields, and anything else that crosses module boundaries through the framework.

## Contract

- **Base class:** `Tests\TestCase` (the project's bootstrapping test case, which uses the `CreatesApplication` trait).
- **The framework is fully booted.** You have facades, the container, the stache, an in-memory SQLite database (per `phpunit.xml`'s `DB_DATABASE=:memory:`), and the `assets_meta` connection (also `:memory:`).
- **The Statamic stache is warm.** The test bootstrap (`Tests\CreatesApplication`) warms it once per PHP process, and the suite runs in parallel (paratest) where each worker warms its own isolated per-`TEST_TOKEN` cache. Tests can rely on real content under `content/` being discoverable — asset containers, taxonomy terms, entries, blueprints, navigations, globals.
- **Anything you assert against must be committed to git.** Fixture taxonomy terms, asset container yamls, entry files — if your test references `Term::find('credentials::atc')`, the file `content/taxonomies/credentials/atc.yaml` must exist in the repo, or the test will pass locally for whoever has stale state and fail on a fresh checkout.
- **Each test should leave the world the way it found it.** Listeners / observers should be unbound (the `MockeryPHPUnitIntegration` trait handles container-bound mocks), generated files cleaned up, ad-hoc entries deleted. Tests run in process-shared application state — pollution will cascade.

## When to use Feature vs Unit

If the smallest reproduction of the bug or behavior requires the framework, it's a Feature test. If it doesn't, it's a Unit test — see [`../Unit/README.md`](../Unit/README.md) for the strict version of that contract.

Examples that clearly belong here:

- An HTTP test asserting a CP route returns 200 for an authorized user.
- A validation rule whose `validate()` body calls `Term::find(...)` or hydrates an `Asset`.
- A listener that reacts to `EntrySaved` and rewrites a sibling entry.
- A computed-field provider whose output depends on resolved blueprint state.
- Anything Antlers-rendering, anything queue / event / cache facade-dependent.

## Example shape

```php
<?php

namespace Tests\Feature\Rules;

use App\Rules\SomeRule;
use Tests\TestCase;

class SomeRuleTest extends TestCase
{
    public function test_rule_passes_for_real_term(): void
    {
        $message = null;
        (new SomeRule)->validate(
            'field',
            'taxonomy::slug',
            function (string $m) use (&$message) { $message = $m; }
        );

        $this->assertNull($message);
    }
}
```

## Performance

Each Feature test class pays ~15 s of bootstrap cost (Laravel + Statamic). Group related assertions into one class rather than spreading them across many small files — the marginal cost of an extra test method is microseconds, but the marginal cost of an extra test class is the whole bootstrap.

## CI

The `PHPUnit` job in `.github/workflows/ci.yml` runs the full suite on Ubuntu with PHP 8.4. If a Feature test passes locally but fails in CI, the first suspect is divergent content state — your machine has something this CI checkout doesn't.
