<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "before" body for `Collection::createLabel()`.
 *
 * Must match `vendor/statamic/cms/src/Entries/Collection.php` for the
 * installed Statamic version (generator verifies this).
 *
 * @see https://github.com/statamic/ideas/issues/1448
 */
class StatamicCollectionCreateLabelOriginal
{
    protected function handle(): string
    {
        return '';
    }

    public function createLabel()
    {
        $key = "messages.{$this->handle()}_collection_create_entry";

        $translation = __($key);

        if ($translation === $key) {
            return __('Create Entry');
        }

        return $translation;
    }
}
