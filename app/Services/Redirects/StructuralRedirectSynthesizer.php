<?php

declare(strict_types=1);

namespace App\Services\Redirects;

use App\Http\Middleware\RedirectWordPressQueryPermalinks;
use App\Support\Redirects\MigrationRedirect;
use App\Support\Redirects\RedirectSource;
use App\Tags\FindRedirectRule;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Plans the *structural* WordPress migration redirects (UAMS-Web/uams-statamic-archive#1657).
 *
 * {@see MigrationRedirectGenerator} covers the URLs WordPress served as `<item>`
 * permalinks, because each imported entity carries its own
 * `original_wordpress_url`. WordPress also served a second family of URLs that is
 * derivable from post data but is never an `<item>` row of its own — date and
 * author archives, pagination, feeds, attachment pages, and the `?p=` / `?page_id=`
 * ugly permalinks. Nothing stores those, so they have to be *synthesized*, which
 * is what this class does.
 *
 * It is deliberately free of Statamic: every method takes primitives the generator
 * has already read off an entity (a numeric post id, a date, an author login, a
 * URL) and returns planned {@see MigrationRedirect}s. That keeps the URL-shape
 * rules — the part worth being sure about — unit-testable without content.
 *
 * Two shapes are **unbounded** and therefore planned as PCRE pattern rules rather
 * than one row per URL: `/<anything>/feed/` and `/<archive>/page/<N>/` have no
 * finite enumeration. {@see FindRedirectRule} runs its exact-source pass before it
 * ever consults a pattern, so a catch-all can only ever match a request that
 * matched nothing else — it cannot shadow a live page or a human-authored rule.
 *
 * Targets are only ever URLs that provably resolve. Where a shape has no
 * equivalent that survived the migration, the caller's fallback dispositions it
 * ({@see NotMigratedRedirectResolver} — nearest surviving ancestor, else
 * `410 Gone`) rather than funnelling it to the home page, which would be a soft-404.
 */
final class StructuralRedirectSynthesizer
{
    /**
     * The path prefix WordPress serves author archives under.
     */
    public const string AUTHOR_ARCHIVE_PREFIX = '/author';

    /**
     * Matches any nested WordPress feed — a per-section (`/news/feed/`) or per-post
     * comment feed (`/2019/05/06/a-post/feed/`), in any of the formats WordPress
     * appends. The site-level feeds are enumerable and handled as literal sources
     * by {@see feeds()}; this covers everything below the root.
     */
    public const string NESTED_FEED_PATTERN = '#^/.+/feed(?:/(?:atom|rdf|rss|rss2))?/?$#i';

    /**
     * Matches WordPress pagination — the blog index's `/page/2/` and any archive's
     * `/<archive>/page/2/`. Statamic paginates with a `?page=` query parameter
     * instead, so no path of this shape survives the migration.
     */
    public const string PAGINATION_PATTERN = '#^/(?:.+/)?page/[0-9]+/?$#i';

    /**
     * The site-level feed paths WordPress serves, each an exact source.
     *
     * @var list<string>
     */
    private const array SITE_FEED_PATHS = [
        '/feed',
        '/feed/atom',
        '/feed/rdf',
        '/feed/rss',
        '/feed/rss2',
        '/comments/feed',
    ];

    /**
     * Plan the `301` for a WordPress ugly permalink (`?p=<id>`).
     *
     * WordPress resolves `/?p=<id>` for *any* post type, and its post ids are
     * unique across types, so one rule per entity covers both the `?p=` and
     * `?page_id=` spellings — {@see RedirectWordPressQueryPermalinks} normalizes
     * the latter onto this source rather than storing a second, identical row.
     *
     * @param  int  $postId  The entity's stamped `wordpress_post_id`
     * @param  string  $entryUrl  The entity's current Statamic URL
     * @return MigrationRedirect|null The planned move, or null when either input cannot drive one
     */
    public function queryPermalink(int $postId, string $entryUrl): ?MigrationRedirect
    {
        // A non-positive id is not a WordPress post id, and an entity with no URL
        // has nowhere to send the visitor.
        if ($postId <= 0 || trim($entryUrl) === '') {
            return null;
        }

        return MigrationRedirect::moved(
            self::queryPermalinkSource($postId),
            $entryUrl,
            sprintf('WordPress ugly permalink ?p=%d.', $postId),
        );
    }

    /**
     * The canonical stored source for a WordPress post id's ugly permalink.
     *
     * Shared with the serving middleware so a request's `?p=` / `?page_id=` is
     * looked up under exactly the key the generator wrote.
     *
     * @param  int  $postId  The WordPress post id
     * @return string The canonical `/?p=<id>` source
     */
    public static function queryPermalinkSource(int $postId): string
    {
        return '/?p='.$postId;
    }

    /**
     * Plan the `301` from a WordPress attachment *page* to the migrated asset.
     *
     * WordPress gives every upload two URLs: the file itself (which the core
     * generator already moves from `original_wordpress_url`) and an attachment
     * page that renders it. Only the latter is stamped as
     * `wordpress_attachment_page_url`, and it has no Statamic equivalent — the
     * asset's own URL is the closest surviving destination.
     *
     * @param  string  $attachmentPageUrl  The stamped WordPress attachment-page URL
     * @param  string  $assetUrl  The migrated asset's current URL
     * @return MigrationRedirect|null The planned move, or null when there is nothing to redirect
     */
    public function attachmentPage(string $attachmentPageUrl, string $assetUrl): ?MigrationRedirect
    {
        $target = trim($assetUrl);

        // Nowhere to send the visitor — the asset did not resolve to a URL.
        if ($target === '') {
            return null;
        }

        $source = RedirectSource::normalize($this->pathOf(trim($attachmentPageUrl)));

        // An attachment page that reduces to the bare root is not a real page URL;
        // redirecting `/` would hijack the home page (the same guard the core uses).
        if ($source === '/') {
            return null;
        }

        // The attachment page and the asset already resolve to the same path: a
        // self-redirect. Compared on paths because an asset container with a public
        // URL yields an absolute one, which would never match a bare path.
        if ($source === RedirectSource::normalize($this->pathOf($target))) {
            return null;
        }

        return MigrationRedirect::moved(
            $source,
            $target,
            'WordPress attachment page; 301 to the migrated asset.',
        );
    }

    /**
     * Plan one disposition per distinct WordPress date archive (`/YYYY/`, `/YYYY/MM/`).
     *
     * WordPress lists a month's or year's posts at these paths. Statamic has no
     * date-archive route, so the caller's fallback dispositions each one rather
     * than pointing it at a page that does not exist. Sources are deduped and sorted so
     * a re-run over the same content plans byte-identical rows.
     *
     * @param  list<DateTimeInterface>  $dates  The dates of the imported dated entries
     * @param  Closure(string, string): MigrationRedirect  $disposition  Maps an archive path + provenance note to its redirect
     * @return list<MigrationRedirect> The planned date-archive dispositions
     */
    public function dateArchives(array $dates, Closure $disposition): array
    {
        $paths = [];

        foreach ($dates as $date) {
            // Both levels WordPress serves; the day archive is not a default
            // permalink shape, so it is left to the entry permalinks themselves.
            $paths['/'.$date->format('Y')] = true;
            $paths['/'.$date->format('Y/m')] = true;
        }

        return $this->disposition(
            $paths,
            $disposition,
            'WordPress date archive; no Statamic equivalent.',
        );
    }

    /**
     * Plan one disposition per distinct WordPress author archive (`/author/<nicename>/`).
     *
     * The importer stamps `wordpress_author_login` — WXR's `dc:creator`, which
     * carries the WordPress `user_login` (`Ashley`) — while the archive URL uses the
     * `user_nicename` slug (`ashley`). WXR exports no nicename anywhere, so the slug
     * is derived here with {@see Str::slug()}, WordPress's `sanitize_title()`
     * behavior for the ASCII logins this corpus uses. A login whose slug is empty
     * (punctuation only) yields no archive rather than a bare `/author` rule.
     *
     * @param  list<string>  $logins  The stamped author logins of the imported entries
     * @param  Closure(string, string): MigrationRedirect  $disposition  Maps an archive path + provenance note to its redirect
     * @return list<MigrationRedirect> The planned author-archive dispositions
     */
    public function authorArchives(array $logins, Closure $disposition): array
    {
        $paths = [];

        foreach ($logins as $login) {
            $slug = Str::slug($login);

            // A login whose slug comes out empty cannot name an author archive.
            if ($slug === '') {
                continue;
            }

            $paths[self::AUTHOR_ARCHIVE_PREFIX.'/'.$slug] = true;
        }

        return $this->disposition(
            $paths,
            $disposition,
            'WordPress author archive; no Statamic equivalent.',
        );
    }

    /**
     * Plan the feed redirects — the enumerable site-level feeds plus one catch-all
     * for every nested (per-section, per-post) feed.
     *
     * The app serves one RSS 2.0 feed per site at `{site}/feed.xml` (UAMS-Web/uams-statamic-archive#1042), which
     * is the migration's answer to all of them: a per-post comment feed has no
     * equivalent at all, and a per-section feed's nearest surviving analog is the
     * site feed.
     *
     * @param  string  $feedUrl  The site's current feed URL
     * @return list<MigrationRedirect> The planned feed redirects
     */
    public function feeds(string $feedUrl): array
    {
        $target = trim($feedUrl);

        // With no feed route registered there is no destination to move to.
        if ($target === '') {
            return [];
        }

        $plans = [];

        foreach (self::SITE_FEED_PATHS as $path) {
            $plans[] = MigrationRedirect::moved(
                $path,
                $target,
                'WordPress site feed; 301 to the site RSS feed.',
            );
        }

        // One pattern covers every per-section and per-post feed, which have no
        // finite enumeration to write a row each for.
        $plans[] = MigrationRedirect::movedPattern(
            self::NESTED_FEED_PATTERN,
            $target,
            'WordPress per-section/per-post feed; 301 to the site RSS feed.',
        );

        return $plans;
    }

    /**
     * Plan the pagination catch-all (`/page/N/`, `/<archive>/page/N/`).
     *
     * Statamic paginates with a `?page=` query parameter, so no path of this shape
     * survives. The archive index is the honest destination when the site has one;
     * without it the shape is declared `410 Gone` rather than pointed at the home
     * page. Either way it is a single pattern rule: page numbers are unbounded.
     *
     * @param  string|null  $archiveUrl  The posts index URL, or null when the collection is unmounted
     * @return list<MigrationRedirect> The planned pagination rule
     */
    public function pagination(?string $archiveUrl): array
    {
        $target = $archiveUrl === null ? '' : trim($archiveUrl);

        if ($target === '') {
            return [
                MigrationRedirect::gonePattern(
                    self::PAGINATION_PATTERN,
                    'WordPress pagination; no posts index to land on.',
                ),
            ];
        }

        return [
            MigrationRedirect::movedPattern(
                self::PAGINATION_PATTERN,
                $target,
                'WordPress pagination; 301 to the posts index.',
            ),
        ];
    }

    /**
     * Disposition a set of synthesized paths through the caller's fallback.
     *
     * @param  array<string, true>  $paths  The distinct paths to disposition, keyed by path
     * @param  Closure(string, string): MigrationRedirect  $disposition  Maps a path + provenance note to its redirect
     * @param  string  $notes  The provenance note recorded on each row
     * @return list<MigrationRedirect> The resulting redirects, in sorted path order
     */
    private function disposition(array $paths, Closure $disposition, string $notes): array
    {
        // Sort so the planned order is a function of the content, not of iteration
        // order — a re-run over the same import plans the same rows in the same order.
        $sources = array_keys($paths);
        sort($sources);

        return array_map(
            static fn (string $path): MigrationRedirect => $disposition($path, $notes),
            $sources,
        );
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
