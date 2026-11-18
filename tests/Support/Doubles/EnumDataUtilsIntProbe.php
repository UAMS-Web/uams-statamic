<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

/**
 * An int-backed enum for `EnumDataUtils`, whose backing value must reach the extractor as a string.
 */
enum EnumDataUtilsIntProbe: int
{
    #[EnumDataUtilsProbeInfo(label: 'Seven')]
    case Seven = 7;
}
