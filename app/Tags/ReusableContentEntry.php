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
 * would prevent that does not accept an entry. So this tag reads the fields
 * first, and the template still loops the entry inside it, which keeps the
 * entry's own values (such as its computeds) in reach of the inner partials.
 * Both keys are always returned, so a nested reference never sees the values of
 * the reference around it.
 *
 * Usage: {{ reusable_content_entry :entry="content" }}{{ content }}{{ reusable_sets }}...{{ /reusable_sets }}{{ /content }}{{ /reusable_content_entry }}
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
        $entry = $this->params->get('entry');

        return [
            'reusable_sets' => $this->fieldValue($entry, 'content'),
            'reusable_blocks' => $this->fieldValue($entry, 'page_builder'),
        ];
    }

    /**
     * Reads a field the entry's blueprint declares, or null when it has no content.
     */
    private function fieldValue(mixed $entry, string $handle): mixed
    {
        if (! $entry instanceof Entry) {
            return null;
        }

        /** @var Blueprint $blueprint */
        $blueprint = $entry->blueprint();

        if (! $blueprint->hasField($handle)) {
            return null;
        }

        /** @var Value $field */
        $field = $entry->augmentedValue($handle);

        // Resolved once here, because a large Bard field is expensive to convert to HTML.
        $value = $field->value();

        return empty($value) ? null : $value;
    }
}
