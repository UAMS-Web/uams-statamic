<?php

declare(strict_types=1);

namespace App\Enums;

use Attribute;

/**
 * Attribute for storing county information on enum cases.
 *
 * This attribute uses normalized property names aligned to TIGERweb fields and
 * internal conventions.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class CountyInfo
{
    /**
     * Initializes a CountyInfo attribute instance with county geographic and
     * administrative data.
     *
     * @param  string  $fullName  The full name of the county
     * @param  ?string  $fipsCode  The FIPS (Federal Information Processing Standards) code for the county
     * @param  ?string  $baseName  The base name of the county without administrative suffixes
     * @param  ?string  $parentUspsCode  The USPS (United States Postal Service) code of the parent state
     * @param  ?string  $parentFipsCode  The FIPS code of the parent state
     * @param  ?string  $geoId  The geographic identifier for the county
     * @param  ?string  $nationalStandardCode  The national standard code for the county
     * @param  ?string  $fipsClassCode  The FIPS class code classification
     * @param  ?string  $functionalStatusCode  The functional status code
     * @param  ?string  $mafTigerFeatureClassCode  The MAF/TIGER feature class code
     * @param  ?string  $mafTigerObjectIdentifier  The MAF/TIGER object identifier
     * @param  ?string  $legalStatisticalAreaDescriptionCode  The legal statistical area description code
     * @param  ?string  $areaLand  The land area measurement
     * @param  ?string  $areaWater  The water area measurement
     * @param  ?string  $arcGisObjectIdentifier  The ArcGIS object identifier
     * @param  array{0:string,1:string}|array<int,string>  $centroidGeo  The geographic coordinates of the county centroid
     * @param  array{0:string,1:string}|array<int,string>  $internalPointGeo  The geographic coordinates of an internal point within the county
     */
    public function __construct(
        public string $fullName,
        public ?string $fipsCode = null,
        public ?string $baseName = null,
        public ?string $parentUspsCode = null,
        public ?string $parentFipsCode = null,
        public ?string $geoId = null,
        public ?string $nationalStandardCode = null,
        public ?string $fipsClassCode = null,
        public ?string $functionalStatusCode = null,
        public ?string $mafTigerFeatureClassCode = null,
        public ?string $mafTigerObjectIdentifier = null,
        public ?string $legalStatisticalAreaDescriptionCode = null,
        public ?string $areaLand = null,
        public ?string $areaWater = null,
        public ?string $arcGisObjectIdentifier = null,
        public array $centroidGeo = [],
        public array $internalPointGeo = [],
    ) {}
}
