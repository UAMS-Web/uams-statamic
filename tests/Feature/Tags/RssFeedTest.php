<?php

declare(strict_types=1);

use App\Support\Http\OutboundUrlGuard;
use App\Tags\RssFeed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests the Statamic rss_feed tag: URL scheme validation and RSS parsing from
 * HTTP responses.
 *
 * Mutation: 100% on App\Tags\RssFeed (102 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/RssFeedTest.php
 */

// The feed fetch flows through OutboundUrlGuard, which resolves the host's IPs to
// pin the connection (UAMS-Web/uams-statamic-archive#1013). These tests fake HTTP but not DNS, so stub the
// guard's resolver to a deterministic public IP — otherwise the test feed hosts
// resolve unpredictably (or not at all) on the runner. Reset after each test.
beforeEach(function () {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);
});

afterEach(function () {
    OutboundUrlGuard::setResolver(null);
});
it('rejects file scheme and returns no items', function () {
    // Fake HTTP requests.
    Http::fake();

    // Parse the RSS feed.
    $out = Antlers::parse(
        '{{ rss_feed url="file:///etc/passwd" items_to_show="1" }}{{ title }}{{ /rss_feed }}',
        [],
        true
    );

    // A file:// feed URL yields empty output because only HTTP(S) URLs pass
    // validation before any request runs.
    expect(trim((string) $out))->toBe('');
});

it('fetches https rss feed via http client', function () {
    // Fake the RSS feed.
    $rss = <<<'XML'
<?xml version="1.0"?>
<rss version="2.0">
<channel>
<title>Test Channel</title>
<link>https://example.com</link>
<item>
<title>Hello</title>
<link>https://example.com/post</link>
<pubDate>Mon, 01 Jan 2024 00:00:00 GMT</pubDate>
<description>Desc</description>
</item>
</channel>
</rss>
XML;

    // Fake the HTTP request.
    Http::fake([
        'https://example.com/feed.xml' => Http::response($rss, 200),
    ]);

    // Parse the RSS feed.
    $out = Antlers::parse(
        '{{ rss_feed url="https://example.com/feed.xml" items_to_show="1" }}{{ title }}{{ /rss_feed }}',
        [],
        true
    );

    // The tag loads RSS over HTTPS and renders item titles from the Antlers loop.
    expect((string) $out)->toContain('Hello');
});

it('reuses the cache on a second parse with a single http request', function () {
    // Fake a minimal RSS response for a stable feed URL.
    $rss = <<<'XML'
<?xml version="1.0"?>
<rss version="2.0">
<channel>
<title>Cache Test</title>
<link>https://cache-test.example/</link>
<item>
<title>Cached Once</title>
<link>https://cache-test.example/post</link>
<pubDate>Mon, 01 Jan 2024 00:00:00 GMT</pubDate>
<description>Body</description>
</item>
</channel>
</rss>
XML;

    // Fake the HTTP request with a one-response sequence so a second uncached
    // request would exhaust the sequence and fail.
    Http::fake([
        'https://cache-test.example/feed.xml' => Http::sequence()->push(
            $rss,
            200
        ),
    ]);

    // Build a template that requests one item from the feed.
    $template = '{{ rss_feed url="https://cache-test.example/feed.xml" items_to_show="1" }}{{ title }}{{ /rss_feed }}';

    // Parse once and assert the feed rendered so HTTP ran before cache assertions.
    $firstOut = Antlers::parse(
        $template,
        [],
        true
    );
    expect((string) $firstOut)->toContain('Cached Once');

    // Parse the template a second time so the pass should hit the cache; the
    // single-response sequence proves no second HTTP request was issued.
    $secondOut = Antlers::parse(
        $template,
        [],
        true
    );
    expect((string) $secondOut)->toContain('Cached Once');
});

/**
 * Run the rss_feed tag directly with the given parameters, with caching off unless
 * a TTL is passed, and return what index() returns (no Antlers parser is set, so
 * that is the raw array).
 *
 * @param  array<array-key, mixed>  $params
 */
