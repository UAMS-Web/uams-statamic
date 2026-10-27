<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Statamic\Contracts\Taxonomies\Term as TermContract;

/**
 * Minimal term-shaped stub for scope tests. Exposes the RAW stored `parent`
 * value via `get('parent')` — the read `ContentDescendantIds::forTerm` uses.
 */
final class ExcludeDescendantsTermStub implements TermContract
{
    /**
     * @param  string  $idValue  Canonical `handle::slug` id
     * @param  mixed  $parentRaw  Raw stored `parent` value (bare slug,
     *                            `handle::slug` reference, one-element array, or null)
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
