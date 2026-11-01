<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class EnumCaseProbeOther
{
    public function __construct(public string $label, public ?string $note = 'other note') {}
}
