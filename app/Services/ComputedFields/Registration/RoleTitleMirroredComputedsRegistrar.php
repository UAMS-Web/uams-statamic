<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Support\RoleTitleValue;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Optional `Collection::computed` registrations that expose a root-level string
 * composed from stored `role_title` subfields via RoleTitleValue::composeFromMixed()
 * for configured top-level source fields.
 */
final class RoleTitleMirroredComputedsRegistrar
{
    /**
     * Tuples of collection handle, source `role_title` field handle, and computed handle.
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const array ROLE_TITLE_COMPUTED_DERIVATIONS = [];

    /**
     * Create the registrar over the tuples to register.
     *
     * The container builds it with the constant, so production registers nothing
     * until a collection opts in there; a test passes its own tuples.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $derivations  Collection, source field and computed handles
     */
    public function __construct(
        private readonly array $derivations = self::ROLE_TITLE_COMPUTED_DERIVATIONS
    ) {}

    /**
     * Register optional role-title mirrored string computeds from the tuple list.
     */
    public function register(): void
    {
        // Walk each configured collection, source field, and computed handle tuple
        foreach ($this->derivations as [$collectionHandle, $sourceHandle, $computedHandle]) {
            // Register a computed string mirroring RoleTitleValue::composeFromMixed output
            Collection::computed(
                $collectionHandle,
                $computedHandle,
                fn (Entry $entry, mixed $_stored): string => RoleTitleValue::composeFromMixed(
                    $entry->get($sourceHandle)
                )
            );
        }
    }
}
