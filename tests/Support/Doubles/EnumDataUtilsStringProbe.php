<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

/**
 * A string-backed enum for `EnumDataUtils`: two cases with metadata, one tagged, and one with none between them.
 */
enum EnumDataUtilsStringProbe: string
{
    #[EnumDataUtilsProbeInfo(label: 'Red', primary: true)]
    #[EnumDataUtilsProbeTag(tag: 'warm')]
    case Red = 'red';

    // Between two cases with metadata, so skipping it must not end the walk
    case Bare = 'bare';

    #[EnumDataUtilsProbeInfo(label: 'Blue')]
    case Blue = 'blue';
}
