<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

/**
 * Which content item the traversal is currently inside.
 *
 * Carried down the walk so each emitted occurrence records where it came from without every
 * recursive call threading four separate scalars.
 */
final readonly class OccurrenceContext
{
    /**
     * @param  ContentItemType  $itemType  Which repository the item was read from.
     * @param  string  $container  Collection handle, global set handle, or taxonomy handle.
     * @param  string  $id  Entry id, global set handle, or term id.
     * @param  string  $site  Handle of the site whose localization is being walked.
     */
    public function __construct(
        public ContentItemType $itemType,
        public string $container,
        public string $id,
        public string $site,
    ) {}
}
