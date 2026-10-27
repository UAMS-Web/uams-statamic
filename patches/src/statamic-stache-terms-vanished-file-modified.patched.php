<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "after" body for `TaxonomyTermsStore::getItemFromModifiedPath()`.
 *
 * The `statamic-stache-store-vanished-file-modified` patch makes the parent return null
 * for a file deleted before its read (#2643). This override calls `localizations()` on
 * the parent's result, so without this patch that null would fatal the request. It
 * returns no items instead.
 */
class StatamicStacheTermsVanishedFileModifiedPatched
{
    protected function getItemFromModifiedPath($path)
    {
        // The parent skips a file that vanished before its read, and returns null for it
        return parent::getItemFromModifiedPath($path)?->localizations()->all() ?? [];
    }
}
