<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "before" body for `BasicStore::getItem()`.
 *
 * Must match `vendor/statamic/cms/src/Stache/Stores/BasicStore.php` for the installed
 * Statamic version (the generator verifies this).
 */
class StatamicStacheBasicVanishedFileGetItemOriginal
{
    public function getItem($key)
    {
        $this->handleFileChanges();

        if (! $path = $this->getPath($key)) {
            return null;
        }

        if ($item = $this->getCachedItem($key)) {
            if (method_exists($item, 'syncOriginal')) {
                $item->syncOriginal();
            }

            return $item;
        }

        if (! File::exists($path)) {
            return null;
        }

        $item = $this->makeItemFromFile($path, File::get($path));

        $this->cacheItem($item);

        if (method_exists($item, 'syncOriginal')) {
            $item->syncOriginal();
        }

        return $item;
    }
}
