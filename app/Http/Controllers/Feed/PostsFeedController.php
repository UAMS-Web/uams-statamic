<?php

declare(strict_types=1);

namespace App\Http\Controllers\Feed;

use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Statamic\Stache\Query\EntryQueryBuilder;
use XMLWriter;

/**
 * Serves the site's outbound `posts` syndication feed at `{site}/feed.xml` (UAMS-Web/uams-statamic-archive#1042).
 *
 * WordPress auto-generated a per-site `/feed/`; Statamic v6 ships no native feed,
 * so this app-owned route rebuilds it. The feed is scoped to the request's site
 * ({@see Site::current()}, resolved from the URL) and carries the pinned output
 * policy from UAMS-Web/uams-statamic-archive#185/UAMS-Web/uams-statamic-archive#186 (`docs/specs/syndication-feed-output-policy.md`): the 10
 * most-recent published entries, newest first, each with its excerpt/summary
 * rather than full content.
 *
 * `whereStatus('published')` excludes draft entries and — because the `posts`
 * collection sets `date_behavior.future: private` — scheduled/future-dated ones.
 * The document is built with XMLWriter (matching the app's SEO routes), so every
 * value is XML-escaped and the excerpt is CDATA-wrapped.
 *
 * Route registration (per site, `posts`-enabled sites only) lives in
 * `routes/web.php`.
 */
final class PostsFeedController
{
    /**
     * The syndicated collection.
     */
    private const string COLLECTION = 'posts';

    /**
     * The fixed item cap pinned by UAMS-Web/uams-statamic-archive#185 (not configurable).
     */
    private const int MAX_ITEMS = 10;

    /**
     * Render the current site's `posts` feed as RSS 2.0.
     */
    public function __invoke(): Response
    {
        $site = Site::current();

        // Site::current() always resolves (matched site or default); narrow the
        // facade's mixed return for the analyzer.
        if (! $site instanceof SiteObject) {
            abort(500, 'Unable to resolve the current site for the feed.');
        }

        // Site's accessors are untyped (mixed); guard each to a string rather
        // than cast, matching the app's SEO-route controllers.
        $name = $this->asString($site->name());
        $siteUrl = rtrim($this->asString($site->absoluteUrl()), '/').'/';
        $feedUrl = $siteUrl.'feed.xml';

        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');

        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $writer->startElement('channel');

        // Channel-level metadata. The atom:self link avoids the W3C Feed
        // Validator's "missing self reference" recommendation warning.
        $writer->writeElement('title', $name);
        $writer->writeElement('link', $siteUrl);
        $writer->writeElement('description', sprintf('Latest posts from %s.', $name));

        $writer->startElement('atom:link');
        $writer->writeAttribute('href', $feedUrl);
        $writer->writeAttribute('rel', 'self');
        $writer->writeAttribute('type', 'application/rss+xml');
        $writer->endElement();

        $lang = $this->asString($site->lang());
        if ($lang !== '') {
            $writer->writeElement('language', $lang);
        }

        $writer->writeElement('lastBuildDate', now()->toRssString());

        foreach ($this->recentPosts($site) as $entry) {
            $this->writeItem($writer, $entry);
        }

        $writer->endElement(); // channel
        $writer->endElement(); // rss
        $writer->endDocument();

        return response(
            $writer->outputMemory(),
            200,
            ['Content-Type' => 'application/rss+xml; charset=UTF-8']
        );
    }

    /**
     * The 10 most-recent published posts for the site, newest first.
     *
     * @return Collection<int, Entry> The published posts, capped at {@see self::MAX_ITEMS}
     */
    private function recentPosts(SiteObject $site): Collection
    {
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::COLLECTION);
        $query->where('site', $site->handle());
        // Excludes draft + scheduled/future (the collection's future date_behavior
        // is private), so the feed carries only publicly-live posts.
        $query->whereStatus('published');
        $query->orderBy('date', 'desc');
        $query->limit(self::MAX_ITEMS);

        /** @var Collection<int, Entry> $results */
        $results = $query->get();

        return $results;
    }

    /**
     * Write one `<item>` for the entry — title, permalink, pubDate, and the
     * excerpt (CDATA) per the excerpt-only policy (UAMS-Web/uams-statamic-archive#186).
     */
    private function writeItem(XMLWriter $writer, Entry $entry): void
    {
        $writer->startElement('item');

        $title = $entry->value('title');
        $writer->writeElement('title', \is_string($title) ? $title : '');

        $url = $entry->absoluteUrl();
        if (\is_string($url)) {
            $writer->writeElement('link', $url);

            // A permalink guid is the stable per-item identifier feed readers dedupe on.
            $writer->startElement('guid');
            $writer->writeAttribute('isPermaLink', 'true');
            $writer->text($url);
            $writer->endElement();
        }

        $date = $entry->date();
        if ($date instanceof CarbonInterface) {
            $writer->writeElement('pubDate', $date->toRssString());
        }

        // Excerpt-only (UAMS-Web/uams-statamic-archive#186): never the full body. CDATA-wrap so any markup or
        // entities in the summary survive intact. A literal `]]>` in the excerpt
        // would otherwise close the CDATA section early and corrupt the whole
        // feed, so split it with the standard `]]]]><![CDATA[>` escape first.
        $excerpt = $entry->value('excerpt');
        $writer->startElement('description');
        $writer->writeCdata($this->cdataSafe(\is_string($excerpt) ? $excerpt : ''));
        $writer->endElement();

        $writer->endElement(); // item
    }

    /**
     * Narrow an untyped Statamic accessor result to a string, defaulting to empty.
     */
    private function asString(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    /**
     * Neutralize any literal `]]>` so it cannot close a CDATA section early.
     * Splits it across two CDATA sections (`]]]]><![CDATA[>`), the standard way
     * to embed the terminator without breaking the document.
     */
    private function cdataSafe(string $value): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $value);
    }
}
