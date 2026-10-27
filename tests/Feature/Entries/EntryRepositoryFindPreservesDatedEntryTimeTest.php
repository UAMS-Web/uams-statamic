<?php

declare(strict_types=1);

use App\Entries\EntryRepository;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection as IlluminateCollection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Fields\Blueprint;
use Statamic\Stache\Stache;
use Tests\Support\Doubles\EntryRepoPreserveDatedTimeHarness;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(EntryRepository::class);

/**
 * Ensures the custom entry repository copies date when re-instantiating entries
 * so dated filenames and Entry::date() stay aligned after container binding swaps
 * the class (Statamic 6.x; upstream dated-path fixes in statamic/cms #14322 do
 * not replace this copy step).
 *
 * Mutation: 100% on App\Entries\EntryRepository (24 mutants, --covered-only).
 *
 * @see EntryRepository
 *
 * @command  php artisan test --compact tests/Feature/Entries/EntryRepositoryFindPreservesDatedEntryTimeTest.php
 */

/**
 * Build the substitution-path harness `find()` uses after `parent::find()`,
 * exposing the protected re-wrap without loading a real stache id.
 */
function entryRepoPreserveDatedTimeRepository(): EntryRepoPreserveDatedTimeHarness
{
    return new class(app(Stache::class)) extends EntryRepository implements EntryRepoPreserveDatedTimeHarness
    {
        /**
         * Expose parent-row re-wrap for tests.
         *
         * @param  ?StatamicEntry  $entry  Synthetic row from `Entry::make()`
         * @return ?StatamicEntry Re-bound entry instance
         */
        public function reinstantiateForTest(?StatamicEntry $entry): ?StatamicEntry
        {
            return $this->reinstantiateEntryFromParentRow($entry);
        }
    };
}

it('preserves the publish datetime from the entry file path', function (): void {
    // Resolve the dated `posts` collection from the Stache
    /** @var ?StatamicCollection $collection */
    $collection = Collection::find('posts');

    expect($collection)->not->toBeNull();

    // Narrow away null so analyzers know `dated()` exists on the instance
    Assert::assertInstanceOf(StatamicCollection::class, $collection);

    // Assert the collection is dated so filenames carry publish time
    expect($collection->dated())->toBeTrue();

    // Build a synthetic stache-style row (no committed markdown fixture)
    $expectedPrefix = '2022-05-05-1200';

    $row = Entry::make();

    expect($row)->toBeInstanceOf(StatamicEntry::class);

    // Narrow the contract to the concrete entry whose fluent setters exist
    Assert::assertInstanceOf(StatamicEntry::class, $row);

    // Resolve the timezone once so it is a concrete string for `Carbon::parse()`
    $timezone = config('app.timezone');
    Assert::assertIsString($timezone);

    $row->collection($collection);
    $row->id('77777777-7777-4777-8777-777777777777');
    $row->slug('entry-repo-preserve-test');
    $row->blueprint('post');
    $row->published(true);
    $row->locale('default');
    $row->data(
        [
            'title' => 'Synthetic dated row',
        ]
    );
    $row->date(
        Carbon::parse(
            '2022-05-05 12:00',
            $timezone
        )
    );
    $row->initialPath(
        "/fake/content/collections/posts/default/{$expectedPrefix}.entry-repo-preserve-test.md"
    );

    // Read the dated markdown path as a string for prefix extraction
    $initialPath = $row->initialPath();
    $initialPath = is_string($initialPath) ? $initialPath : '';

    // Extract the `Y-m-d-Hi` prefix from the markdown filename
    expect($initialPath)->toMatch(
        '/\/(\d{4}-\d{2}-\d{2}-\d{4})\.[^\/.]+\.md$/'
    );

    // Capture the same prefix into matches for the expected datetime string
    $matched = preg_match(
        '/\/(\d{4}-\d{2}-\d{2}-\d{4})\.[^\/.]+\.md$/',
        $initialPath,
        $matches
    );

    // Read the first capture group as the expected Y-m-d-Hi prefix
    Assert::assertSame(1, $matched);
    $expectedPrefixFromPath = $matches[1];

    // Published instant must match the filesystem prefix, not `updated_at` fallback
    $rowDate = $row->date();
    Assert::assertInstanceOf(CarbonInterface::class, $rowDate);
    expect($rowDate->format('Y-m-d-Hi'))->toBe($expectedPrefixFromPath);

    // Expose reinstantiation without loading a real stache id
    $repository = entryRepoPreserveDatedTimeRepository();

    // Run the same substitution path `find()` uses after `parent::find()`
    $found = $repository->reinstantiateForTest($row);

    // Assert the repository returned a row after re-instantiation
    expect($found)->not->toBeNull();

    // Narrow the type so analyzers know `date()` exists on the instance
    expect($found)->toBeInstanceOf(StatamicEntry::class);
    Assert::assertInstanceOf(StatamicEntry::class, $found);

    // Assert re-wrap did not drop the publish instant from the filename prefix
    $foundDate = $found->date();
    Assert::assertInstanceOf(CarbonInterface::class, $foundDate);
    expect($foundDate->format('Y-m-d-Hi'))->toBe($expectedPrefixFromPath);

    // Same behavior on a second pass (mirrors idempotent reload semantics)
    $reloaded = $repository->reinstantiateForTest($row);

    // Assert the repository returned a row after reloading
    expect($reloaded)->not->toBeNull();

    // Narrow the type so analyzers know `date()` exists on the instance
    expect($reloaded)->toBeInstanceOf(StatamicEntry::class);
    Assert::assertInstanceOf(StatamicEntry::class, $reloaded);

    // Assert second re-wrap did not drop the publish instant from the prefix
    $reloadedDate = $reloaded->date();
    Assert::assertInstanceOf(CarbonInterface::class, $reloadedDate);
    expect($reloadedDate->format('Y-m-d-Hi'))->toBe($expectedPrefixFromPath);
});

