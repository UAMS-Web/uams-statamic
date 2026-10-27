<?php

declare(strict_types=1);

use App\Services\OrphanedAncestors\OrphanedAncestorResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Sites\Site;
use Statamic\Structures\Page;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\OrphanedAncestors\OrphanedAncestorResolver
 *
 * UAMS-Web/uams-statamic-archive#1455 folds `knowledge_bases` onto the universal orphaned-ancestor mechanism,
 * retiring KB's bespoke UAMS-Web/uams-statamic-archive#217 redirect. These regression tests replace the deleted
 * `KnowledgeBaseOrphanedAncestorRedirectTest`, asserting the universal resolver
 * reproduces KB's redirect outcomes for a KB-shaped hierarchy (knowledge_base
 * root → topic → article).
 *
 * The decision (UAMS-Web/uams-statamic-archive#1455) anticipated one behavior delta — an unpublished KB root
 * redirecting to a "KB mount root" instead of the site root. That delta does NOT
 * manifest: `knowledge_bases` carries no collection-level `mount` (it uses
 * per-entry mounting via the `mount_parent` field + `{mount_prefix}` token), so
 * `Collection::mount()` is null, `OrphanedAncestorResolver::mountRootUrl()`
 * returns null, and an unpublished KB root falls through to the site root —
 * byte-for-byte identical to the retired KB policy. These tests pin that.
 *
 * Pure Mockery doubles, so no content/ submodule is required.
 *
 * @command  php artisan test --compact tests/Feature/Services/OrphanedAncestors/KnowledgeBasesUniversalFoldTest.php
 */
beforeEach(fn () => Blink::flush());

/** A `knowledge_bases` collection double: structured, routable, and — crucially — with NO collection-level mount. */
function kbFoldCollection(): Collection
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn('knowledge_bases');
    $collection->shouldReceive('hasStructure')->andReturn(true);
    $collection->shouldReceive('route')->andReturn('{mount_prefix}{parent_uri}/{slug}');
    // No `mount:` key on the KB collection — the fact the "mount root" delta rests on.
    $collection->shouldReceive('mount')->andReturnNull();

    return $collection;
}

/**
 * A published KB descendant (topic/article) sitting under an ancestor chain, for
 * the universal resolver. Ancestors are nearest-parent-first up to (and including)
 * the knowledge_base root.
 *
 * @param  array<int, array{status: string, url?: string|null, title?: string|null}>  $ancestors
 */
function kbFoldDescendant(array $ancestors, string $siteUrl = 'https://site.test'): Entry
{
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('url')->andReturn($siteUrl);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('descendant');
    $entry->shouldReceive('locale')->andReturn('medicine_radiology');
    $entry->shouldReceive('site')->andReturn($site);
    $entry->shouldReceive('collection')->andReturn(kbFoldCollection());

    // Build ancestor pages 1..N; page[N] parents to the (excluded) structure root.
    $ancestorPages = [];
    $count = count($ancestors);

    foreach ($ancestors as $i => $anc) {
        $k = $i + 1;

        $page = Mockery::mock(Page::class);
        $page->shouldReceive('id')->andReturn('anc-'.$i);
        $page->shouldReceive('isRoot')->andReturn(false);
        $ancestorPages[$k] = $page;

        $ancestorEntry = Mockery::mock(Entry::class);
        $ancestorEntry->shouldReceive('status')->andReturn($anc['status']);
        $ancestorEntry->shouldReceive('url')->andReturn($anc['url'] ?? null);
        $ancestorEntry->shouldReceive('get')->with('title')->andReturn($anc['title'] ?? null);
        EntryFacade::shouldReceive('find')->with('anc-'.$i)->andReturn($ancestorEntry);
    }

    // The structural root sits above the topmost ancestor (the KB root) and is excluded.
    $rootPage = Mockery::mock(Page::class);
    $rootPage->shouldReceive('isRoot')->andReturn(true);
    $ancestorPages[$count + 1] = $rootPage;

    $entryPage = Mockery::mock(Page::class);
    $entryPage->shouldReceive('isRoot')->andReturn(false);
    $entryPage->shouldReceive('parent')->andReturn($ancestorPages[1] ?? $rootPage);
    $entry->shouldReceive('page')->andReturn($entryPage);

    foreach ($ancestorPages as $k => $page) {
        if ($k <= $count) {
            $page->shouldReceive('parent')->andReturn($ancestorPages[$k + 1]);
        }
    }

    return $entry;
}

