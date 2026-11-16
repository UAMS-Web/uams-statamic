<?php

declare(strict_types=1);

namespace App\Enums;

use Attribute;

/**
 * Attribute for storing currency information on enum cases.
 *
 * @param  string  $name  The English name of the currency
 * @param  string  $numeric  The ISO 4217 numeric code
 * @param  string  $symbol  The currency's symbol
 * @param  string  $symbol_placement  The placement of the currency's symbol relative to the numeric amounts
 * @param  bool  $symbol_separation  Whether the currency's symbol is separated from the numeric amounts
 * @param  int  $minor_unit  The number of minor units in the currency
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class CurrencyInfo
{
    /**
     * Initializes a CurrencyInfo attribute instance with currency ISO 4217
     * codes and formatting information.
     *
     * @param  string  $name  The English name of the currency
     * @param  string  $numeric  The ISO 4217 numeric code
     * @param  string  $symbol  The currency's symbol
     * @param  string  $symbol_placement  The placement of the currency's symbol relative to the numeric amounts
     * @param  bool  $symbol_separation  Whether the currency's symbol is separated from the numeric amounts
     * @param  int  $minor_unit  The number of minor units in the currency
     */
    public function __construct(
        public string $name,
        public string $numeric,
        public string $symbol,
        public string $symbol_placement,
        public bool $symbol_separation,
        public int $minor_unit
    ) {}
}
