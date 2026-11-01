<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

enum EnumCasePureProbe
{
    case Solo;

    // A float NAN stringifies to 'NAN', a legal case name.
    case NAN;
}
