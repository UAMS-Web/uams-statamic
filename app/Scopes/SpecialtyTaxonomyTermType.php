<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Support\Arr;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\TermQueryBuilder;

/**
 * Term query scope for specialty / subspecialty taxonomies (ACGME, AOA,
 * non-accredited, research ACGME + non-accredited).
 *
 * Filters terms by the blueprint `specialty_subspecialty_type` field and optional
 * taxonomies. Encoded scope handles map to:
 *   - `specialty` / `subspecialty`: any of `specialties_acgme`, `specialties_aoa`,
 *     `specialties_non_accredited`
 *   - `specialty_type_only` / `subspecialty_type_only`: the `specialty_subspecialty_type`
 *     filter with NO taxonomy clause, so the field's own `taxonomies` is the sole
 *     taxonomy constraint (for non-medical pickers such as `other_specialty`). A scope
 *     cannot narrow a picker's taxonomy set — taxonomy constraints union — so reusing
 *     `specialty` there would leak the three medical taxonomies (UAMS-Web/uams-statamic-archive#1365).
 *   - `specialty_<suffix>` / `subspecialty_<suffix>`: single taxonomy via suffix
 *     (`acgme`, `aoa`, `non_accredited`)
 *   - `medical_research_specialty` / `medical_research_subspecialty`: research
 *     taxonomies with `medical_query` true
 *   - `non_medical_research_specialty` / `non_medical_research_subspecialty`:
 *     research taxonomies with `medical_query` false
 *
 * Use `expectationFromScopeAlias()` / `expectationFromRuleArguments()` for the
 * same encoding in validation rules.
 */
class SpecialtyTaxonomyTermType extends Scope
{
    /**
     * Scope handle for registration (unique class handle; aliases cover picks).
     *
     * @var string
     */
    protected static $handle = 'specialty_taxonomy_term_type';

    /**
     * Blueprint field used to distinguish specialty / subspecialty.
     */
    public const string TYPE_FIELD = 'specialty_subspecialty_type';

    /**
     * Blueprint boolean field on research specialty taxonomies (medical vs non-medical).
     */
    public const string MEDICAL_QUERY_FIELD = 'medical_query';

    /**
     * Stored `specialty_subspecialty_type` value for specialty terms.
     */
    public const string TYPE_VALUE_SPECIALTY = 'specialty';

    /**
     * Stored `specialty_subspecialty_type` value for subspecialty terms.
     */
    public const string TYPE_VALUE_SUBSPECIALTY = 'subspecialty';

    /**
     * Research specialty taxonomy handles (medical_query-aware aliases).
     *
     * @var array<int, string>
     */
    public const array RESEARCH_TAXONOMIES = [
        'research_specialties_acgme',
        'research_specialties_non_accredited',
    ];

    /**
     * Prefix used in shorthand rule aliases (`medical_specialty`, …).
     */
    public const string MEDICAL_PREFIX = 'medical_';

    /**
     * Prefix used in shorthand rule aliases (`non_medical_specialty`, …).
     */
    public const string NON_MEDICAL_PREFIX = 'non_medical_';

    /**
     * Map encoded handle suffix (after type prefix) to taxonomy handle.
     *
     * @var array<string, string>
     */
    public const array TAXONOMY_BY_HANDLE_SUFFIX = [
        'acgme' => 'specialties_acgme',
        'aoa' => 'specialties_aoa',
        'non_accredited' => 'specialties_non_accredited',
    ];

    /**
     * Register dynamic aliases for encoded type and taxonomy in scope handles.
     *
     * @return array<int, string> All registered alias handles.
     */
    public static function aliases(): array
    {
        // Build the list starting with the two bare type handles
        $aliases = [
            self::TYPE_VALUE_SPECIALTY,
            self::TYPE_VALUE_SUBSPECIALTY,
        ];

        // Type-only handles: constrain by `specialty_subspecialty_type` alone, with
        // no taxonomy clause, so the picker's own taxonomies stand unmodified.
        $aliases[] = self::TYPE_VALUE_SPECIALTY.'_type_only';
        $aliases[] = self::TYPE_VALUE_SUBSPECIALTY.'_type_only';

        // Add specialty and subspecialty variants for each taxonomy suffix
        foreach (self::TAXONOMY_BY_HANDLE_SUFFIX as $suffix => $taxonomy) {
            // Register a `specialty_{suffix}` handle for this taxonomy key
            $aliases[] = self::TYPE_VALUE_SPECIALTY.'_'.$suffix;

            // Register a `subspecialty_{suffix}` handle for this taxonomy key
            $aliases[] = self::TYPE_VALUE_SUBSPECIALTY.'_'.$suffix;

            // Unset local variables to free memory
            unset(
                $suffix,
                $taxonomy
            );
        }

        // Research taxonomies: medical vs non-medical specialty/subspecialty
        $aliases[] = 'medical_research_specialty';
        $aliases[] = 'medical_research_subspecialty';
        $aliases[] = 'non_medical_research_specialty';
        $aliases[] = 'non_medical_research_subspecialty';

        // Return the complete set of encodable scope handles
        return $aliases;
    }

