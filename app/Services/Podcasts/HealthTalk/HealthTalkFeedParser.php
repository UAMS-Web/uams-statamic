<?php

declare(strict_types=1);

namespace App\Services\Podcasts\HealthTalk;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use SimpleXMLElement;

/**
 * Reads the UAMS Health Talk RSS feed into episode value objects (UAMS-Web/uams-statamic-archive#1803).
 *
 * The mapping follows `docs/specs/uams-health-talk-podcast.md`:
 *
 * - `<customid>` is the key; an item without one is skipped, since it could
 *   never be matched to its entry again.
 * - `<description>` opens with a thumbnail `<a><img></a>` that is stripped.
 * - The episode number is not a feed field. It is parsed from the enclosure
 *   filename (`uams118.mp3`), and one Physician BioPod item (`uams016bpd.mp3`)
 *   legitimately yields none.
 * - An enclosure outside the configured prefix is dropped, because the URL is
 *   later rendered as a player source.
 *
 * The XML is parsed with network access disabled and without entity
 * substitution, so a hostile feed cannot make the parser read local files or
 * reach other hosts.
 */
final readonly class HealthTalkFeedParser
{
    /**
     * The iTunes namespace the duration lives in.
     */
    private const string ITUNES_NS = 'http://www.itunes.com/dtds/podcast-1.0.dtd';

    /**
     * @param  string  $audioUrlPrefix  The only enclosure prefix trusted as a player source
     */
    public function __construct(
        private string $audioUrlPrefix
    ) {}

    /**
     * Parse a feed document into its episodes, in feed order.
     *
     * @param  string  $xml  Raw feed body
     * @return list<HealthTalkFeedItem>
     *
     * @throws HealthTalkSourceException When the body is not a readable RSS feed
     */
    public function parse(
        string $xml
    ): array {
        // Collecting libxml's errors keeps a malformed body from raising PHP warnings; a test
        // without Laravel's error handler cannot observe the difference.
        // @pest-mutate-ignore: TrueToFalse
        $previous = libxml_use_internal_errors(true);

        try {
            // NONET and NOCDATA are defense in depth: nothing here loads a DTD, and a cast reads
            // CDATA as text, so neither flag changes the result for any input a test can give.
            // @pest-mutate-ignore: BitwiseOrToBitwiseAnd
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        // Counting the named children, since a missing child reads as an empty element
        if (! $document instanceof SimpleXMLElement || \count($document->channel) === 0) {
            throw new HealthTalkSourceException('The UAMS Health Talk feed is not a readable RSS document.');
        }

        $items = [];

        foreach ($document->channel->item as $item) {
            $parsed = $this->item($item);

            // A repeated id keeps its first item, since one id can only key one entry
            if ($parsed instanceof HealthTalkFeedItem) {
                $items[$parsed->sourceId] ??= $parsed;
            }
        }

        return \array_values($items);
    }

    /**
     * One `<item>`, or null when it carries no usable key.
     */
    private function item(
        SimpleXMLElement $item
    ): ?HealthTalkFeedItem {
        $sourceId = \trim((string) $item->customid);

        if ($sourceId === '') {
            return null;
        }

        // A missing child reads as an empty element, so an absent enclosure is an empty string
        $enclosure = \trim((string) $item->enclosure['url']);
        $duration = \trim((string) $item->children(self::ITUNES_NS)->duration);

        return new HealthTalkFeedItem(
            sourceId: $sourceId,
            title: $this->title((string) $item->title),
            publishedAt: $this->publishedAt((string) $item->pubDate),
            showNotesHtml: $this->showNotes((string) $item->description),
            duration: $duration === '' ? null : $duration,
            episodeNumber: $this->episodeNumber($enclosure),
            audioUrl: $this->trustedAudioUrl($enclosure)
        );
    }

    /**
     * The title as plain text.
     *
     * SimpleXML has already decoded the feed's entities once, which is all the
     * feed encodes, so the title is not decoded again. Markup is stripped,
     * because a title is rendered raw by several templates.
     */
    private function title(
        string $value
    ): string {
        $text = \strip_tags($value);

        return \trim(\preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * The item's RFC 2822 publication date, or null when it is missing or in any other form.
     *
     * The parse is strict on purpose: a lenient one reads an empty value as now
     * and `next tuesday` as a future date, and either would move the entry's date.
     */
    private function publishedAt(
        string $value
    ): ?CarbonImmutable {
        try {
            $date = CarbonImmutable::createFromFormat(DATE_RFC2822, \trim($value));
        } catch (InvalidFormatException) {
            return null;
        }

        // Carbon's strict mode throws rather than returning false, so this guard only narrows the
        // type and InstanceOfToTrue cannot be killed.
        // @pest-mutate-ignore: InstanceOfToTrue
        return $date instanceof CarbonImmutable ? $date : null;
    }

    /**
     * Show notes with the leading thumbnail anchor removed.
     */
    private function showNotes(
        string $description
    ): string {
        $stripped = \preg_replace('~^\s*<a\b[^>]*>\s*<img\b[^>]*>\s*</a>~i', '', $description);

        return \trim(\is_string($stripped) ? $stripped : $description);
    }

    /**
     * The episode number an enclosure filename carries (`uams118.mp3` is 118).
     */
    private function episodeNumber(
        string $enclosure
    ): ?int {
        if (\preg_match('~/uams(\d+)\.mp3$~i', $enclosure, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * The enclosure URL when it sits under the trusted prefix, else null.
     */
    private function trustedAudioUrl(
        string $enclosure
    ): ?string {
        if (! \str_starts_with($enclosure, $this->audioUrlPrefix)) {
            return null;
        }

        return \filter_var($enclosure, FILTER_VALIDATE_URL) === false ? null : $enclosure;
    }
}
