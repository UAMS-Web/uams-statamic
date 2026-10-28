<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Fieldtypes\Traits\HasChoiceSubmissionLimits;
use Statamic\Fields\Fieldtype;

/**
 * A fieldtype exposing the HasChoiceSubmissionLimits trait's config fields and
 * its full-choice list.
 */
final class ChoiceSubmissionLimitsHarness extends Fieldtype
{
    use HasChoiceSubmissionLimits;

    /** @return array<string, array<string, mixed>> */
    public function limitFields(): array
    {
        return $this->choiceSubmissionLimitConfigField();
    }

    /** @return list<string> */
    public function full(): array
    {
        return $this->fullChoicesForRender();
    }
}
