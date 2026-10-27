<?php

declare(strict_types=1);

namespace App\Services\Redirects;

use App\Http\Controllers\Feed\PostsFeedController;
use App\Support\Redirects\MigrationRedirect;
use App\Support\Redirects\RedirectSource;
use App\Tags\FindRedirectRule;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;
use Statamic\Assets\QueryBuilder as AssetQueryBuilder;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Entries\Collection as EntriesCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\AssetContainer as AssetContainerFacade;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Generates the WordPress → Statamic migration redirect map (UAMS-Web/uams-statamic-archive#1641).
 *
 * The migration changes URLs; any production URL that no longer resolves would
 * 404 and forfeit its SEO equity. This generator produces the redirect map that
 * closes that gap, from data the importer already stamped — every imported entry
 * and asset carries its `original_wordpress_url` — so it needs no external source
 * to fetch and is safe to re-run after any re-import.
 *
 * It writes into the shared `redirects` store beside the human-authored marketing
 * redirects (UAMS-Web/uams-statamic-archive#1355) and the importer's Safe Redirect Manager rules
 * ({@see FindRedirectRule} serves them all). It emits three
 * dispositions:
 *
 *   - **Entry / asset moves** — a surviving entity whose Statamic URL differs from
 *     its original WordPress URL gets a `301` old → new.
 *   - **Retired-ancestor moves** — a retired permalink with a surviving ancestor
 *     gets a `301` to that ancestor (via {@see NotMigratedRedirectResolver}).
 *   - **Gone** — a retired permalink with no surviving ancestor is declared `410`.
 *
 * Every run is idempotent: a candidate whose source an existing redirect already
 * covers is skipped, so re-running after a re-import adds nothing new and never
 * clobbers a human-authored rule for the same path.
 *
 * Structural WordPress URLs that are not `<item>` permalinks — date and author
 * archives, pagination, feeds, attachment pages, and `?p=`/`?page_id=` ugly
 * permalinks — are synthesized rather than read off an entity, because nothing
 * stores them (UAMS-Web/uams-statamic-archive#1657). {@see StructuralRedirectSynthesizer} owns those URL-shape
 * rules; this class supplies it the importer-stamped metadata
 * (`wordpress_post_id`, `wordpress_author_login`,
 * `wordpress_attachment_page_url`) it derives them from, and folds the result
 * through the same idempotent, collision-safe append as everything else.
 */
class MigrationRedirectGenerator
{
    /**
     * The site's outbound RSS feed — the WordPress `/feed/` analog, registered at
     * `{site}/feed.xml` by {@see PostsFeedController} for every site the `posts`
     * collection is enabled on (UAMS-Web/uams-statamic-archive#1042).
     */
    private const string SITE_FEED_URL = '/feed.xml';

    /**
     * @param  MarketingRedirectStore  $store  Reads existing rules and appends the generated ones
     * @param  NotMigratedRedirectResolver  $resolver  Dispositions retired permalinks by rule
     * @param  StructuralRedirectSynthesizer  $synthesizer  Plans the synthesized structural URL shapes
     */
    public function __construct(
        private readonly MarketingRedirectStore $store,
        private readonly NotMigratedRedirectResolver $resolver,
        private readonly StructuralRedirectSynthesizer $synthesizer,
    ) {}

    /**
     * Generate the migration redirect map and (unless dry-running) persist it.
     *
     * @param  bool  $dryRun  When true, plan the rules but write nothing
     * @param  list<string>  $retiredPaths  Retired WordPress permalinks to disposition (301-to-ancestor / 410)
     * @param  list<array{source: string, target: string, notes: string|null, source_wordpress_post_id?: int|null, target_wordpress_post_id?: int|null}>  $dedupPairs  The WXR cleaner's duplicate redirect pairs (UAMS-Web/uams-statamic-archive#2943)
     * @param  string|null  $dedupSite  The site the pairs' posts were imported into, which scopes a pair's post id (UAMS-Web/uams-statamic-archive#2960)
     * @return MigrationRedirectReport The categorized rules produced and the skipped-existing count
     */
    public function generate(bool $dryRun = false, array $retiredPaths = [], array $dedupPairs = [], ?string $dedupSite = null): MigrationRedirectReport
    {
        // Seed the dedup set with sources an existing redirect already covers, so a
        // re-run is idempotent and a human-authored rule is never overwritten.
        $seen = $this->existingSourceKeys();
        $skipped = 0;

        // Build the surviving-URL predicate once and lazily: it costs a full entry
        // scan, and neither the structural fallback nor the retired disposition
        // necessarily needs it.
        $survives = $this->survivingUrlPredicate();

        // Plan each disposition, deduping every candidate against what is already seen.
        $entryMoves = $this->dedupe($this->planEntryRedirects(), $seen, $skipped);
        $dedup = $this->planDedupRedirects($dedupPairs, $dedupSite);
        $dedupMoves = $this->dedupe($dedup['moves'], $seen, $skipped);
        $assetMoves = $this->dedupe($this->planAssetRedirects(), $seen, $skipped);
        $structural = $this->dedupe($this->planStructuralRedirects($survives), $seen, $skipped);
        $retired = $this->dedupe($this->resolveRetired($retiredPaths, $survives), $seen, $skipped);

        // Split the retired dispositions into 301-to-ancestor moves and 410s.
        $ancestorMoves = array_values(array_filter($retired, static fn (MigrationRedirect $r): bool => $r->response === '301'));
        $gone = array_values(array_filter($retired, static fn (MigrationRedirect $r): bool => $r->response === '410'));

        // Persist every planned row in one write unless this is a dry run.
        $rows = array_map(
            static fn (MigrationRedirect $r): array => $r->toRow(),
            [...$entryMoves, ...$dedupMoves, ...$assetMoves, ...$structural, ...$ancestorMoves, ...$gone],
        );

        if (! $dryRun && $rows !== []) {
            $this->store->appendMany($rows);
        }

        return new MigrationRedirectReport(
            entryMoves: $entryMoves,
            assetMoves: $assetMoves,
            structural: $structural,
            ancestorMoves: $ancestorMoves,
            gone: $gone,
            skippedExisting: $skipped,
            written: ! $dryRun && $rows !== [],
            dedupMoves: $dedupMoves,
            dedupRefused: $dedup['refused'],
        );
    }

    /**
     * Plan every synthesized structural WordPress URL (UAMS-Web/uams-statamic-archive#1657).
     *
     * Walks the imported entities once to gather the importer-stamped metadata the
     * shapes derive from — `wordpress_post_id` for ugly permalinks, the entry date
     * for date archives, `wordpress_author_login` for author archives, and
     * `wordpress_attachment_page_url` for attachment pages — then hands each to
     * {@see StructuralRedirectSynthesizer}. The site feeds and pagination need no
     * per-entity metadata and are planned from the routing config.
     *
     * Only entities carrying an `original_wordpress_url` contribute, so a natively
     * authored Statamic entry never has WordPress archives synthesized for it. The
     * site-wide catch-alls are further gated on the `posts` collection existing:
     * feeds and pagination are what a WordPress *blog* served, so an install
     * without one has nothing of that shape to redirect.
     *
     * Sources are paths, so a structural URL every WordPress subsite served — the
     * date archives, `/feed`, and a post id reused across subsites — resolves to
     * one rule, first-wins, exactly as the deterministic core's own path-keyed
     * sources already do.
     *
     * @param  Closure(string): bool  $survives  Whether a candidate path still resolves
     * @return list<MigrationRedirect> The planned structural redirects
     */
    public function planStructuralRedirects(Closure $survives): array
    {
        $plans = [];
        $dates = [];
        $logins = [];

        // Walk one collection at a time so a large import never loads every entry at once.
        foreach (CollectionFacade::handles() as $handle) {
            if (! \is_string($handle)) {
                continue;
            }

            foreach ($this->entriesIn($handle) as $entry) {
                // The import marker: without it this entry never had WordPress URLs.
                if ($this->stringData($entry->get('original_wordpress_url')) === null) {
                    continue;
                }

                $url = $this->stringData($entry->url());

                // The ugly permalink resolves straight to the entry, so it only needs
                // the stamped id and a URL to land on.
                $postId = $this->intData($entry->get('wordpress_post_id'));

                if ($postId !== null && $url !== null) {
                    $plan = $this->synthesizer->queryPermalink($postId, $url);

                    if ($plan instanceof MigrationRedirect) {
                        $plans[] = $plan;
                    }
                }

                // A date is only present on a dated collection's entries — exactly the
                // ones WordPress served date archives for.
                $date = $entry->date();

                if ($date instanceof DateTimeInterface) {
                    $dates[] = $date;
                }

                $login = $this->stringData($entry->get('wordpress_author_login'));

                if ($login !== null) {
                    $logins[] = $login;
                }
            }
        }

        // Attachment pages come off the assets, whose stamped page URL is the only
        // record that they existed.
        $plans = [...$plans, ...$this->planAttachmentPageRedirects()];

        // The blog is what WordPress served feeds and pagination from, so the
        // site-wide catch-alls are only planned where one exists. Its mount, when it
        // has one, is also the app's own record of where the posts archive lives.
        /** @var CollectionContract|null $posts */
        $posts = CollectionFacade::findByHandle('posts');
        $archiveUrl = $this->archiveUrl($posts);

        // Archives have no Statamic equivalent: prefer the posts index when the
        // collection is mounted, else disposition through the retired-permalink rule
        // (nearest surviving ancestor, else 410) rather than a home-page soft-404.
        $disposition = fn (string $path, string $notes): MigrationRedirect => $archiveUrl === null
            ? $this->resolver->resolve($path, $survives)
            : MigrationRedirect::moved($path, $archiveUrl, $notes);

        $siteWide = $posts === null
            ? []
            : [
                ...$this->synthesizer->feeds(self::SITE_FEED_URL),
                ...$this->synthesizer->pagination($archiveUrl),
            ];

        return [
            ...$plans,
            ...$this->synthesizer->dateArchives($dates, $disposition),
            ...$this->synthesizer->authorArchives($logins, $disposition),
            ...$siteWide,
        ];
    }

    /**
     * Plan a `301` from every imported asset's WordPress attachment *page* to the
     * migrated asset (UAMS-Web/uams-statamic-archive#1657).
     *
     * @return list<MigrationRedirect> The planned attachment-page moves
     */
    public function planAttachmentPageRedirects(): array
    {
        $plans = [];

        /** @var Collection<int, AssetContainer> $containers */
        $containers = AssetContainerFacade::all();

        // Walk one container at a time for the same memory reason as entries.
        foreach ($containers as $container) {
            /** @var AssetQueryBuilder $query */
            $query = $container->queryAssets();

            /** @var Collection<int, Asset> $assets */
            $assets = $query->get();

            foreach ($assets as $asset) {
                // No stamped attachment page: this asset never had one to redirect.
                $pageUrl = $this->stringData($asset->get('wordpress_attachment_page_url'));

                if ($pageUrl === null) {
                    continue;
                }

                // No resolvable asset URL: nowhere to send the visitor.
                $assetUrl = $this->stringData($asset->url());

                if ($assetUrl === null) {
                    continue;
                }

                $plan = $this->synthesizer->attachmentPage($pageUrl, $assetUrl);

                if ($plan instanceof MigrationRedirect) {
                    $plans[] = $plan;
                }
            }
        }

        return $plans;
    }

    /**
     * Plan a `301` for every imported entry whose Statamic URL differs from its
     * original WordPress URL.
     *
     * @return list<MigrationRedirect> The planned entry moves
     */
    public function planEntryRedirects(): array
    {
        $plans = [];

        // Walk one collection at a time so a large import never loads every entry at once.
        foreach (CollectionFacade::handles() as $handle) {
            if (! \is_string($handle)) {
                continue;
            }

            foreach ($this->entriesIn($handle) as $entry) {
                $plan = $this->planMove(
                    $this->stringData($entry->get('original_wordpress_url')),
                    $this->stringData($entry->url()),
                    sprintf('Imported entry %s URL changed in migration.', $this->stringData($entry->id()) ?? 'unknown'),
                );

                if ($plan instanceof MigrationRedirect) {
                    $plans[] = $plan;
                }
            }
        }

        return $plans;
    }

    /**
     * Plan a `301` from each dropped duplicate's WordPress path to its canonical entry (UAMS-Web/uams-statamic-archive#2943).
     *
     * The WXR cleaner drops all but one post of each duplicate set before import (#798) and
     * writes a pair per dropped post: its WordPress URL, and the kept post's. A dropped post is
     * never imported, so {@see planEntryRedirects()} never sees it, and only this app can name
     * the kept post's Statamic URL, through the entry whose `original_wordpress_url` is the
     * pair's target. Sending the dropped URL straight there costs one hop rather than two.
     *
     * A pair that carries the kept post's WordPress post id is resolved by that id, to the entry
     * whose `wordpress_post_id` it is (UAMS-Web/uams-statamic-archive#2960). An entry's `original_wordpress_url` is the export's
     * `<link>`, on the host the export was taken from and sometimes in the `?p=` form, so it need
     * not equal the public permalink a pair names. Post ids repeat across WordPress subsites, so
     * `$site` names the site the pairs' posts were imported into and only its entries are
     * searched; with no site every entry is. A pair without the id is resolved by URL as before.
     *
     * A pair is refused, reported and not planned, when its source is listed with more than
     * one target, when its target is itself a dropped post (by URL, or by its post id being a
     * dropped post's id), when no entry carries its target (by id when it has one, never by URL
     * instead), when more than one entry carries its target's id, or when the kept entry's
     * Statamic URL is a dropped post's path, which would send a redirect back to a URL that
     * redirects (a loop, or a self-redirect). URLs are compared by host and case-insensitive
     * path, so the scheme, a leading `www.` and a trailing slash never decide a match.
     *
     * @param  list<array{source: string, target: string, notes: string|null, source_wordpress_post_id?: int|null, target_wordpress_post_id?: int|null}>  $pairs  The cleaner's duplicate pairs
     * @param  string|null  $site  The site whose entries a pair's post id is looked up among, or null for every site
     * @return array{moves: list<MigrationRedirect>, refused: list<array{source: string, target: string, reason: string}>}
     */
    public function planDedupRedirects(array $pairs, ?string $site = null): array
    {
        $moves = [];
        $refused = [];

        // Without pairs, skip the walk over every entry below; the loop would return the same
        // empty answer, so removing this return changes only the cost.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($pairs === []) {
            return ['moves' => $moves, 'refused' => $refused];
        }

        // Every dropped URL, its targets, its path and its post id, before any pair is judged
        $targetsBySource = [];
        $droppedPaths = [];
        $droppedPostIds = [];
        $anyTargetId = false;

        foreach ($pairs as $pair) {
            // Both maps are read only by key (isset() and count()), so the stored value is a
            // marker and false would serve as well as true.
            // @pest-mutate-ignore: TrueToFalse
            $targetsBySource[$this->wordpressUrlKey($pair['source'])][$this->wordpressUrlKey($pair['target'])] = true;
            // @pest-mutate-ignore: TrueToFalse
            $droppedPaths[RedirectSource::collisionKey($this->pathOf($pair['source']))] = true;

            $sourceId = $pair['source_wordpress_post_id'] ?? null;

            if ($sourceId !== null) {
                // @pest-mutate-ignore: TrueToFalse
                $droppedPostIds[$sourceId] = true;
            }

            $anyTargetId = $anyTargetId || ($pair['target_wordpress_post_id'] ?? null) !== null;
        }

        $entryUrls = $this->entryUrlsByWordpressUrl();

        // Only built when a pair can use it: it costs another walk over every entry
        $entryUrlsById = $anyTargetId ? $this->entryUrlsByWordpressPostId($site) : [];

        foreach ($pairs as $pair) {
            $sourceKey = $this->wordpressUrlKey($pair['source']);
            $targetKey = $this->wordpressUrlKey($pair['target']);
            $targetId = $pair['target_wordpress_post_id'] ?? null;

            $refuse = static fn (string $reason): array => ['source' => $pair['source'], 'target' => $pair['target'], 'reason' => $reason];

            if (\count($targetsBySource[$sourceKey]) > 1) {
                $refused[] = $refuse('the source is listed with more than one target');

                continue;
            }

            if (isset($targetsBySource[$targetKey]) || ($targetId !== null && isset($droppedPostIds[$targetId]))) {
                $refused[] = $refuse('the target is itself a dropped duplicate');

                continue;
            }

            if ($targetId !== null) {
                // By id: a miss is reported, never retried by URL, which is the comparison
                // the id exists to replace
                $candidates = $entryUrlsById[$targetId] ?? [];

                if ($candidates === []) {
                    $refused[] = $refuse(sprintf("no entry has the target's WordPress post id %d", $targetId));

                    continue;
                }

                if (\count($candidates) > 1) {
                    $refused[] = $refuse(sprintf("more than one entry has the target's WordPress post id %d", $targetId));

                    continue;
                }

                $entryUrl = $candidates[0];
            } else {
                $entryUrl = $entryUrls[$targetKey] ?? null;

                if ($entryUrl === null) {
                    $refused[] = $refuse('no entry has the target as its original_wordpress_url');

                    continue;
                }
            }

            if (isset($droppedPaths[RedirectSource::collisionKey($entryUrl)])) {
                $refused[] = $refuse("the target entry's URL is a dropped duplicate's path, so the redirect would loop");

                continue;
            }

            $moves[] = MigrationRedirect::moved(
                $this->pathOf($pair['source']),
                $entryUrl,
                $pair['notes'] ?? sprintf('Dropped duplicate of %s (#798).', $pair['target']),
            );
        }

        return ['moves' => $moves, 'refused' => $refused];
    }

    /**
     * Every routable entry's Statamic URL, keyed by its `original_wordpress_url` (UAMS-Web/uams-statamic-archive#2943).
     *
     * @return array<string, string> {@see wordpressUrlKey()} => the entry's current URL
     */
    private function entryUrlsByWordpressUrl(): array
    {
        $urls = [];

        foreach (CollectionFacade::handles() as $handle) {
            if (! \is_string($handle)) {
                continue;
            }

            foreach ($this->entriesIn($handle) as $entry) {
                $original = $this->stringData($entry->get('original_wordpress_url'));
                $url = $this->stringData($entry->url());

                if ($original !== null && $url !== null) {
                    $urls[$this->wordpressUrlKey($original)] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * Every imported, routable entry's Statamic URLs, keyed by its `wordpress_post_id` (UAMS-Web/uams-statamic-archive#2960).
     *
     * A list per id, so an id two entries carry is seen as ambiguous rather than resolved to
     * whichever was read last.
     *
     * @param  string|null  $site  Only this site's entries, or every site's when null
     * @return array<int, list<string>> WordPress post id => the Statamic URLs of the entries carrying it
     */
    private function entryUrlsByWordpressPostId(?string $site): array
    {
        $urls = [];

        foreach (CollectionFacade::handles() as $handle) {
            if (! \is_string($handle)) {
                continue;
            }

            foreach ($this->entriesIn($handle) as $entry) {
                if ($site !== null && $entry->locale() !== $site) {
                    continue;
                }

                // The import marker, as for every other disposition here
                if ($this->stringData($entry->get('original_wordpress_url')) === null) {
                    continue;
                }

                $postId = $this->intData($entry->get('wordpress_post_id'));
                $url = $this->stringData($entry->url());

                if ($postId !== null && $url !== null) {
                    $urls[$postId][] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * A WordPress URL's comparison key: its host, less any leading `www.`, and its
     * case-insensitive path, so the scheme and a trailing slash never decide a match.
     */
    private function wordpressUrlKey(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        // The key is only ever compared with another key built here, so any fixed stand-in for
        // a missing host, and either order of the two parts, compares the same.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $host = \is_string($host) ? mb_strtolower($host) : '';

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, \strlen('www.'));
        }

        // @pest-mutate-ignore: ConcatSwitchSides
        return $host.RedirectSource::collisionKey($this->pathOf($url));
    }

    /**
     * Plan a `301` for every imported asset whose Statamic URL differs from its
     * original WordPress media URL.
     *
     * @return list<MigrationRedirect> The planned asset moves
     */
    public function planAssetRedirects(): array
    {
        $plans = [];

        /** @var Collection<int, AssetContainer> $containers */
        $containers = AssetContainerFacade::all();

        // Walk one container at a time for the same memory reason as entries.
        foreach ($containers as $container) {
            /** @var AssetQueryBuilder $query */
            $query = $container->queryAssets();

            /** @var Collection<int, Asset> $assets */
            $assets = $query->get();

            foreach ($assets as $asset) {
                $plan = $this->planMove(
                    $this->stringData($asset->get('original_wordpress_url')),
                    $this->stringData($asset->url()),
                    sprintf('Imported asset %s URL changed in migration.', $this->stringData($asset->path()) ?? 'unknown'),
                );

                if ($plan instanceof MigrationRedirect) {
                    $plans[] = $plan;
                }
            }
        }

        return $plans;
    }

    /**
     * Disposition each retired WordPress permalink against the set of surviving
     * Statamic URLs, via the ancestor/410 rule.
     *
     * @param  list<string>  $retiredPaths  The retired permalinks to disposition
     * @param  Closure(string): bool|null  $survives  Whether a candidate ancestor still resolves (built here when omitted)
     * @return list<MigrationRedirect> The 301-to-ancestor and 410 dispositions
     */
    public function resolveRetired(array $retiredPaths, ?Closure $survives = null): array
    {
        // Nothing to disposition — skip the (potentially large) surviving-URL scan.
        if ($retiredPaths === []) {
            return [];
        }

        // Reuse the caller's predicate when it has one, so a single generate() run
        // scans the surviving URLs at most once.
        $survives ??= $this->survivingUrlPredicate();

        return array_map(
            fn (string $path): MigrationRedirect => $this->resolver->resolve($path, $survives),
            $retiredPaths,
        );
    }

    /**
     * Build the "does this path still resolve?" predicate, scanning lazily.
     *
     * The surviving-URL set costs a full entry walk, and a run whose archives all
     * land on a mounted posts index and whose retired list is empty never asks the
     * question — so the scan is deferred until the first call and then memoized for
     * the rest of the run.
     *
     * @return Closure(string): bool The surviving-path predicate
     */
    private function survivingUrlPredicate(): Closure
    {
        /** @var array<string, true>|null $surviving */
        $surviving = null;

        return function (string $path) use (&$surviving): bool {
            $surviving ??= $this->survivingUrlKeys();

            return isset($surviving[RedirectSource::collisionKey($path)]);
        };
    }

    /**
     * The URL of the posts archive index, when the collection is mounted on a page.
     *
     * WordPress date, author and pagination URLs all listed posts, so the site's
     * own posts index is their honest destination. The mount is the only
     * app-authoritative record of where that index lives; without one there is no
     * page to send them to and the caller dispositions them instead.
     *
     * @param  CollectionContract|null  $posts  The posts collection, or null when absent
     * @return string|null The posts index URL, or null when the collection is absent or unmounted
     */
    private function archiveUrl(?CollectionContract $posts): ?string
    {
        // `mount()` is on the Stache implementation, not the contract, so narrow to
        // it rather than assuming every Collection implementation carries a mount.
        if (! $posts instanceof EntriesCollection) {
            return null;
        }

        /** @var Entry|null $mount */
        $mount = $posts->mount();

        return $mount === null ? null : $this->stringData($mount->url());
    }

    /**
     * Query every entry in a collection.
     *
     * @param  string  $handle  The collection handle
     * @return Collection<int, Entry> The collection's entries
     */
    private function entriesIn(string $handle): Collection
    {
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();

        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', $handle);

        /** @var Collection<int, Entry> $entries */
        $entries = $query->get();

        return $entries;
    }

    /**
     * Plan a single move, or null when there is nothing to redirect.
     *
     * Returns null when the original URL is missing, the entity is not routable
     * (no current URL), or the URL did not actually change — in each case a
     * redirect would be pointless or a self-redirect.
     *
     * @param  string|null  $originalUrl  The entity's original WordPress URL
     * @param  string|null  $currentUrl  The entity's current Statamic URL
     * @param  string  $notes  Provenance recorded on the row
     * @return MigrationRedirect|null The planned move, or null when none is warranted
     */
    private function planMove(?string $originalUrl, ?string $currentUrl, string $notes): ?MigrationRedirect
    {
        // No original URL, or nowhere to send it: nothing to plan.
        if ($originalUrl === null || $currentUrl === null || $currentUrl === '') {
            return null;
        }

        // Reduce the original WordPress URL to its path component, canonicalized.
        $source = RedirectSource::normalize($this->pathOf($originalUrl));

        // Never redirect the bare site root. The imported WordPress front page's
        // `original_wordpress_url` reduces to `/`, but a `301 /` is not a moved URL —
        // it would hijack the home page on a site with no home entry. Skip it.
        if ($source === '/') {
            return null;
        }

        // A URL that did not change is a self-redirect — skip it.
        if ($source === RedirectSource::normalize($currentUrl)) {
            return null;
        }

        return MigrationRedirect::moved($source, $currentUrl, $notes);
    }

    /**
     * Drop candidates whose source is already seen, recording how many were skipped.
     *
     * The first rule for a given case-insensitive source wins; later duplicates
     * (including those already in the store) are counted as skipped and dropped.
     *
     * @param  list<MigrationRedirect>  $candidates  The planned rules to filter
     * @param  array<string, true>  $seen  The running set of source keys (mutated)
     * @param  int  $skipped  The running skipped count (mutated)
     * @return list<MigrationRedirect> The kept, deduped rules
     */
    private function dedupe(array $candidates, array &$seen, int &$skipped): array
    {
        $kept = [];

        foreach ($candidates as $candidate) {
            $key = $candidate->collisionKey();

            // A source already covered by an existing or earlier rule is skipped.
            if (isset($seen[$key])) {
                $skipped++;

                continue;
            }

            $seen[$key] = true;
            $kept[] = $candidate;
        }

        return $kept;
    }

    /**
     * The case-insensitive source keys of every redirect already in the store.
     *
     * @return array<string, true> The existing source collision keys
     */
    private function existingSourceKeys(): array
    {
        $keys = [];

        foreach ($this->store->all() as $row) {
            $source = $row['source'] ?? null;

            if (\is_string($source) && $source !== '') {
                // Key through the value object so a stored *pattern* rule (UAMS-Web/uams-statamic-archive#1657) is
                // keyed the same way the planner keys it — otherwise a re-run would
                // normalize the pattern into a different key and re-add it every time.
                $keys[MigrationRedirect::keyFor($source, (bool) ($row['use_regex'] ?? false))] = true;
            }
        }

        return $keys;
    }

    /**
     * The case-insensitive path keys of every surviving Statamic URL.
     *
     * @return array<string, true> The surviving URL collision keys
     */
    private function survivingUrlKeys(): array
    {
        $keys = [];

        // Every routable entry's current URL survives.
        foreach (CollectionFacade::handles() as $handle) {
            if (! \is_string($handle)) {
                continue;
            }

            foreach ($this->entriesIn($handle) as $entry) {
                $url = $this->stringData($entry->url());

                if ($url !== null) {
                    $keys[RedirectSource::collisionKey($url)] = true;
                }
            }
        }

        return $keys;
    }

    /**
     * Coerce a loose data value to a non-empty string, or null.
     *
     * @param  mixed  $value  The raw data value (loose key, so untyped)
     * @return string|null The trimmed string, or null when empty/non-string
     */
    private function stringData(mixed $value): ?string
    {
        if (! \is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Coerce a loose data value to a positive integer, or null.
     *
     * The importer stamps `wordpress_post_id` as an integer, but a hand-edited or
     * re-serialized entry can carry the digits as a string — accept both, and
     * reject anything that could not be a WordPress post id.
     *
     * @param  mixed  $value  The raw data value (loose key, so untyped)
     * @return int|null The positive integer, or null when the value is not one
     */
    private function intData(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (\is_string($value) && ctype_digit(trim($value))) {
            $id = (int) trim($value);

            return $id > 0 ? $id : null;
        }

        return null;
    }

    /**
     * Reduce a URL (absolute or relative) to its path component.
     *
     * @param  string  $url  The URL to reduce
     * @return string The path component (defaults to the input when unparseable)
     */
    private function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return \is_string($path) && $path !== '' ? $path : $url;
    }
}
