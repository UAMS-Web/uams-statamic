<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "after" body for `BasicStore::getItem()`.
 *
 * Treats a file removed after its path was recorded as gone (#2643). Statamic 6.31.0
 * (statamic/cms#15310) returns null when the file is already missing, but a delete that
 * lands between that `File::exists()` check and the read still reaches `File::get()`,
 * which returns null for a missing file, and `makeItemFromFile()` would otherwise build a
 * new, empty item from it. The check applies only when a fresh listing of the file's directory
 * succeeds and no longer contains it, so an empty file that is present keeps Statamic's
 * behavior.
 */
class StatamicStacheBasicVanishedFileGetItemPatched
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

        $contents = File::get($path);

        // A file a fresh listing shows was removed after its path was recorded is
        // treated as gone, rather than rebuilt from no contents as a new, empty item
        if ($contents === null) {
            $listing = @scandir(dirname($path));

            if ($listing !== false && ! in_array(basename($path), $listing, true)) {
                return null;
            }
        }

        $item = $this->makeItemFromFile($path, $contents);

        $this->cacheItem($item);

        if (method_exists($item, 'syncOriginal')) {
            $item->syncOriginal();
        }

        return $item;
    }
}
