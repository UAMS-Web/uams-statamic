<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "after" body for `Store::getItemFromModifiedPath()`.
 *
 * Skips a file that is removed after the change scan recorded it and before it is read
 * (#2643). `File::get()` returns null for a missing file, and `makeItemFromFile()`
 * would otherwise build a new item from no contents and, for entries, write it back
 * to disk under a fresh id. The skip is narrow: it applies only when a fresh listing
 * of the file's directory succeeds and no longer contains the file, so an empty file
 * that is present, or a directory that cannot be listed, keeps Statamic's behavior.
 */
class StatamicStacheStoreVanishedFileModifiedPatched
{
    protected function getItemFromModifiedPath($path)
    {
        $contents = File::get($path);

        // A file a fresh listing shows was removed after the traversal stat'ed it is
        // skipped, rather than rebuilt from no contents as a new, empty item
        if ($contents === null) {
            $listing = @scandir(dirname($path));

            if ($listing !== false && ! in_array(basename($path), $listing, true)) {
                return null;
            }
        }

        return $this->makeItemFromFile($path, $contents);
    }
}
