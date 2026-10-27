<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "before" body for `Store::getItemFromModifiedPath()`.
 *
 * Must match `vendor/statamic/cms/src/Stache/Stores/Store.php` for the installed
 * Statamic version (the generator verifies this).
 */
class StatamicStacheStoreVanishedFileModifiedOriginal
{
    protected function getItemFromModifiedPath($path)
    {
        return $this->makeItemFromFile($path, File::get($path));
    }
}
