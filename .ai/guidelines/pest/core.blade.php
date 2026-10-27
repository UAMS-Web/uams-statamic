## Pest

Every test in `tests/Unit`, `tests/Feature`, and `tests/Browser` is Pest function-style, and Pest is the suite's runner — the CI `pest` job invokes `vendor/bin/pest`. PHPUnit is only the engine Pest is built on.

- Write all tests in Pest. Create them with `php artisan make:test --pest {name}`. The `{name}` argument should not include the test suite directory — use `php artisan make:test --pest SomeFeatureTest`, not `php artisan make:test --pest Feature/SomeFeatureTest`.
- Class-based PHPUnit tests (`class …Test extends TestCase`) are not used in this suite.
- `tests/Browser` is a dedicated Pest browser-smoke suite (headless Control Panel tests via `pestphp/pest-plugin-browser`, bootstrapped by `tests/Pest.php`). It is **not** registered as a PHPUnit testsuite, so `php artisan test` does not run it — run it explicitly with `vendor/bin/pest tests/Browser`. Add browser smoke tests here as Pest (`it(...)`) using the shared `tests/Support/CpSmoke.php` helpers.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- `toContain()` and `toContainEqual()` take **needles only** — both are variadic (`toContain(mixed ...$needles)`), and there is no message parameter. A string passed in that position becomes an **additional required substring**, so a well-meant explanation silently makes the assertion stricter and the failure output then presents your own prose as the expectation. The sibling `toContainOnlyInstancesOf(string $class, string $message = '')` *does* take a message, and PHPUnit's `assertContains($needle, $haystack, $message)` does too, which is why the mistake is easy to make. Put the explanation in a comment above the assertion or in the test name instead.
- A **characterization test** that a `wordpress-importer` ticket will invert tags itself `@invertsOn UAMS-Web/wordpress-importer#N` — see [`.claude/rules/characterization-test-inversion.md`](.claude/rules/characterization-test-inversion.md).
