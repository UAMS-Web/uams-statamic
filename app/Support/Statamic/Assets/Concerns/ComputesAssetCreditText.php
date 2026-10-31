<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets\Concerns;

use App\Support\Statamic\Assets\CreditTextResolver;

/**
 * Augmented-asset helper: computed `credit_text` from blueprint fields.
 */
trait ComputesAssetCreditText
{
    /**
     * Computed credit line from `creator` and `creator_organization`.
     *
     * @return string|null Combined or single value, or null
     */
    protected function creditText(): ?string
    {
        // Resolve the credit text from the creator and creator organization
        return CreditTextResolver::resolve(
            $this->getFromData('creator'),
            $this->getFromData('creator_organization')
        );
    }
}
