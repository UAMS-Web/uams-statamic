<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Support\Arr;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\TermQueryBuilder;

/**
 * Term query scope for the `professional_and_leadership_development_activities`
 * taxonomy.
 *
 * Filters terms by the blueprint `type` field. The default scope handle
 * (`professional_development_activity_term`) keeps terms whose `type` is
 * `professional_development`. The `leadership_development_activity_term` alias
 * selects `leadership_development` only.
 *
 * Use `expectationFromScopeAlias()` for the same encoding in validation rules.
 */
class ProfessionalAndLeadershipDevelopmentActivitiesTermType extends Scope
{
    /**
     * Scope handle for professional-development activity terms.
     *
     * @var string
     */
    protected static $handle = 'professional_development_activity_term';

    /**
     * Query scope alias for leadership-development activity terms.
     */
    public const string ALIAS_LEADERSHIP_DEVELOPMENT_ACTIVITY_TERM = 'leadership_development_activity_term';

    /**
     * Blueprint field that distinguishes activity kinds.
     */
    public const string TYPE_FIELD = 'type';

    /**
     * Stored `type` value for professional development activities.
     */
    public const string TYPE_VALUE_PROFESSIONAL_DEVELOPMENT = 'professional_development';

    /**
     * Stored `type` value for leadership development activities.
     */
    public const string TYPE_VALUE_LEADERSHIP_DEVELOPMENT = 'leadership_development';

    /**
     * Taxonomy handle this scope targets.
     */
    public const string TAXONOMY = 'professional_and_leadership_development_activities';

    /**
     * Register the leadership-development filter under its scope handle.
     *
     * @return array<int, string> Alias handles bound to this scope class.
     */
    public static function aliases(): array
    {
        return [
            self::ALIAS_LEADERSHIP_DEVELOPMENT_ACTIVITY_TERM,
        ];
    }

    /**
     * Apply taxonomy and `type` filters when a matching scope handle is active.
     *
     * @param  mixed  $query  Term index query or another builder (ignored).
     * @param  mixed  $values  Context for `Scope::apply`, including `queryScopes` when present.
     */
    public function apply(
        $query,
        $values
    ): void {
        // Check whether the builder is a Statamic index query this scope supports
        if (! $query instanceof Builder) {
            return;
        }

        // Check whether scope context is an array (request-style values)
        if (! \is_array($values)) {
            return;
        }

        // Delegate filtering to the typed term-query implementation
        /** @var array<string, mixed> $values */
        $this->applyWithTypedTermQuery(
            $query,
            $values
        );
    }

    /**
     * Apply filters when the merged scope list includes this scope’s handles.
     *
     * @param  Builder|TermQueryBuilder  $query  Index query for taxonomy terms.
     * @param  array<string, mixed>  $values  `Scope::apply` context, including `queryScopes` when present.
     */
    private function applyWithTypedTermQuery(
        Builder|TermQueryBuilder $query,
        array $values
    ): void {
        // Resolve desired `type` from active scope handles in context
        $desiredType = $this->desiredTypeFromQueryScopes($values);

        // Check whether any known handle matched
        if ($desiredType === null) {
            return;
        }

        // Restrict results to the activities taxonomy
        $query->whereIn(
            'taxonomies',
            [
                self::TAXONOMY,
            ]
        );

        // Constrain terms by blueprint type
        $query->where(
            self::TYPE_FIELD,
            $desiredType
        );
    }

    /**
     * Resolve the `type` value from the first matching scope handle.
     *
     * Checks the leadership alias before the default handle (same ordering idea
     * as `BoardCertifications`).
     *
     * @param  array<string, mixed>  $values  Scope context including `queryScopes`.
     * @return string|null Expected `type` value, or null when no known handle matches.
     */
    private function desiredTypeFromQueryScopes(
        array $values
    ): ?string {
        // Normalize `queryScopes` to a list, even when it is a single value
        $queryScopes = Arr::wrap(
            $values['queryScopes'] ?? []
        );

        // Resolve the default scope handle for the professional-development filter
        $professionalHandle = static::handle();

        // Walk scope handles in order and return the first known match
        foreach ($queryScopes as $handle) {
            // Skip entries that are not a non-empty string handle
            if (! \is_string($handle)) {
                continue;
            }

            if ($handle === '') {
                continue;
            }

            // Check if the active handle selects leadership-development activities
            if ($handle === self::ALIAS_LEADERSHIP_DEVELOPMENT_ACTIVITY_TERM) {
                unset(
                    $queryScopes,
                    $professionalHandle,
                    $handle
                );

                return self::TYPE_VALUE_LEADERSHIP_DEVELOPMENT;
            }

            // Check if the active handle selects professional-development activities
            if ($handle === $professionalHandle) {
                unset(
                    $queryScopes,
                    $professionalHandle,
                    $handle
                );

                return self::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT;
            }
        }

        // Unset local variables after no known handle matched
        unset(
            $queryScopes,
            $professionalHandle
        );

        return null;
    }

    /**
     * Map a scope handle to the expected `type` value.
     *
     * Shared with validation rules so CP rules match query scope handles.
     *
     * @param  string  $handle  `professional_development_activity_term` or
     *                          `leadership_development_activity_term`.
     * @return array{type: string}|null Parsed type, or null when the handle is unknown.
     */
    public static function expectationFromScopeAlias(
        string $handle
    ): ?array {
        // Check if the handle selects professional-development activities
        if ($handle === static::handle()) {
            return [
                'type' => self::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT,
            ];
        }

        // Check if the handle selects leadership-development activities
        if ($handle === self::ALIAS_LEADERSHIP_DEVELOPMENT_ACTIVITY_TERM) {
            return [
                'type' => self::TYPE_VALUE_LEADERSHIP_DEVELOPMENT,
            ];
        }

        return null;
    }
}
