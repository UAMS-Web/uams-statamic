<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "before" body for `TaxonomyTermsStore::getItemFromModifiedPath()`.
 *
 * Must match `vendor/statamic/cms/src/Stache/Stores/TaxonomyTermsStore.php` for the
 * installed Statamic version (the generator verifies this).
 */
class StatamicStacheTermsVanishedFileModifiedOriginal
{
    protected function getItemFromModifiedPath($path)
    {
        return parent::getItemFromModifiedPath($path)->localizations()->all();
    }
}