    /**
     * Apply the scope to the term query.
     *
     * The Statamic `Scope::apply` base signature does not use parameter type hints;
     * the typed work lives in `applyWithTypedTermQuery`. `queryScopes` in `$values`
     * should list the active alias (from field config merge) when present.
     *
     * @param  mixed  $query  See `applyWithTypedTermQuery` (term index query).
     * @param  mixed  $values  See `applyWithTypedTermQuery` (context for `Scope::apply`, including
     *                         `queryScopes` when present).
     */
    public function apply(
        $query,
        $values
    ): void {
        // Check the builder is a term index query the scope can filter
        if (! $query instanceof Builder) {
            // Stop when the query is not a term index `Builder` this scope targets
            return;
        }

        // Check `Scope::apply` context is a map of request-style values
        if (! \is_array($values)) {
            // Stop when the scope context is not an array of request-style values
            return;
        }

        // Narrow the request-style context to a string-keyed map for the typed delegate
        /** @var array<string, mixed> $values */

        // Delegate to the implementation that works with typed term queries
        $this->applyWithTypedTermQuery(
            $query,
            $values
        );
    }

    /**
     * Apply the scope with a known term query and param array.
     *
     * @param  Builder|TermQueryBuilder  $query  Index query for taxonomy terms.
     * @param  array<string, mixed>  $values  `Scope::apply` context, including `queryScopes` when present.
     */
    private function applyWithTypedTermQuery(
        Builder|TermQueryBuilder $query,
        array $values
    ): void {
        // Resolve type, taxonomies, and optional medical_query from encoded scope handles
        $rule = $this->ruleFromQueryScopes($values);

        // Return early when no applicable encoded handle is present
        if ($rule === null) {
            return;
        }

        // Constrain by blueprint specialty/subspecialty type (indexed column for term stache)
        $query->where(
            self::TYPE_FIELD,
            $rule['type']
        );

        // Restrict to one or more taxonomy handles.
        //
        // Skip entirely for a type-only rule (empty set): a `whereIn('taxonomies', …)`
        // UNIONS onto the picker's own taxonomies rather than intersecting, so adding
        // any taxonomy here can only widen the picker — never narrow it (UAMS-Web/uams-statamic-archive#1365).
        if ($rule['taxonomies'] !== []) {
            $query->whereIn(
                'taxonomies',
                $rule['taxonomies']
            );
        }

        // Research aliases also filter `medical_query`
        if ($rule['medical_query'] !== null) {
            $query->where(
                self::MEDICAL_QUERY_FIELD,
                $rule['medical_query']
            );
        }

        // Unset local variables to free memory
        unset(
            $rule
        );
    }

    /**
     * Read the first matching specialty scope handle and parse it into type,
     * taxonomies, and optional medical_query filter.
     *
     * @param  array<string, mixed>  $values  `Scope::apply` context, including `queryScopes` when present.
     * @return array{type: string, taxonomies: array<int, string>, medical_query: bool|null}|null
     */
    private function ruleFromQueryScopes(
        array $values
    ): ?array {
        // Map known aliases to keys for fast `array_key_exists` checks
        $known = array_flip(self::aliases());

        // Normalize `queryScopes` to a list, even when it is a single value
        $queryScopes = Arr::wrap(
            $values['queryScopes'] ?? []
        );

        // Find the first configured scope handle that encodes a specialty rule
        foreach ($queryScopes as $handle) {
            // Check if the value is a usable string handle
            if (! \is_string($handle)) {
                // Skip list entries that are not a non-empty string handle
                continue;
            }

            // An empty handle is not an alias, so the next check skips it too (EmptyStringToNotEmpty is equivalent)
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($handle === '') {
                // Skip list entries that are not a non-empty string handle
                continue;
            }

            // Check if the handle is one of our registered aliases
            if (! \array_key_exists(
                $handle,
                $known
            )) {
                // Skip handles that are not encoded aliases for this scope
                continue;
            }

            // Map this alias string to type, taxonomies, and optional medical_query
            $parsed = self::expectationFromScopeAlias($handle);

            // Check if the alias parsed into a complete rule
            if ($parsed !== null) {
                // Unset local variables to free memory
                unset(
                    $handle,
                    $known,
                    $queryScopes
                );

                // Return the first parsed expectation for a known alias
                return $parsed;
            }
        }

        // Unset local variables to free memory
        unset(
            $queryScopes,
            $known
        );

        // Signal that `queryScopes` had no encodable alias for this scope
        return null;
    }

