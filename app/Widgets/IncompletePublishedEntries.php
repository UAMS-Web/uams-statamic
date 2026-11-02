<?php

declare(strict_types=1);

namespace App\Widgets;

use App\Services\EntryValidation\EntryValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;
use Statamic\Auth\User;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Exceptions\BlueprintNotFoundException;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User as UserFacade;
use Statamic\Fields\Blueprint;
use Statamic\Sites\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Widgets\Widget;

/**
 * CP dashboard widget listing published entries that are missing required fields so
 * editors can find and complete them.
 */
class IncompletePublishedEntries extends Widget
{
    /**
     * Request query key used for pagination page number.
     */
    protected const string PAGE_QUERY_KEY = 'incomplete_entries_page';

    /**
     * Cache key holding the Unix time an entry was last saved (UAMS-Web/uams-statamic-archive#2506).
     *
     * Saving an entry records when content changed, and deliberately does NOT
     * discard the scan payload: the scan costs over a minute to rebuild, so a
     * single publish throwing it away is what kept the dashboard permanently
     * cold. The widget keeps showing the last completed scan and says content
     * has changed since it ran.
     */
    public const string CACHE_CONTENT_CHANGED_KEY = 'incomplete_published_entries_content_changed_at';

    /**
     * The HTML that should be shown in the widget.
     *
     * **This never scans (UAMS-Web/uams-statamic-archive#2506).** It reads the payload the scheduled
     * `cp:scan-incomplete-published-entries` command writes, and on a miss it
     * renders a "not yet computed" state. The scan costs about 90s in a
     * request-shaped run against a 60s request limit, and a request that dies
     * before writing the cache can never warm it, so an inline fallback here is
     * the outage itself. Because the refusal lives in the widget rather than in
     * the dashboard's widget list, it holds however the widget is enabled — the
     * config list or a saved `widgets` preference, which overrides it.
     *
     * @return string|View Rendered widget HTML or the view instance
     */
    #[\Override]
    public function html(): string|View
    {
        // ---
        // Read pagination config; normalize page and per_page.
        // ---
        // Get entries per page and ensure at least 1
        // (Widget::config()'s declared string|Collection return type is inaccurate
        // — it returns the raw config value, which is numeric here.)
        $perPageValue = $this->config('per_page', 25);
        // Any fallback below 1 is replaced by 25 on the next line, so -1 reads as 0.
        // @pest-mutate-ignore: DecrementInteger
        $perPage = \is_scalar($perPageValue) ? (int) $perPageValue : 0;
        $perPage = $perPage < 1 ? 25 : $perPage;

        // Get current page from query string and clamp to positive
        // A default of 0 is clamped back to 1 on the next line.
        // @pest-mutate-ignore: DecrementInteger
        $currentPage = (int) request()->query(self::PAGE_QUERY_KEY, 1);
        $currentPage = max(1, $currentPage);

        // Read the last completed scan, or null when none has run
        $payload = $this->storedScan();

        // Get when content last changed, to say whether the scan predates it
        $changedValue = Cache::get(self::CACHE_CONTENT_CHANGED_KEY);
        // The listener stores time() as an int, and a numeric string compares with
        // `>` exactly as its int would, so dropping the cast is not observable.
        // @pest-mutate-ignore: RemoveIntegerCast
        $contentChangedAt = \is_scalar($changedValue) ? (int) $changedValue : null;

        // Apply the current user's view permissions on EVERY request (never
        // cached), so the shared candidate cache cannot leak entries across users.
        $all = $payload !== null ? $this->visibleFailingList($payload['rows']) : [];

        // ---
        // Paginate the full list and build view data.
        // ---

        // Compute total count and last page
        $total = \count($all);
        $lastPage = max(1, (int) ceil($total / $perPage));

        // Clamp current page to valid range
        $currentPage = min($currentPage, $lastPage);

        // Slice the list to the current page
        $offset = ($currentPage - 1) * $perPage;
        $entries = collect(\array_slice($all, $offset, $perPage));

        // Build pagination metadata for the view
        $pagination = [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'page_query_key' => self::PAGE_QUERY_KEY,
            'base_url' => request()->url(),
            'query' => request()->query(),
        ];

        // Free locals before returning view
        unset($all, $offset);

        // Return widget view. `computed` is false on a miss, which the view renders
        // as its own state rather than as an empty (clean) result.
        return view(
            'widgets.incomplete_published_entries',
            [
                'entries' => $entries,
                'title' => $this->config('title', __('Published Entries With Validation Issues')),
                'pagination' => $pagination,
                'computed' => $payload !== null,
                'scan' => $payload['scan'] ?? null,
                'computed_at' => $payload['computed_at'] ?? null,
                'content_changed_since' => $payload !== null
                    && $contentChangedAt !== null
                    && $contentChangedAt > $payload['computed_at'],
            ]
        );
    }

