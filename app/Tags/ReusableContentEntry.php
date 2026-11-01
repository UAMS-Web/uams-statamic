<?php

declare(strict_types=1);

namespace App\Tags;

use Statamic\Entries\Entry;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Value;
use Statamic\Tags\Tags;

/**
 * Exposes the two fields a reusable content entry can render from.
 *
 * A WordPress reusable block has one type and can be used from both kinds of
 * page, so an entry can reach either reference with either blueprint. A template
 * cannot ask the entry itself: inside its loop a field the blueprint lacks
 * resolves to the page's value of the same name, and the `scope` parameter that
 * would prevent that does not accept an entry. Both keys are always returned, so
 * a nested reference never sees the values of the reference around it.
 *
 * Usage: {{ reusable_content_entry :entry="content" }}{{ reusable_sets }}...{{ /reusable_sets }}{{ /reusable_content_entry }}
 */
class ReusableContentEntry extends Tags
{
    /**
     * Returns the entry's Bard sets and page-builder blocks, each null when empty.
     *
     * @return array{reusable_sets: mixed, reusable_blocks: mixed}
     */
    public function index(): array
    {
        $entry = $this->resolveEntry($this->params->get('entry'));

        return [
            'reusable_sets' => $this->fieldValue($entry, 'content'),
            'reusable_blocks' => $this->fieldValue($entry, 'page_builder'),
        ];
    }

    /**
     * Unwraps the value of a single-entry entries field.
     */
    private function resolveEntry(mixed $value): ?Entry
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        return $value instanceof Entry ? $value : null;
    }

    /**
     * Reads a field the entry's blueprint declares, or null when it has no content.
     */
    private function fieldValue(?Entry $entry, string $handle): mixed
    {
        $blueprint = $entry?->blueprint();

        if (! $entry instanceof Entry || ! $blueprint instanceof Blueprint || ! $blueprint->hasField($handle)) {
            return null;
        }

        $value = $entry->augmentedValue($handle);

        return empty($value instanceof Value ? $value->value() : $value) ? null : $value;
    }
}