    /**
     * Turn a scope alias string into type, taxonomy list, and optional medical_query.
     *
     * Shared with `App\Rules\SpecialtyTaxonomyTermType` so validation matches scope
     * encoding.
     *
     * @param  string  $handle  A registered alias (e.g., `specialty_acgme`).
     * @return array{type: string, taxonomies: array<int, string>, medical_query: bool|null}|null
     */
    public static function expectationFromScopeAlias(
        string $handle
    ): ?array {
        // Research taxonomies with medical_query — resolve before generic `specialty_*` parsing
        if ($handle === 'medical_research_specialty') {
            return self::researchExpectation(
                self::TYPE_VALUE_SPECIALTY,
                true
            );
        }

        if ($handle === 'medical_research_subspecialty') {
            return self::researchExpectation(
                self::TYPE_VALUE_SUBSPECIALTY,
                true
            );
        }

        if ($handle === 'non_medical_research_specialty') {
            return self::researchExpectation(
                self::TYPE_VALUE_SPECIALTY,
                false
            );
        }

        if ($handle === 'non_medical_research_subspecialty') {
            return self::researchExpectation(
                self::TYPE_VALUE_SUBSPECIALTY,
                false
            );
        }

        // Type-only handles: constrain by `specialty_subspecialty_type` alone (empty
        // taxonomy set = no taxonomy clause). Resolved before the `specialty_` /
        // `subspecialty_` taxonomy-suffix parsing, whose suffix map has no `type_only`.
        if ($handle === self::TYPE_VALUE_SPECIALTY.'_type_only') {
            return [
                'type' => self::TYPE_VALUE_SPECIALTY,
                'taxonomies' => [],
                'medical_query' => null,
            ];
        }

        if ($handle === self::TYPE_VALUE_SUBSPECIALTY.'_type_only') {
            return [
                'type' => self::TYPE_VALUE_SUBSPECIALTY,
                'taxonomies' => [],
                'medical_query' => null,
            ];
        }

        // Check if the alias is the bare specialty type
        if ($handle === self::TYPE_VALUE_SPECIALTY) {
            // Return a rule for any configured clinical specialty taxonomy
            return [
                'type' => self::TYPE_VALUE_SPECIALTY,
                'taxonomies' => array_values(self::TAXONOMY_BY_HANDLE_SUFFIX),
                'medical_query' => null,
            ];
        }

        // Check if the alias is the bare subspecialty type
        if ($handle === self::TYPE_VALUE_SUBSPECIALTY) {
            return [
                'type' => self::TYPE_VALUE_SUBSPECIALTY,
                'taxonomies' => array_values(self::TAXONOMY_BY_HANDLE_SUFFIX),
                'medical_query' => null,
            ];
        }

        // Set the `subspecialty_` prefix used to detect taxonomy-suffixed handles
        $prefix = self::TYPE_VALUE_SUBSPECIALTY.'_';

        // Check if the alias starts with the subspecialty prefix
        if (str_starts_with(
            $handle,
            $prefix
        )) {
            // Take the part after the subspecialty_ prefix
            $suffix = substr(
                $handle,
                \strlen($prefix)
            );

            // Resolve the taxonomy for that handle suffix, if any
            $taxonomy = self::TAXONOMY_BY_HANDLE_SUFFIX[$suffix] ?? null;

            // Unset local variables to free memory
            unset(
                $prefix,
                $suffix
            );

            // Check if the suffix maps to a configured taxonomy
            if ($taxonomy === null) {
                // Reject unmapped suffixes so the scope never filters an unknown taxonomy
                return null;
            }

            // Return subspecialty plus the single taxonomy the suffix selects
            return [
                'type' => self::TYPE_VALUE_SUBSPECIALTY,
                'taxonomies' => [
                    $taxonomy,
                ],
                'medical_query' => null,
            ];
        }

        // Set the `specialty_` prefix used to detect taxonomy-suffixed handles
        $prefix = self::TYPE_VALUE_SPECIALTY.'_';

        // Check if the alias encodes specialty plus a taxonomy suffix
        if (str_starts_with(
            $handle,
            $prefix
        )) {
            // Take the part after the specialty_ prefix
            $suffix = substr(
                $handle,
                \strlen($prefix)
            );

            // Resolve the taxonomy for that handle suffix, if any
            $taxonomy = self::TAXONOMY_BY_HANDLE_SUFFIX[$suffix] ?? null;

            // Unset local variables to free memory
            unset(
                $prefix,
                $suffix
            );

            // Check if the suffix maps to a configured taxonomy
            if ($taxonomy === null) {
                // Reject unmapped suffixes so the scope never filters an unknown taxonomy
                return null;
            }

            // Return specialty plus the single taxonomy the suffix selects
            return [
                'type' => self::TYPE_VALUE_SPECIALTY,
                'taxonomies' => [
                    $taxonomy,
                ],
                'medical_query' => null,
            ];
        }

        // Treat unrelated strings as not encodable by this scope
        return null;
    }

