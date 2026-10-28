<?php

declare(strict_types=1);

use App\Rules\FeedUrl;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

covers(FeedUrl::class);

/**
 * Behavior of the `FeedUrl` rule: a URL passes when it serves either an Atom or
 * an RSS feed, trying Atom first and RSS only when Atom fails, and fails with the
 * combined feed message otherwise. HTTP is faked against a public IP literal, so
 * nothing leaves the machine and no DNS lookup is made.
 *
 * @command  php artisan test --compact tests/Feature/Rules/FeedUrlTest.php
 */
const FEED_URL_PUBLIC = 'https://93.184.216.34/feed.xml';

/**
 * Run the rule against the public URL with a faked response body, returning the
 * messages it failed with.
 *
 * @return list<mixed>
 */
function feedUrlMessages(string $body): array
{
    Http::fake(['*' => Http::response($body, 200)]);

    $messages = [];

    (new FeedUrl)->validate('feed', FEED_URL_PUBLIC, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

it('accepts an Atom feed without trying RSS', function (): void {
    $body = '<feed xmlns="http://www.w3.org/2005/Atom"><title>News</title><id>urn:uuid:1</id>'
        .'<updated>2024-01-01T00:00:00Z</updated></feed>';

    expect(feedUrlMessages($body))->toBe([]);
    Http::assertSentCount(1);
});

it('accepts an RSS feed after Atom refuses it', function (): void {
    $body = '<rss version="2.0"><channel><title>News</title><link>https://example.com/</link></channel></rss>';

    expect(feedUrlMessages($body))->toBe([]);
    Http::assertSentCount(2);
});

it('rejects a URL that serves neither feed with the combined message', function (): void {
    expect(feedUrlMessages('<html><body>Not a feed</body></html>'))->toBe([__('statamic::validation.url_feed.valid')]);
    Http::assertSentCount(2);
});

it('rejects a value that is not a URL without fetching', function (): void {
    Http::fake();

    $messages = [];
    (new FeedUrl)->validate('feed', 'not a url', function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($messages)->toBe([__('statamic::validation.url_feed.valid')]);
    Http::assertNothingSent();
});
