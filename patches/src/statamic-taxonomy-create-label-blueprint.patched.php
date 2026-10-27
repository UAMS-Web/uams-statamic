<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "after" body for `Taxonomy::createLabel()` (blueprint-aware).
 *
 * @see https://github.com/statamic/ideas/issues/1448
 */
class StatamicTaxonomyCreateLabelPatched
{
    protected function handle(): string
    {
        return '';
    }

    public function createLabel()
    {
        $key = "statamic::messages.{$this->handle()}_taxonomy_create_term";
        $fallbackLabel = __('Create Term');
        $blueprint = request()->query('blueprint');

        if (! empty($blueprint)) {
            $blueprintKey = "{$key}.{$blueprint}";
            $blueprintTranslation = __($blueprintKey);

            if ($blueprintTranslation !== $blueprintKey) {
                return $blueprintTranslation;
            }
        }

        $translation = __($key);

        if (! \is_string($translation)) {
            return $fallbackLabel;
        }

        if ($translation === $key) {
            return $fallbackLabel;
        }

        return $translation;
    }
}
