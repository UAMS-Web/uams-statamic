<?php

declare(strict_types=1);

use App\Providers\SearchServiceProvider;
use App\Search\AreaOfExpertiseSectionProvider;
use App\Search\AreaOfExpertiseSuggestionPresenter;
use App\Search\Loupe\SiteFilteredLoupeIndex;
use App\Search\PrivateEntryVisibility;
use App\Search\SecurityHeadersResponseAllowance;
use App\Search\SubjectSiteTree;
use App\Support\Multisite\SiteTreeCache;
use Daun\StatamicLoupe\Loupe\Index as LoupeIndex;
use Illuminate\Contracts\Foundation\Application;
use Statamic\Search\Searchables\Providers;
use Tests\TestCase;
use UAMSWeb\Aether\Search\Contracts\EntryVisibility;
use UAMSWeb\Aether\Search\Contracts\ResponseAllowance;
use UAMSWeb\Aether\Search\Contracts\SiteTree;
use UAMSWeb\Aether\Search\Contracts\SuggestionPresenter;

uses(TestCase::class);

covers(SearchServiceProvider::class);

/**
 * Feature tests for `SearchServiceProvider`: the five container bindings that hand the
 * `aether-search` contracts and the `loupe` index their application implementations, the
 * request-scoped multisite tree (#480), and the searchable provider it registers with Statamic.
 *
 * The bindings are pinned twice. Against a mocked application, `register()` must bind exactly
 * these five pairs and scope the tree; against the booted application, the four contracts must
 * resolve to the application classes rather than the `bindIf` defaults `aether-search` falls
 * back to.
 *
 * Mutation: 100% on App\Providers\SearchServiceProvider (7 mutants, 7 killed; macOS, #480).
 *
 * @command  php artisan test --compact tests/Feature/Providers/SearchServiceProviderTest.php
 */
it('binds the four aether-search contracts and the loupe index to the application classes, and scopes the tree', function (): void {
    $app = Mockery::mock(Application::class);
    $app->expects('bind')->with(EntryVisibility::class, PrivateEntryVisibility::class);
    $app->expects('bind')->with(SuggestionPresenter::class, AreaOfExpertiseSuggestionPresenter::class);
    $app->expects('bind')->with(ResponseAllowance::class, SecurityHeadersResponseAllowance::class);
    $app->expects('bind')->with(SiteTree::class, SubjectSiteTree::class);
    $app->expects('bind')->with(LoupeIndex::class, SiteFilteredLoupeIndex::class);
    $app->expects('scoped')->with(SiteTreeCache::class);

    (new SearchServiceProvider($app))->register();
});

it('resolves each aether-search contract to the application class, not the package default', function (): void {
    expect(app(EntryVisibility::class)::class)->toBe(PrivateEntryVisibility::class)
        ->and(app(SuggestionPresenter::class)::class)->toBe(AreaOfExpertiseSuggestionPresenter::class)
        ->and(app(ResponseAllowance::class)::class)->toBe(SecurityHeadersResponseAllowance::class)
        ->and(app(SiteTree::class)::class)->toBe(SubjectSiteTree::class);
});

it('registers the Area of Expertise section searchable provider with Statamic', function (): void {
    expect(app(Providers::class)->providers()->get(AreaOfExpertiseSectionProvider::handle()))
        ->toBeInstanceOf(AreaOfExpertiseSectionProvider::class);
});
