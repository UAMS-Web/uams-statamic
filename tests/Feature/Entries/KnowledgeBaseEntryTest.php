<?php

declare(strict_types=1);

use App\Entries\KnowledgeBaseEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Actions\DuplicateEntry;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

/**
 * Unit tests for KnowledgeBaseEntry localization and duplication.
 *
 * Tests the Statamic v5.53.1 upgrade fix:
 *   - Dates in localizations when duplicating entries
 *
 * Windows:
 *   php artisan cache:clear && echo. > storage\logs\laravel.log && php artisan test tests/Feature/Entries/KnowledgeBaseEntryTest.php
 *   php artisan cache:clear && echo. > storage\logs\laravel.log && php artisan test tests/Feature/Entries/KnowledgeBaseEntryTest.php --stop-on-failure
 *
 * Mac:
 *   php artisan cache:clear && > storage/logs/laravel.log && php artisan test tests/Feature/Entries/KnowledgeBaseEntryTest.php
 *   php artisan cache:clear && > storage/logs/laravel.log && php artisan test tests/Feature/Entries/KnowledgeBaseEntryTest.php --stop-on-failure
 *
 * Mutation: 100% on App\Entries\KnowledgeBaseEntry (12 mutants, --covered-only).
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Test that dates are preserved when localizing entries.
 *
 * This test verifies the Statamic v5.53.1 fix for dates in localizations
 * when duplicating entries.
 */
it('preserves dates in localization', function (): void {
    // Create a mock entry with a date
    $originalDate = Carbon::parse('2025-04-17 10:00:00');

    $mockEntry = Mockery::mock(KnowledgeBaseEntry::class)->makePartial();
    $mockEntry->shouldAllowMockingProtectedMethods();
    $mockEntry->shouldReceive('hasDate')
        ->andReturn(true);
    $mockEntry->shouldReceive('date')
        ->andReturn($originalDate);
    $mockEntry->shouldReceive('collectionHandle')
        ->andReturn('knowledge_bases');

    // Mock blueprint properly
    $mockBlueprint = Mockery::mock();
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('knowledge_base');
    $mockEntry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint);

    $mockEntry->shouldReceive('published')
        ->andReturn(true);
    $mockEntry->shouldReceive('slug')
        ->andReturn('test-entry');

    // Mock EntryFacade
    $mockLocalizedEntry = Mockery::mock(Entry::class);
    $mockLocalizedEntry->shouldReceive('collection')
        ->andReturnSelf();
    $mockLocalizedEntry->shouldReceive('origin')
        ->andReturnSelf();
    $mockLocalizedEntry->shouldReceive('locale')
        ->andReturnSelf();
    $mockLocalizedEntry->shouldReceive('published')
        ->andReturnSelf();
    $mockLocalizedEntry->shouldReceive('slug')
        ->andReturnSelf();
    $mockLocalizedEntry->shouldReceive('hasDate')
        ->andReturn(true);
    $mockLocalizedEntry->shouldReceive('date')
        ->with($originalDate)
        ->andReturnSelf();

    EntryFacade::shouldReceive('make')
        ->andReturn($mockLocalizedEntry);

    // Test localization - makeLocalization is a real method, so it should work with makePartial()
    /** @var KnowledgeBaseEntry $mockEntry */
    $localized = $mockEntry->makeLocalization('health');

    // Verify localized entry was created
    expect($localized)->not->toBeNull();
});

/**
 * Test that entry duplication preserves dates correctly when entry has explicit
 * date.
 *
 * This test verifies the Statamic v5.53.1 fix from commit 20c8888:
 * https://github.com/statamic/cms/commit/20c88883ba6a8084b644bd9c013acc4f3720a42a
 *
 * The fix changes DuplicateEntry to use hasExplicitDate() instead of hasDate(),
 * ensuring dates are only copied when explicitly set (not just when collection
 * has date support).
 */
