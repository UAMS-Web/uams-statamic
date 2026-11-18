<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Attribute;

/**
 * Case metadata for the `EnumDataUtils` probes: a label, and a flag only some cases set.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class EnumDataUtilsProbeInfo
{
    public function __construct(public string $label, public ?bool $primary = null) {}
}
