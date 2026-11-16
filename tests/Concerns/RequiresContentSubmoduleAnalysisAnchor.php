<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Tests\TestCase;

/**
 * PHPStan analysis anchor for {@see RequiresContentSubmodule}.
 *
 * The trait is mixed into Pest test cases at runtime via
 * `uses(TestCase::class, RequiresContentSubmodule::class)`, but this project's
 * PHPStan setup does not load Pest's own extension, so PHPStan never sees that
 * `uses()` call as a trait `use` site. Without one it reports the trait as
 * `trait.unused` and skips analyzing its body at the same level 10 as `app/`
 * (UAMS-Web/uams-statamic-archive#1344). This never-instantiated, abstract class supplies the real `use` site
 * so the trait is analyzed alongside the tests that actually mix it in.
 *
 * It is abstract and lives in `tests/Concerns`, which is outside every PHPUnit
 * testsuite (`tests/Unit`, `tests/Feature` in `phpunit.xml`), so it is never
 * discovered or executed as a test.
 *
 * @see https://phpstan.org/blog/how-phpstan-analyses-traits
 *
 * @internal
 */
abstract class RequiresContentSubmoduleAnalysisAnchor extends TestCase
{
    use RequiresContentSubmodule;
}
