<?php

declare(strict_types=1);

namespace App\Stache;

use Statamic\Stache\Stores\EntriesStore as BaseStore;

/**
 * Stache entries store override that wires the child store to the app's
 * CollectionEntriesStore.
 */
class EntriesStore extends BaseStore
{
    /** @var class-string<CollectionEntriesStore> */
    protected $childStore = CollectionEntriesStore::class;
}
