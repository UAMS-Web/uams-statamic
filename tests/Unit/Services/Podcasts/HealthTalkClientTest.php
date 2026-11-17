<?php

declare(strict_types=1);

use App\Services\Podcasts\HealthTalk\HealthTalkClient;
use App\Services\Podcasts\HealthTalk\HealthTalkSourceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

/**
 * UAMS-Web/uams-statamic#309: pins `HealthTalkClient`, the fetcher behind the
 * UAMS Health Talk sync (UAMS-Web/uams-statamic-archive#1803), against a faked
 * HTTP factory: the URLs it requests, the options it sends, and the single
 * exception type every failure is raised as.
 *
 * @command  php artisan test --compact tests/Unit/Services/Podcasts/HealthTalkClientTest.php
 */
const HEALTH_TALK_CLIENT_FEED_URL = 'https://feeds.example.test/health-talk.xml';
const HEALTH_TALK_CLIENT_ITEM_PATTERN = 'https://pages.example.test/item/%s?view=full';

/**
 * A client over a faked factory, recording every request with its options.
 *
 * @param  Closure(Request, array<array-key, mixed>): mixed  $respond  The fake response to each request
 * @param  list<array{request: Request, options: array<array-key, mixed>}>  $sent  Each request and its options, in order
 */
function healthTalkClient(Closure $respond, array &$sent = [], int $timeout = 17): HealthTalkClient
{
    // Build a standalone factory whose every request is answered by the fake
    $http = new HttpFactory;
    $http->fake(function (Request $request, array $options) use ($respond, &$sent): mixed {
        // Record the request and the options it carried
        $sent[] = ['request' => $request, 'options' => $options];

        return $respond($request, $options);
    });

    return new HealthTalkClient($http, HEALTH_TALK_CLIENT_FEED_URL, HEALTH_TALK_CLIENT_ITEM_PATTERN, $timeout);
}

it('returns the feed body from a GET of the configured feed URL', function (): void {
    $sent = [];
    $client = healthTalkClient(fn (): mixed => HttpFactory::response('<rss>feed</rss>', 200), $sent);

    expect($client->feed())->toBe('<rss>feed</rss>')
        ->and($sent)->toHaveCount(1)
        ->and($sent[0]['request']->method())->toBe('GET')
        ->and($sent[0]['request']->url())->toBe(HEALTH_TALK_CLIENT_FEED_URL);
});

it('sends the configured timeout and the sync user agent on every request', function (): void {
    $sent = [];
    $client = healthTalkClient(fn (): mixed => HttpFactory::response('ok', 200), $sent, 23);

    $client->feed();
    $client->itemPage('abc');

    expect($sent)->toHaveCount(2);

    foreach ($sent as $call) {
        expect($call['options']['timeout'] ?? null)->toBe(23)
            ->and($call['request']->header('User-Agent'))->toBe(['UAMS Health Talk sync (uams-statamic)']);
    }
});

it('fills the item URL pattern with the raw-url-encoded source id', function (): void {
    $sent = [];
    $client = healthTalkClient(fn (): mixed => HttpFactory::response('<html>page</html>', 200), $sent);

    expect($client->itemPage('a b/c+d'))->toBe('<html>page</html>')
        ->and($sent[0]['request']->url())->toBe('https://pages.example.test/item/a%20b%2Fc%2Bd?view=full');
});

it('raises a source exception naming the URL and status for any status but 200', function (int $status): void {
    $client = healthTalkClient(fn (): mixed => HttpFactory::response('body', $status));

    expect(fn (): string => $client->feed())->toThrow(
        HealthTalkSourceException::class,
        sprintf('Fetching %s returned HTTP %d.', HEALTH_TALK_CLIENT_FEED_URL, $status),
    );
})->with([
    'created' => [201],
    'no content' => [204],
    'redirect' => [301],
    'not found' => [404],
    'server error' => [500],
]);

it('wraps a transport failure in a source exception that keeps the cause', function (): void {
    $cause = new ConnectionException('cURL error 28: timed out');
    $client = healthTalkClient(function () use ($cause): never {
        throw $cause;
    });

    try {
        $client->itemPage('ep-1');
        $this->fail('Expected a HealthTalkSourceException.');
    } catch (HealthTalkSourceException $exception) {
        expect($exception->getMessage())->toBe('Fetching https://pages.example.test/item/ep-1?view=full failed: cURL error 28: timed out')
            ->and($exception->getCode())->toBe(0)
            ->and($exception->getPrevious())->toBe($cause);
    }
});
