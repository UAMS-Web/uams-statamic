<?php

declare(strict_types=1);

namespace App\Enums;

use Attribute;

/**
 * Attribute for storing ZIP code information on enum cases.
 *
 * @param  string  $zip_code  ZIP Code
 * @param  string  $city  City name
 * @param  string  $state_usps  State USPS code
 * @param  string  $state_geoId  State GEOID
 * @param  list<string>  $county_geoId  County GEOIDs
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class ZipCodeInfo
{
    /**
     * @param  string  $zip_code  ZIP Code
     * @param  string  $city  City name
     * @param  string  $state_usps  State USPS code
     * @param  string  $state_geoId  State GEOID
     * @param  list<string>  $county_geoId  County GEOIDs
     */
    public function __construct(
        public string $zip_code,
        public string $city,
        public string $state_usps,
        public string $state_geoId,
        public array $county_geoId,
    ) {}
}
