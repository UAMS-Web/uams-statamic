<?php

declare(strict_types=1);

namespace App\Enums;

use Attribute;

/**
 * Attribute for storing country information on enum cases.
 *
 * @param  string  $country_name  The full name of the country
 * @param  string  $alpha3  The ISO 3166 Alpha-3 code
 * @param  string  $numeric  The ISO 3166 numeric code
 * @param  bool  $useThe  Whether to prefix the country name with 'the' in sentences
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class CountryInfo
{
    /**
     * @param  string  $country_name  The full name of the country
     * @param  string  $alpha3  The ISO 3166 Alpha-3 code
     * @param  string  $numeric  The ISO 3166 numeric code
     * @param  bool  $useThe  Whether to prefix the country name with 'the' in sentences
     */
    public function __construct(
        public string $country_name,
        public string $alpha3,
        public string $numeric,
        public bool $useThe = false,
    ) {}
}
