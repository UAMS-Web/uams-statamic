<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\EnumGeneration\EnumDataUtils;
use Attribute;

/**
 * Attribute for storing province and territory names on enum cases.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class CanadianProvinceInfo
{
    public string $fullName;

    public function __construct(string $fullName)
    {
        $this->fullName = $fullName;
    }
}

/**
 * Canadian province and territory enum with postal abbreviations and full names.
 *
 * Each case's string value is the Canada Post two-letter abbreviation (e.g.
 * `ON` for Ontario). Use `fullName()` for the official English name.
 *
 * This enum provides metadata via `CanadianProvinceInfo` and cached data from
 * `provinceData()` for `fullName()`, consistent with `State`.
 */
enum CanadianProvince: string
{
    #[CanadianProvinceInfo(fullName: 'Alberta')]
    case AB = 'AB';

    #[CanadianProvinceInfo(fullName: 'British Columbia')]
    case BC = 'BC';

    #[CanadianProvinceInfo(fullName: 'Manitoba')]
    case MB = 'MB';

    #[CanadianProvinceInfo(fullName: 'New Brunswick')]
    case NB = 'NB';

    #[CanadianProvinceInfo(fullName: 'Newfoundland and Labrador')]
    case NL = 'NL';

    #[CanadianProvinceInfo(fullName: 'Nova Scotia')]
    case NS = 'NS';

    #[CanadianProvinceInfo(fullName: 'Northwest Territories')]
    case NT = 'NT';

    #[CanadianProvinceInfo(fullName: 'Nunavut')]
    case NU = 'NU';

    #[CanadianProvinceInfo(fullName: 'Ontario')]
    case ON = 'ON';

    #[CanadianProvinceInfo(fullName: 'Prince Edward Island')]
    case PE = 'PE';

    #[CanadianProvinceInfo(fullName: 'Quebec')]
    case QC = 'QC';

    #[CanadianProvinceInfo(fullName: 'Saskatchewan')]
    case SK = 'SK';

    #[CanadianProvinceInfo(fullName: 'Yukon')]
    case YT = 'YT';

    /**
     * Lazily initializes and returns the province data map.
     *
     * @return array<string, array{fullName: string}>
     */
    private static function provinceData(): array
    {
        // Create a closure to extract fullName from CanadianProvinceInfo
        $dataExtractor = fn ($info, $caseValue) => [
            'fullName' => $info->fullName,
        ];

        // Build the lazy data map using EnumDataUtils to cache province data
        $result = EnumDataUtils::buildLazyDataMap(
            self::class,
            CanadianProvinceInfo::class,
            $dataExtractor
        );

        // Clean up variables to free up memory
        unset(
            $dataExtractor
        );

        // Return the cached data map for use by getter methods
        return $result;
    }

    /**
     * Get the full name of the province or territory.
     *
     * @return string The full name
     */
    public function fullName(): string
    {
        // Resolve from cached province data
        $data = self::provinceData();

        // Throw if province is not found
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'fullName',
            'Canadian province'
        );
    }

    /**
     * Get the postal abbreviation of the province or territory.
     *
     * @return string The two-letter abbreviation
     */
    public function postal(): string
    {
        // Return underlying enum case value
        return $this->value;
    }
}
