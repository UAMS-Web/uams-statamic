<?php

declare(strict_types=1);

use App\Services\KnowledgeBase\CrawlerDetector;

/**
 * The best-effort bot filter keeping automated traffic from inflating engagement
 * counts (UAMS-Web/uams-statamic-archive#286, UAMS-Web/uams-statamic-archive#287).
 *
 * @covers \App\Services\KnowledgeBase\CrawlerDetector
 *
 * @command php artisan test --compact tests/Unit/Services/KnowledgeBase/CrawlerDetectorTest.php
 */
beforeEach(function (): void {
    $this->detector = new CrawlerDetector;
});

it('treats a missing or blank User-Agent as a bot', function (string $userAgent): void {
    /** @var CrawlerDetector $detector */
    $detector = $this->detector;
    expect($detector->isBot($userAgent === 'NULL' ? null : $userAgent))->toBeTrue();
})->with([
    'null' => ['NULL'],
    'empty' => [''],
    'whitespace' => ['   '],
]);

it('flags known crawler / automated-client signatures', function (string $userAgent): void {
    /** @var CrawlerDetector $detector */
    $detector = $this->detector;
    expect($detector->isBot($userAgent))->toBeTrue();
})->with([
    'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
    'bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'],
    'facebook' => ['facebookexternalhit/1.1'],
    'curl' => ['curl/8.4.0'],
    'python' => ['python-requests/2.31.0'],
    'headless' => ['Mozilla/5.0 HeadlessChrome/120.0.0.0'],
    'uptime' => ['UptimeRobot/2.0'],
]);

it('does not flag a real browser User-Agent', function (string $userAgent): void {
    /** @var CrawlerDetector $detector */
    $detector = $this->detector;
    expect($detector->isBot($userAgent))->toBeFalse();
})->with([
    'chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36'],
    'safari' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15'],
    'firefox' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0'],
]);
