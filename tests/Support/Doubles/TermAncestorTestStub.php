<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Statamic\Contracts\Taxonomies\Term as TermContract;

/**
 * Minimal term-shaped stub for ancestor-walk tests (blueprint `parent` link).
 */
final class TermAncestorTestStub implements TermContract
{
    /**
     * @param  string  $idValue  Canonical `handle::slug` id
     * @param  object|null  $parentRef  Parent object with id(), or null at root
     */
    public function __construct(
        private string $idValue,
        private ?object $parentRef
    ) {}

    public function id(): string
    {
        return $this->idValue;
    }

    /**
     * Match augmented `parent` access used by `ContentAncestorIds::forTerm`.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'parent') {
            return $this->parentRef;
        }

        return null;
    }

    /**
     * Support isset checks and null coalescing on the virtual `parent` field.
     */
    public function __isset(string $name): bool
    {
        if ($name === 'parent') {
            return $this->parentRef !== null;
        }

        return false;
    }
}
