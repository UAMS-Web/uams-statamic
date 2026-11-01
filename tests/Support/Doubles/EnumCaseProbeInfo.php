<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class EnumCaseProbeInfo
{
    public function __construct(public string $label, public ?string $note = null, protected string $secret = 'hidden') {}
}