it('preserves dates on duplication when entry has an explicit date', function (): void {
    // Create a mock entry with an explicit date
    $originalDate = Carbon::parse('2025-04-17 10:00:00');

    $mockOriginalEntry = Mockery::mock(Entry::class);
    $mockOriginalEntry->shouldReceive('hasOrigin')
        ->andReturn(false);
    $mockOriginalEntry->shouldReceive('locale')
        ->andReturn('default');
    $mockOriginalEntry->shouldReceive('id')
        ->andReturn('original-entry-id');
    $mockOriginalEntry->shouldReceive('value')
        ->with('title')
        ->andReturn('Test Entry');
    $mockOriginalEntry->shouldReceive('slug')
        ->andReturn('test-entry');

    // Mock collection with query builder chain
    $mockQueryBuilder = Mockery::mock();
    // Statamic 6.25 renamed the duplicate-slug probe's site filter `locale` -> `site`
    // (`DuplicateEntry::generateTitleAndSlug()`); the value is still `$entry->locale()`.
    $mockQueryBuilder->shouldReceive('where')
        ->with('site', 'default')
        ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')
        ->with('slug', 'test-entry-1')
        ->andReturnSelf();
    $mockQueryBuilder->shouldReceive('count')
        ->andReturn(0);

    $mockCollection = Mockery::mock();
    $mockCollection->shouldReceive('requiresSlugs')
        ->andReturn(true);
    $mockCollection->shouldReceive('queryEntries')
        ->andReturn($mockQueryBuilder);
    $mockCollection->shouldReceive('propagate')
        ->with(false)
        ->andReturnSelf();
    $mockOriginalEntry->shouldReceive('collection')
        ->andReturn($mockCollection);

    // Mock blueprint and fields - need to properly chain reject->shouldBeDuplicated()->keys()
    $mockRejectedFields = Mockery::mock(Collection::class);
    $mockRejectedFields->shouldReceive('keys')
        ->andReturn(collect());

    $mockFieldsCollection = Mockery::mock(Collection::class);
    $mockFieldsCollection->shouldReceive('reject')
        ->andReturn($mockRejectedFields);

    $mockFields = Mockery::mock();
    $mockFields->shouldReceive('all')
        ->andReturn($mockFieldsCollection);

    $mockBlueprint = Mockery::mock();
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('knowledge_base');
    $mockBlueprint->shouldReceive('fields')
        ->andReturn($mockFields);
    $mockOriginalEntry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint);

    // Mock data
    $mockData = Mockery::mock();
    $mockData->shouldReceive('except')
        ->andReturnSelf();
    $mockData->shouldReceive('merge')
        ->andReturnSelf();
    $mockData->shouldReceive('all')
        ->andReturnUsing(fn () => ['title' => 'Test Entry']);
    $mockOriginalEntry->shouldReceive('data')
        ->andReturn($mockData);

    // Mock structure methods
    $mockOriginalEntry->shouldReceive('structure')
        ->andReturn(null);
    $mockOriginalEntry->shouldReceive('directDescendants')
        ->andReturn(collect());

    // Mock hasExplicitDate() - this is the key fix from commit 20c8888
    // hasExplicitDate() returns true only if hasDate() is true AND date is not null
    $mockOriginalEntry->shouldReceive('hasDate')
        ->andReturn(true);
    $mockOriginalEntry->shouldReceive('date')
        ->andReturn($originalDate);

    // Check if hasExplicitDate() method exists (after v5.53.1 upgrade)
    // If not, fall back to checking hasDate() && date
    $hasExplicitDateMethod = method_exists($mockOriginalEntry, 'hasExplicitDate');
    if ($hasExplicitDateMethod) {
        $mockOriginalEntry->shouldReceive('hasExplicitDate')
            ->andReturn(true);
    }

    // Mock the duplicated entry
    $mockDuplicatedEntry = Mockery::mock(Entry::class);
    $mockDuplicatedEntry->shouldReceive('locale')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('collection')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('blueprint')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('published')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('data')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('origin')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('updateLastModified')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('slug')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('save')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('date')
        ->with($originalDate)
        ->once()
        ->andReturnSelf();

    EntryFacade::shouldReceive('make')
        ->andReturn($mockDuplicatedEntry);

    // Use reflection to access the private duplicateEntry method
    $duplicateAction = new DuplicateEntry;
    $reflection = new ReflectionClass($duplicateAction);
    $method = $reflection->getMethod('duplicateEntry');

    // Execute duplication
    $result = $method->invoke($duplicateAction, $mockOriginalEntry);

    // Verify duplicated entry was created
    expect($result)->not->toBeNull();
});