    /**
     * Run the full scan and store its result for the widget to read (UAMS-Web/uams-statamic-archive#2506).
     *
     * The ONLY path that runs {@see failingCandidateEntryIds()}. Called from the
     * scheduled `cp:scan-incomplete-published-entries` command, outside any web
     * request, where the cold-Stache case (about 163s) has no time limit to hit.
     * The payload is stored without expiry and overwritten by the next run, so it
     * outlives the gap between runs rather than lapsing into a miss.
     *
     * The display rows are built here too, not per request: on real content 330 of
     * 732 published entries fail, and resolving a blueprint and collection for each
     * of them cost about 3.3s of every dashboard load. None of it depends on who is
     * looking, so it is computed once with the scan.
     *
     * @return array{rows: list<array{id: string, edit_url: string, title: string, collection_title: string, blueprint_title: string}>, scan: array{scanned: int, total: int, truncated: bool}, computed_at: int}
     */
    public function refresh(): array
    {
        // Compute the user-AGNOSTIC failing-entry candidate ids (the expensive
        // scan + blueprint validation)
        $result = $this->failingCandidateEntryIds();

        // Load the failing entries in one query, indexed by id, to build their rows
        $entriesById = [];
        foreach ($this->entriesWithIds($result['ids']) as $entry) {
            $entryId = $entry->id();
            if (\is_string($entryId)) {
                $entriesById[$entryId] = $entry;
            }
        }

        // Build one display row per failing entry, in scan order
        $rows = [];
        foreach ($result['ids'] as $id) {
            if (isset($entriesById[$id])) {
                $rows[] = ['id' => $id] + $this->displayRow($entriesById[$id]);
            }
        }

        // Build the payload, stamped with when this scan completed
        $payload = [
            'rows' => $rows,
            'scan' => [
                'scanned' => $result['scanned'],
                'total' => $result['total'],
                'truncated' => $result['truncated'],
            ],
            'computed_at' => time(),
        ];

        // Store it under the shared key until the next run replaces it
        Cache::forever($this->cacheKey(), $payload);

        return $payload;
    }

