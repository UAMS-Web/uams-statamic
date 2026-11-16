<?php

declare(strict_types=1);

namespace App\Enums;

use Attribute;

/**
 * Attribute for storing state information on enum cases.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class StateInfo
{
    public function __construct(
        public string $fullName,
        public string $apAbbreviation,
        public string $type,
        public string $fipsCode,
        public string $iso3166_2,
        public ?string $baseName = null,
        public ?string $geoId = null,
        public ?string $nationalStandardCode = null,
        public ?string $division = null,
        public ?string $region = null,
        public ?string $functionalStatusCode = null,
        public ?string $mafTigerFeatureClassCode = null,
        public ?string $mafTigerObjectIdentifier = null,
        public ?string $legalStatisticalAreaDescriptionCode = null,
        public ?string $areaLand = null,
        public ?string $areaWater = null,
        public ?string $arcGisObjectIdentifier = null,
        /** @var array{0:string,1:string}|array<int,string> */
        public array $centroidGeo = [],
        /** @var array{0:string,1:string}|array<int,string> */
        public array $internalPointGeo = []
    ) {}
}
