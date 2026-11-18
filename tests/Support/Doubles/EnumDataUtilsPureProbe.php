<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

/**
 * A pure enum for `EnumDataUtils`: its case has metadata but no backing value to key it by.
 */
enum EnumDataUtilsPureProbe
{
    #[EnumDataUtilsProbeInfo(label: 'Solo')]
    case Solo;
}
