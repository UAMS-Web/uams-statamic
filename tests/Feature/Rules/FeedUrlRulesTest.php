<?php

declare(strict_types=1);

use App\Rules\AtomFeedUrl;
use App\Rules\BaseRules\BaseValidationRule;
use App\Rules\RssFeedUrl;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

covers(AtomFeedUrl::class, RssFeedUrl::class);

/**
 * Behavior of the feed URL rules (`AtomFeedUrl`, `RssFeedUrl`) past the SSRF
 * guard: the URL format check, a fetch that throws or fails, a body that is not
 * XML, and each structural check on the parsed feed, asserting the exact message
 * each one fails with. HTTP is faked against a public IP literal, so nothing
 * leaves the machine and no DNS lookup is made.
 *
 * The SSRF refusals themselves are covered by tests/Feature/Rules/FeedUrlSsrfTest.php.
 *
 * @command  php artisan test --compact tests/Feature/Rules/FeedUrlRulesTest.php
 */
const FEED_RULES_PUBLIC_URL = 'https://93.184.216.34/feed.xml';

const FEED_RULES_ATOM_NS = 'http://www.w3.org/2005/Atom';

const FEED_RULES_RDF_OPEN = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/">';

/**
 * Run a feed rule against the public URL with a faked response, returning the
 * messages it failed with.
 *
 * @return list<mixed>
 */