function rssFeedTagResult(array $params, int $cacheTtl = 0): mixed
{
    config(['rss_feed.cache_ttl_seconds' => $cacheTtl]);

    $tag = new RssFeed;
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag->index();
}

/**
 * The items the tag returns under the `posts` alias.
 *
 * @param  array<array-key, mixed>  $params
 * @return list<array<array-key, mixed>>
 */
function rssFeedPosts(array $params, int $cacheTtl = 0): array
{
    $result = mustBeArray(rssFeedTagResult($params + ['as' => 'posts'], $cacheTtl), 'rss_feed result');

    return array_values(array_map(
        fn (mixed $post): array => mustBeArray($post, 'rss_feed item'),
        mustBeArray($result['posts'] ?? null, 'rss_feed posts')
    ));
}

/**
 * The items under the `posts` alias with `rss_feed.cache_ttl_seconds` set to a raw
 * config value, which rssFeedTagResult() only sets as an int.
 *
 * @param  array<array-key, mixed>  $params
 * @return list<array<array-key, mixed>>
 */
function rssFeedPostsWithTtlConfig(array $params, mixed $ttl): array
{
    config(['rss_feed.cache_ttl_seconds' => $ttl]);

    $tag = new RssFeed;
    $tag->setContext([]);
    $tag->setParameters($params + ['as' => 'posts']);
    $result = mustBeArray($tag->index(), 'rss_feed result');

    return array_values(array_map(
        fn (mixed $post): array => mustBeArray($post, 'rss_feed item'),
        mustBeArray($result['posts'] ?? null, 'rss_feed posts')
    ));
}

/**
 * A feed URL no other test uses, so a cached entry from another test cannot answer
 * for it and no test has to flush the shared cache store.
 */
function rssFeedUniqueUrl(): string
{
    return 'https://feed.test/'.bin2hex(random_bytes(6)).'.xml';
}

/**
 * An RSS 2.0 document with the given item markup and the namespaces the parser reads.
 */
function rssFeedDocument(string $items): string
{
    return '<?xml version="1.0"?><rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"'
        .' xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><title>T</title>'
        .$items.'</channel></rss>';
}

