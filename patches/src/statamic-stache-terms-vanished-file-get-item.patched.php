<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "after" body for `TaxonomyTermsStore::getItem()`.
 *
 * Treats a file removed after its path was recorded as gone (#2643). `File::get()`
 * returns null for a missing file, and `makeItemFromFile()` would otherwise build a new,
 * empty item from it. The check applies only when a fresh listing of the file's directory
 * succeeds and no longer contains it, so an empty file that is present keeps Statamic's
 * behavior.
 */
class StatamicStacheTermsVanishedFileGetItemPatched
{
    public function getItem($key)
    {
        $this->handleFileChanges();

        if ($item = $this->getCachedItem($key)) {
            // Only sync the original state when it's missing, since re-syncing would
            // discard changes made to a term being saved.
            if (empty($item->term()->getOriginal())) {
                $item->term()->syncOriginal();
            }

            return $item;
        }

        [$site, $slug] = explode('::', $key);

        if ($path = $this->getPath($key)) {
            $contents = File::get($path);

            // A file a fresh listing shows was removed after its path was recorded is
            // treated as gone, rather than rebuilt from no contents as a new, empty item
            if ($contents === null) {
                $listing = @scandir(dirname($path));

                if ($listing !== false && ! in_array(basename($path), $listing, true)) {
                    return null;
                }
            }

            $term = $this->makeItemFromFile($path, $contents);
        } else {
            $term = Term::make($slug)
                ->taxonomy($this->childKey())
                ->set('title', $this->index('title')->get($key));
        }

        $term->syncOriginal();

        $item = $term->in($site);

        $this->cacheItem($item);

        return $item;
    }
}
