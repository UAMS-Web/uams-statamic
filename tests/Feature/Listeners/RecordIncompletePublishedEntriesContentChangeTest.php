<?php

declare(strict_types=1);

use App\Listeners\RecordIncompletePublishedEntriesContentChange;
use App\Widgets\IncompletePublishedEntries;
use Illuminate\Support\Facades\Cache;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaved;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(RecordIncompletePublishedEntriesContentChange::class);

/**
 * UAMS-Web/uams-statamic-archive#2506: a save records WHEN content changed and leaves the widget's scan payload in
 * place. The previous listener discarded the whole payload on every save, which sent
 * the next dashboard load into a scan it could not finish inside the request limit.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/RecordIncompletePublishedEntriesContentChangeTest.php
 */
function recordContentChangeFor(): void
{
    // EntrySaved's constructor reads the entry's root id for Statamic's initiator stack.
    $root = Mockery::mock(Entry::class);
    $root->shouldReceive('id')->andReturn('root-id');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('root')->andReturn($root);

    (new RecordIncompletePublishedEntriesContentChange)->handle(new EntrySaved($entry));
}

it('records when content last changed', function () {
    Cache::flush();

    $before = time();
    recordContentChangeFor();

    $recorded = Cache::get(IncompletePublishedEntries::CACHE_CONTENT_CHANGED_KEY);

    expect($recorded)->toBeInt()
        ->and($recorded)->toBeGreaterThanOrEqual($before);
});

it('leaves the stored scan payload in place', function () {
    Cache::flush();

    $widget = new IncompletePublishedEntries;
    $payload = ['rows' => [['id' => 'a', 'edit_url' => '', 'title' => 'A', 'collection_title' => '', 'blueprint_title' => '']], 'scan' => ['scanned' => 1, 'total' => 1, 'truncated' => false], 'computed_at' => 1];
    Cache::forever($widget->cacheKey(), $payload);

    recordContentChangeFor();

    expect(Cache::get($widget->cacheKey()))->toBe($payload);
});
