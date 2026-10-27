<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\AbstractProfessionalAndLeadershipDevelopmentActivitiesTerm as ActivitiesRuleAbstract;
use App\Scopes\ProfessionalAndLeadershipDevelopmentActivitiesTermType as ActivitiesScope;

/**
 * Validates `professional_and_leadership_development_activities` terms whose
 * `type` is `professional_development`, matching the
 * `professional_development_activity_term` query scope.
 */
class ProfessionalDevelopmentActivitiesTerm extends ActivitiesRuleAbstract
{
    /**
     * Translation key prefix for Statamic validation lines.
     *
     * @return string Key prefix under `statamic::validation`.
     */
    protected function translationKeyPrefix(): string
    {
        return 'professional_development_activity_term';
    }

    /**
     * Require the professional-development activity type.
     *
     * @return string The scope’s professional-development `type` value.
     */
    protected function expectedTypeValue(): string
    {
        return ActivitiesScope::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT;
    }
}
