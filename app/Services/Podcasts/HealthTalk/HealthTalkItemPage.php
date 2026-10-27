<?php

declare(strict_types=1);

namespace App\Services\Podcasts\HealthTalk;

/**
 * What one episode's public item page supplies beyond the feed (UAMS-Web/uams-statamic-archive#1803).
 */
final readonly class HealthTalkItemPage
{
    /**
     * @param  list<HealthTalkSpeaker>  $speakers  Featured speakers, in page order
     * @param  string|null  $transcriptHtml  The rendered transcript's HTML, or null when the page has none
     */
    public function __construct(
        public array $speakers,
        public ?string $transcriptHtml
    ) {}
}
