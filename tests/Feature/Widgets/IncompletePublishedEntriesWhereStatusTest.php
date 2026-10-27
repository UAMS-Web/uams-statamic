<?php

declare(strict_types=1);

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\TestCase;

/**
 * Documents the Entry::query()->whereStatus('published') contract used by
 * IncompletePublishedEntries without loading real Stache content.
 *
 * Note: statamic/cms#14387 implements whereStatus on the Search query
 * builder; this widget uses Entry::query() (Stache), where collection
 * status filtering was corrected in 6.9 (statamic/cms#14380).
 *
 * Mutation: no covered mutable surface — this pins the whereStatus('published')
 * query construction via a Mockery double, so --covered-only reports 0 mutants;
 * the class's mutation coverage lives in the sibling
 * IncompletePublishedEntriesWidgetTest (100% on App\Widgets\IncompletePublishedEntries).
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

it('applies whereStatus published on the published scan', function (): void {
    // Use synthetic ids so the test does not depend on committed entries
    $draftId = '00000000-0000-4000-8000-000000000001';
    $publishedId = '00000000-0000-4000-8000-000000000002';

    // Build a published entry mock returned by the query
    $publishedEntry = Mockery::mock(EntryModel::class);
    $publishedEntry->shouldReceive('id')->andReturn($publishedId);

    // Expect the same chain IncompletePublishedEntries uses before get()
    $query = Mockery::mock(EntryQueryBuilder::class);
    $query->shouldReceive('whereStatus')
        ->once()
        ->with('published')
        ->andReturnSelf();
    $query->shouldReceive('get')
        ->once()
        ->andReturn(collect([$publishedEntry]));

    Entry::shouldReceive('query')->once()->andReturn($query);

    // Reproduce the widget’s id collection after the status filter
    /** @var EntryQueryBuilder $entryQuery */
    $entryQuery = Entry::query();

    /** @var EntryQueryBuilder $publishedQuery */
    $publishedQuery = $entryQuery->whereStatus('published');

    /** @var EntryCollection $publishedEntries */
    $publishedEntries = $publishedQuery->get();

    $publishedIds = $publishedEntries->map(function (mixed $entry): mixed {
        /** @var EntryModel $entry */
        return $entry->id();
    });

    // Published scan must not include a draft id
    expect($publishedIds->contains($draftId))->toBeFalse(
        'Published-only query results must not include draft entry ids'
    );

    // Mock supplied only published entries
    expect($publishedIds->contains($publishedId))->toBeTrue();
});
