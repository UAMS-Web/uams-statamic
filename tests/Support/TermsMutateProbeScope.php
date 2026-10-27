<?php

declare(strict_types=1);

namespace Tests\Support;

use Statamic\Query\Scopes\Scope;

/**
 * Disposable scope used only to observe Relationship::applyIndexQueryScopes.
 */
final class TermsMutateProbeScope extends Scope
{
    /**
     * @var string
     */
    protected static $handle = 'terms_mutate_probe';

    /**
     * Recorded apply() invocations for the current test.
     *
     * @var list<array{query: mixed, values: array<array-key, mixed>}>
     */
    public static array $calls = [];

    /**
     * Clear recorded calls between examples.
     */
    public static function reset(): void
    {
        self::$calls = [];
    }

    /**
     * @param  mixed  $query  Index query builder
     * @param  mixed  $values  Scope params from the relationship
     */
    public function apply(
        $query,
        $values
    ): void {
        self::$calls[] = [
            'query' => $query,
            'values' => is_array($values) ? $values : [],
        ];
    }
}