it('make resolves the Entry contract with the collection handle', function (): void {
    // Stub the Entry contract binding return value
    $entry = Mockery::mock(StatamicEntry::class);

    // Bind Entry so make() receives the collection handle parameter
    app()->bind(
        EntryContract::class,
        function ($app, array $parameters) use ($entry) {
            expect($parameters)->toHaveKey('collectionHandle')
                ->and($parameters['collectionHandle'])->toBe('knowledge_bases');

            return $entry;
        }
    );

    // Call make() with a collection handle
    $made = (new EntryRepository(app(Stache::class)))->make('knowledge_bases');

    // Assert make() returned the bound entry
    expect($made)->toBe($entry);
});

it('reinstantiate returns null when the parent row is null', function (): void {
    expect(entryRepoPreserveDatedTimeRepository()->reinstantiateForTest(null))->toBeNull();
});

it('copies identity, locale, data, slug, published, path, origin, and blueprint', function (): void {
    // Stub origin and blueprint collaborators for the source row
    $origin = Mockery::mock(StatamicEntry::class);
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('post');

    // Stub the source parent row returned by parent::find()
    $row = Mockery::mock(StatamicEntry::class);
    $row->shouldReceive('collectionHandle')->andReturn('posts');
    $row->shouldReceive('id')->andReturn('entry-id-1');
    $row->shouldReceive('locale')->andReturn('default');
    $row->shouldReceive('data')->andReturn(collect(['title' => 'Copied']));
    $row->shouldReceive('published')->andReturn(true);
    $row->shouldReceive('slug')->andReturn('copied-slug');
    // Null collection: `?->dated()` must short-circuit (kills RemoveNullSafeOperator).
    $row->shouldReceive('collection')->andReturn(null);
    $row->shouldReceive('initialPath')->andReturn('/fake/posts/copied-slug.md');
    $row->shouldReceive('hasOrigin')->andReturn(true);
    $row->shouldReceive('origin')->andReturn($origin);
    $row->shouldReceive('blueprint')->andReturn($blueprint);

    // Stub the replacement entry that receives copied identity fields
    $newEntry = Mockery::mock(StatamicEntry::class);
    $newEntry->shouldReceive('id')->once()->with('entry-id-1')->andReturnSelf();
    $newEntry->shouldReceive('collection')->once()->with('posts')->andReturnSelf();
    $newEntry->shouldReceive('locale')->once()->with('default')->andReturnSelf();
    $newEntry->shouldReceive('data')->once()->with(Mockery::type(IlluminateCollection::class))->andReturnSelf();
    $newEntry->shouldReceive('published')->once()->with(true)->andReturnSelf();
    $newEntry->shouldReceive('slug')->once()->with('copied-slug')->andReturnSelf();
    $newEntry->shouldReceive('initialPath')->once()->with('/fake/posts/copied-slug.md')->andReturnSelf();
    $newEntry->shouldReceive('origin')->once()->with($origin)->andReturnSelf();
    $newEntry->shouldReceive('blueprint')->once()->with('post')->andReturnSelf();

    // Bind Entry so re-wrap resolves the replacement instance
    app()->bind(
        EntryContract::class,
        function ($app, array $parameters) use ($newEntry) {
            expect($parameters['collectionHandle'])->toBe('posts');

            return $newEntry;
        }
    );

    // Run the substitution-path re-wrap against the stubbed parent row
    $result = entryRepoPreserveDatedTimeRepository()->reinstantiateForTest($row);

    // Assert re-wrap returned the replacement entry after copying fields
    expect($result)->toBe($newEntry);
});

it('applySubstitutions filters null items before delegating to parent', function (): void {
    // Stub a kept entry that survives the null filter
    $kept = Mockery::mock(StatamicEntry::class);
    $kept->shouldReceive('id')->andReturn('kept-id');

    // Run applySubstitutions with a null hole in the collection
    $real = new EntryRepository(app(Stache::class));
    $result = $real->applySubstitutions(collect([null, mustBe(StatamicEntry::class, $kept, 'the kept entry')]));

    // Assert nulls were dropped and the kept entry remains
    expect($result->all())->not->toContain(null)
        ->and($result->values()->all())->toContain($kept);
});

it('bindings returns the parent binding map', function (): void {
    // Resolve the repository binding map
    $bindings = EntryRepository::bindings();

    // Assert the parent map is a non-empty array
    expect($bindings)->not->toBe([]);
});
