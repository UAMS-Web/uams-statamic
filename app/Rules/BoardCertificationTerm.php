<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\AbstractCertificationTermBoardQueryRule;

/**
 * Validates `certifications` terms that are board certifications: a
 * `board_certification_query` of true on a term whose kind is a certification.
 * Matches the `board_certifications` query scope.
 */
class BoardCertificationTerm extends AbstractCertificationTermBoardQueryRule
{
    /**
     * Translation key prefix for Statamic validation lines.
     *
     * @return string Key prefix under `statamic::validation`.
     */
    protected function translationKeyPrefix(): string
    {
        return 'board_certification_term';
    }

    /**
     * Require a board certification.
     *
     * @return bool Always true for this rule variant.
     */
    protected function requiresBoardCertificationQueryTrue(): bool
    {
        return true;
    }
}
