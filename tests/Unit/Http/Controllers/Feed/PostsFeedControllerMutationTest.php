<?php

declare(strict_types=1);

use App\Http\Controllers\Feed\PostsFeedController;
use Illuminate\Support\Carbon;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(PostsFeedController::class);

/**
 * Narrow mutate coverage for App\Http\Controllers\Feed\PostsFeedController.
 *
 * The current site, the entry query and its entries are mocked, so mutant children
 * never touch the Stache, and each case compares the whole document. The feed
 * against real entries is covered by tests/Feature/Feed/PostsFeedControllerTest.php.
 *
 * @command  php artisan test --compact tests/Unit/Http/Controllers/Feed/PostsFeedControllerMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``, and fix the
 * clock the channel's lastBuildDate reads.
 */
beforeEach(function (): void {
    padMutateChildTimeout();
    $this->travelTo(Carbon::parse('2026-01-02 03:04:05', 'UTC'));
});

/**
 * Make the current site a double with the given name, URL and language.
 */
function postsFeedSite(mixed $name = 'UAMS', mixed $url = 'https://uams.test/', mixed $lang = 'en'): void
{
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('name')->andReturn($name);
    $site->shouldReceive('absoluteUrl')->andReturn($url);
    $site->shouldReceive('lang')->andReturn($lang);
    $site->shouldReceive('handle')->andReturn('default');

    Site::shouldReceive('current')->andReturn($site);
}

/**
 * Expect the feed's one query, with each of its conditions once, returning the entries.
 *
 * @param  list<Entry>  $entries
 */
function postsFeedQuery(array $entries): void
{
    $query = Mockery::mock();
    $query->shouldReceive('where')->once()->with('collection', 'posts')->andReturnSelf();
    $query->shouldReceive('where')->once()->with('site', 'default')->andReturnSelf();
    $query->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $query->shouldReceive('where')->once()->with('status_and_visibility', '!=', 'private')->andReturnSelf();
    $query->shouldReceive('where')->once()->with('password_protected', '!=', true)->andReturnSelf();
    $query->shouldReceive('orderBy')->once()->with('date', 'desc')->andReturnSelf();
    $query->shouldReceive('limit')->once()->with(10)->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(collect($entries));

    EntryFacade::shouldReceive('query')->once()->andReturn($query);
}

/**
 * A post double.
 */
function postsFeedEntry(mixed $title, mixed $url, mixed $date, mixed $excerpt): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('value')->with('title')->andReturn($title);
    $entry->shouldReceive('value')->with('excerpt')->andReturn($excerpt);
    $entry->shouldReceive('absoluteUrl')->andReturn($url);
    $entry->shouldReceive('date')->andReturn($date);

    return $entry;
}

/**
 * Render the feed and return its body.
 */
function postsFeedBody(): string
{
    $response = (new PostsFeedController)();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('application/rss+xml; charset=UTF-8');

    return (string) $response->getContent();
}

/**
 * The document the feed renders around the given channel title, link, language
 * element and items.
 */
function postsFeedDocument(string $title, string $link, string $language, string $items): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
        .'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
        .'<title>'.$title.'</title>'
        .'<link>'.$link.'</link>'
        .'<description>Latest posts from '.$title.'.</description>'
        .'<atom:link href="'.$link.'feed.xml" rel="self" type="application/rss+xml"/>'
        .$language
        .'<lastBuildDate>Fri, 02 Jan 2026 03:04:05 +0000</lastBuildDate>'
        .$items
        .'</channel></rss>'."\n";
}

it('renders the channel and an item with its link, guid, date and excerpt', function (): void {
    postsFeedSite();
    postsFeedQuery([
        postsFeedEntry('First post', 'https://uams.test/news/first', Carbon::parse('2025-12-01 10:00:00', 'UTC'), 'A summary.'),
    ]);

    expect(postsFeedBody())->toBe(postsFeedDocument(
        'UAMS',
        'https://uams.test/',
        '<language>en</language>',
        '<item><title>First post</title>'
            .'<link>https://uams.test/news/first</link>'
            .'<guid isPermaLink="true">https://uams.test/news/first</guid>'
            .'<pubDate>Mon, 01 Dec 2025 10:00:00 +0000</pubDate>'
            .'<description><![CDATA[A summary.]]></description></item>',
    ));
});

it('adds exactly one trailing slash to the site URL', function (): void {
    postsFeedSite(url: 'https://uams.test/news');
    postsFeedQuery([]);

    expect(postsFeedBody())->toBe(postsFeedDocument('UAMS', 'https://uams.test/news/', '<language>en</language>', ''));
});

it('leaves out the language when the site has none, and reads a name or URL that is not a string as empty', function (): void {
    postsFeedSite(name: null, url: 42, lang: '');
    postsFeedQuery([]);

    expect(postsFeedBody())->toBe(postsFeedDocument('', '/', '', ''));
});

it('leaves out the language when it is not a string', function (): void {
    postsFeedSite(lang: ['en']);
    postsFeedQuery([]);

    expect(postsFeedBody())->toBe(postsFeedDocument('UAMS', 'https://uams.test/', '', ''));
});

it('writes an item without a link, guid or date, with empty text for a title or excerpt that is not a string', function (): void {
    postsFeedSite();
    postsFeedQuery([postsFeedEntry(null, null, '2025-12-01', ['not', 'text'])]);

    expect(postsFeedBody())->toBe(postsFeedDocument(
        'UAMS',
        'https://uams.test/',
        '<language>en</language>',
        '<item><title></title><description><![CDATA[]]></description></item>',
    ));
});

it('aborts with a 500 when the current site does not resolve', function (): void {
    Site::shouldReceive('current')->andReturn(null);
    EntryFacade::shouldReceive('query')->never();

    try {
        (new PostsFeedController)();
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(500)
            ->and($exception->getMessage())->toBe('Unable to resolve the current site for the feed.');

        return;
    }

    throw new RuntimeException('The feed rendered without a current site.');
});
