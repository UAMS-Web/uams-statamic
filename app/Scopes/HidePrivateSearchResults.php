<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Http\Controllers\SiteSearchSuggestionsController;
use App\Http\Middleware\ProtectEntry;
use App\Search\AreaOfExpertiseSectionDocument;
use App\Support\Entries\PrivateEntryAccess;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Keeps `private` entries the reader may not open out of the `/search` results page
 * (UAMS-Web/uams-statamic-archive#2634).
 *
 * A `private` entry is still `published`, so `{{ search:results }}` lists it, while
 * {@see ProtectEntry} 404s it for anyone {@see PrivateEntryAccess::canView()} refuses.
 * This scope applies that same rule to the results query, so the page never offers a
 * title, URL or type the reader cannot open. It mirrors the type-ahead's
 * `SiteSearchSuggestionsController::visible()` ({@see SiteSearchSuggestionsController}),
 * so the two surfaces cannot disagree. An Area-of-Expertise section subpage is as
 * private as its Area of Expertise, as the type-ahead treats it.
 *
 * It runs as a `query_scope` on the search tag's query, whose conditions filter the
 * hits before pagination, so the page counts only what it lists. A reader who may
 * open a private entry (a super user, or one who may publish its collection) still
 * finds it.
 */
final class HidePrivateSearchResults extends Scope
{
    /**
     * Scope handle the `/search` results listing passes as `query_scope`.
     *
     * @var string
     */
    protected static $handle = 'hide_private_search_results';

    /**
     * @param  PrivateEntryAccess  $privateEntryAccess  Decides whether this reader may open a `private` entry
     */
    public function __construct(
        private readonly PrivateEntryAccess $privateEntryAccess
    ) {}

    /**
     * Drop the hits that are, or belong to, a `private` entry this reader may not open.
     *
     * @param  mixed  $query  The search query builder
     * @param  mixed  $values  The tag parameters (unused)
     */
    public function apply(
        mixed $query,
        mixed $values
    ): void {
        // Stop when the scope is not applied to a Statamic query builder
        if (! $query instanceof Builder) {
            return;
        }

        // Collect the private entries this reader may not open
        $hiddenIds = $this->hiddenEntryIds();

        // Nothing to hide: leave the query untouched
        if ($hiddenIds === []) {
            return;
        }

        // Drop an entry hit by its own id, and a section subpage by its Area of Expertise
        $query->whereNotIn('id', $hiddenIds);
        $query->whereNotIn(AreaOfExpertiseSectionDocument::AREA_OF_EXPERTISE_ID_FIELD, $hiddenIds);
    }

    /**
     * The ids of every `private` entry the current reader may not open.
     *
     * @return list<string>
     */
    private function hiddenEntryIds(): array
    {
        // Read every private entry; `value()` resolves a localization's inherited status
        /** @var EntryQueryBuilder $lookup */
        $lookup = EntryFacade::query();
        $lookup->where('status_and_visibility', 'private');

        /** @var EntryCollection<int, Entry> $privateEntries */
        $privateEntries = $lookup->get();

        // Keep only the ones this reader is refused
        $ids = [];
        foreach ($privateEntries as $entry) {
            if ($this->privateEntryAccess->canView($entry)) {
                continue;
            }

            $id = $entry->id();
            if (\is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
