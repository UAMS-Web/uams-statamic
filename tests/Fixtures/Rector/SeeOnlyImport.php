<?php

declare(strict_types=1);

namespace Tests\Fixtures\Rector;

use Tests\Fixtures\Rector\Referenced\DocblockReferencedClass;

/**
 * Fixture anchoring the #1131 regression guard. The imported
 * {@see DocblockReferencedClass} is referenced *only* inside this docblock —
 * never in code below.
 *
 * Rector's unused-import remover (`withImportNames(removeUnusedImports: ...)`)
 * is not `{@see}`-aware, so with removal enabled it strips this import. The fix
 * (`removeUnusedImports: false` in `rector.php`) keeps it. `RectorSeeImportPreservationTest`
 * runs Rector over this file and asserts it is left unchanged, so a regression
 * that re-enables removal turns the guard red.
 *
 * Keep this class rector-neutral — no closures, casts, or other rule-actionable
 * code — so the guard can assert a clean "no change" instead of parsing a diff.
 */
final class SeeOnlyImport {}
