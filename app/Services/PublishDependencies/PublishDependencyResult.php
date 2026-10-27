<?php

declare(strict_types=1);

namespace App\Services\PublishDependencies;

/**
 * The resolved publish-dependency state for a dependent entry whose declared
 * `publish_depends_on` source(s) are down (unpublished or deleted) under its
 * suppression policy (UAMS-Web/uams-statamic-archive#1593).
 *
 * `redirectUrl` is where the live page should 302 (the collection mount root,
 * else the site root). `downSourceTitles` names the down source(s) for the
 * Control Panel banner (each source's title, or its id when it has no readable
 * title; empty when none were resolvable). `requireAll` records the policy that
 * produced the suppression — `true` for the default AND policy (every source is
 * down), `false` for the OR policy (at least one source is down) — so the banner
 * can explain *why* the entry is suppressed.
 */
final readonly class PublishDependencyResult
{
    /**
     * @param  list<string>  $downSourceTitles  The down sources' titles (or ids), for the CP banner.
     */
    public function __construct(
        public string $redirectUrl,
        public array $downSourceTitles,
        public bool $requireAll,
    ) {}
}