    /**
     * Parse validation rule parameters into the same expectation shape as scope aliases.
     *
     * Supports one argument (scope alias or shorthand such as `medical_specialty`) or
     * two arguments (`specialty,medical`, `subspecialty,non-medical`, order-independent).
     *
     * @param  string  ...$args  Rule parameters after the rule name.
     * @return array{type: string, taxonomies: array<int, string>, medical_query: bool|null}|null
     */
    public static function expectationFromRuleArguments(
        string ...$args
    ): ?array {
        $count = \count($args);

        if ($count === 1) {
            $trimmed = trim($args[0]);

            // An empty handle matches no alias and no shorthand below, which return null too
            // @pest-mutate-ignore: EmptyStringToNotEmpty, RemoveEarlyReturn
            if ($trimmed === '') {
                return null;
            }

            $fromAlias = self::expectationFromScopeAlias($trimmed);

            if ($fromAlias !== null) {
                return $fromAlias;
            }

            return self::expectationFromSingleArgumentShorthand($trimmed);
        }

        if ($count === 2) {
            return self::expectationFromTwoArguments(
                trim($args[0]),
                trim($args[1])
            );
        }

        return null;
    }

    /**
     * Build expectation for research taxonomies with a medical_query constraint.
     *
     * @return array{type: string, taxonomies: array<int, string>, medical_query: bool|null}
     */
    private static function researchExpectation(
        string $type,
        bool $medicalQuery
    ): array {
        return [
            'type' => $type,
            'taxonomies' => self::RESEARCH_TAXONOMIES,
            'medical_query' => $medicalQuery,
        ];
    }

    /**
     * Single-parameter rule shorthands that are not registered scope aliases.
     *
     * @return array{type: string, taxonomies: array<int, string>, medical_query: bool|null}|null
     */
    private static function expectationFromSingleArgumentShorthand(
        string $handle
    ): ?array {
        return match ($handle) {
            'medical_specialty' => self::researchExpectation(
                self::TYPE_VALUE_SPECIALTY,
                true
            ),
            'medical_subspecialty' => self::researchExpectation(
                self::TYPE_VALUE_SUBSPECIALTY,
                true
            ),
            'non_medical_specialty' => self::researchExpectation(
                self::TYPE_VALUE_SPECIALTY,
                false
            ),
            'non_medical_subspecialty' => self::researchExpectation(
                self::TYPE_VALUE_SUBSPECIALTY,
                false
            ),
            default => null,
        };
    }

    /**
     * Two-parameter forms: `specialty,medical`, `medical,specialty`, etc.
     *
     * @return array{type: string, taxonomies: array<int, string>, medical_query: bool|null}|null
     */
    private static function expectationFromTwoArguments(
        string $first,
        string $second
    ): ?array {
        // An empty token normalizes to neither a type nor a dimension, so it returns null below
        $typeFirst = self::normalizeTypeToken($first);
        $medFirst = self::normalizeMedicalDimensionToken($first);
        $typeSecond = self::normalizeTypeToken($second);
        $medSecond = self::normalizeMedicalDimensionToken($second);

        // Order-independent: one token must be specialty/subspecialty, the other medical dimension
        if ($typeFirst !== null && $medSecond !== null) {
            return self::researchExpectation(
                $typeFirst,
                $medSecond
            );
        }

        if ($typeSecond !== null && $medFirst !== null) {
            return self::researchExpectation(
                $typeSecond,
                $medFirst
            );
        }

        return null;
    }

    /**
     * @return self::TYPE_VALUE_SPECIALTY|self::TYPE_VALUE_SUBSPECIALTY|null
     */
    private static function normalizeTypeToken(string $token): ?string
    {
        $lower = strtolower($token);

        if ($lower === self::TYPE_VALUE_SPECIALTY) {
            return self::TYPE_VALUE_SPECIALTY;
        }

        if ($lower === self::TYPE_VALUE_SUBSPECIALTY) {
            return self::TYPE_VALUE_SUBSPECIALTY;
        }

        return null;
    }

    /**
     * Medical vs non-medical research dimension for comma-separated rule parameters.
     *
     * @return bool|null true = medical, false = non-medical, null = unrecognized
     */
    private static function normalizeMedicalDimensionToken(string $token): ?bool
    {
        $lower = strtolower($token);

        if ($lower === 'medical') {
            return true;
        }

        if (
            in_array($lower, ['non_medical', 'nonmedical', 'non-medical'], true)
        ) {
            return false;
        }

        return null;
    }
}
