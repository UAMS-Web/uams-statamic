<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "after" body for `Traverser::traverse()`.
 *
 * Skips a file that is removed between the directory listing and the mtime read,
 * so a concurrent delete cannot abort the whole traversal. The skip is narrow:
 * the stat failure is re-raised unless a fresh listing of the file's directory
 * shows the file is gone, so a file that is present but cannot be stat'ed, or a
 * directory that cannot be listed, still raises.
 */
class StatamicStacheTraverserVanishedFilePatched
{
    public function traverse($store)
    {
        if (! $dir = $store->directory()) {
            throw new \Exception("Store [{$store->key()}] does not have a directory defined.");
        }

        $dir = rtrim($dir, '/');

        if (! file_exists($dir)) {
            return collect();
        }

        $files = Finder::create()->files()->ignoreDotFiles(true)->in($dir)->sortByName();

        $paths = [];
        foreach ($files as $file) {
            if ($this->filter && ! call_user_func($this->filter, $file)) {
                continue;
            }

            try {
                $mtime = $file->getMTime();
            } catch (\RuntimeException $e) {
                // Skip the file only when a fresh listing shows it was removed after it was listed
                $listing = @scandir($file->getPath());

                if ($listing === false || in_array($file->getFilename(), $listing, true)) {
                    throw $e;
                }

                continue;
            }

            $paths[Path::tidy($file->getPathname())] = $mtime;
        }

        return collect($paths)->sort();
    }
}
