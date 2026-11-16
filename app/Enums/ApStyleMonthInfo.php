<?php

declare(strict_types=1);

namespace App\Enums;

use Attribute;

/**
 * AP Stylebook month labels for `ApStyleGregorianMonth` enum cases.
 *
 * Holds running-text (`prose`) and tabular (`tabular`) forms per case.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class ApStyleMonthInfo
{
    /**
     * @param  string  $prose  Running-text AP label (abbreviations use periods
     *                         where the Stylebook requires them)
     * @param  string  $tabular  Three-letter tabular form without periods
     */
    public function __construct(
        public string $prose,
        public string $tabular,
    ) {}
}
