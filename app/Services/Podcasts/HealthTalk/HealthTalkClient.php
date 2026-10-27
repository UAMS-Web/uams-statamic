<?php

declare(strict_types=1);

namespace App\Services\Podcasts\HealthTalk;

use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Fetches the UAMS Health Talk feed and item pages (UAMS-Web/uams-statamic-archive#1803).
 *
 * Both URLs come from `config/uams_health_talk.php` and are never editor
 * supplied, so they need no outbound-URL guard. A failure of any kind -- a
 * transport error, a timeout or a non-200 -- is raised as one exception type,
 * because the sync's only safe response to any of them is to change nothing.
 */
final readonly class HealthTalkClient
{
    /**
     * @param  HttpFactory  $http  Laravel HTTP client factory
     * @param  string  $feedUrl  The RSS feed URL
     * @param  string  $itemUrlPattern  The item page URL, with `%s` for the item id
     * @param  int  $timeout  Seconds before a request is abandoned
     */
    public function __construct(
        private HttpFactory $http,
        private string $feedUrl,
        private string $itemUrlPattern,
        private int $timeout
    ) {}

    /**
     * The feed body.
     *
     * @throws HealthTalkSourceException When the feed cannot be fetched
     */
    public function feed(): string
    {
        return $this->get($this->feedUrl);
    }

    /**
     * One episode's item page body.
     *
     * @param  string  $sourceId  The item's `<customid>`
     *
     * @throws HealthTalkSourceException When the page cannot be fetched
     */
    public function itemPage(
        string $sourceId
    ): string {
        return $this->get(\sprintf($this->itemUrlPattern, \rawurlencode($sourceId)));
    }

    /**
     * GET a URL and return its body, or raise on anything but a 200.
     *
     * @throws HealthTalkSourceException
     */
    private function get(
        string $url
    ): string {
        try {
            $response = $this->http
                ->timeout($this->timeout)
                ->withHeaders(['User-Agent' => 'UAMS Health Talk sync (uams-statamic)'])
                ->get($url);
        } catch (Throwable $throwable) {
            throw new HealthTalkSourceException(\sprintf('Fetching %s failed: %s', $url, $throwable->getMessage()), 0, $throwable);
        }

        if ($response->status() !== 200) {
            throw new HealthTalkSourceException(\sprintf('Fetching %s returned HTTP %d.', $url, $response->status()));
        }

        return $response->body();
    }
}
