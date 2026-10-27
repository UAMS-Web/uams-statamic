<?php

declare(strict_types=1);

use App\Services\Podcasts\HealthTalk\HealthTalkFeedItem;
use App\Services\Podcasts\HealthTalk\HealthTalkFeedParser;
use App\Services\Podcasts\HealthTalk\HealthTalkItemPageParser;
use App\Services\Podcasts\HealthTalk\HealthTalkSourceException;
use App\Services\Podcasts\HealthTalk\HealthTalkSpeaker;
use PHPUnit\Framework\Assert;

/**
 * UAMS-Web/uams-statamic-archive#1803 — the feed and item-page parsers, against the committed real fixtures
 * and against minimal hostile or malformed variants of them.
 *
 * @command php artisan test --compact tests/Unit/Services/Podcasts/HealthTalkParsersTest.php
 */
const HEALTH_TALK_PARSER_FIXTURES = __DIR__.'/../../../Fixtures/HealthTalk';
const HEALTH_TALK_PARSER_PREFIX = 'https://support.doctorpodcasting.com/';

/**
 * The fixture feed's items, or those of another feed body, in feed order.
 *
 * @return list<HealthTalkFeedItem>
 */
function parsedHealthTalkFeed(?string $xml = null): array
{
    return (new HealthTalkFeedParser(HEALTH_TALK_PARSER_PREFIX))
        ->parse($xml ?? (string) file_get_contents(HEALTH_TALK_PARSER_FIXTURES.'/feed.xml'));
}

/**
 * One parsed item by its source id, failing the test when it is absent.
 */
function parsedHealthTalkItem(string $sourceId, ?string $xml = null): HealthTalkFeedItem
{
    foreach (parsedHealthTalkFeed($xml) as $item) {
        if ($item->sourceId === $sourceId) {
            return $item;
        }
    }

    Assert::fail("No parsed item {$sourceId}.");
}

/**
 * The source ids a feed body parses to.
 *
 * @return list<string>
 */
function parsedHealthTalkIds(?string $xml = null): array
{
    return array_map(static fn (HealthTalkFeedItem $item): string => $item->sourceId, parsedHealthTalkFeed($xml));
}

/**
 * A one-item feed.
 */
function healthTalkOneItemFeed(string $item): string
{
    return '<?xml version="1.0"?><rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"><channel><title>t</title>'.$item.'</channel></rss>';
}

/**
 * Speaker name and slug pairs from a page.
 *
 * @return list<array{0: string, 1: string|null}>
 */
function healthTalkSpeakers(string $html): array
{
    return array_map(
        static fn (HealthTalkSpeaker $speaker): array => [$speaker->name, $speaker->providerSlug],
        (new HealthTalkItemPageParser)->parse($html)->speakers
    );
}

it('reads every fixture item with its episode number, duration and trusted audio URL', function (): void {
    $latest = parsedHealthTalkItem('69402');
    $bioPod = parsedHealthTalkItem('41530');

    expect(parsedHealthTalkIds())->toBe(['69402', '69184', '68170', '43833', '41530'])
        ->and($latest->episodeNumber)->toBe(120)
        ->and(parsedHealthTalkItem('43833')->episodeNumber)->toBe(37)
        ->and($bioPod->episodeNumber)->toBeNull()
        ->and($bioPod->audioUrl)->toEndWith('/uams016bpd.mp3')
        ->and($latest->duration)->toMatch('/^\d{2}:\d{2}:\d{2}$/')
        ->and($latest->publishedAt?->format('Y-m-d'))->toBe('2026-08-27');
});

it('strips the leading thumbnail anchor from the show notes and nothing after it', function (): void {
    $item = parsedHealthTalkItem('1', healthTalkOneItemFeed(
        '<item><customid>1</customid><title>T</title><description><![CDATA[ <a href="https://x.test/"><img src="https://x.test/a.png"></a><p>Body <a href="https://y.test/">link</a></p>]]></description></item>'
    ));

    expect($item->showNotesHtml)->toBe('<p>Body <a href="https://y.test/">link</a></p>');
});

it('drops an enclosure outside the trusted prefix and keeps its episode number', function (string $url, ?string $expected): void {
    $item = parsedHealthTalkItem('1', healthTalkOneItemFeed(
        '<item><customid>1</customid><title>T</title><description>d</description><enclosure url="'.$url.'" type="audio/mpeg"/></item>'
    ));

    expect($item->audioUrl)->toBe($expected)
        ->and($item->episodeNumber)->toBe(7);
})->with([
    'the vendor host' => ['https://support.doctorpodcasting.com/download/1/x/uams007.mp3', 'https://support.doctorpodcasting.com/download/1/x/uams007.mp3'],
    'another host' => ['https://evil.test/uams007.mp3', null],
    'a lookalike host' => ['https://support.doctorpodcasting.com.evil.test/uams007.mp3', null],
    'plain http' => ['http://support.doctorpodcasting.com/uams007.mp3', null],
]);

