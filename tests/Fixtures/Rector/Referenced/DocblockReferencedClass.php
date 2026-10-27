<?php

declare(strict_types=1);

namespace Tests\Fixtures\Rector\Referenced;

use Tests\Fixtures\Rector\SeeOnlyImport;

/**
 * A trivial class referenced only through a `{@see}` docblock tag by
 * {@see SeeOnlyImport}, anchoring the #1131 regression
 * guard. Deliberately has no behavior; it exists solely to be imported.
 */
final class DocblockReferencedClass {}
