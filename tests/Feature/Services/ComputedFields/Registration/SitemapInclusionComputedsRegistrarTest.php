<?php

declare(strict_types=1);

use App\Entries\PersonEntry;
use App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Feature/Services/ComputedFields/Registration/SitemapInclusionComputedsRegistrarTest.php
 */
it('registers the sitemap-inclusion computed on every status-gated collection, people, and the route-gated post collections', function (): void {
    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle) use (&$captured): void {
            // The handle is identical across collections, so key by collection to
            // prove every intended collection is wired (and none is dropped).
            $captured[$collection] = $handle;
        }
    );

    app(SitemapInclusionComputedsRegistrar::class)->register();

    expect($captured)->toEqual([
        'pages' => SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE,
        'posts' => SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE,
        'clinical_expertise' => SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE,
        'people' => SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE,
        // Gated on the indexed URI (UAMS-Web/uams-statamic-archive#2542); the gating itself is asserted by
        // tests/Feature/Seo/SitemapStructureOrphansTest.php.
        'post_categories' => SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE,
        'post_tags' => SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE,
    ]);
});

it('reads the people routability toggle with get(), not value(), so a localization inheriting an origin opt-out stays in the sitemap', function (): void {
    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$collection] = $callback;
        }
    );

    app(SitemapInclusionComputedsRegistrar::class)->register();

    // A secondary-site localization of a person whose origin set profile_page_query
    // = false. The field is localizable:false, so the localization has no OWN copy:
    // get() returns null (routable — PersonEntry::route() also reads get() and serves
    // this profile a URL), while value() would inherit the origin's false. Because
    // route() resolves a URL for it, the profile belongs in that site's sitemap, so
    // the gate must INCLUDE it. Stubbing get() and value() to diverge is exactly the
    // production state a get()->value() regression would mishandle.
    $localization = Mockery::mock(Entry::class);
    $localization->shouldReceive('value')->with('status_and_visibility')->andReturn('published');
    $localization->shouldReceive('value')->with('password_protected')->andReturn(false);
    $localization->shouldReceive('value')->with(SitemapInclusionComputedsRegistrar::EDITOR_TOGGLE_HANDLE)->andReturn(null);
    $localization->shouldReceive('get')->with(PersonEntry::PROFILE_PAGE_FIELD)->andReturnNull();
    // Present but must NOT be consulted for routability — a get()->value() swap would
    // read this false and wrongly drop the reachable localized profile.
    $localization->shouldReceive('value')->with(PersonEntry::PROFILE_PAGE_FIELD)->andReturn(false);

    expect(($captured['people'])($localization, null))->toBeTrue();
});

it('excludes a person whose own profile_page_query is false via the people closure', function (): void {
    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$collection] = $callback;
        }
    );

    app(SitemapInclusionComputedsRegistrar::class)->register();

    // The origin (or any entry with its OWN profile_page_query = false): get() reads
    // the own false, PersonEntry::route() returns null, so the profile is non-routable
    // and must be excluded even though it is published.
    $origin = Mockery::mock(Entry::class);
    $origin->shouldReceive('value')->with('status_and_visibility')->andReturn('published');
    $origin->shouldReceive('value')->with('password_protected')->andReturn(false);
    $origin->shouldReceive('value')->with(SitemapInclusionComputedsRegistrar::EDITOR_TOGGLE_HANDLE)->andReturn(null);
    $origin->shouldReceive('get')->with(PersonEntry::PROFILE_PAGE_FIELD)->andReturn(false);

    expect(($captured['people'])($origin, null))->toBeFalse();
});
