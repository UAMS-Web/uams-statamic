<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Statamic\Facades\Site;
use Statamic\Sites\Sites;
use Tests\Support\CachedSites;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Proves the per-process sites-hydration cache (UAMS-Web/uams-statamic-archive#1582) is correct and safe: the
 * reused collection must resolve identically to a fresh hydration, and each app
 * must get a fresh `Sites` instance (so per-request mutable state never leaks)
 * over one shared, read-only collection. The once-per-process *count* invariant
 * is guarded separately by TestBootstrapCostTest.
 */
it('binds the caching CachedSites for every test app', function (): void {
    /** @var Application $app */
    $app = $this->app;

    expect($app->make(Sites::class))->toBeInstanceOf(CachedSites::class);
});

it('serves a cached sites collection identical to a fresh hydration', function (): void {
    // The collection reused across test apps...
    /** @var Collection<string, Statamic\Sites\Site> $cached */
    $cached = Site::all();

    // ...compared against a real, independent hydration of resources/sites.yaml
    // (`new Sites` always parses + Antlers-resolves every site).
    /** @var Collection<string, Statamic\Sites\Site> $fresh */
    $fresh = (new Sites)->all();

    expect($cached->count())->toBe($fresh->count())
        ->and($cached->count())->toBeGreaterThan(1);

    foreach ($fresh as $handle => $freshSite) {
        /** @var Statamic\Sites\Site $cachedSite */
        $cachedSite = $cached->get($handle);

        expect($cachedSite)->not->toBeNull("site [{$handle}] missing from the cached collection");
        expect($cachedSite->url())->toBe($freshSite->url());
        expect($cachedSite->absoluteUrl())->toBe($freshSite->absoluteUrl());
        expect($cachedSite->name())->toBe($freshSite->name());
        expect($cachedSite->locale())->toBe($freshSite->locale());
    }
});

it('keeps the Site facade mockable — CachedSites must not be final', function (): void {
    // Regression guard (UAMS-Web/uams-statamic-archive#1582): CachedSites is bound as the Sites singleton, so
    // it is the Site facade root. `Site::shouldReceive()` mocks get_class(root)
    // = CachedSites; Mockery cannot subclass a `final` class, so a `final`
    // CachedSites throws here and breaks every test that mocks the Site facade.
    Site::shouldReceive('default')->once()->andReturnNull();

    expect(Site::default())->toBeNull();
});

it('reuses one hydrated collection but hands each app a fresh Sites instance', function (): void {
    /** @var Application $app */
    $app = $this->app;
    $sitesA = $app->make(Sites::class);

    // A fresh per-test app, exactly as the next test method would boot.
    $probe = new (get_class($this))('sites_reuse_probe');
    /** @var Application $appB */
    $appB = $probe->createApplication();
    $sitesB = $appB->make(Sites::class);

    // Distinct instances → the mutable per-request state a `Sites` carries
    // ($current / $currentUrlCallback) starts fresh and cannot bleed between
    // tests...
    expect($sitesB)->not->toBe($sitesA);

    // ...while the expensive hydrated collection is the very same object, reused.
    expect($sitesB->all())->toBe($sitesA->all());
});
