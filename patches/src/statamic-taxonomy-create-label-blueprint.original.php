<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "before" body for `Taxonomy::createLabel()`.
 *
 * Must match `vendor/statamic/cms/src/Taxonomies/Taxonomy.php` for the
 * installed Statamic version (generator verifies this).
 *
 * @see https://github.com/statamic/ideas/issues/1448
 */
class StatamicTaxonomyCreateLabelOriginal
{
    protected function handle(): string
    {
        return '';
    }

    public function createLabel()
    {
        $key = "messages.{$this->handle()}_taxonomy_create_term";

        $translation = __($key);

        if ($translation === $key) {
            return __('Create Term');
        }

        return $translation;
    }
}
