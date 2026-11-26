<?php

declare(strict_types=1);

use App\Tags\ChildSiteUrl;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Antlers;
use Statamic\Facades\Site;
use Statamic\Fields\Value;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ChildSiteUrl::class);

/**
 * Coverage for the `{{ child_site_url }}` tag (UAMS-Web/uams-statamic-archive#219): in the primary-nav loop it
 * resolves a `child_site_link` item's selected child site (the `child_site`
 * field in context) to its absolute URL, and returns an empty string for a
 * normal page so the nav falls back to the page's own URL.
 *
 * Mutation: 100% on App\Tags\ChildSiteUrl (31 mutants), measured on macOS 26.5 (arm64) on 2026-11-25 with PHP 8.4.28 and Herd's Xdebug 3.5.3, without --covered-only, over this file,
 * PostsCategoryLinkUrlTagTest and PostsCategoryLinkNavTest (UAMS-Web/uams-statamic#801). No mutant
 * is annotated.
 *
 * @command  php artisan test --compact tests/Feature/Tags/ChildSiteUrlTagTest.php
 */
it('resolves the selected child site handle in context to its absolute URL', function (): void {
    $site = Site::get('health');

    expect($site)->toBeInstanceOf(SiteObject::class);

    $out = (string) Antlers::parse('{{ child_site_url }}', ['child_site' => 'health']);

    /** @var SiteObject $site */
    expect(trim($out))->toBe($site->absoluteUrl());
});

it('returns an empty string when there is no child_site in context', function (): void {
    $out = (string) Antlers::parse('{{ child_site_url }}', ['title' => 'A normal page']);

    expect(trim($out))->toBe('');
});

it('returns an empty string for an unknown site handle', function (): void {
    $out = (string) Antlers::parse('{{ child_site_url }}', ['child_site' => 'definitely_not_a_site_xyz']);

    expect(trim($out))->toBe('');
});

it('resolves an already-augmented Site object in context', function (): void {
    // A `sites` field augments to a Site object, so the nav-item context may
    // hold the Site itself rather than a handle string.
    $site = Site::get('health');
    expect($site)->toBeInstanceOf(SiteObject::class);

    /** @var SiteObject $site */
    $out = (string) Antlers::parse('{{ child_site_url }}', ['child_site' => $site]);

    expect(trim($out))->toBe($site->absoluteUrl());
});

it('resolves the first valid site from an iterable of handles', function (): void {
    $health = Site::get('health');
    expect($health)->toBeInstanceOf(SiteObject::class);

    $out = (string) Antlers::parse('{{ child_site_url }}', ['child_site' => ['health', 'default']]);

    /** @var SiteObject $health */
    expect(trim($out))->toBe($health->absoluteUrl());
});

/**
 * The tag's output for a context and parameters, without Antlers.
 *
 * @param  array<string, mixed>  $context
 * @param  array<string, mixed>  $params
 */
function childSiteUrlFor(array $context, array $params = []): string
{
    $tag = new ChildSiteUrl;
    $tag->setContext($context);
    $tag->setParameters($params);

    return $tag->index();
}

it('prefers the handle parameter over the context', function (): void {
    $health = mustBe(SiteObject::class, Site::get('health'), 'the site');

    expect(childSiteUrlFor(['child_site' => 'default'], ['handle' => 'health']))->toBe($health->absoluteUrl());
});

it('reads a field value wrapping the selected site', function (): void {
    $health = mustBe(SiteObject::class, Site::get('health'), 'the site');

    expect(childSiteUrlFor(['child_site' => new Value('health')]))->toBe($health->absoluteUrl());
});

it('skips the items of a list that name no site', function (): void {
    $health = mustBe(SiteObject::class, Site::get('health'), 'the site');

    expect(childSiteUrlFor(['child_site' => ['definitely_not_a_site_xyz', 7, 'health']]))->toBe($health->absoluteUrl());
});

it('looks up no site for an empty handle', function (): void {
    Site::shouldReceive('get')->never();

    expect(childSiteUrlFor(['child_site' => '']))->toBe('');
});

it('returns an empty string when the site lookup gives something other than a site', function (): void {
    Site::shouldReceive('get')->with('health')->andReturn('health');

    expect(childSiteUrlFor(['child_site' => 'health']))->toBe('');
});

it('returns an empty string when the site has no text URL', function (): void {
    $site = Mockery::mock(SiteObject::class);
    $site->allows('absoluteUrl')->andReturns(null);

    expect(childSiteUrlFor(['child_site' => $site]))->toBe('');
});
