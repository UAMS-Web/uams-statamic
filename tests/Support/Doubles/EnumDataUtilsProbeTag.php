<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Attribute;

/**
 * A second attribute on the `EnumDataUtils` probes, so one enum yields two data maps.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class EnumDataUtilsProbeTag
{
    public function __construct(public string $tag) {}
}
