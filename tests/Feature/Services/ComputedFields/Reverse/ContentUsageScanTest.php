<?php

declare(strict_types=1);

use App\Services\ComputedFields\Reverse\ContentUsageComputedValues;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| ContentUsageComputedValues — real-Stache scan (UAMS-Web/uams-statamic-archive#1375)
|--------------------------------------------------------------------------
|
| Proves the "used by" scan end-to-end against the REAL Stache: saves embedding
| entries whose page-builder field carries a transclusion id nested inside a
| Bard/replicator-shaped value, then asserts the source entry's usage resolves —
| the acceptance criteria: a nested reference is found, an unused entry is empty
| (in_use false), one entry used on multiple pages lists them all, and matches are
| found across the different page-builder field handles.
|
| Serial + self-cleaning: writes prefixed entries into two existing FLAT collections
| (reusable_content, tables) and deletes them by slug prefix. Skipped when the
| content/ submodule (and thus those collections) is absent.
|
| @covers \App\Services\ComputedFields\Reverse\ContentUsageComputedValues
|
| @command php artisan test --compact tests/Feature/Services/ComputedFields/Reverse/ContentUsageScanTest.php
*/

const USAGE_PREFIX = 'zz-usage-';

const USAGE_COLLECTION_A = 'reusable_content';

const USAGE_COLLECTION_B = 'tables';

function usageTearDown(): void
{
    foreach ([USAGE_COLLECTION_A, USAGE_COLLECTION_B] as $handle) {
        if (! Collection::find($handle)) {
            continue;
        }

        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', $handle);
        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();
        $entries->filter(function (Statamic\Entries\Entry $entry): bool {
            /** @var string|null $slug */
            $slug = $entry->slug();

            return str_starts_with((string) $slug, USAGE_PREFIX);
        })->each(fn ($entry) => $entry->delete());
    }
}

/**
 * Save an embedding entry whose given page-builder field carries $builderValue
 * (a nested Bard/replicator-shaped array), published unless told otherwise.
 */
function usageMakeEmbeddingEntry(string $collection, string $suffix, string $builderField, mixed $builderValue, bool $published = true): Statamic\Contracts\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection($collection);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('default');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug(USAGE_PREFIX.$suffix);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published($published);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => 'Usage '.$suffix, $builderField => $builderValue]);
    $entry->save();

    return $entry;
}

/**
 * A Bard-set-shaped page-builder value that transcludes $targetId.
 *
 * @return array<int, array<string, mixed>>
 */
function usageTransclusion(string $targetId): array
{
    return [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Intro copy.']]],
        ['type' => 'set', 'attrs' => ['values' => ['type' => 'reusable_content', 'content' => [$targetId]]]],
    ];
}

/** A source entry stub carrying the given id (the entry whose usage we resolve). */
function usageSource(string $id): Statamic\Entries\Entry
{
    $entry = Mockery::mock(Statamic\Entries\Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

/**
 * The sorted slugs of a resolved usage set.
 *
 * @param  iterable<array-key, mixed>  $set
 * @return array<int, string>
 */
function usageSlugs(iterable $set): array
{
    $slugs = [];

    /** @var Statamic\Entries\Entry $entry */
    foreach ($set as $entry) {
        /** @var string $slug */
        $slug = $entry->slug();
        $slugs[] = $slug;
    }
    sort($slugs);

    return $slugs;
}

beforeEach(function (): void {
    if (! Collection::find(USAGE_COLLECTION_A) || ! Collection::find(USAGE_COLLECTION_B)) {
        ContentSubmodule::reportMissing('reusable_content + tables collections');
    }

    Blink::flush();
    usageTearDown();
});

afterEach(fn () => usageTearDown());

it('finds a page that transcludes the entry nested inside a page-builder set', function (): void {
    $targetId = 'zz-usage-target-found';

    usageMakeEmbeddingEntry(USAGE_COLLECTION_A, 'embedder', 'content', usageTransclusion($targetId));
    // An unrelated page (transcludes something else) must be excluded.
    usageMakeEmbeddingEntry(USAGE_COLLECTION_A, 'unrelated', 'content', usageTransclusion('zz-someone-else'));

    $usage = app(ContentUsageComputedValues::class);
    $source = usageSource($targetId);

    expect(usageSlugs($usage->resolveUsedBy($source)))->toBe([USAGE_PREFIX.'embedder'])
        ->and($usage->isInUse($source))->toBeTrue();
})->group('serial');

it('reports an unused entry as an empty list and in_use false', function (): void {
    usageMakeEmbeddingEntry(USAGE_COLLECTION_A, 'unrelated', 'content', usageTransclusion('zz-other-id'));

    $usage = app(ContentUsageComputedValues::class);
    $source = usageSource('zz-usage-nobody-uses-me');

    expect(usageSlugs($usage->resolveUsedBy($source)))->toBe([])
        ->and($usage->isInUse($source))->toBeFalse();
})->group('serial');

it('lists every page when the entry is used on multiple pages, across collections and builder fields', function (): void {
    $targetId = 'zz-usage-target-multi';

    // Two pages transclude it via the `content` Bard, one via the `page_builder` replicator —
    // spanning both flat collections and two of the three page-builder handles.
    usageMakeEmbeddingEntry(USAGE_COLLECTION_A, 'page-a', 'content', usageTransclusion($targetId));
    usageMakeEmbeddingEntry(USAGE_COLLECTION_B, 'page-b', 'content', usageTransclusion($targetId));
    usageMakeEmbeddingEntry(USAGE_COLLECTION_A, 'page-c', 'page_builder', usageTransclusion($targetId));

    $usage = app(ContentUsageComputedValues::class);

    expect(usageSlugs($usage->resolveUsedBy(usageSource($targetId))))->toBe([
        USAGE_PREFIX.'page-a',
        USAGE_PREFIX.'page-b',
        USAGE_PREFIX.'page-c',
    ]);
})->group('serial');

it('excludes a draft embedding page from the usage list', function (): void {
    $targetId = 'zz-usage-target-draft';

    usageMakeEmbeddingEntry(USAGE_COLLECTION_A, 'published-user', 'content', usageTransclusion($targetId), published: true);
    usageMakeEmbeddingEntry(USAGE_COLLECTION_A, 'draft-user', 'content', usageTransclusion($targetId), published: false);

    $usage = app(ContentUsageComputedValues::class);

    expect(usageSlugs($usage->resolveUsedBy(usageSource($targetId))))->toBe([USAGE_PREFIX.'published-user']);
})->group('serial');
