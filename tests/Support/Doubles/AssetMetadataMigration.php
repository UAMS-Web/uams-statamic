<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Illuminate\Database\Migrations\Migration;

/**
 * Structural type for the reversible asset_metadata migration under test.
 *
 * The base {@see Migration} declares neither
 * `up()` nor `down()`, so this names the pair the anonymous migration exposes.
 * It is referenced only from `@return` / `@var` annotations, never implemented
 * or instantiated at runtime.
 */
interface AssetMetadataMigration
{
    public function up(): void;

    public function down(): void;
}
