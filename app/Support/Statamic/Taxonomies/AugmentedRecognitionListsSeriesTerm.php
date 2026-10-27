<?php

declare(strict_types=1);

namespace App\Support\Statamic\Taxonomies;

use Statamic\Fields\Value;
use Statamic\Taxonomies\AugmentedTerm;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Augmented recognition list series term exposing computed `full_name`.
 */
class AugmentedRecognitionListsSeriesTerm extends AugmentedTerm
{
    /**
     * Cached key list including the virtual `full_name` handle.
     *
     * @var list<string>|null
     */
    private ?array $keysWithComputedFullName = null;

    /**
     * {@inheritdoc}
     *
     * Appends the virtual `full_name` key for Antlers, GraphQL, and CP previews.
     *
     * @return list<string>
     */
    #[\Override]
    public function keys(): array
    {
        // Reuse the cached augmented key list when already built
        if ($this->keysWithComputedFullName !== null) {
            return $this->keysWithComputedFullName;
        }

        // Merge Statamic default keys with the computed handle
        /** @var list<string> $defaultKeys */
        $defaultKeys = parent::keys();

        // `->values()` reindexes sequentially, so the result is a list
        /** @var list<string> $keysWithComputedFullName */
        $keysWithComputedFullName = collect($defaultKeys)
            ->push('full_name')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $this->keysWithComputedFullName = $keysWithComputedFullName;
    }

    /**
     * Computed display string: publication `name` plus series `name`.
     *
     * @return Value
     */
    protected function fullName()
    {
        // Build the combined publication and series label for this term
        /** @var LocalizedTerm $seriesLocalized */
        $seriesLocalized = $this->data;
        $label = RecognitionListTaxonomyComputedValues::fullNameForSeriesLocalizedTerm(
            $seriesLocalized
        );

        /** @var Value $value */
        $value = $this->wrapValue($label, 'full_name');

        return $value;
    }
}
