<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\Isced;

/**
 * Per-enum configuration for `IscedEnumWriter`.
 */
final class IscedEnumConfig
{
    /**
     * @param  string  $enumClass  Target class name under `App\Enums` (e.g. `IscedP2011`)
     * @param  string  $casePrefix  Prefix used when forming case names (e.g. `ISCEDP`)
     * @param  string  $classification  Long human-readable classification name
     * @param  string  $classificationShort  Short tag (e.g. `ISCED-P 2011`)
     * @param  string  $sourceTitle  Title of the publication used to populate this enum
     * @param  string  $sourceUrl  Canonical URL for the source
     * @param  string  $emitNotes  Free-form notes added to the enum class docblock
     * @param  int  $minTierLength  Length in characters of top-tier codes (1 for ISCED-P/A, 2 for ISCED-F)
     */
    public function __construct(
        public string $enumClass,
        public string $casePrefix,
        public string $classification,
        public string $classificationShort,
        public string $sourceTitle,
        public string $sourceUrl,
        public string $emitNotes,
        public int $minTierLength,
    ) {}
}
