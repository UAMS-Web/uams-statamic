<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

use function Pest\Laravel\get;

/**
 * Route-wiring coverage for the outbound `posts` feed (UAMS-Web/uams-statamic-archive#1042): that
 * `{site}/feed.xml` is registered per posts-enabled site and served as RSS 2.0.
 * The per-site registration reads the real `posts` collection at boot, so these
 * cases need the content submodule and skip gracefully without it (UAMS-Web/uams-statamic-archive#856). The
 * feed's item behavior is covered hermetically in PostsFeedControllerTest.
 */
uses(TestCase::class);

it('serves the default-site posts feed at /feed.xml as RSS 2.0', function (): void {
    if (Collection::findByHandle('posts') === null) {
        ContentSubmodule::reportMissing('posts collection');
    }

    $response = get('/feed.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

    /** @var SimpleXMLElement $xml */
    $xml = simplexml_load_string((string) $response->getContent());
    expect($xml->getName())->toBe('rss');
    expect(isset($xml->channel))->toBeTrue();
});

it('registers the feed under a non-default posts-enabled site prefix', function (): void {
    $posts = Collection::findByHandle('posts');

    if ($posts === null) {
        ContentSubmodule::reportMissing('posts collection');
    }

    /** @var Illuminate\Support\Collection<int, string> $postsSites */
    $postsSites = $posts->sites();
    $postsSiteHandles = $postsSites->all();

    /** @var Illuminate\Support\Collection<string, Statamic\Sites\Site> $sites */
    $sites = Site::all();

    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();
    $defaultHandle = $defaultSite->handle();

    // A posts-enabled site other than the default whose URL carries a path prefix.
    $nonDefault = $sites
        ->reject(fn ($site) => $site->handle() === $defaultHandle)
        ->first(function ($site) use ($postsSiteHandles): bool {
            /** @var string $url */
            $url = $site->url();

            return in_array($site->handle(), $postsSiteHandles, true)
                && trim(URL::makeRelative($url), '/') !== '';
        });

    if ($nonDefault === null) {
        Assert::markTestSkipped('No non-default posts-enabled site with a path prefix.');
    }

    /** @var string $nonDefaultUrl */
    $nonDefaultUrl = $nonDefault->url();
    $path = '/'.trim(URL::makeRelative($nonDefaultUrl), '/').'/feed.xml';

    $response = get($path);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    /** @var SimpleXMLElement $xml */
    $xml = simplexml_load_string((string) $response->getContent());
    expect($xml->getName())->toBe('rss');
});
