<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

enum EnumCaseStringProbe: string
{
    case Alpha = 'a';

    case Beta = 'B-1';
}
