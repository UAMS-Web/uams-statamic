<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "before" body for `Store::paths()`.
 *
 * Must match `vendor/statamic/cms/src/Stache/Stores/Store.php` for the installed
 * Statamic version (the generator verifies this).
 */
class StatamicStacheStoreVanishedFilePathsOriginal
{
    public function paths()
    {
        $this->handleFileChanges();

        if ($this->paths) {
            return $this->paths;
        }

        if ($paths = Stache::cacheStore()->get($this->pathsCacheKey())) {
            return $this->paths = collect($paths);
        }

        $files = Traverser::filter([$this, 'getItemFilter'])->traverse($this);

        $fileItems = $files->map(function ($timestamp, $path) {
            return [
                'item' => $item = $this->makeItemFromFile($path, File::get($path)),
                'key' => $this->getItemKey($item),
                'path' => $path,
            ];
        });

        $items = $fileItems->reject(function ($item) {
            try {
                $this->keys()->add($item['key'], $item['path']);
            } catch (DuplicateKeyException $e) {
                $isDuplicate = true;
                Stache::duplicates()->track($this, $e->getKey(), $e->getPath());
            }

            return $isDuplicate ?? false;
        });

        $items->each(fn ($item) => $this->cacheItem($item['item']));

        $paths = $items->pluck('path', 'key');

        $this->cachePaths($paths);

        $this->keys()->cache();

        Stache::duplicates()->cache();

        return $paths;
    }
}