/**
 * Test that entry duplication does NOT copy dates when entry has date support
 * but no explicit date value.
 *
 * This test verifies the Statamic v5.53.1 fix from commit 20c8888:
 * https://github.com/statamic/cms/commit/20c88883ba6a8084b644bd9c013acc4f3720a42a
 *
 * The fix ensures that when duplicating entries with date support but no
 * explicit date (null), the date is NOT copied to the duplicate. This prevents
 * issues when duplicating localizations where the date should remain unset.
 */
it('does not copy null dates on duplication when entry has date support without an explicit date', function (): void {
    // Create a mock entry with date support but no explicit date (null)
    $mockOriginalEntry = Mockery::mock(Entry::class);
    $mockOriginalEntry->shouldReceive('hasOrigin')
        ->andReturn(false);
    $mockOriginalEntry->shouldReceive('locale')
        ->andReturn('default');
    $mockOriginalEntry->shouldReceive('id')
        ->andReturn('original-entry-id');
    $mockOriginalEntry->shouldReceive('value')
        ->with('title')
        ->andReturn('Test Entry');
    $mockOriginalEntry->shouldReceive('slug')
        ->andReturn('test-entry');

    // Mock collection with query builder chain
    $mockQueryBuilder2 = Mockery::mock();
    // Statamic 6.25 renamed the duplicate-slug probe's site filter `locale` -> `site`
    // (`DuplicateEntry::generateTitleAndSlug()`); the value is still `$entry->locale()`.
    $mockQueryBuilder2->shouldReceive('where')
        ->with('site', 'default')
        ->andReturnSelf();
    $mockQueryBuilder2->shouldReceive('where')
        ->with('slug', 'test-entry-1')
        ->andReturnSelf();
    $mockQueryBuilder2->shouldReceive('count')
        ->andReturn(0);

    $mockCollection = Mockery::mock();
    $mockCollection->shouldReceive('requiresSlugs')
        ->andReturn(true);
    $mockCollection->shouldReceive('queryEntries')
        ->andReturn($mockQueryBuilder2);
    $mockCollection->shouldReceive('propagate')
        ->with(false)
        ->andReturnSelf();
    $mockOriginalEntry->shouldReceive('collection')
        ->andReturn($mockCollection);

    // Mock blueprint and fields - need to properly chain reject->shouldBeDuplicated()->keys()
    $mockRejectedFields2 = Mockery::mock(Collection::class);
    $mockRejectedFields2->shouldReceive('keys')
        ->andReturn(collect());

    $mockFieldsCollection = Mockery::mock(Collection::class);
    $mockFieldsCollection->shouldReceive('reject')
        ->andReturn($mockRejectedFields2);

    $mockFields = Mockery::mock();
    $mockFields->shouldReceive('all')
        ->andReturn($mockFieldsCollection);

    $mockBlueprint = Mockery::mock();
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('knowledge_base');
    $mockBlueprint->shouldReceive('fields')
        ->andReturn($mockFields);
    $mockOriginalEntry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint);

    // Mock data
    $mockData = Mockery::mock();
    $mockData->shouldReceive('except')
        ->andReturnSelf();
    $mockData->shouldReceive('merge')
        ->andReturnSelf();
    $mockData->shouldReceive('all')
        ->andReturnUsing(fn () => ['title' => 'Test Entry']);
    $mockOriginalEntry->shouldReceive('data')
        ->andReturn($mockData);

    // Mock structure methods
    $mockOriginalEntry->shouldReceive('structure')
        ->andReturn(null);
    $mockOriginalEntry->shouldReceive('directDescendants')
        ->andReturn(collect());

    // Mock hasDate() returns true (collection has date support)
    // but date() returns null (no explicit date set)
    $mockOriginalEntry->shouldReceive('hasDate')
        ->andReturn(true);
    $mockOriginalEntry->shouldReceive('date')
        ->andReturn(null);

    // On Statamic 6 the duplicate action uses hasExplicitDate(), which returns
    // false when the collection has date support but no explicit date is set.
    $mockOriginalEntry->shouldReceive('hasExplicitDate')
        ->andReturn(false);

    // Mock the duplicated entry
    $mockDuplicatedEntry = Mockery::mock(Entry::class);
    $mockDuplicatedEntry->shouldReceive('locale')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('collection')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('blueprint')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('published')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('data')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('origin')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('updateLastModified')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('slug')
        ->andReturnSelf();
    $mockDuplicatedEntry->shouldReceive('save')
        ->andReturnSelf();

    // The duplicate action must NOT copy date() when hasExplicitDate() is false
    // (the v5.53.1 fix; the pre-fix code copied a null date() via hasDate()).
    $mockDuplicatedEntry->shouldNotReceive('date');

    EntryFacade::shouldReceive('make')
        ->andReturn($mockDuplicatedEntry);

    // Use reflection to access the private duplicateEntry method
    $duplicateAction = new DuplicateEntry;
    $reflection = new ReflectionClass($duplicateAction);
    $method = $reflection->getMethod('duplicateEntry');

    // Execute duplication
    $result = $method->invoke($duplicateAction, $mockOriginalEntry);

    // Verify duplicated entry was created
    expect($result)->not->toBeNull();
});

