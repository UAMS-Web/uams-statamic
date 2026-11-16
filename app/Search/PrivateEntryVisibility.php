<?php

declare(strict_types=1);

namespace App\Search;

use App\Http\Middleware\ProtectEntry;
use App\Scopes\HidePrivateSearchResults;
use App\Support\Entries\PrivateEntryAccess;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\Search\Contracts\EntryVisibility;

/**
 * The private-entry rule for the type-ahead suggestions.
 *
 * Applies the rule {@see ProtectEntry} applies to an entry's URL: a `private` entry
 * shows only to a reader who can view it. The `/search` page applies the same rule
 * through {@see HidePrivateSearchResults}, so the two surfaces cannot disagree.
 */
final class PrivateEntryVisibility implements EntryVisibility
{
    /**
     * @param  PrivateEntryAccess  $privateEntryAccess  Decides whether this reader may see a `private` entry
     */
    public function __construct(private readonly PrivateEntryAccess $privateEntryAccess) {}

    /**
     * {@inheritDoc}
     *
     * `value()` rather than `get()`, so a localization reads its origin's status, as
     * the middleware does.
     */
    public function canView(Entry $entry): bool
    {
        if ($entry->value('status_and_visibility') !== 'private') {
            return true;
        }

        return $this->privateEntryAccess->canView($entry);
    }
}
