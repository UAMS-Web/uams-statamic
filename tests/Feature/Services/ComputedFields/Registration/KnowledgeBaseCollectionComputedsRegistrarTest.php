<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\KnowledgeBaseCollectionComputedsRegistrar;
use App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar;
use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use App\Services\OrphanedAncestors\OrphanedAncestorResolver;
use App\Services\OrphanedAncestors\OrphanedAncestorResult;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\TestCase;

covers(KnowledgeBaseCollectionComputedsRegistrar::class);

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Mutation: tracked under KnowledgeBaseCollectionComputedsRegistrar (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Services/ComputedFields/Registration/KnowledgeBaseCollectionComputedsRegistrarTest.php
 */
it('registers the six knowledge_bases computeds and the ancestor field map', function (): void {
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class);
    $resolver = Mockery::mock(OrphanedAncestorResolver::class);

    // The ancestor-inherited configuration computeds are registered via the service,
    // not Collection::computed, so assert that delegation happens exactly once.
    $service->shouldReceive('registerKnowledgeBaseComputedFieldsFromAncestor')->once();

    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collection;
        }
    );

    (new KnowledgeBaseCollectionComputedsRegistrar($service, $resolver))->register();

    // UAMS-Web/uams-statamic-archive#1455 retired the bespoke `kb_orphaned_redirect_url` computed; the orphaned
    // signal now comes from the universal OrphanedAncestorResolver, leaving six.
    $expectedHandles = [
        'ancestor_restriction_enabled',
        'has_descendant_articles',
        SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE,
        'has_displayable_subtopics',
        'direct_subtopic_count',
        'direct_article_count',
    ];

    // Exactly one registration per handle (a duplicate would collapse in the keyed map).
    expect($callCount)->toBe(count($expectedHandles))
        ->and(array_keys($captured))->toEqualCanonicalizing($expectedHandles);

    // Every structural computed targets the knowledge_bases collection.
    foreach ($expectedHandles as $handle) {
        expect($captured[$handle])->toBe('knowledge_bases');
    }
});

it('delegates each structural computed to the resolver on the injected service', function (): void {
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class);
    $service->shouldReceive('registerKnowledgeBaseComputedFieldsFromAncestor');
    $resolver = Mockery::mock(OrphanedAncestorResolver::class);

    $entry = Mockery::mock(Entry::class);

    // Each captured callback must forward the entry to its matching resolver.
    $service->shouldReceive('resolveHasDescendantArticles')->once()->with($entry)->andReturn(true);
    $service->shouldReceive('resolveDirectSubtopicCount')->once()->with($entry)->andReturn(4);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    (new KnowledgeBaseCollectionComputedsRegistrar($service, $resolver))->register();

    expect(($captured['has_descendant_articles'])($entry, null))->toBeTrue()
        ->and(($captured['direct_subtopic_count'])($entry, null))->toBe(4);
});

it('gates the sitemap-inclusion computed on the universal orphaned-ancestor redirect and the editor toggle', function (): void {
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class);
    $service->shouldReceive('registerKnowledgeBaseComputedFieldsFromAncestor');
    $resolver = Mockery::mock(OrphanedAncestorResolver::class);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    (new KnowledgeBaseCollectionComputedsRegistrar($service, $resolver))->register();

    $sitemap = $captured[SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE];
    $toggle = SitemapInclusionComputedsRegistrar::EDITOR_TOGGLE_HANDLE;

    // Orphaned (non-null universal redirect result) → excluded, even with the toggle
    // explicitly on. Both closure arguments are evaluated, so `value()` is stubbed too.
    $orphaned = Mockery::mock(Entry::class);
    $orphaned->shouldReceive('value')->with($toggle)->andReturn(true);
    $resolver->shouldReceive('resolve')->with($orphaned)
        ->andReturn(new OrphanedAncestorResult('/kb/parent', 'Hidden Topic'));

    // Fully-published lineage (null result), toggle absent → included.
    $included = Mockery::mock(Entry::class);
    $included->shouldReceive('value')->with($toggle)->andReturn(null);
    $resolver->shouldReceive('resolve')->with($included)->andReturnNull();

    // Non-orphaned but the editor opted out → excluded.
    $optedOut = Mockery::mock(Entry::class);
    $optedOut->shouldReceive('value')->with($toggle)->andReturn(false);
    $resolver->shouldReceive('resolve')->with($optedOut)->andReturnNull();

    expect($sitemap($orphaned, null))->toBeFalse()
        ->and($sitemap($included, null))->toBeTrue()
        ->and($sitemap($optedOut, null))->toBeFalse();
});