it('skips an item with no id, keeps the first of a repeated id, and survives a bad date', function (): void {
    $feed = healthTalkOneItemFeed(
        '<item><title>No id</title></item>'
        .'<item><customid> 9 </customid><title>Q &amp; A</title><pubDate>not a date</pubDate><description></description></item>'
        .'<item><customid>9</customid><title>Second</title><description></description></item>'
    );
    $item = parsedHealthTalkItem('9', $feed);

    expect(parsedHealthTalkIds($feed))->toBe(['9'])
        ->and($item->title)->toBe('Q & A')
        ->and($item->publishedAt)->toBeNull()
        ->and($item->audioUrl)->toBeNull();
});

it('trims padding from every field it reads', function (): void {
    $item = parsedHealthTalkItem('1', healthTalkOneItemFeed(
        '<item><customid>1</customid><title>  Padded title  </title>'
        .'<pubDate>  Wed, 27 Aug 2025 01:00:00 -0500  </pubDate>'
        .'<itunes:duration>  00:12:34  </itunes:duration>'
        .'<description><![CDATA[  <p>Notes</p>  ]]></description>'
        .'<enclosure url="  https://support.doctorpodcasting.com/download/1/x/uams007.mp3  " type="audio/mpeg"/></item>'
    ));

    expect($item->title)->toBe('Padded title')
        ->and($item->publishedAt?->toIso8601String())->toBe('2025-08-27T01:00:00-05:00')
        ->and($item->duration)->toBe('00:12:34')
        ->and($item->showNotesHtml)->toBe('<p>Notes</p>')
        ->and($item->audioUrl)->toBe('https://support.doctorpodcasting.com/download/1/x/uams007.mp3')
        ->and($item->episodeNumber)->toBe(7);
});

it('reads a missing duration as null, and an invalid URL under the trusted prefix as no audio', function (): void {
    $item = parsedHealthTalkItem('1', healthTalkOneItemFeed(
        '<item><customid>1</customid><title>T</title><description>d</description>'
        .'<enclosure url="https://support.doctorpodcasting.com/a b/uams007.mp3" type="audio/mpeg"/></item>'
    ));

    expect($item->duration)->toBeNull()
        ->and($item->audioUrl)->toBeNull();
});

it('reads only an RFC 2822 date, never an empty or relative one', function (string $pubDate, ?string $expected): void {
    $item = parsedHealthTalkItem('1', healthTalkOneItemFeed(
        '<item><customid>1</customid><title>T</title>'.$pubDate.'<description>d</description></item>'
    ));

    expect($item->publishedAt?->toIso8601String())->toBe($expected);
})->with([
    'the feed form' => ['<pubDate>Wed, 27 Aug 2025 01:00:00 -0500</pubDate>', '2025-08-27T01:00:00-05:00'],
    'missing' => ['', null],
    'empty' => ['<pubDate></pubDate>', null],
    'relative' => ['<pubDate>next tuesday</pubDate>', null],
]);

it('decodes a title once and strips any markup from it', function (string $title, string $expected): void {
    $item = parsedHealthTalkItem('1', healthTalkOneItemFeed(
        '<item><customid>1</customid><title>'.$title.'</title><description>d</description></item>'
    ));

    expect($item->title)->toBe($expected);
})->with([
    'an entity the feed encodes' => ['Q &amp; A', 'Q & A'],
    'a double-encoded tag stays text' => ['A &amp;lt;script&amp;gt;x', 'A &lt;script&gt;x'],
    'markup in CDATA is stripped' => ['<![CDATA[Ep <img src=x onerror=alert(1)> one]]>', 'Ep one'],
]);

it('refuses a body that is not RSS and does not resolve external entities', function (string $body): void {
    expect(fn () => parsedHealthTalkFeed($body))->toThrow(HealthTalkSourceException::class);
})->with([
    'html' => ['<html><body>maintenance</body></html>'],
    'empty' => [''],
    'an external entity' => ['<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><r>&x;</r>'],
]);

it('credits a lone linked speaker with the page slug', function (): void {
    expect(healthTalkSpeakers((string) file_get_contents(HEALTH_TALK_PARSER_FIXTURES.'/item-69402.html')))
        ->toBe([['Robert Hopkins, M.D.', 'robert-h-hopkins-jr']]);
});

it('credits a panel slug only to the speaker its link names', function (): void {
    expect(healthTalkSpeakers((string) file_get_contents(HEALTH_TALK_PARSER_FIXTURES.'/item-68170.html')))
        ->toBe([['Lexie Vaughn, MD', 'lexie-h-vaughn'], ['Tyler Rives, M.D', null]]);
});

