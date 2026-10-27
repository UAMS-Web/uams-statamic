<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Statamic\Contracts\Taxonomies\Term as TermContract;

/**
 * Minimal term-shaped stub for descendant-walk tests. Exposes the RAW stored
 * `parent` value via `get('parent')` — the read `ContentDescendantIds::forTerm`
 * uses (never the augmented `parent` property, which would run a Term sub-query).
 */
final class TermDescendantTestStub implements TermContract
{
    /**
     * @param  string  $idValue  Canonical `handle::slug` id
     * @param  mixed  $parentRaw  Raw stored `parent` value: a bare slug, a
     *                            `handle::slug` reference, a one-element array of
     *                            either, or null/other for no resolvable parent
     */
    public function __construct(
        private string $idValue,
        private mixed $parentRaw = null
    ) {}

    public function id(): string
    {
        return $this->idValue;
    }

    /**
     * Answer the raw stored data read used by `ContentDescendantIds::forTerm`.
     *
     * @param  string  $key
     * @param  mixed  $fallback
     */
    public function get($key, $fallback = null): mixed
    {
        return $key === 'parent' ? $this->parentRaw : $fallback;
    }
}
