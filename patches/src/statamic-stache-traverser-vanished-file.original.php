<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "before" body for `Traverser::traverse()`.
 *
 * Must match `vendor/statamic/cms/src/Stache/Traverser.php` for the installed
 * Statamic version (the generator verifies this).
 */
class StatamicStacheTraverserVanishedFileOriginal
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

            $paths[Path::tidy($file->getPathname())] = $file->getMTime();
        }

        return collect($paths)->sort();
    }
}
