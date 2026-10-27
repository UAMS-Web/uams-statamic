<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "after" body for `TaxonomyTermsStore::paths()`.
 *
 * Treats a file removed after its path was recorded as gone (#2643). `File::get()`
 * returns null for a missing file, and `makeItemFromFile()` would otherwise build a new,
 * empty item from it. The check applies only when a fresh listing of the file's directory
 * succeeds and no longer contains it, so an empty file that is present keeps Statamic's
 * behavior.
 */
class StatamicStacheTermsVanishedFilePathsPatched
{
    public function paths()
    {
        if ($this->paths) {
            return $this->paths;
        }

        if ($paths = Stache::cacheStore()->get($this->pathsCacheKey())) {
            return $this->paths = collect($paths);
        }

        $files = Traverser::filter([$this, 'getItemFilter'])->traverse($this);

        $paths = $files->mapWithKeys(function ($timestamp, $path) {
            $contents = File::get($path);

            // A file a fresh listing shows was removed after its path was recorded is
            // treated as gone, rather than rebuilt from no contents as a new, empty item
            if ($contents === null) {
                $listing = @scandir(dirname($path));

                if ($listing !== false && ! in_array(basename($path), $listing, true)) {
                    return [];
                }
            }

            $term = $this->makeItemFromFile($path, $contents);

            return $term->localizations()->flatMap(function ($localization, $locale) use ($path) {
                $this->cacheItem($localization);

                return [$this->getItemKey($localization) => $path];
            })->all();
        });

        $this->cachePaths($paths);

        return $paths;
    }
}
