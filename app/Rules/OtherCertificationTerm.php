<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\AbstractCertificationTermBoardQueryRule;

/**
 * Validates `certifications` terms that are not board certifications: a
 * `board_certification_query` that is not strictly `true`, or a kind that can
 * never be a board certification. Matches the `other_certifications` scope alias.
 */
class OtherCertificationTerm extends AbstractCertificationTermBoardQueryRule
{
    /**
     * Translation key prefix for Statamic validation lines.
     *
     * @return string Key prefix under `statamic::validation`.
     */
    protected function translationKeyPrefix(): string
    {
        return 'other_certification_term';
    }

    /**
     * Require a certifications term that is not a board certification.
     *
     * @return bool Always false so the base rule uses the “not true” branch.
     */
    protected function requiresBoardCertificationQueryTrue(): bool
    {
        return false;
    }
}
