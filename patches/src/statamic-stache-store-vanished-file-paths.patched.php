<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "after" body for `Store::paths()`.
 *
 * Skips a file that is removed after the traversal stat'ed it and before it is read
 * (#2643). `File::get()` returns null for a missing file, and `makeItemFromFile()`
 * would otherwise build a new item from no contents and, for entries, write it back
 * to disk under a fresh id. The skip is narrow: it applies only when a fresh listing
 * of the file's directory succeeds and no longer contains the file, so an empty file
 * that is present, or a directory that cannot be listed, keeps Statamic's behavior.
 */
class StatamicStacheStoreVanishedFilePathsPatched
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
            $contents = File::get($path);

            // A file a fresh listing shows was removed after the traversal stat'ed it is
            // skipped, rather than rebuilt from no contents as a new, empty item
            if ($contents === null) {
                $listing = @scandir(dirname($path));

                if ($listing !== false && ! in_array(basename($path), $listing, true)) {
                    return null;
                }
            }

            return [
                'item' => $item = $this->makeItemFromFile($path, $contents),
                'key' => $this->getItemKey($item),
                'path' => $path,
            ];
        })->filter();

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
