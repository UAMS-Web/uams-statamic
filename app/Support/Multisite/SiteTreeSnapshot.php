<?php

declare(strict_types=1);

namespace App\Support\Multisite;

/**
 * The multisite tree at one moment: each site's parent and `subject`, and what search reads from
 * them (#480).
 *
 * A site's parent is its `uams_multisite_settings` localization origin, and a site with none is a
 * root. A site is a search **tier** when its `subject` is `root` or `major_entity` and it has a
 * site below it; "This site" on a tier covers its whole subtree. Every other site is offered
 * "All of <nearest tier>" when that tier is not a root. The `subject` here is the one the
 * localization's `value()` reads, so a site that stores none inherits its origin's.
 */
final readonly class SiteTreeSnapshot
{
    /**
     * The `subject` values that make a site with descendants a tier.
     */
    private const array TIER_SUBJECTS = ['root', 'major_entity'];

    /**
     * Each site's child sites, in site order.
     *
     * @var array<string, list<string>>
     */
    private array $children;

    /**
     * @param  array<string, string|null>  $parents  Site handle => parent site handle, null for a root.
     * @param  array<string, string|null>  $subjects  Site handle => `subject`, null when it has none.
     */
    public function __construct(
        public array $parents,
        public array $subjects,
    ) {
        $children = [];

        foreach ($parents as $handle => $parent) {
            if ($parent !== null && $parent !== $handle) {
                $children[$parent][] = $handle;
            }
        }

        $this->children = $children;
    }

    /**
     * Every site below a site, at any depth, nearest first.
     *
     * @param  string  $handle  The site's handle.
     * @return list<string> Site handles, without the site itself.
     */
    public function descendants(string $handle): array
    {
        $found = [];
        $queue = $this->children[$handle] ?? [];

        while ($queue !== []) {
            $child = array_shift($queue);

            // A site already found closes a loop in the tree, so its subtree is not walked twice
            if ($child === $handle || \in_array($child, $found, true)) {
                continue;
            }

            $found[] = $child;
            array_push($queue, ...($this->children[$child] ?? []));
        }

        return $found;
    }

    /**
     * Whether a site is a search tier: a `root` or `major_entity` subject, and a site below it.
     *
     * @param  string  $handle  The site's handle.
     */
    public function isTier(string $handle): bool
    {
        return \in_array($this->subjects[$handle] ?? null, self::TIER_SUBJECTS, true)
            && ($this->children[$handle] ?? []) !== [];
    }

    /**
     * The nearest tier above a site, or null when it is a root or there is none.
     *
     * A tier that is a root is the root site's own scope, every site, so it is not offered.
     *
     * @param  string  $handle  The site's handle.
     */
    public function nearestTier(string $handle): ?string
    {
        $seen = [$handle];
        $ancestor = $this->parents[$handle] ?? null;

        while ($ancestor !== null && ! \in_array($ancestor, $seen, true)) {
            if ($this->isTier($ancestor)) {
                return ($this->parents[$ancestor] ?? null) === null ? null : $ancestor;
            }

            $seen[] = $ancestor;
            $ancestor = $this->parents[$ancestor] ?? null;
        }

        return null;
    }

    /**
     * The sites "This site" covers from a site: its whole subtree on a tier, else the site alone.
     *
     * @param  string  $handle  The site's handle.
     * @return list<string> Site handles, the site first.
     */
    public function covered(string $handle): array
    {
        return $this->isTier($handle) ? [$handle, ...$this->descendants($handle)] : [$handle];
    }
}
