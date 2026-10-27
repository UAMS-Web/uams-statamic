<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "before" body for `TaxonomyTermsStore::paths()`.
 *
 * Must match `vendor/statamic/cms/src/Stache/Stores/TaxonomyTermsStore.php` for the installed
 * Statamic version (the generator verifies this).
 */
class StatamicStacheTermsVanishedFilePathsOriginal
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
            $term = $this->makeItemFromFile($path, File::get($path));

            return $term->localizations()->flatMap(function ($localization, $locale) use ($path) {
                $this->cacheItem($localization);

                return [$this->getItemKey($localization) => $path];
            })->all();
        });

        $this->cachePaths($paths);

        return $paths;
    }
}