it('redirects a KB descendant under an unpublished knowledge_base root to the site root (no collection mount)', function () {
    // article → published topic → UNPUBLISHED knowledge_base root.
    $entry = kbFoldDescendant([
        ['status' => 'published', 'url' => '/kb/topic'],
        ['status' => 'draft', 'url' => '/kb', 'title' => 'Cardiology'],
    ]);

    $result = (new OrphanedAncestorResolver)->resolve($entry);

    // No live ancestor above the break and no collection mount → site root. This is
    // exactly the retired KB policy's outcome (the anticipated delta does not occur).
    expect($result?->redirectUrl)->toBe('https://site.test')
        ->and($result?->unpublishedAncestorTitle)->toBe('Cardiology');
});

it('redirects a KB descendant under an unpublished topic to the published knowledge_base root url', function () {
    // article → UNPUBLISHED topic → published knowledge_base root.
    $entry = kbFoldDescendant([
        ['status' => 'draft', 'url' => '/kb/topic', 'title' => 'Hidden Topic'],
        ['status' => 'published', 'url' => '/kb'],
    ]);

    $result = (new OrphanedAncestorResolver)->resolve($entry);

    // Nearest live ancestor above the unpublished topic is the published KB root.
    expect($result?->redirectUrl)->toBe('/kb')
        ->and($result?->unpublishedAncestorTitle)->toBe('Hidden Topic');
});

it('redirects an orphaned KB descendant nested under a published subtopic to the nearest live subtopic, not the KB root', function () {
    // A depth-3 hierarchy (nested subtopics are a supported KB shape): article →
    // published topic C → UNPUBLISHED topic B → published topic A → published KB root.
    $entry = kbFoldDescendant([
        ['status' => 'published', 'url' => '/kb/a/b/c'],
        ['status' => 'draft', 'url' => '/kb/a/b', 'title' => 'Hidden Subtopic'],
        ['status' => 'published', 'url' => '/kb/a'],
        ['status' => 'published', 'url' => '/kb'],
    ]);

    $result = (new OrphanedAncestorResolver)->resolve($entry);

    // The nearest live ancestor above the break (topic B) is the intermediate
    // published subtopic A → /kb/a. This is a documented, accepted delta from the
    // retired KB policy, which always sent an orphaned descendant of a published KB
    // root to the KB ROOT url (/kb) regardless of intervening published subtopics.
    // Adopting the universal nearest-live-ancestor rule (the point of UAMS-Web/uams-statamic-archive#1429) yields
    // the more precise target.
    expect($result?->redirectUrl)->toBe('/kb/a')
        ->and($result?->unpublishedAncestorTitle)->toBe('Hidden Subtopic');
});

it('does not redirect a KB descendant when every ancestor is published', function () {
    $entry = kbFoldDescendant([
        ['status' => 'published', 'url' => '/kb/topic'],
        ['status' => 'published', 'url' => '/kb'],
    ]);

    expect((new OrphanedAncestorResolver)->resolve($entry))->toBeNull();
});

it('falls back to the site root when a published knowledge_base root has no url', function () {
    $entry = kbFoldDescendant([
        ['status' => 'draft', 'url' => '/kb/topic', 'title' => 'Hidden Topic'],
        ['status' => 'published', 'url' => null],
    ]);

    // The nearest live ancestor (the KB root) has no url, and there is no mount, so
    // the redirect falls through to the site root — matching the retired KB policy.
    expect((new OrphanedAncestorResolver)->resolve($entry)?->redirectUrl)->toBe('https://site.test');
});

it('handles knowledge_bases by default — it is no longer in the disabled_collections opt-out (#1455)', function () {
    // The shipped config must not opt knowledge_bases out any more.
    expect(config('orphaned_ancestors.disabled_collections'))->not->toContain('knowledge_bases');

    // And the resolver actually handles the KB collection (routable + hierarchical + enabled).
    expect((new OrphanedAncestorResolver)->handles(kbFoldCollection(), 'medicine_radiology'))->toBeTrue();
});
