<?php

declare(strict_types=1);

use App\Support\Http\OutboundUrlGuard;
use App\Tags\RssFeed;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression test for the remote-feed sinks in the RSS page-builder block.
 *
 * `_uamsweb_block_rss.antlers.html` rendered each item's `title`, `permalink` and
 * `author` as {@see RssFeed} parsed them from the remote document, and Antlers is
 * raw by default, so a feed the block points at could place markup in the page
 * and a `javascript:` link in the item's `href`. The title and author now pass
 * through `sanitize` and the permalink through `sanitize_url`, which collapses a
 * dangerous scheme to an inert `#` and encodes the attribute. The scheme logic is
 * covered in `tests/Unit/Modifiers/SanitizeUrlTest.php`; this pins the wiring at
 * the block's render sinks, against the real partial and a faked feed.
 *
 * @command  php artisan test --compact tests/Feature/Views/RssBlockRemoteFeedEscapingTest.php
 */
beforeEach(function (): void {
    // The feed fetch resolves the host through OutboundUrlGuard; pin it to a public
    // address so the faked request is reached (as RssFeedTest does).
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);

    // Parsed items are cached per URL across the suite's shared file cache.
    config()->set('rss_feed.cache_ttl_seconds', 0);
});

afterEach(function (): void {
    OutboundUrlGuard::setResolver(null);
});

/**
 * Render the RSS block partial against a feed document served at a fixed URL.
 */
function renderRssBlockWithFeed(string $rss): string
{
    Http::fake([
        'https://hostile.example/feed.xml' => Http::response($rss, 200),
    ]);

    // The block's validity gate reads `more_query != null` loosely, so an off
    // toggle fails it; render with the "more" link on, as an editor would.
    $fields = [
        'block_heading' => 'Partner news',
        'feed_url' => 'https://hostile.example/feed.xml',
        'item_limit' => 5,
        'excerpt_limit' => 0,
        'background_color' => 'bg-white',
        'more_query' => true,
        'more_heading' => 'More partner news',
        'more_button_text' => 'See all',
        'more_button_url' => 'https://partner.example/news',
        'date_query' => false,
        'author_query' => true,
        'excerpt_query' => false,
    ];

    return (string) Antlers::parse(
        '{{ partial:page_builder/uamsweb_block_rss }}',
        $fields + ['block' => $fields],
        true,
    );
}

it('escapes a remote item title so its markup renders as text', function (): void {
    $html = renderRssBlockWithFeed(<<<'XML'
<?xml version="1.0"?>
<rss version="2.0">
<channel>
<title>Hostile</title>
<link>https://hostile.example</link>
<item>
<title>Clinic update &lt;script&gt;alert(1)&lt;/script&gt;</title>
<link>https://hostile.example/post</link>
</item>
</channel>
</rss>
XML);

    expect($html)->not->toContain('<script>');
    expect($html)->toContain('Clinic update &lt;script&gt;alert(1)&lt;/script&gt;');
});

it('neutralizes a javascript: item permalink in the href and content attributes', function (): void {
    $html = renderRssBlockWithFeed(<<<'XML'
<?xml version="1.0"?>
<rss version="2.0">
<channel>
<title>Hostile</title>
<link>https://hostile.example</link>
<item>
<title>Clinic update</title>
<link>javascript:alert(document.cookie)</link>
</item>
</channel>
</rss>
XML);

    expect($html)->not->toContain('javascript');
    expect($html)->toContain('href="#"');
    expect($html)->toContain('content="#"');
    expect($html)->toContain('Clinic update');
});

it('encodes a quote in an item permalink so it cannot leave the href attribute', function (): void {
    $html = renderRssBlockWithFeed(<<<'XML'
<?xml version="1.0"?>
<rss version="2.0">
<channel>
<title>Hostile</title>
<link>https://hostile.example</link>
<item>
<title>Clinic update</title>
<link>https://hostile.example/post" onmouseover="alert(1)</link>
</item>
</channel>
</rss>
XML);

    expect($html)->not->toContain('onmouseover="alert(1)');
    expect($html)->toContain('href="https://hostile.example/post&quot; onmouseover=&quot;alert(1)"');
});

it('escapes a remote item author so its markup renders as text', function (): void {
    $html = renderRssBlockWithFeed(<<<'XML'
<?xml version="1.0"?>
<rss version="2.0">
<channel>
<title>Hostile</title>
<link>https://hostile.example</link>
<item>
<title>Clinic update</title>
<link>https://hostile.example/post</link>
<author>&lt;img src=x onerror=alert(1)&gt;</author>
</item>
</channel>
</rss>
XML);

    expect($html)->not->toContain('<img src=x onerror=alert(1)>');
    expect($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
});
