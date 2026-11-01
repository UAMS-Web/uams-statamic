<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Statamic\Query\Scopes\Scope;

/**
 * A caller scope that records it ran and keeps every hit, standing in for the
 * knowledge-base subtree scope the listing's other caller passes.
 */
final class RecordingCallerSearchScope extends Scope
{
    /**
     * Whether the scope has been applied since the last reset.
     */
    public static bool $applied = false;

    /**
     * @param  mixed  $query  The search query builder
     * @param  mixed  $values  The tag parameters
     */
    public function apply(mixed $query, mixed $values): void
    {
        self::$applied = true;
    }
}
