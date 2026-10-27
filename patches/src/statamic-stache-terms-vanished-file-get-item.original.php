<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "before" body for `TaxonomyTermsStore::getItem()`.
 *
 * Must match `vendor/statamic/cms/src/Stache/Stores/TaxonomyTermsStore.php` for the installed
 * Statamic version (the generator verifies this).
 */
class StatamicStacheTermsVanishedFileGetItemOriginal
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
            $term = $this->makeItemFromFile($path, File::get($path));
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
