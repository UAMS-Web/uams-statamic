<?php

declare(strict_types=1);

namespace App\Services\Podcasts\HealthTalk;

use Carbon\CarbonImmutable;

/**
 * One UAMS Health Talk episode as the DoctorPodcasting feed describes it (UAMS-Web/uams-statamic-archive#1803).
 *
 * Everything here is feed-owned: a sync writes these values over the entry's
 * copies on every change and never touches a field the feed does not supply.
 */
final readonly class HealthTalkFeedItem
{
    /**
     * @param  string  $sourceId  The item's `<customid>`, the stable key joining feed, item page and entry
     * @param  string  $title  Episode title, entity-decoded
     * @param  CarbonImmutable|null  $publishedAt  `<pubDate>`, or null when it is not an RFC 2822 date
     * @param  string  $showNotesHtml  `<description>` with the leading thumbnail anchor stripped
     * @param  string|null  $duration  `<itunes:duration>` as `HH:MM:SS`, or null when absent
     * @param  int|null  $episodeNumber  Parsed from the enclosure filename (`uams118.mp3`); null when it carries none
     * @param  string|null  $audioUrl  The enclosure URL, or null when absent or outside the trusted prefix
     */
    public function __construct(
        public string $sourceId,
        public string $title,
        public ?CarbonImmutable $publishedAt,
        public string $showNotesHtml,
        public ?string $duration,
        public ?int $episodeNumber,
        public ?string $audioUrl
    ) {}
}
