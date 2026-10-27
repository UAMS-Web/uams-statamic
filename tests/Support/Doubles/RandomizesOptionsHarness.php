<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Fieldtypes\Traits\RandomizesOptions;
use Statamic\Fields\Fieldtype;

/**
 * A fieldtype using the RandomizesOptions trait whose preset options are the
 * letters a to j in order.
 */
final class RandomizesOptionsHarness extends Fieldtype
{
    use RandomizesOptions;

    /** @return array<array-key, mixed> */
    protected function getOptionsWithPreset(): array
    {
        return range('a', 'j');
    }

    /** @return array<array-key, mixed> */
    public function options(): array
    {
        return $this->getOptions();
    }
}