/**
 * The fields of one parsed item, with defaults for the ones a test does not set.
 *
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function rssFeedItem(array $fields): array
{
    return array_merge([
        'title' => '',
        'permalink' => '',
        'date' => '',
        'authors' => [],
        'author' => '',
        'description' => '',
    ], $fields);
}

const RSS_FEED_NO_RESULTS = ['no_results' => true, 'total_results' => 0];

it('returns no results without a usable URL', function (array $params) {
    Http::fake();

    expect(rssFeedTagResult($params))->toBe(RSS_FEED_NO_RESULTS);

    Http::assertNothingSent();
})->with([
    'no url' => [[]],
    'a blank url' => [['url' => '   ']],
    'a url that is not a scalar' => [['url' => ['https://feed.test/a.xml']]],
]);

it('returns an empty alias structure for a URL that fails validation', function () {
    Http::fake();

    expect(rssFeedTagResult(['url' => 'ftp://feed.test/a.xml', 'as' => 'posts']))
        ->toBe(['posts' => [], 'total_results' => 0, 'no_results' => true]);

    Http::assertNothingSent();
});

it('ignores a non-scalar alias for a URL that fails validation', function () {
    Http::fake();

    expect(rssFeedTagResult(['url' => 'ftp://feed.test/a.xml', 'as' => ['posts']]))->toBe(RSS_FEED_NO_RESULTS);
});

it('returns the items under the alias with their count', function () {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument(
        '<item><title>One</title></item><item><title>Two</title></item>'
    ))]);

    expect(rssFeedTagResult(['url' => ' https://feed.test/a.xml ', 'as' => 'posts']))->toBe([
        'posts' => [rssFeedItem(['title' => 'One']), rssFeedItem(['title' => 'Two'])],
        'total_results' => 2,
        'no_results' => false,
    ]);
});

it('returns an empty alias structure when the feed has no items', function (array $params, mixed $expected) {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument(''))]);

    expect(rssFeedTagResult(['url' => 'https://feed.test/a.xml'] + $params))->toBe($expected);
})->with([
    'with an alias' => [['as' => 'posts'], ['posts' => [], 'total_results' => 0, 'no_results' => true]],
    'with a non-scalar alias' => [['as' => ['posts']], RSS_FEED_NO_RESULTS],
    'without an alias' => [[], RSS_FEED_NO_RESULTS],
]);

it('limits the items by items_to_show, then limit, then ten', function (array $params, int $expected) {
    $items = '';
    for ($i = 1; $i <= 12; $i++) {
        $items .= "<item><title>T{$i}</title></item>";
    }
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument($items))]);

    expect(rssFeedPosts(['url' => 'https://feed.test/a.xml'] + $params))->toHaveCount($expected);
})->with([
    'items_to_show wins over limit' => [['items_to_show' => '2', 'limit' => '5'], 2],
    'limit when items_to_show is absent' => [['limit' => '3'], 3],
    'ten by default' => [[], 10],
    'all twelve when the limit allows' => [['items_to_show' => '12'], 12],
]);

it('returns no items when the limit is not a scalar', function () {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument('<item><title>One</title></item>'))]);

    expect(rssFeedTagResult(['url' => 'https://feed.test/a.xml', 'items_to_show' => ['2']]))->toBe(RSS_FEED_NO_RESULTS);
});

it('truncates descriptions longer than the excerpt limit, counting characters', function (array $params, string $expected) {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument(
        '<item><title>One</title><description>Naïve café</description></item>'
    ))]);

    expect(rssFeedPosts(['url' => 'https://feed.test/a.xml'] + $params)[0]['description'])->toBe($expected);
})->with([
    'excerpt_char_limit' => [['excerpt_char_limit' => '7'], 'Naïve c...'],
    'excerpt_limit when excerpt_char_limit is absent' => [['excerpt_limit' => '3'], 'Naï...'],
    'excerpt_char_limit wins over excerpt_limit' => [['excerpt_char_limit' => '5', 'excerpt_limit' => '2'], 'Naïve...'],
    'a limit equal to the length leaves it whole' => [['excerpt_char_limit' => '10'], 'Naïve café'],
    'a limit one under the length truncates' => [['excerpt_char_limit' => '9'], 'Naïve caf...'],
    'no excerpt limit' => [[], 'Naïve café'],
    'an excerpt limit of zero' => [['excerpt_char_limit' => '0'], 'Naïve café'],
    'a truthy limit that casts to zero' => [['excerpt_char_limit' => '0.5'], 'Naïve café'],
    'a negative excerpt limit' => [['excerpt_char_limit' => '-3'], 'Naïve café'],
    'a non-scalar excerpt limit' => [['excerpt_char_limit' => ['3']], 'Naïve café'],
]);

it('caches parsed items per URL, limit and excerpt limit', function () {
    Http::fake(['https://feed.test/*' => Http::sequence()
        ->push(rssFeedDocument('<item><title>First</title><description>abcdef</description></item>'))
        ->push(rssFeedDocument('<item><title>Second</title><description>abcdef</description></item>'))
        ->push(rssFeedDocument('<item><title>Third</title><description>abcdef</description></item>'))
        ->push(rssFeedDocument('<item><title>Fourth</title><description>abcdef</description></item>')),
    ]);
    $url = rssFeedUniqueUrl();

    $titleOf = fn (array $params): mixed => rssFeedPosts(['url' => $url] + $params, 300)[0]['title'];

    expect($titleOf([]))->toBe('First')
        ->and($titleOf([]))->toBe('First')
        ->and($titleOf(['items_to_show' => '5']))->toBe('Second')
        ->and($titleOf(['excerpt_char_limit' => '3']))->toBe('Third')
        // An excerpt limit of zero means no limit, so it shares the no-limit entry.
        ->and($titleOf(['excerpt_char_limit' => '0']))->toBe('First');

    Http::assertSentCount(3);
});

it('stores the cached items for the configured TTL under a hashed key', function () {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument('<item><title>One</title></item>'))]);
    $url = rssFeedUniqueUrl();
    Carbon\Carbon::setTestNow('2026-01-01 00:00:00');

    try {
        rssFeedTagResult(['url' => $url, 'items_to_show' => '4', 'excerpt_char_limit' => '9'], 120);
        $key = 'rss_feed:'.hash('sha256', $url.'|4|9');
        $noExcerptKey = 'rss_feed:'.hash('sha256', $url.'|4|none');

        expect(Cache::get($key))->toBe([rssFeedItem(['title' => 'One'])])
            ->and(Cache::has($noExcerptKey))->toBeFalse();

        Carbon\Carbon::setTestNow('2026-01-01 00:01:59');
        expect(Cache::has($key))->toBeTrue();

        Carbon\Carbon::setTestNow('2026-01-01 00:02:01');
        expect(Cache::has($key))->toBeFalse();
    } finally {
        Carbon\Carbon::setTestNow();
    }
});

it('fetches every time when the configured TTL is not numeric', function () {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument('<item><title>One</title></item>'))]);
    config(['rss_feed.cache_ttl_seconds' => 'soon']);

    foreach ([1, 2] as $_) {
        $tag = new RssFeed;
        $tag->setContext([]);
        $tag->setParameters(['url' => 'https://feed.test/a.xml']);
        $tag->index();
    }

    Http::assertSentCount(2);
});

it('refuses a feed whose host resolves to a private address, without fetching', function () {
    Http::fake();
    OutboundUrlGuard::setResolver(fn (string $host): array => ['10.0.0.5']);

    expect(rssFeedTagResult(['url' => 'https://feed.test/a.xml']))->toBe(RSS_FEED_NO_RESULTS);

    Http::assertNothingSent();
});

it('requests the feed with a 15 second timeout', function () {
    $timeouts = [];
    Http::fake(function ($request, array $options) use (&$timeouts) {
        $timeouts[] = $options['timeout'] ?? null;

        return Http::response(rssFeedDocument('<item><title>One</title></item>'));
    });

    rssFeedTagResult(['url' => 'https://feed.test/a.xml']);

    expect($timeouts)->toBe([15]);
});

it('follows up to five redirects and no more', function (int $hops, mixed $expected) {
    Http::fake(function (Request $request) use ($hops) {
        $step = (int) basename((string) parse_url($request->url(), PHP_URL_PATH));

        return $step < $hops
            ? Http::response('', 302, ['Location' => 'https://feed.test/'.($step + 1)])
            : Http::response(rssFeedDocument('<item><title>Arrived</title></item>'));
    });

    expect(rssFeedPosts(['url' => 'https://feed.test/0']))->toBe($expected);
})->with([
    'five redirects' => [5, [rssFeedItem(['title' => 'Arrived'])]],
    'six redirects' => [6, []],
]);

it('returns no items for a failed response, invalid XML, or a thrown fetch, and restores the libxml error mode', function (Closure $respond, bool $mode) {
    Http::fake(['https://feed.test/*' => $respond]);
    $previous = libxml_use_internal_errors($mode);

    try {
        $result = rssFeedTagResult(['url' => 'https://feed.test/a.xml']);
        $modeAfter = libxml_use_internal_errors(null);
        $errorsAfter = libxml_get_errors();
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    expect($result)->toBe(RSS_FEED_NO_RESULTS)
        ->and($modeAfter)->toBe($mode)
        ->and($errorsAfter)->toBe([]);
})->with([
    'a 500' => fn () => fn () => Http::response(rssFeedDocument('<item><title>One</title></item>'), 500),
    'invalid XML' => fn () => fn () => Http::response('<rss><channel><item>', 200),
    'a thrown fetch' => fn () => fn () => throw new ConnectionException('refused'),
])->with([
    'found off' => [false],
    'found on' => [true],
]);

it('parses Atom entries', function () {
    Http::fake(['https://feed.test/*' => Http::response(<<<'XML'
    <?xml version="1.0"?>
    <feed xmlns="http://www.w3.org/2005/Atom">
      <title>T</title>
      <entry>
        <title>Published</title>
        <link href="https://feed.test/one"/>
        <published>2024-01-01T00:00:00Z</published>
        <updated>2024-02-01T00:00:00Z</updated>
        <author><name>Ann</name></author>
        <author><name>0</name></author>
        <author><name></name></author>
        <author><name>Bea</name></author>
        <summary>A &lt;b&gt;summary&lt;/b&gt;</summary>
        <content>Ignored content</content>
      </entry>
      <entry>
        <title>Updated</title>
        <updated>2024-03-01T00:00:00Z</updated>
        <content>Only content</content>
      </entry>
      <entry>
        <title>Bare</title>
      </entry>
    </feed>
    XML)]);

    expect(rssFeedPosts(['url' => 'https://feed.test/a.xml']))->toBe([
        rssFeedItem([
            'title' => 'Published',
            'permalink' => 'https://feed.test/one',
            'date' => '2024-01-01T00:00:00Z',
            'authors' => ['Ann', 'Bea'],
            'author' => 'Ann and Bea',
            'description' => 'A summary',
        ]),
        rssFeedItem(['title' => 'Updated', 'date' => '2024-03-01T00:00:00Z', 'description' => 'Only content']),
        rssFeedItem(['title' => 'Bare']),
    ]);
});

it('parses RSS items, their dc:creator and author fallbacks, and content:encoded', function () {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument(<<<'XML'
    <item>
      <title>Link</title>
      <link>https://feed.test/link</link>
      <guid>https://feed.test/guid-ignored</guid>
      <pubDate>Mon, 01 Jan 2024 00:00:00 GMT</pubDate>
      <dc:creator>Ann</dc:creator>
      <dc:creator>0</dc:creator>
      <dc:creator></dc:creator>
      <dc:creator>Bea</dc:creator>
      <author>ignored@feed.test</author>
      <description>&lt;p&gt;Plain&lt;/p&gt;</description>
    </item>
    <item>
      <title>Guid</title>
      <guid>https://feed.test/guid</guid>
      <author>Cy</author>
      <content:encoded>&lt;p&gt;Encoded&lt;/p&gt;</content:encoded>
    </item>
    <item>
      <title>Empty creators</title>
      <dc:creator>0</dc:creator>
      <author>0</author>
      <description>0</description>
      <content:encoded>Fallback</content:encoded>
    </item>
    XML))]);

    expect(rssFeedPosts(['url' => 'https://feed.test/a.xml']))->toBe([
        rssFeedItem([
            'title' => 'Link',
            'permalink' => 'https://feed.test/link',
            'date' => 'Mon, 01 Jan 2024 00:00:00 GMT',
            'authors' => ['Ann', 'Bea'],
            'author' => 'Ann and Bea',
            'description' => 'Plain',
        ]),
        rssFeedItem([
            'title' => 'Guid',
            'permalink' => 'https://feed.test/guid',
            'authors' => ['Cy'],
            'author' => 'Cy',
            'description' => 'Encoded',
        ]),
        rssFeedItem(['title' => 'Empty creators', 'description' => 'Fallback']),
    ]);
});

it('reads the author element and no content:encoded in a feed without those namespaces', function () {
    Http::fake(['https://feed.test/*' => Http::response(<<<'XML'
    <?xml version="1.0"?>
    <rss version="2.0"><channel><title>T</title>
      <item><title>One</title><author>Dee</author></item>
      <item><title>Two</title><author>0</author><description></description></item>
    </channel></rss>
    XML)]);

    expect(rssFeedPosts(['url' => 'https://feed.test/a.xml']))->toBe([
        rssFeedItem(['title' => 'One', 'authors' => ['Dee'], 'author' => 'Dee']),
        rssFeedItem(['title' => 'Two']),
    ]);
});

it('joins author names, adding a serial comma after a name that has one', function (array $names, string $expected) {
    $creators = '';
    foreach ($names as $name) {
        $creators .= '<dc:creator>'.mustBeString($name, 'author name').'</dc:creator>';
    }
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument("<item><title>One</title>{$creators}</item>"))]);

    expect(rssFeedPosts(['url' => 'https://feed.test/a.xml'])[0]['author'])->toBe($expected);
})->with([
    'two' => [['Ann', 'Bea'], 'Ann and Bea'],
    'two, the first with a comma' => [['Ann Lee, MD', 'Bea'], 'Ann Lee, MD, and Bea'],
    'two, the second with a comma' => [['Ann', 'Bea Lee, MD'], 'Ann and Bea Lee, MD'],
    'three' => [['Ann', 'Bea', 'Cy'], 'Ann, Bea and Cy'],
    'three, the penultimate with a comma' => [['Ann', 'Bea Lee, MD', 'Cy'], 'Ann, Bea Lee, MD, and Cy'],
    'three, only the first with a comma' => [['Ann Lee, MD', 'Bea', 'Cy'], 'Ann Lee, MD, Bea and Cy'],
    'four' => [['Ann', 'Bea', 'Cy', 'Dee'], 'Ann, Bea, Cy and Dee'],
]);

it('returns no results, not an alias structure, for a missing or blank URL with an alias', function (mixed $url) {
    Http::fake();

    expect(rssFeedTagResult(['url' => $url, 'as' => 'posts']))->toBe(RSS_FEED_NO_RESULTS);
})->with([
    'a blank url' => ['  '],
    'a url that is not a scalar' => [['https://feed.test/a.xml']],
]);

it('casts a scalar URL to a string before validating it', function (mixed $url) {
    Http::fake();

    expect(rssFeedTagResult(['url' => $url]))->toBe(RSS_FEED_NO_RESULTS);

    Http::assertNothingSent();
})->with([
    'a number' => [5],
    'true' => [true],
]);

it('refuses a URL the validator rejects even when it could be fetched', function (array $params, mixed $expected) {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument('<item><title>One</title></item>'))]);

    expect(rssFeedTagResult(['url' => 'https://feed.test/a b.xml'] + $params))->toBe($expected);

    Http::assertNothingSent();
})->with([
    'without an alias' => [[], RSS_FEED_NO_RESULTS],
    'with an alias' => [['as' => 'posts'], ['posts' => [], 'total_results' => 0, 'no_results' => true]],
]);

it('keys a float alias by its string form', function (string $body, array $expected) {
    Http::fake(['https://feed.test/*' => Http::response($body)]);

    expect(rssFeedTagResult(['url' => 'https://feed.test/a.xml', 'as' => 1.5]))->toBe($expected);
})->with([
    'with items' => [rssFeedDocument('<item><title>One</title></item>'), ['1.5' => [rssFeedItem(['title' => 'One'])], 'total_results' => 1, 'no_results' => false]],
    'without items' => [rssFeedDocument(''), ['1.5' => [], 'total_results' => 0, 'no_results' => true]],
]);

it('keys a float alias by its string form when the URL fails validation', function () {
    Http::fake();

    expect(rssFeedTagResult(['url' => 'ftp://feed.test/a.xml', 'as' => 1.5]))
        ->toBe(['1.5' => [], 'total_results' => 0, 'no_results' => true]);
});

it('loops over the items directly when the alias is empty', function () {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument('<item><title>One</title></item>'))]);

    expect(rssFeedTagResult(['url' => 'https://feed.test/a.xml', 'as' => '']))->toBe([rssFeedItem(['title' => 'One'])]);
});

it('caches for a TTL of one second', function () {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument('<item><title>One</title></item>'))]);
    $url = rssFeedUniqueUrl();
    // Frozen time, so the two renders cannot straddle the one-second expiry
    Carbon\Carbon::setTestNow('2026-01-01 00:00:00');

    try {
        rssFeedTagResult(['url' => $url], 1);
        rssFeedTagResult(['url' => $url], 1);
    } finally {
        Carbon\Carbon::setTestNow();
    }

    Http::assertSentCount(1);
});

it('fetches afresh when the TTL drops to zero or below one second after a cached render', function () {
    Http::fake(['https://feed.test/*' => Http::sequence()
        ->push(rssFeedDocument('<item><title>Cached</title></item>'))
        ->push(rssFeedDocument('<item><title>Zero</title></item>'))
        ->push(rssFeedDocument('<item><title>Half</title></item>')),
    ]);
    $url = rssFeedUniqueUrl();

    // The cache key leaves out the TTL, so only the uncached branch skips the
    // entry the first render stored.
    expect(rssFeedPosts(['url' => $url], 60)[0]['title'])->toBe('Cached');

    expect(rssFeedPosts(['url' => $url], 0)[0]['title'])->toBe('Zero');

    expect(rssFeedPostsWithTtlConfig(['url' => $url], '0.5')[0]['title'])->toBe('Half');
});

it('truncates to a single character with an excerpt limit of one', function () {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument('<item><title>One</title><description>abc</description></item>'))]);

    expect(rssFeedPosts(['url' => 'https://feed.test/a.xml', 'excerpt_char_limit' => '1'])[0]['description'])
        ->toBe('a...');
});

it('leaves out missing titles, nameless Atom authors and absent content:encoded', function () {
    Http::fake(['https://feed.test/a.xml' => Http::response(<<<'XML'
    <?xml version="1.0"?>
    <feed xmlns="http://www.w3.org/2005/Atom"><title>T</title>
      <entry><author><email>a@feed.test</email></author></entry>
    </feed>
    XML), 'https://feed.test/b.xml' => Http::response(rssFeedDocument(<<<'XML'
    <item><content:other>x</content:other></item>
    <item><dc:creator></dc:creator><author>Eve</author></item>
    <item><author></author></item>
    <item><dc:creator></dc:creator><author></author></item>
    XML))]);

    expect(rssFeedPosts(['url' => 'https://feed.test/a.xml']))->toBe([rssFeedItem([])])
        ->and(rssFeedPosts(['url' => 'https://feed.test/b.xml']))->toBe([
            rssFeedItem([]),
            rssFeedItem(['authors' => ['Eve'], 'author' => 'Eve']),
            rssFeedItem([]),
            rssFeedItem([]),
        ]);
});

it('keeps libxml errors the caller had already buffered', function () {
    Http::fake(['https://feed.test/*' => Http::response(rssFeedDocument('<item><title>One</title></item>'))]);
    $previous = libxml_use_internal_errors(true);
    simplexml_load_string('<unclosed');

    try {
        rssFeedTagResult(['url' => 'https://feed.test/a.xml']);
        $buffered = count(libxml_get_errors());
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    expect($buffered)->toBeGreaterThan(0);
});

it('reads every Atom entry, including an empty first one', function () {
    Http::fake(['https://feed.test/*' => Http::response(<<<'XML'
    <?xml version="1.0"?>
    <feed xmlns="http://www.w3.org/2005/Atom"><title>T</title><entry/><entry><title>Second</title></entry></feed>
    XML)]);

    expect(rssFeedPosts(['url' => 'https://feed.test/a.xml']))->toBe([rssFeedItem([]), rssFeedItem(['title' => 'Second'])]);
});

it('returns no results for an RSS document with no channel', function () {
    Http::fake(['https://feed.test/*' => Http::response('<?xml version="1.0"?><rss version="2.0"/>')]);

    expect(rssFeedTagResult(['url' => 'https://feed.test/a.xml']))->toBe(RSS_FEED_NO_RESULTS);
});
