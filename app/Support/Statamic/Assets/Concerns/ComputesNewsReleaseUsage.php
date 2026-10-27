<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets\Concerns;

use App\Support\Statamic\Assets\NewsReleaseUsageResolver;
use Statamic\Contracts\Entries\Entry as EntryContract;

/**
 * Augmented-asset helper: computed `in_use` and `used_by` for `news_releases` assets.
 *
 * Statamic dispatches an augmented key to a camelCase method (`Str::camel`), so the
 * methods are named `inUse` / `usedBy` for the `in_use` / `used_by` field handles.
 * Both derive from the system-managed `news_release_entry` link via
 * {@see NewsReleaseUsageResolver}.
 */
trait ComputesNewsReleaseUsage
{
    /**
     * Computed `in_use`: whether the linked `posts` entry exists and is published.
     */
    protected function inUse(): bool
    {
        return NewsReleaseUsageResolver::isInUse($this->getFromData('news_release_entry'));
    }

    /**
     * Computed `used_by`: the linked `posts` entry, if it still exists, else null.
     */
    protected function usedBy(): ?EntryContract
    {
        return NewsReleaseUsageResolver::resolve($this->getFromData('news_release_entry'));
    }
}
