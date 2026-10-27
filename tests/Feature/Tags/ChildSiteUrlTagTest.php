<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the `{{ child_site_url }}` tag (UAMS-Web/uams-statamic-archive#219): in the primary-nav loop it
 * resolves a `child_site_link` item's selected child site (the `child_site`
 * field in context) to its absolute URL, and returns an empty string for a
 * normal page so the nav falls back to the page's own URL.
 *
 * @covers \App\Tags\ChildSiteUrl
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