it('reads an unlinked speaker with no slug', function (): void {
    expect(healthTalkSpeakers((string) file_get_contents(HEALTH_TALK_PARSER_FIXTURES.'/item-41530.html')))
        ->toBe([['Joseph Henske, MD', null]]);
});

it('ignores provider links inside the transcript', function (): void {
    $html = '<span class="featured_speaker">Featured Speaker:</span><h6> A Person </h6>'
        .'<div class="transcriptbox"><strong>Transcription:</strong><br/> Title <br/><br/><p>See <a href="https://uamshealth.com/provider/someone-else/">x</a></p></div>';

    expect(healthTalkSpeakers($html))->toBe([['A Person', null]]);
});

it('returns the transcript without its heading or repeated title, and null when there is none', function (): void {
    $parser = new HealthTalkItemPageParser;

    $transcript = $parser->parse((string) file_get_contents(HEALTH_TALK_PARSER_FIXTURES.'/item-69402.html'))->transcriptHtml;
    expect($transcript)->toBeString()
        ->not->toContain('Transcription:')
        ->toStartWith('<p>');

    expect($parser->parse((string) file_get_contents(HEALTH_TALK_PARSER_FIXTURES.'/item-43833.html'))->transcriptHtml)->toBeNull()
        ->and($parser->parse('<html><body><p>No speakers here</p></body></html>')->speakers)->toBe([]);
});

/**
 * A minimal item page with this speaker block and transcript box.
 */
function healthTalkPage(string $speakerBlock, string $transcript = ''): string
{
    // No charset declaration, so the parser's own UTF-8 handling is what keeps names intact
    return '<html><body>'.$speakerBlock.$transcript.'</body></html>';
}

it('reads no speakers from a page without the label or without the name heading', function (string $html): void {
    expect((new HealthTalkItemPageParser)->parse($html)->speakers)->toBe([]);
})->with([
    'no label' => [healthTalkPage('<h6> A Person </h6>')],
    'no heading' => [healthTalkPage('<span class="featured_speaker">Featured Speaker:</span><p>A Person</p>')],
]);

it('drops empty panel parts and keeps non-ASCII names intact', function (): void {
    expect(healthTalkSpeakers(healthTalkPage('<span class="featured_speaker">Featured Speakers:</span><h6> Renée Zoë, MD |  | Ana&nbsp;Li </h6>')))
        ->toBe([['Renée Zoë, MD', null], ['Ana Li', null]]);
});

it('lowercases a slug, and credits a lone speaker whatever the link text says', function (): void {
    expect(healthTalkSpeakers(healthTalkPage(
        '<span class="featured_speaker">Featured Speaker:</span><h6> A Person </h6>'
        .'<a href="https://uamshealth.com/provider/Some-Person/">Learn more</a>'
        .'<a href="https://uamshealth.com/provider/some-person">Profile</a>'
    )))->toBe([['A Person', 'some-person']]);
});

it('credits no slug to a lone speaker whose page links two providers', function (): void {
    expect(healthTalkSpeakers(healthTalkPage(
        '<span class="featured_speaker">Featured Speaker:</span><h6> A Person </h6>'
        .'<a href="https://uamshealth.com/provider/one/">One</a><a href="https://uamshealth.com/provider/two/">Two</a>'
    )))->toBe([['A Person', null]]);
});

it('reads the transcript from its box, trimmed, and null when the box is absent or has no text', function (string $box, ?string $expected): void {
    expect((new HealthTalkItemPageParser)->parse(healthTalkPage('', $box))->transcriptHtml)->toBe($expected);
})->with([
    'heading and title stripped, padding trimmed' => ['<div class="transcriptbox"><strong>Transcription:</strong><br/> Title <br/><br/>  <p>Line</p> tail  </div>', '<p>Line</p> tail'],
    'heading stripped with no title line' => ['<div class="transcriptbox"><strong>Transcription:</strong><p>Line</p></div>', '<p>Line</p>'],
    'no box' => ['<div class="other"><p>Line</p></div>', null],
    'markup without text' => ['<div class="transcriptbox"><strong>Transcription:</strong><p><br></p></div>', null],
]);

it('restores the global libxml state after parsing, even a body it rejects', function (bool $before): void {
    $previous = libxml_use_internal_errors($before);
    libxml_clear_errors();

    try {
        // An unmatched end tag is one libxml records as an error, so there is something to clear
        (new HealthTalkItemPageParser)->parse('<div></span></div>');

        expect(libxml_use_internal_errors())->toBe($before)
            ->and(libxml_get_errors())->toBe([]);

        try {
            parsedHealthTalkFeed('<rss><channel>');
        } catch (HealthTalkSourceException) {
            // Refused, as expected; the state is what is under test
        }

        expect(libxml_use_internal_errors())->toBe($before)
            ->and(libxml_get_errors())->toBe([]);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
})->with(['errors off before' => [false], 'errors collected before' => [true]]);