    /**
     * Read the last completed scan, or null when none is usable.
     *
     * A payload missing `scan` or `computed_at` predates this shape and cannot
     * say what it covered or when, so it is treated as a MISS rather than
     * reported — silence must not read as coverage (UAMS-Web/uams-statamic-archive#1905).
     *
     * @return array{rows: list<array{id: string, edit_url: string, title: string, collection_title: string, blueprint_title: string}>, scan: array{scanned: int, total: int, truncated: bool}, computed_at: int}|null
     */
    protected function storedScan(): ?array
    {
        // Load cached payload for this widget configuration
        $cached = Cache::get($this->cacheKey());

        // Reject anything that is not a complete, current-shape payload
        if (
            ! \is_array($cached)
            || ! isset($cached['rows'], $cached['scan'], $cached['computed_at'])
            || ! \is_array($cached['rows'])
            || ! \is_array($cached['scan'])
            || ! \is_scalar($cached['computed_at'])
        ) {
            return null;
        }

        // Read each key defensively: the payload is whatever the cache store
        // handed back, so a malformed `scan` must degrade to zeroes rather
        // than cast `mixed` and report a fabricated coverage number.
        $cachedScan = $cached['scan'];
        $scannedValue = $cachedScan['scanned'] ?? 0;
        $totalValue = $cachedScan['total'] ?? 0;
        $truncatedValue = $cachedScan['truncated'] ?? false;

        // Keep only rows carrying a string id and the four display strings
        $rows = [];
        foreach ($cached['rows'] as $row) {
            if (! \is_array($row)) {
                continue;
            }

            if (! \is_string($row['id'] ?? null)) {
                continue;
            }

            $rows[] = [
                'id' => $row['id'],
                'edit_url' => \is_string($row['edit_url'] ?? null) ? $row['edit_url'] : '',
                'title' => \is_string($row['title'] ?? null) ? $row['title'] : '',
                'collection_title' => \is_string($row['collection_title'] ?? null) ? $row['collection_title'] : '',
                'blueprint_title' => \is_string($row['blueprint_title'] ?? null) ? $row['blueprint_title'] : '',
            ];
        }

        return [
            'rows' => $rows,
            'scan' => [
                'scanned' => \is_scalar($scannedValue) ? (int) $scannedValue : 0,
                'total' => \is_scalar($totalValue) ? (int) $totalValue : 0,
                'truncated' => (bool) $truncatedValue,
            ],
            'computed_at' => (int) $cached['computed_at'],
        ];
    }

    /**
     * Build the cache key for this widget configuration.
     *
     * **Not scoped by site (UAMS-Web/uams-statamic-archive#2506).** The scan's query applies no site filter and
     * returned a byte-identical id set under every site sampled, so one scan
     * serves the whole install; keying per site would have meant one scheduled
     * scan for each of 255 sites to warm the same answer.
     *
     * @return string Cache key from the hashed collection filter plus limit
     */
    public function cacheKey(): string
    {
        // Include collection filter and scan limit in key
        $collectionHandles = $this->config('collections');

        // Get scan limit from config for cache key. Resolved through the SAME
        // helper the scan uses, so an uncapped scan and a `limit: 0` one cannot
        // collapse onto one key and serve each other's result (UAMS-Web/uams-statamic-archive#1905).
        $limit = $this->configuredScanLimit();

        // Hash config so different widget configs get different keys. The hash only
        // bounds the key's length: the raw JSON would key each config just as
        // distinctly, so unwrapping it (or the cast) changes no observable result.
        // @pest-mutate-ignore: UnwrapMd5, RemoveStringCast
        $configHash = md5((string) json_encode([
            'collections' => $collectionHandles,
            'limit' => $limit,
        ]));

        // Return cache key combining the widget name and config hash
        return sprintf('incomplete_published_entries_%s', $configHash);
    }

    /**
     * Build the display row for one entry: everything the widget table shows, with
     * no Entry object, so it can be stored with the scan.
     *
     * @return array{edit_url: string, title: string, collection_title: string, blueprint_title: string}
     */
    protected function displayRow(StatamicEntry $entry): array
    {
        // Resolve blueprint and collection for their display titles
        /** @var Blueprint|null $blueprint */
        $blueprint = $entry->blueprint();

        /** @var \Statamic\Entries\Collection $collection */
        $collection = $entry->collection();

        // Resolve display values, coercing to string only when scalar
        $editUrlValue = $entry->editUrl();
        $titleValue = $entry->get('title') ?? $entry->slug();
        $collectionTitleValue = $collection->title();
        // A saved entry always resolves a blueprint (Statamic falls back to the
        // collection default), so the null-safe call only satisfies the type.
        // @pest-mutate-ignore: RemoveNullSafeOperator
        $blueprintTitleValue = $blueprint?->title();

        return [
            'edit_url' => $this->displayString($editUrlValue),
            'title' => $this->displayString($titleValue),
            'collection_title' => $this->displayString($collectionTitleValue),
            'blueprint_title' => $this->displayString($blueprintTitleValue),
        ];
    }

