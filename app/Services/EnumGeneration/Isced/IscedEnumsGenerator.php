<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\Isced;

/**
 * Coordinator that runs the three ISCED sub-generators and writes:
 *
 *   - `app/Enums/IscedA2011.php` (educational attainment levels)
 *   - `app/Enums/IscedP2011.php` (education programme levels)
 *   - `app/Enums/IscedF2013.php` (fields of education and training)
 *
 * Each enum case carries an `#[IscedInfo(...)]` attribute exposing
 * `code`, `label`, `parent`, `ancestors`, `children`, `descendants`, and
 * `tier` (1-based hierarchy depth).
 */
final class IscedEnumsGenerator
{
    /**
     * Run all three sub-generators.
     *
     * @param  ?string  $iscedFFixturePath  Optional path to `isced-f-2013.json`
     *                                      (or compatible); when null the
     *                                      bundled fixture beside the
     *                                      generator is used.
     * @return array{a: string, p: string, f: string} Absolute paths to the
     *                                                three written enum files
     */
    public function generate(?string $iscedFFixturePath = null): array
    {
        $aPath = (new IscedA2011EnumGenerator)->generate();
        $pPath = (new IscedP2011EnumGenerator)->generate();
        $fPath = (new IscedF2013EnumGenerator)->generate($iscedFFixturePath);

        return [
            'a' => $aPath,
            'p' => $pPath,
            'f' => $fPath,
        ];
    }
}