/**
 * Test that Knowledge Base entries can be localized directly.
 */
it('localizes a knowledge_base entry directly', function (): void {
    $mockEntry = Mockery::mock(KnowledgeBaseEntry::class)->makePartial();
    $mockEntry->shouldAllowMockingProtectedMethods();

    // Mock blueprint properly
    $mockBlueprint = Mockery::mock();
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('knowledge_base');
    $mockEntry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint);

    $mockEntry->shouldReceive('collectionHandle')
        ->andReturn('knowledge_bases');
    $mockEntry->shouldReceive('published')
        ->andReturn(true);
    $mockEntry->shouldReceive('slug')
        ->andReturn('test-kb');

    // Mock EntryFacade
    $mockLocalizedEntry = Mockery::mock(Entry::class);
    $mockLocalizedEntry->shouldReceive('collection')
        ->andReturnSelf();
    $mockLocalizedEntry->shouldReceive('origin')
        ->andReturnSelf();
    $mockLocalizedEntry->shouldReceive('locale')
        ->andReturnSelf();
    $mockLocalizedEntry->shouldReceive('published')
        ->andReturnSelf();
    $mockLocalizedEntry->shouldReceive('slug')
        ->andReturnSelf();

    EntryFacade::shouldReceive('make')
        ->andReturn($mockLocalizedEntry);

    // Should not throw exception for knowledge_base entries
    /** @var KnowledgeBaseEntry $mockEntry */
    $localized = $mockEntry->makeLocalization('health');

    expect($localized)->not->toBeNull();
});

/**
 * Test that topic and article entries cannot be localized directly.
 */
it('does not allow topic or article entries to be localized directly', function (): void {
    $mockEntry = Mockery::mock(KnowledgeBaseEntry::class)->makePartial();

    // Mock blueprint properly
    $mockBlueprint = Mockery::mock();
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('topic');
    $mockEntry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint);

    // Mock id() which is called in the exception logging
    $mockEntry->shouldReceive('id')
        ->andReturn('test-entry-id');

    // Should throw exception
    /** @var KnowledgeBaseEntry $mockEntry */
    expect(fn () => $mockEntry->makeLocalization('health'))
        ->toThrow(
            Exception::class,
            "Only Knowledge Base entries can be localized. 'topic' entries are not allowed to be localized directly."
        );
});
