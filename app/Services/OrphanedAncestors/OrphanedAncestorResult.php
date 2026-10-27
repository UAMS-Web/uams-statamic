<?php

declare(strict_types=1);

namespace App\Services\OrphanedAncestors;

/**
 * The resolved orphaned-ancestor state for a published entry that has an
 * unpublished structural ancestor.
 *
 * `redirectUrl` is where the live page should 302 (the nearest live ancestor,
 * else the collection mount root, else the site root). `unpublishedAncestorTitle`
 * names the nearest unpublished ancestor for the Control Panel banner (null when
 * that ancestor has no readable title).
 */
final readonly class OrphanedAncestorResult
{
    public function __construct(
        public string $redirectUrl,
        public ?string $unpublishedAncestorTitle,
    ) {}
}
