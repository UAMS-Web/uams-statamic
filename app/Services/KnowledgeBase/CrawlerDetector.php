<?php

declare(strict_types=1);

namespace App\Services\KnowledgeBase;

/**
 * Best-effort crawler / bot detection from the request User-Agent, used to keep
 * automated traffic from inflating knowledge base engagement counts (UAMS-Web/uams-statamic-archive#286, UAMS-Web/uams-statamic-archive#287).
 *
 * This is a courtesy filter, not a security control: a determined client can spoof
 * any User-Agent. It catches the common search-engine, social-preview, and
 * monitoring bots (which honestly identify themselves) plus obviously empty agents,
 * which is all the engagement counters need — repeat-vote prevention and rate
 * limiting bound the rest.
 */
class CrawlerDetector
{
    /**
     * Case-insensitive substrings that mark a User-Agent as an automated client.
     * Deliberately broad ("bot", "crawl", "spider") plus a few named agents that do
     * not contain those tokens.
     *
     * @var list<string>
     */
    private const array SIGNATURES = [
        'bot',
        'crawl',
        'spider',
        'slurp',
        'mediapartners',
        'facebookexternalhit',
        'facebot',
        'embedly',
        'quora link preview',
        'pinterest',
        'developers.google.com/+/web/snippet',
        'feedfetcher',
        'headlesschrome',
        'phantomjs',
        'python-requests',
        'curl/',
        'wget',
        'go-http-client',
        'apache-httpclient',
        'okhttp',
        'axios/',
        'guzzlehttp',
        'lighthouse',
        'pingdom',
        'uptimerobot',
        'monitoring',
    ];

    /**
     * Whether the given User-Agent looks like an automated client.
     *
     * An empty or missing User-Agent is treated as a bot: a real browser always
     * sends one, so its absence is a reliable non-human signal.
     *
     * @param  string|null  $userAgent  The request User-Agent header value
     * @return bool True when the agent is empty or matches a known bot signature
     */
    public function isBot(?string $userAgent): bool
    {
        // Treat a missing or blank agent as automated
        if ($userAgent === null || trim($userAgent) === '') {
            return true;
        }

        // Match case-insensitively against the known bot signatures
        $haystack = strtolower($userAgent);

        // Any signature substring present marks the agent as a bot
        return \array_any(self::SIGNATURES, static fn (string $signature): bool => str_contains($haystack, $signature));
    }
}