function feedRuleMessages(BaseValidationRule $rule, string $body, int $status = 200): array
{
    Http::fake(['*' => Http::response($body, $status)]);

    $messages = [];

    $rule->validate('feed', FEED_RULES_PUBLIC_URL, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

it('accepts a well-formed Atom feed and fetches it once', function (): void {
    $body = '<feed xmlns="'.FEED_RULES_ATOM_NS.'"><title>News</title><id>urn:uuid:1</id>'
        .'<updated>2024-01-01T00:00:00Z</updated></feed>';

    expect(feedRuleMessages(new AtomFeedUrl, $body))->toBe([]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === FEED_RULES_PUBLIC_URL);
});

it('accepts a well-formed RSS feed', function (string $body): void {
    expect(feedRuleMessages(new RssFeedUrl, $body))->toBe([]);

    Http::assertSentCount(1);
})->with([
    'RSS 2.0' => ['<rss version="2.0"><channel><title>News</title><link>https://example.com/</link></channel></rss>'],
    'RSS 1.0 with a channel' => [FEED_RULES_RDF_OPEN.'<channel><title>News</title></channel></rdf:RDF>'],
    'RSS 1.0 with only items' => [FEED_RULES_RDF_OPEN.'<item><title>Story</title></item></rdf:RDF>'],
    'RDF root without namespaces' => ['<RDF><channel/></RDF>'],
]);

it('rejects a value that is not a URL before fetching', function (BaseValidationRule $rule, string $value): void {
    Http::fake();
    $messages = [];

    $rule->validate('feed', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($messages)->toBe([__('statamic::validation.url')]);
    Http::assertNothingSent();
})->with([
    'Atom, plain words' => [new AtomFeedUrl, 'not a url'],
    'Atom, missing scheme' => [new AtomFeedUrl, 'example.com/feed'],
    'RSS, plain words' => [new RssFeedUrl, 'not a url'],
    'RSS, missing scheme' => [new RssFeedUrl, 'example.com/feed'],
]);

it('rejects a non-http scheme as not accessible without fetching', function (BaseValidationRule $rule, string $message): void {
    Http::fake();
    $messages = [];

    $rule->validate('feed', 'ftp://93.184.216.34/feed.xml', function (mixed $failure) use (&$messages): void {
        $messages[] = $failure;
    });

    expect($messages)->toBe([__($message)]);
    Http::assertNothingSent();
})->with([
    'Atom' => [new AtomFeedUrl, 'statamic::validation.url_feed.atom.not_accessible'],
    'RSS' => [new RssFeedUrl, 'statamic::validation.url_feed.rss.not_accessible'],
]);

it('reports a fetch that throws as not accessible', function (BaseValidationRule $rule, string $message): void {
    // A redirect that points back at itself exhausts the guard's hop limit, which
    // throws rather than returning a response.
    Http::fake(['*' => Http::response('', 302, ['Location' => '/again'])]);
    $messages = [];

    $rule->validate('feed', FEED_RULES_PUBLIC_URL, function (mixed $failure) use (&$messages): void {
        $messages[] = $failure;
    });

    expect($messages)->toBe([__($message)]);
    Http::assertSentCount(6);
})->with([
    'Atom' => [new AtomFeedUrl, 'statamic::validation.url_feed.atom.not_accessible'],
    'RSS' => [new RssFeedUrl, 'statamic::validation.url_feed.rss.not_accessible'],
]);

it('reports an error response as not accessible', function (BaseValidationRule $rule, int $status, string $message): void {
    $body = '<feed xmlns="'.FEED_RULES_ATOM_NS.'"><title>t</title><id>i</id><updated>u</updated></feed>';

    expect(feedRuleMessages($rule, $body, $status))->toBe([__($message)]);
})->with([
    'Atom, 404' => [new AtomFeedUrl, 404, 'statamic::validation.url_feed.atom.not_accessible'],
    'Atom, 500' => [new AtomFeedUrl, 500, 'statamic::validation.url_feed.atom.not_accessible'],
    'RSS, 404' => [new RssFeedUrl, 404, 'statamic::validation.url_feed.rss.not_accessible'],
    'RSS, 503' => [new RssFeedUrl, 503, 'statamic::validation.url_feed.rss.not_accessible'],
]);

it('reports a body that is not XML', function (BaseValidationRule $rule, string $body): void {
    expect(feedRuleMessages($rule, $body))->toBe([__('statamic::validation.xml_valid')]);
})->with([
    'Atom, HTML fragment' => [new AtomFeedUrl, '<html><body>not closed'],
    'Atom, empty body' => [new AtomFeedUrl, ''],
    'RSS, plain text' => [new RssFeedUrl, 'just some text'],
    'RSS, JSON' => [new RssFeedUrl, '{"items": []}'],
]);

it('leaves no libxml errors behind after a bad body', function (): void {
    libxml_clear_errors();

    feedRuleMessages(new AtomFeedUrl, '<feed>');
    expect(libxml_get_errors())->toBe([]);

    feedRuleMessages(new RssFeedUrl, '<rss>');
    expect(libxml_get_errors())->toBe([]);
});

it('requires the Atom root element to be feed', function (string $body): void {
    expect(feedRuleMessages(new AtomFeedUrl, $body))->toBe([__('statamic::validation.url_feed.atom.root_feed')]);
})->with([
    'RSS document' => ['<rss version="2.0"><channel><title>t</title><link>l</link></channel></rss>'],
    'entry document in the Atom namespace' => ['<entry xmlns="'.FEED_RULES_ATOM_NS.'"><title>t</title></entry>'],
    'prefixed entry document in the Atom namespace' => ['<a:entry xmlns:a="'.FEED_RULES_ATOM_NS.'"><a:title>t</a:title></a:entry>'],
]);

it('requires the Atom root element to be in the Atom namespace', function (string $body): void {
    expect(feedRuleMessages(new AtomFeedUrl, $body))->toBe([__('statamic::validation.url_feed.atom.atom_namespace')]);
})->with([
    'no namespace' => ['<feed><title>t</title><id>i</id><updated>u</updated></feed>'],
    'another namespace' => ['<feed xmlns="http://example.com/ns"><title>t</title><id>i</id><updated>u</updated></feed>'],
    'another namespace through a prefix' => ['<o:feed xmlns:o="http://example.com/ns"><o:title>t</o:title><o:id>i</o:id><o:updated>u</o:updated></o:feed>'],
    // UAMS-Web/uams-statamic-archive#2955: the Atom namespace in scope for an attribute does not put the element in it
    'Atom namespace bound only for an attribute' => ['<o:feed xmlns:o="http://example.com/ns" xmlns:a="'.FEED_RULES_ATOM_NS.'" a:lang="en"><o:title>t</o:title></o:feed>'],
]);

it('accepts an Atom feed whose root takes the Atom namespace through a prefix', function (string $body): void {
    // UAMS-Web/uams-statamic-archive#2955: RFC 4287 binds Atom to the namespace URI, never to a prefix
    expect(feedRuleMessages(new AtomFeedUrl, $body))->toBe([]);
})->with([
    'prefixed root and children' => ['<a:feed xmlns:a="'.FEED_RULES_ATOM_NS.'"><a:title>t</a:title><a:id>i</a:id><a:updated>u</a:updated></a:feed>'],
    'prefixed root, children under the Atom default namespace' => ['<x:feed xmlns:x="'.FEED_RULES_ATOM_NS.'" xmlns="'.FEED_RULES_ATOM_NS.'"><title>t</title><id>i</id><updated>u</updated></x:feed>'],
]);

it('requires the Atom title, id and updated elements', function (string $children): void {
    $body = '<feed xmlns="'.FEED_RULES_ATOM_NS.'">'.$children.'</feed>';

    expect(feedRuleMessages(new AtomFeedUrl, $body))->toBe([__('statamic::validation.url_feed.atom.required_elements')]);
})->with([
    'missing title' => ['<id>i</id><updated>u</updated>'],
    'missing id' => ['<title>t</title><updated>u</updated>'],
    'missing updated' => ['<title>t</title><id>i</id>'],
    'missing all three' => ['<subtitle>s</subtitle>'],
    'title in another namespace' => ['<o:title xmlns:o="http://example.com/ns">t</o:title><id>i</id><updated>u</updated>'],
]);

it('requires the title, id and updated elements in the Atom namespace under a prefixed root', function (string $children): void {
    $body = '<a:feed xmlns:a="'.FEED_RULES_ATOM_NS.'">'.$children.'</a:feed>';

    expect(feedRuleMessages(new AtomFeedUrl, $body))->toBe([__('statamic::validation.url_feed.atom.required_elements')]);
})->with([
    'missing updated' => ['<a:title>t</a:title><a:id>i</a:id>'],
    'children in no namespace' => ['<title>t</title><id>i</id><updated>u</updated>'],
]);

it('requires an RSS 2.0 channel with a title and a link', function (string $body): void {
    expect(feedRuleMessages(new RssFeedUrl, $body))->toBe([__('statamic::validation.url_feed.rss.channel_elements')]);
})->with([
    'no channel' => ['<rss version="2.0"><item><title>t</title></item></rss>'],
    'channel without a title' => ['<rss version="2.0"><channel><link>l</link></channel></rss>'],
    'channel without a link' => ['<rss version="2.0"><channel><title>t</title></channel></rss>'],
    'empty channel' => ['<rss version="2.0"><channel/></rss>'],
]);

it('requires an RSS 1.0 channel or item', function (): void {
    $body = FEED_RULES_RDF_OPEN.'<image><title>t</title></image></rdf:RDF>';

    expect(feedRuleMessages(new RssFeedUrl, $body))->toBe([__('statamic::validation.url_feed.rss.rss_1_0_elements')]);
});

it('requires the RSS root element to be rss or RDF', function (string $body): void {
    expect(feedRuleMessages(new RssFeedUrl, $body))->toBe([__('statamic::validation.url_feed.rss.root_rss')]);
})->with([
    'Atom feed' => ['<feed xmlns="'.FEED_RULES_ATOM_NS.'"><title>t</title><id>i</id><updated>u</updated></feed>'],
    'HTML document' => ['<html><body/></html>'],
    'uppercase RSS' => ['<RSS><channel><title>t</title><link>l</link></channel></RSS>'],
]);

it('bounds the feed fetch at a ten-second timeout', function (BaseValidationRule $rule, string $body): void {
    // A feed host that never answers must not hold the save open past the bound.
    $timeouts = [];

    Http::fake(function (Request $request, array $options) use (&$timeouts, $body) {
        $timeouts[] = $options['timeout'] ?? null;

        return Http::response($body);
    });

    $messages = [];

    $rule->validate('feed', FEED_RULES_PUBLIC_URL, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($messages)->toBe([])
        ->and($timeouts)->toBe([10]);
})->with([
    'Atom' => [new AtomFeedUrl, '<feed xmlns="'.FEED_RULES_ATOM_NS.'"><title>t</title><id>i</id><updated>u</updated></feed>'],
    'RSS' => [new RssFeedUrl, '<rss version="2.0"><channel><title>t</title><link>l</link></channel></rss>'],
]);

it('reports an error response as not accessible without reading its body', function (BaseValidationRule $rule, string $message): void {
    // The error page is not XML, so reading it would add a second, misleading message.
    expect(feedRuleMessages($rule, '<html><body>Not Found', 404))->toBe([__($message)]);
})->with([
    'Atom' => [new AtomFeedUrl, 'statamic::validation.url_feed.atom.not_accessible'],
    'RSS' => [new RssFeedUrl, 'statamic::validation.url_feed.rss.not_accessible'],
]);

it('leaves no libxml warnings behind after a feed that parses', function (BaseValidationRule $rule, string $body, ?string $expected): void {
    // A relative namespace URI parses but makes libxml record a warning.
    libxml_clear_errors();

    $messages = feedRuleMessages($rule, $body);

    expect($messages)->toBe($expected === null ? [] : [__($expected)])
        ->and(libxml_get_errors())->toBe([]);
})->with([
    'Atom' => [
        new AtomFeedUrl,
        '<feed xmlns="relative"><title>t</title><id>i</id><updated>u</updated></feed>',
        'statamic::validation.url_feed.atom.atom_namespace',
    ],
    'RSS' => [
        new RssFeedUrl,
        '<rss xmlns="relative" version="2.0"><channel><title>t</title><link>l</link></channel></rss>',
        null,
    ],
]);

it('reports a body that is not XML even when libxml errors are not yet buffered', function (BaseValidationRule $rule): void {
    // Each rule turns on libxml's internal error buffer itself, so a malformed body
    // becomes a validation message rather than a PHP warning, whatever ran before.
    $previous = libxml_use_internal_errors(false);

    try {
        expect(feedRuleMessages($rule, '<rss><channel>'))->toBe([__('statamic::validation.xml_valid')]);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
})->with([
    'Atom' => [new AtomFeedUrl],
    'RSS' => [new RssFeedUrl],
]);

it('reads the Atom default namespace from the root element only', function (): void {
    // UAMS-Web/uams-statamic-archive#2949: an unqualified `<feed>` is not an Atom feed, even when a descendant declares
    // the Atom default namespace, so the namespace check must not read past the root.
    $body = '<feed><entry xmlns="'.FEED_RULES_ATOM_NS.'"/><title>t</title><id>i</id><updated>u</updated></feed>';

    expect(feedRuleMessages(new AtomFeedUrl, $body))->toBe([__('statamic::validation.url_feed.atom.atom_namespace')]);
});

it('accepts an Atom feed whose descendant declares another default namespace', function (): void {
    // Control for the root-only read: the root is in the Atom namespace, so a child in
    // another namespace does not change the verdict.
    $body = '<feed xmlns="'.FEED_RULES_ATOM_NS.'"><entry xmlns="http://example.com/ns"/>'
        .'<title>t</title><id>i</id><updated>u</updated></feed>';

    expect(feedRuleMessages(new AtomFeedUrl, $body))->toBe([]);
});

it('restores the libxml error mode it found, on every path past the fetch (#2994)', function (BaseValidationRule $rule, string $body, bool $mode): void {
    $previous = libxml_use_internal_errors($mode);

    try {
        feedRuleMessages($rule, $body);

        // Passing null reads the mode without changing it. With the mode found on, the
        // buffer survives the restore, so only the rule's own clear empties it.
        expect(libxml_use_internal_errors(null))->toBe($mode)
            ->and(libxml_get_errors())->toBe([]);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
})->with([
    'Atom, not XML' => [new AtomFeedUrl, '<feed>'],
    'Atom, not a feed' => [new AtomFeedUrl, '<rss/>'],
    'Atom, a valid feed' => [new AtomFeedUrl, '<feed xmlns="http://www.w3.org/2005/Atom"><title>t</title><id>i</id><updated>u</updated></feed>'],
    'RSS, not XML' => [new RssFeedUrl, '<rss>'],
    'RSS, not a feed' => [new RssFeedUrl, '<feed/>'],
    'RSS, a valid feed' => [new RssFeedUrl, '<rss version="2.0"><channel><title>t</title><link>l</link></channel></rss>'],
])->with([
    'found off' => [false],
    'found on' => [true],
]);
