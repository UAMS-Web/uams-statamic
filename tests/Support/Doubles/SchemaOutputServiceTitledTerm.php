<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

/**
 * Test-local interface declaring `title()` so the credentials term doubles can be
 * asserted PHPStan-clean. `Statamic\Contracts\Taxonomies\Term` is an empty marker
 * interface (no `title()`), and switching the doubles to a concrete `LocalizedTerm`
 * or `TaxonomyTerm` would flip the SUT's many term-type `instanceof` branches.
 * Co-mocking this interface adds `title()` without changing the SUT-visible type.
 */
interface SchemaOutputServiceTitledTerm
{
    public function title(): ?string;
}
