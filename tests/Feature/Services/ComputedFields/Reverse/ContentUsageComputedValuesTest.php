<?php

declare(strict_types=1);

use App\Services\ComputedFields\Reverse\ContentUsageComputedValues;
use App\Services\ComputedFields\Reverse\ReverseEntryReferenceResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ContentUsageComputedValues::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(12_000_000);
        $padded = true;
    }
});

/*
|--------------------------------------------------------------------------
| ContentUsageComputedValues (UAMS-Web/uams-statamic-archive#1375)
|--------------------------------------------------------------------------
|
| The content-usage computeds are thin wiring over the shared resolver's nested
| scan (whose real-Stache behavior is proved end-to-end in ContentUsageScanTest).
| These mock the resolver and the Collection facade to pin the exact scan the
| service issues: every collection handle, restricted to the three page-builder
| field handles (content / body / page_builder), for this entry's id — and that
| `in_use` is exactly "the used-by scan is non-empty".
|
| @covers \App\Services\ComputedFields\Reverse\ContentUsageComputedValues
|
| @command php artisan test --compact tests/Feature/Services/ComputedFields/Reverse/ContentUsageComputedValuesTest.php
*/

function usageEntry(?string $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

it('scans every collection restricted to the three page-builder fields for this entry id', function (): void {
    // The service asks the Collection facade for the full handle list.
    Collection::shouldReceive('handles')->andReturn(collect(['pages', 'posts', 'reusable_content']));

    $expected = EntryCollection::make();
    $entry = usageEntry('rc-1');

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolveNested')
        ->once()
        ->with(['pages', 'posts', 'reusable_content'], ['content', 'body', 'page_builder'], 'rc-1')
        ->andReturn($expected);

    expect((new ContentUsageComputedValues($resolver))->resolveUsedBy($entry))
        ->toBe($expected);
});

it('reports in_use true when the used-by scan finds a transclusion', function (): void {
    Collection::shouldReceive('handles')->andReturn(collect(['pages']));

    $entry = usageEntry('rc-2');
    $nonEmpty = EntryCollection::make([Mockery::mock(Entry::class)]);

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolveNested')->once()->andReturn($nonEmpty);

    expect((new ContentUsageComputedValues($resolver))->isInUse($entry))->toBeTrue();
});

it('reports in_use false when nothing transcludes the entry', function (): void {
    Collection::shouldReceive('handles')->andReturn(collect(['pages']));

    $entry = usageEntry('rc-3');
    $empty = EntryCollection::make();

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolveNested')->once()->andReturn($empty);

    expect((new ContentUsageComputedValues($resolver))->isInUse($entry))->toBeFalse();
});

it('filters non-string handles and reindexes before scanning', function (): void {
    // Associative keys + a non-string value: ``array_filter`` + ``array_values``
    // must both run or the resolver receives the wrong list.
    Collection::shouldReceive('handles')->andReturn(collect([
        'a' => 'pages',
        'b' => 42,
        'c' => 'posts',
    ]));

    $expected = EntryCollection::make();
    $entry = usageEntry('rc-4');

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolveNested')
        ->once()
        ->with(
            ['pages', 'posts'],
            ['content', 'body', 'page_builder'],
            'rc-4',
        )
        ->andReturn($expected);

    expect((new ContentUsageComputedValues($resolver))->resolveUsedBy($entry))
        ->toBe($expected);
});
