<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "after" body for `Collection::createLabel()` (blueprint-aware).
 *
 * @see https://github.com/statamic/ideas/issues/1448
 */
class StatamicCollectionCreateLabelPatched
{
    protected function handle(): string
    {
        return '';
    }

    public function createLabel()
    {
        $key = "statamic::messages.{$this->handle()}_collection_create_entry";
        $fallbackLabel = __('Create Entry');
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