    /**
     * Render a Statamic value as display text: a scalar as its string form, anything
     * else (an array title, a missing blueprint) as an empty string.
     */
    protected function displayString(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * Load the entries with these ids in one query (UAMS-Web/uams-statamic-archive#2506).
     *
     * One `find()` per id cost 3.7-5.0s for 330 ids on real content; one query
     * about 1s. Ids that no longer resolve are simply absent from the result.
     *
     * @param  list<string>  $ids
     * @return Collection<int, StatamicEntry>
     */
    protected function entriesWithIds(array $ids): Collection
    {
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();

        /** @var EntryQueryBuilder $query */
        $query = $query->whereIn('id', $ids);

        /** @var Collection<int, StatamicEntry> $entries */
        $entries = $query->get();

        return $entries;
    }

    /**
     * Filter the stored user-agnostic rows down to those the CURRENT user may view.
     * Runs on every request (never cached), so the shared payload cannot leak
     * entries across users (UAMS-Web/uams-statamic-archive#860).
     *
     * The check is Statamic's own entry `view` policy, which reads the entry's site,
     * collection and authors, so it is given real entries rather than reimplemented.
     * They are loaded in ONE query (UAMS-Web/uams-statamic-archive#2506): loading 330 candidates one `find()` at a
     * time cost 3.7-5.0s per dashboard load on real content, and the single query
     * about 1s. A row whose entry no longer resolves is dropped.
     *
     * @param  list<array{id: string, edit_url: string, title: string, collection_title: string, blueprint_title: string}>  $rows  Stored display rows
     * @return array<int, array{edit_url: string, title: string, collection_title: string, blueprint_title: string}> Display rows the current user may view
     */
    protected function visibleFailingList(array $rows): array
    {
        // Resolve the current user; with no user, nothing is viewable
        /** @var User|null $currentUser */
        $currentUser = UserFacade::current();

        if ($currentUser === null || $rows === []) {
            return [];
        }

        // Collect the ids this user may view, through the real policy, loading every
        // candidate in one query
        $viewable = [];
        foreach ($this->entriesWithIds(array_column($rows, 'id')) as $entry) {
            $entryId = $entry->id();
            if (\is_string($entryId) && $currentUser->can('view', $entry)) {
                // Membership is read with isset(), so the stored value is never
                // consulted and flipping it to false is not observable.
                // @pest-mutate-ignore: TrueToFalse
                $viewable[$entryId] = true;
            }
        }

        // Keep viewable rows in scan order, without the id the view does not need
        $visible = [];
        foreach ($rows as $row) {
            if (isset($viewable[$row['id']])) {
                $visible[] = [
                    'edit_url' => $row['edit_url'],
                    'title' => $row['title'],
                    'collection_title' => $row['collection_title'],
                    'blueprint_title' => $row['blueprint_title'],
                ];
            }
        }

        return $visible;
    }

    /**
     * Resolve the configured scan cap.
     *
     * `null` means uncapped — the default since UAMS-Web/uams-statamic-archive#1905, because a capped scan that
     * says nothing about its own coverage reads as a complete audit. An explicit
     * `0` is preserved as "scan nothing" rather than folded in with absent, since
     * that is what `limit(0)` has always done here.
     *
     * (Widget::config()'s declared string|Collection return type is inaccurate —
     * it returns the raw config value, which is numeric here.)
     *
     * @return int|null The cap, or null to audit every published entry
     */
    protected function configuredScanLimit(): ?int
    {
        $limitValue = $this->config('limit');

        return \is_scalar($limitValue) ? (int) $limitValue : null;
    }

    /**
     * Build the published-entry query this widget audits, honoring the optional
     * `collections` filter.
     *
     * Ordered by `id` so a *capped* scan covers a reproducible set rather than
     * whatever the Stache happens to return first. Without this the widget's
     * coverage was luck: UAMS-Web/uams-statamic-archive#1887's three over-limit `bio_short` entries fell inside
     * the first 500 by accident, and nothing pinned that (UAMS-Web/uams-statamic-archive#1905).
     *
     * @return EntryQueryBuilder A fresh query for published entries
     */
    protected function publishedEntriesQuery(): EntryQueryBuilder
    {
        // Get collection filter from config (null or array of handles), normalized
        // to an array (Widget::config()'s declared string|Collection return type is
        // inaccurate — it returns the raw config value).
        $collectionsValue = $this->config('collections');
        // With a key, config() returns the raw value (an array, a scalar, or null here),
        // which collect() wraps exactly as the cast does. The cast exists for the
        // declared return type.
        // @pest-mutate-ignore: RemoveArrayCast
        $collectionHandles = collect((array) $collectionsValue)->all();

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();

        // Restrict query to configured collections when any are set. This MUST
        // precede whereStatus(): Statamic's EntryQueryBuilder enforces that the
        // collection clause comes before the status clause (it throws otherwise).
        if ($collectionHandles !== []) {
            $query->whereIn('collection', $collectionHandles);
        }

        // ---
        // Restrict to published entries only (Stache QueriesEntryStatus; not Search).
        // Statamic 6.9 fixed collection whereStatus logic (statamic/cms#14380).
        // statamic/cms#14387 adds whereStatus on the Search query builder only.
        // ---
        /** @var EntryQueryBuilder $query */
        $query = $query->whereStatus('published');

        /** @var EntryQueryBuilder $ordered */
        $ordered = $query->orderBy('id');

        return $ordered;
    }

    /**
     * Get the ids of published entries that do not pass current blueprint
     * validation, with the scan's own coverage alongside them. User-AGNOSTIC:
     * this is the expensive, cacheable computation (scan + validation). Per-user
     * `view` filtering happens later, on read, in visibleFailingList() — so the
     * shared cache never leaks across users (UAMS-Web/uams-statamic-archive#860).
     *
     * **Uncapped by default (UAMS-Web/uams-statamic-archive#1905).** `limit` used to default to 500 against 732
     * published entries, so 232 were never audited and nothing in the UI said so —
     * a partial audit that read as a clean bill of health. Measured on the real
     * content before removing it: 500 entries took 31.09s and all 732 took 32.91s,
     * so the cap bought about 6% of the scan while hiding 32% of the content. It
     * survives only as an explicit opt-in, and when it truncates, the widget says
     * so rather than presenting the remainder as complete.
     *
     * @return array{ids: list<string>, scanned: int, total: int, truncated: bool}
     *                                                                             Failing ids in scan order, plus what the scan actually covered
     */
    protected function failingCandidateEntryIds(): array
    {
        // Max published entries to scan. `null` (absent) is the new default and
        // means "audit everything" — the only setting under which this widget's
        // answer means what it appears to mean.
        //
        // An explicit `0` is NOT folded into that: it has always meant "scan
        // nothing" (Laravel's `limit(0)` returns no rows) and two tests pin it.
        // Absent and zero are therefore kept distinct rather than both treated as
        // falsy, which is how a config-driven cap turns into a silent no-op.
        $limit = $this->configuredScanLimit();

        // Collect ids of entries that fail validation (user-agnostic)
        /** @var list<string> $failingIds */
        $failingIds = [];

        // Execute query with limit and get entry collection; catch missing blueprint
        try {
            $query = $this->publishedEntriesQuery();

            // Only pay for the total when a cap could actually hide something. A
            // second count() is cheap (no per-entry validation) next to the scan.
            // The uncapped 0 is never read: with no limit, `$total` is replaced by
            // `$scanned` after the loop.
            // @pest-mutate-ignore: DecrementInteger, IncrementInteger
            $countValue = $limit !== null ? $this->publishedEntriesQuery()->count() : 0;
            // count() returns an int, so the cast is a no-op and the fallback is
            // unreachable; both exist for phpstan, which types count() as mixed.
            // @pest-mutate-ignore: RemoveIntegerCast, DecrementInteger, IncrementInteger
            $total = \is_scalar($countValue) ? (int) $countValue : 0;

            if ($limit !== null) {
                /** @var EntryQueryBuilder $query */
                $query = $query->limit($limit);
            }

            /** @var Collection<int, StatamicEntry> $entries */
            $entries = $query->get();
        } catch (BlueprintNotFoundException $blueprintNotFoundException) {
            $total = 0;
            // Log and continue with no entries when an entry references a missing blueprint
            Log::warning(
                'IncompletePublishedEntries: skipped loading entries due to missing blueprint',
                [
                    'message' => $blueprintNotFoundException->getMessage(),
                    'userId' => UserFacade::current()?->getAuthIdentifier(),
                ]
            );
            $entries = collect();
        }

        // Loop through published entries and collect those that fail validation
        foreach ($entries as $entry) {
            /** @var StatamicEntry $entry */

            // Resolve entry blueprint for validation; skip entry if blueprint is missing
            try {
                /** @var Blueprint|null $blueprint */
                $blueprint = $entry->blueprint();
            } catch (BlueprintNotFoundException $e) {
                // Resolve collection for log context (may be null)
                /** @var \Statamic\Entries\Collection|null $entryCollection */
                $entryCollection = $entry->collection();

                // Skip entry when it references a blueprint that no longer exists
                Log::warning(
                    'IncompletePublishedEntries: skipped entry with missing blueprint',
                    [
                        'message' => $e->getMessage(),
                        'entryId' => $entry->id(),
                        'collection' => $entryCollection?->handle(),
                    ]
                );

                continue;
            }

            // ---
            // Skip entries without a blueprint
            // ---

            // Check if the entry has no blueprint
            if ($blueprint === null) {
                // Skip entry when it has no blueprint
                continue;
            }

            // Validate entry against blueprint and get errors
            $validationResult = $this->validateEntry($entry, $blueprint);

            // ---
            // Add to failing list when validation has errors
            // ---

            // Check if validation returned any errors
            if ($validationResult['errors']->isNotEmpty()) {
                // Record the failing entry id (user-agnostic candidate)
                $entryId = $entry->id();
                if (\is_string($entryId)) {
                    $failingIds[] = $entryId;
                }
            }
        }

        // What the scan actually covered. With no cap, `scanned` IS the total, so
        // the widget can state coverage without a second count().
        $scanned = $entries->count();
        $total = $limit !== null ? $total : $scanned;

        // Free locals before return
        unset(
            $entries,
            $limit,
            $query
        );

        // Return the user-agnostic candidate ids alongside the scan's coverage
        return [
            'ids' => $failingIds,
            'scanned' => $scanned,
            'total' => $total,
            'truncated' => $total > $scanned,
        ];
    }

    /**
     * Validate an entry against its blueprint and return errors plus field labels.
     *
     * Delegates to {@see EntryValidator}, which owns the Control Panel's validation and
     * display-condition logic (#275).
     *
     * @param  StatamicEntry  $entry  Entry being checked against the blueprint
     * @param  Blueprint  $blueprint  Blueprint that defines fields, conditions, and rules
     * @return array{errors: MessageBag, attributes: array<string, string>} Validation errors plus attribute labels keyed by field handle
     */
    protected function validateEntry(
        StatamicEntry $entry,
        Blueprint $blueprint
    ): array {
        $result = app(EntryValidator::class)->validate($entry, $blueprint);

        return [
            'errors' => $result->errors,
            'attributes' => $result->attributes,
        ];
    }
}
