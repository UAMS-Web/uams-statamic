<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Collection as SupportCollection;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;

/**
 * Mirrors a `logo_list_items` entry's nested `order_values` key/value pairs to
 * prefixed top-level fields so the Logo List block's `collection` tag can sort by
 * them (UAMS-Web/uams-statamic-archive#73).
 *
 * Statamic's `collection` tag `sort` parameter cannot reach nested field values,
 * so each `order_values` pair `{ key: value }` is flattened, on save, to a
 * top-level `order_value__<key>` field. The block then sorts on that prefixed
 * handle. The `order_value__` namespace keeps the flattened keys from colliding
 * with real blueprint handles.
 *
 * Stale prefixed fields are pruned every save: a key removed or renamed in
 * `order_values` drops its mirror, so no orphan `order_value__*` field lingers.
 */
class FlattenLogoOrderValues
{
    /**
     * The collection whose entries carry sortable order values.
     */
    private const string LOGO_ITEMS_COLLECTION = 'logo_list_items';

    /**
     * The collision-safe prefix for a flattened, sortable order value.
     */
    private const string PREFIX = 'order_value__';

    /**
     * Flatten `order_values` to prefixed top-level fields, pruning stale mirrors.
     */
    public function handle(EntrySaving $event): void
    {
        /** @var Entry $entry */
        $entry = $event->entry;

        /** @var ?Collection $collection */
        $collection = $entry->collection();

        // Only logo-list items carry order values; ignore every other collection.
        if ($collection === null || $collection->handle() !== self::LOGO_ITEMS_COLLECTION) {
            return;
        }

        // Build the desired set of prefixed fields from the current order values.
        $desired = [];
        $orderValues = $entry->get('order_values');

        if (\is_array($orderValues)) {
            foreach ($orderValues as $key => $value) {
                // Dynamic array rows are keyed by the editor's order key.
                if (\is_string($key) && $key !== '') {
                    $desired[self::PREFIX.$key] = $value;
                }
            }
        }

        // Write every desired mirror.
        foreach ($desired as $prefixedKey => $value) {
            $entry->set($prefixedKey, $value);
        }

        // Prune any prefixed field no longer backed by an order value.
        $data = $entry->data();
        $existingKeys = $data instanceof SupportCollection ? array_keys($data->all()) : [];

        foreach ($existingKeys as $existingKey) {
            if (
                \is_string($existingKey)
                && str_starts_with($existingKey, self::PREFIX)
                && ! \array_key_exists($existingKey, $desired)
            ) {
                $entry->remove($existingKey);
            }
        }
    }
}
