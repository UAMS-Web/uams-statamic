<?php

declare(strict_types=1);

use App\Http\Controllers\Seo\SitemapIndexController;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SitemapIndexController::class);

/**
 * Narrow mutate coverage for App\Http\Controllers\Seo\SitemapIndexController.
 *
 * @command  php artisan test --compact tests/Unit/Http/Controllers/Seo/SitemapIndexControllerMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * A Site mock reporting the given absolute URL.
 */
function sitemapIndexSite(string $absoluteUrl): SiteObject
{
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('absoluteUrl')->andReturn($absoluteUrl);

    return $site;
}

it('emits a sitemapindex with one loc per site', function (): void {
    Site::shouldReceive('all')->once()->andReturn(collect([
        sitemapIndexSite('https://alpha.test/'),
        sitemapIndexSite('https://beta.test'),
    ]));

    $response = (new SitemapIndexController)();

    expect($response->headers->get('Content-Type'))->toBe('application/xml')
        ->and($response->getStatusCode())->toBe(200);

    $body = (string) $response->getContent();
    // RemoveMethodCall on startDocument / endDocument would drop the
    // declaration or leave sitemapindex unclosed.
    expect($body)->toStartWith('<?xml version="1.0"')
        ->and(simplexml_load_string($body))->not->toBeFalse()
        ->and($body)->toContain('<sitemapindex')
        ->toContain('xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"')
        ->toContain('<loc>https://alpha.test/sitemap.xml</loc>')
        ->toContain('<loc>https://beta.test/sitemap.xml</loc>');

    /** @var SimpleXMLElement $xml */
    $xml = simplexml_load_string($body);
    expect($xml->getName())->toBe('sitemapindex')
        ->and($xml->sitemap)->toHaveCount(2);
});

it('skips non-Site entries in the sites collection', function (): void {
    Site::shouldReceive('all')->once()->andReturn(collect([
        'not-a-site',
        sitemapIndexSite('https://only.test'),
    ]));

    $body = (string) (new SitemapIndexController)()->getContent();

    /** @var SimpleXMLElement $xml */
    $xml = simplexml_load_string($body);
    expect($xml)->not->toBeFalse()
        ->and($xml->sitemap)->toHaveCount(1)
        ->and((string) $xml->sitemap[0]->loc)->toBe('https://only.test/sitemap.xml');
});

it('treats a non-string absoluteUrl as an empty host before appending sitemap.xml', function (): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('absoluteUrl')->andReturn(null);
    Site::shouldReceive('all')->once()->andReturn(collect([$site]));

    $body = (string) (new SitemapIndexController)()->getContent();

    /** @var SimpleXMLElement $xml */
    $xml = simplexml_load_string($body);
    expect($xml)->not->toBeFalse()
        ->and((string) $xml->sitemap[0]->loc)->toBe('/sitemap.xml');
});

it('emits an empty sitemapindex when Site::all is not iterable', function (): void {
    Site::shouldReceive('all')->once()->andReturn(null);

    $body = (string) (new SitemapIndexController)()->getContent();

    expect($body)->toStartWith('<?xml version="1.0"');
    /** @var SimpleXMLElement $xml */
    $xml = simplexml_load_string($body);
    expect($xml)->not->toBeFalse()
        ->and($xml->getName())->toBe('sitemapindex')
        ->and(isset($xml->sitemap))->toBeFalse();
});
