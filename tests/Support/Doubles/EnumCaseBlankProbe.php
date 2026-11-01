<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

enum EnumCaseBlankProbe: string
{
    case Blank = '';

    case Filled = 'x';
}
