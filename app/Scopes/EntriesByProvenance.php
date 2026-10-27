<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Support\Arr;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Entry query scope that filters `organizations` entries by provenance.
 *
 * Resolves provenance from encoded query scope handles:
 * - entries_by_provenance_first_party
 * - entries_by_provenance_third_party
 *
 * One scope class serves both via aliases; the invoking handle's suffix
 * (first_party | third_party) is matched against each entry's `provenance` field.
 * Used with the entries fieldtype so the entry browser shows only the
 * brand organizations of the requested provenance.
 */
class EntriesByProvenance extends Scope
{
    /**
     * Scope handle prefix for registration and alias encoding.
     *
     * @var string
     */
    protected static $handle = 'entries_by_provenance';

    /**
     * Provenance values that can be encoded as scope-handle suffixes.
     *
     * @var array<int, string>
     */
    public const array PROVENANCES = [
        'first_party',
        'third_party',
    ];

    /**
     * Register one alias per provenance so a single class serves both.
     *
     * The list is tiny and static, so it is built inline (no ScopeAliasCache).
     *
     * @return array<int, string>
     */
    public static function aliases(): array
    {
        $prefix = static::$handle.'_';

        return array_map(
            static fn (string $provenance): string => $prefix.$provenance,
            self::PROVENANCES
        );
    }

    /**
     * Filter to entries whose `provenance` is in the set encoded by the
     * invoking scope handle(s). With no recognized handle, no filter applies.
     *
     * @param  Builder  $query  Entry query builder
     * @param  array<string, mixed>  $values  Request values (e.g., queryScopes)
     */
    public function apply(
        $query,
        $values
    ): void {
        $provenances = $this->provenancesFromScopeHandles($values);

        // No recognized provenance handle: leave the query unfiltered.
        if ($provenances === []) {
            return;
        }

        $query->whereIn(
            'provenance',
            $provenances
        );

        unset($provenances);
    }

    /**
     * Extract recognized provenance suffixes from the query scope handles.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function provenancesFromScopeHandles(array $values): array
    {
        $queryScopes = Arr::wrap($values['queryScopes'] ?? []);
        $prefix = static::$handle.'_';
        $provenances = [];

        foreach ($queryScopes as $queryScope) {
            if (! \is_string($queryScope)) {
                continue;
            }

            if (! str_starts_with($queryScope, $prefix)) {
                continue;
            }

            $suffix = strtolower(substr($queryScope, \strlen($prefix)));
            if (\in_array($suffix, self::PROVENANCES, true)) {
                $provenances[] = $suffix;
            }
        }

        unset($queryScope);

        return array_values(array_unique($provenances));
    }
}
