# `tests/Unit/`

Tests in this directory do **not** boot Laravel or Statamic. They exercise individual classes / functions in isolation, with collaborators stubbed via Mockery or hand-rolled fakes.

## Contract

- **Base class:** `PHPUnit\Framework\TestCase` (not `Tests\TestCase`).
- **No facades, no container, no stache, no database, no HTTP, no filesystem under `content/`.** If you need any of those, the test belongs in [`../Feature/`](../Feature/README.md).
- **No `setUp()` that requires the framework.** Construct objects directly with `new`, inject mocks, assert.
- **Fast.** A unit test class should run in well under a second. If yours takes longer than the Feature average, something is off — usually a hidden framework dependency.

## Why the strict rule

Two reasons it matters:

1. **Speed.** Booting Laravel + Statamic costs ~15 s per test class. A "unit" test that boots the framework pays that cost but gives none of the framework's guarantees back, so it's the worst of both worlds — slow *and* fragile.
2. **CI portability.** Framework-booted tests need a warm Statamic stache, populated content folders, etc. Pure unit tests run identically on any machine, in any order, with no setup. Misclassified ones break only in CI, far from the author.

## Rule of thumb when writing a new test

If the code you're testing constructs a `Tests\TestCase`-derived parent, or calls `Term::find(...)`, `Asset::find(...)`, `Entry::find(...)`, `Statamic::*`, `config(...)` that depends on Statamic config, or any facade that resolves through the container — your test is a Feature test no matter what it asserts.

## Example shape

```php
<?php

namespace Tests\Unit\Support\Some;

use App\Support\Some\Calculator;
use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{
    public function test_adds_two_numbers(): void
    {
        $this->assertSame(3, (new Calculator)->add(1, 2));
    }
}
```

## Subdirectories

Organized by the production code they cover (e.g. `Unit/Modifiers/` mirrors `app/Modifiers/`). Follow the source layout — don't invent a new top-level subdir without a corresponding code namespace.

## Enforcement

Every file in this directory extends `PHPUnit\Framework\TestCase`. A grep that returns matches in `tests/Unit/`:

```sh
grep -rl 'use Tests\\TestCase;' tests/Unit
```

…indicates a misclassification — that file belongs in `Feature/`. If you're adding a test and you find yourself reaching for `Tests\TestCase` to get a facade, write it in `Feature/` from the start.
