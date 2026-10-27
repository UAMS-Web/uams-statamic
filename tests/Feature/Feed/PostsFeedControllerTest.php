<?php

declare(strict_types=1);

use App\Http\Controllers\Feed\PostsFeedController;
use Illuminate\Support\Carbon;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Behavioral coverage for {@see PostsFeedController}, the outbound RSS 2.0 feed
 * (UAMS-Web/uams-statamic-archive#1042). The content snapshot ships zero `posts`, so every case builds its own
 * fixtures in an isolated Stache and invokes the controller directly (the
 * per-site route is registered at boot from the real collection, so the wiring is
 * covered separately in PostsFeedRouteTest). This exercises the real published
 * status, future-date, site-scope, date-order, and 10-item cap paths against the
 * `whereStatus('published')` query — and the pinned excerpt-only output (UAMS-Web/uams-statamic-archive#185/UAMS-Web/uams-statamic-archive#186).
 *
 * @covers \App\Http\Controllers\Feed\PostsFeedController
 *
 * @command  php artisan test --compact tests/Feature/Feed/PostsFeedControllerTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Persist a fixture post (bypassing listeners) on a site with a date, status, and
 * excerpt — the dimensions the feed filters/orders on.
 */
function feedPost(string $id, string $site, string $date, bool $published, string $excerpt = ''): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('posts');
    $entry->id($id);
    $entry->slug($id);
    $entry->locale($site);
    $entry->published($published);
    $entry->data(['title' => 'Post '.$id, 'excerpt' => $excerpt]);
    $entry->date(Carbon::parse($date));
    $entry->saveQuietly();
}

/**
 * Render the feed for a site and return the raw RSS body.
 *
 * The controller always emits at least an RSS envelope, so an empty body means
 * it failed to render at all. Rejecting that here rather than at the call site
 * keeps the guarantee in one place — every caller then gets a `non-empty-string`
 * the analyser has verified, instead of narrowing it again with an `assert()`
 * that says nothing when it trips and vanishes under `zend.assertions=-1`
 * (UAMS-Web/uams-statamic-archive#1733).
 *
 * @return non-empty-string The rendered RSS body.
 */
function renderFeed(string $siteHandle = 'default'): string
{
    Site::setCurrent($siteHandle);

    $body = (string) (new PostsFeedController)()->getContent();

    if ($body === '') {
        throw new RuntimeException(sprintf('The %s posts feed rendered an empty body.', $siteHandle));
    }

    return $body;
}

/**
 * The `<item><title>` values of a rendered feed, in document order.
 *
 * @return array<int, string>
 */
function feedItemTitles(string $body): array
{
    /** @var SimpleXMLElement $xml */
    $xml = simplexml_load_string($body, null, LIBXML_NOCDATA);
    $titles = [];

    foreach ($xml->channel->item as $item) {
        $titles[] = (string) $item->title;
    }

    return $titles;
}

beforeEach(function (): void {
    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'excerpt' => ['type' => 'text'],
    ]);
    $blueprint->setHandle('post');
    $blueprint->setNamespace('collections.posts');
    $blueprint->save();

    // Mirror production posts config: dated, default + news sites, and future
    // dates treated as private so a scheduled post is excluded from the feed.
    $collection = CollectionFacade::make('posts');
    $collection->title('Posts');
    $collection->sites(['default', 'news']);
    $collection->dated(true);
    $collection->futureDateBehavior('private');
    $collection->save();
});

it('caps the feed at the 10 most-recent published posts, newest first', function (): void {
    // 12 published posts, ascending dates p01..p12 (p12 newest).
    foreach (range(1, 12) as $n) {
        feedPost(sprintf('p%02d', $n), 'default', sprintf('2024-%02d-01', $n), true);
    }

    $titles = feedItemTitles(renderFeed('default'));

    // Exactly the 10 newest, newest first: p12 down to p03. p01/p02 dropped.
    expect($titles)->toHaveCount(10);
    expect($titles[0])->toBe('Post p12');
    expect($titles[9])->toBe('Post p03');
    expect($titles)->not->toContain('Post p01');
    expect($titles)->not->toContain('Post p02');
});

it('carries each entry excerpt as the item description, not full content', function (): void {
    feedPost('a', 'default', '2024-05-01', true, 'A concise cardiology summary.');

    /** @var SimpleXMLElement $xml */
    $xml = simplexml_load_string(renderFeed('default'), null, LIBXML_NOCDATA);

    expect((string) $xml->channel->item[0]->description)->toBe('A concise cardiology summary.');
});

it('excludes draft posts', function (): void {
    feedPost('published', 'default', '2024-05-01', true);
    feedPost('drafted', 'default', '2024-06-01', false);

    $titles = feedItemTitles(renderFeed('default'));
    expect($titles)->toContain('Post published');
    expect($titles)->not->toContain('Post drafted');
});

it('excludes scheduled (future-dated) posts', function (): void {
    feedPost('live', 'default', '2024-05-01', true);
    feedPost('scheduled', 'default', '2999-01-01', true);

    $titles = feedItemTitles(renderFeed('default'));
    expect($titles)->toContain('Post live');
    expect($titles)->not->toContain('Post scheduled');
});

it('scopes each feed to the requested site', function (): void {
    feedPost('on-default', 'default', '2024-05-01', true);
    feedPost('on-news', 'news', '2024-05-01', true);

    $defaultTitles = feedItemTitles(renderFeed('default'));
    expect($defaultTitles)->toContain('Post on-default');
    expect($defaultTitles)->not->toContain('Post on-news');

    $newsTitles = feedItemTitles(renderFeed('news'));
    expect($newsTitles)->toContain('Post on-news');
    expect($newsTitles)->not->toContain('Post on-default');
});

it('renders a valid RSS 2.0 document with the feed content type', function (): void {
    feedPost('a', 'default', '2024-05-01', true, 'Summary.');

    Site::setCurrent('default');
    $response = (new PostsFeedController)();

    expect($response->headers->get('Content-Type'))->toBe('application/rss+xml; charset=UTF-8');

    /** @var SimpleXMLElement $xml */
    $xml = simplexml_load_string((string) $response->getContent());
    expect($xml->getName())->toBe('rss');
    expect((string) $xml['version'])->toBe('2.0');
    expect($xml->channel->item)->toHaveCount(1);
    // The self-reference link the W3C validator recommends.
    $atom = $xml->channel->children('http://www.w3.org/2005/Atom');
    expect((string) $atom->link->attributes()['href'])->toEndWith('/feed.xml');
});

it('neutralizes a CDATA terminator in an excerpt so it cannot inject XML into the feed', function (): void {
    // An excerpt containing `]]>` (e.g. a pasted code/XML snippet, or a crafted
    // one) must not close the CDATA section early and inject a sibling element.
    feedPost('cdata', 'default', '2024-05-01', true, 'Read more ]]><injected>owned</injected>');

    $body = renderFeed('default');

    $dom = new DOMDocument;
    expect($dom->loadXML($body))->toBeTrue();

    // The `<injected>` markup survives only as CDATA text, never as a parsed element.
    expect($dom->getElementsByTagName('injected')->length)->toBe(0);

    // The item's own description (not the channel's) carries the excerpt verbatim.
    $itemDescription = null;
    foreach ($dom->getElementsByTagName('description') as $description) {
        if ($description->parentNode?->nodeName === 'item') {
            $itemDescription = $description;
            break;
        }
    }
    expect($itemDescription?->textContent)->toBe('Read more ]]><injected>owned</injected>');
});
