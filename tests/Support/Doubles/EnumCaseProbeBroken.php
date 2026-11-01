<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Attribute;
use RuntimeException;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class EnumCaseProbeBroken
{
    public string $label = 'never read';

    public function __construct()
    {
        throw new RuntimeException('This attribute cannot be instantiated.');
    }
}
