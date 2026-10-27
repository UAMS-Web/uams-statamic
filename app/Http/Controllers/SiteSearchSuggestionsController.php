<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Search\AreaOfExpertiseSectionDocument;
use App\Support\Entries\PrivateEntryAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Contracts\Search\Result;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Search\Index;
use Statamic\Search\QueryBuilder;
use Statamic\Sites\Site as SiteInstance;

/**
 * Type-ahead suggestions for the site-search input on `/search` (UAMS-Web/uams-statamic-archive#1506).
 *
 * Answers the question the `/search` page's `{{ search:results }}` tag answers for
 * the same index (the page's active type tab, or the unified `default` index), the
 * current site and published items, but returns a short JSON list the input's
 * flyout renders as the reader types. It does not replace the page: selecting "See
 * all results", or submitting the form, still lands on `/search`.
 *
 * The index, site and status constraints mirror `Statamic\Search\Tags::results()`.
 * A `private` entry is dropped unless the reader may see it, as `ProtectEntry`
 * decides for the entry's own URL; the page applies the same rule through the
 * `hide_private_search_results` scope (UAMS-Web/uams-statamic-archive#2634). One thing is stricter than the page:
 * a hit with no public URL is dropped, since a suggestion is a link.
 */
final class SiteSearchSuggestionsController
{
    /**
     * The fewest characters worth querying, so a single keystroke does not scan the
     * whole index.
     */
    public const int MIN_QUERY_LENGTH = 2;

    /**
     * The longest query accepted. A longer one returns no suggestions rather than an
     * error, because the flyout has nothing useful to show for it anyway.
     */
    public const int MAX_QUERY_LENGTH = 100;

    /**
     * How many suggestions the flyout shows.
     */
    public const int LIMIT = 8;

    /**
     * The indexes a suggestion may come from: the `/search` page's type tabs, whose
     * handles its template validates the same way. Anything else falls back to
     * `default`, so a tampered `index` can never reach an unconfigured one.
     *
     * @var list<string>
     */
    public const array INDEXES = ['default', 'people', 'clinical_expertise', 'clinical_locations', 'clinical_resources', 'posts'];

    /**
     * The result-type label for each collection, matching `search/_result.antlers.html`
     * so the flyout and the results page name a type the same way.
     *
     * @var array<string, string>
     */
    public const array TYPE_LABELS = [
        'clinical_expertise' => 'Area of Expertise',
        'people' => 'Person',
        'posts' => 'News',
        'clinical_locations' => 'Clinical Location',
        'clinical_resources' => 'Clinical Resource',
        'knowledge_bases' => 'Knowledge Base',
        'pages' => 'Page',
    ];

    /**
     * @param  PrivateEntryAccess  $privateEntryAccess  Decides whether this reader may see a `private` entry
     */
    public function __construct(private readonly PrivateEntryAccess $privateEntryAccess) {}

    /**
     * Return up to {@see LIMIT} suggestions for `?q=`, from `?index=` when it is one of
     * {@see INDEXES}.
     *
     * @param  Request  $request  The incoming request, carrying `q` and optionally `index`
     * @return JsonResponse `{ query, index, results: [{ title, url, type }] }`
     */
    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->query('q');
        $query = \is_string($raw) ? trim($raw) : '';
        $length = mb_strlen($query);

        $requestedIndex = $request->query('index');
        $index = \is_string($requestedIndex) && \in_array($requestedIndex, self::INDEXES, true) ? $requestedIndex : 'default';

        $suggestions = [];

        if ($length >= self::MIN_QUERY_LENGTH && $length <= self::MAX_QUERY_LENGTH) {
            $suggestions = $this->suggest($query, $index);
        }

        return new JsonResponse([
            'query' => $query,
            'index' => $index,
            'results' => $suggestions,
        ]);
    }

    /**
     * Run the query against the index and shape each hit for the flyout.
     *
     * Asks for twice {@see LIMIT} and trims after shaping, so a dropped hit (private,
     * or without a URL) does not leave the flyout short while more matches exist.
     *
     * @return list<array{title: string, url: string, type: string}>
     */
    private function suggest(string $query, string $handle): array
    {
        /** @var Index $index */
        $index = Search::index($handle);
        $index->ensureExists();

        /** @var QueryBuilder $builder */
        $builder = $index->search($query);

        if (Site::hasMultiple()) {
            /** @var SiteInstance $site */
            $site = Site::current();
            $builder->whereIn('site', [$site->handle()]);
        }

        $builder->where('status', 'published');
        $builder->limit(self::LIMIT * 2);

        $hits = $builder->get();

        if (! is_iterable($hits)) {
            return [];
        }

        $suggestions = [];

        foreach ($hits as $result) {
            if (! $result instanceof Result) {
                continue;
            }

            $suggestion = $this->shape($result);

            if ($suggestion !== null) {
                $suggestions[] = $suggestion;
            }

            if (\count($suggestions) === self::LIMIT) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * One hit as `{ title, url, type }`, or null when it has no public URL or is a
     * private entry this reader may not see.
     *
     * @return array{title: string, url: string, type: string}|null
     */
    private function shape(Result $result): ?array
    {
        $searchable = $result->getSearchable();

        if ($searchable instanceof AreaOfExpertiseSectionDocument) {
            // A section subpage is as private as its Area of Expertise; the reference is
            // `clinical_expertise_section::<aoe id>::<section>`.
            $aoe = EntryFacade::find(explode('::', $searchable->getSearchReference())[1] ?? '');

            if (! $aoe instanceof Entry || ! $this->visible($aoe)) {
                return null;
            }

            $data = $searchable->augmentedArrayData();

            return $this->suggestion($data['title'], $data['url'], 'Area of Expertise');
        }

        if (! $searchable instanceof Entry || ! $this->visible($searchable)) {
            return null;
        }

        $title = $searchable->value('title');
        $url = $searchable->url();
        $collection = $searchable->collectionHandle();

        return $this->suggestion(
            \is_string($title) ? $title : '',
            \is_string($url) ? $url : '',
            \is_string($collection) ? (self::TYPE_LABELS[$collection] ?? '') : '',
        );
    }

    /**
     * Whether this reader may see the entry, by the rule `ProtectEntry` applies to its
     * URL: a `private` entry only for a reader who can view it. `value()` rather than
     * `get()` so a localization reads its origin's status, as the middleware does.
     */
    private function visible(Entry $entry): bool
    {
        if ($entry->value('status_and_visibility') !== 'private') {
            return true;
        }

        return $this->privateEntryAccess->canView($entry);
    }

    /**
     * Build a suggestion, or null when the title or URL is empty.
     *
     * @return array{title: string, url: string, type: string}|null
     */
    private function suggestion(string $title, string $url, string $type): ?array
    {
        if ($title === '' || $url === '') {
            return null;
        }

        return ['title' => $title, 'url' => $url, 'type' => $type];
    }
}
