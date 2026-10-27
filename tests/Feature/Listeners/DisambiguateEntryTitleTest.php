<?php

declare(strict_types=1);

use App\Listeners\DisambiguateEntryTitle;
use App\Services\TitleDisambiguator;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaved;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Listeners\DisambiguateEntryTitle
 *
 * The disambiguator is mocked, so these tests pin the listener's own decisions: when it asks
 * for a suffix, when it renames and saves, its re-entry guard, and that a failure is logged
 * rather than thrown out of the save.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/DisambiguateEntryTitleTest.php
 */

/**
 * Bind a mock disambiguator and return it.
 */
function disambiguatorMock(): TitleDisambiguator&MockInterface
{
    $disambiguator = Mockery::mock(TitleDisambiguator::class);
    app()->instance(TitleDisambiguator::class, $disambiguator);

    return $disambiguator;
}

/**
 * A mock entry with a collection (or none) and the given title.
 *
 * @param  mixed  $title  What `get('title')` returns.
 */
function disambiguateEntry(mixed $title, bool $inCollection = true, string $id = 'entry-1'): Entry&MockInterface
{
    $root = Mockery::mock(Entry::class);
    $root->shouldReceive('id')->andReturn('root-id');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('root')->andReturn($root);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('collection')->andReturn($inCollection ? Mockery::mock(StatamicCollection::class) : null);
    $entry->shouldReceive('get')->with('title')->andReturn($title);

    return $entry;
}

/**
 * Fire the listener for a saved entry.
 */
function disambiguateFire(Entry $entry): void
{
    (new DisambiguateEntryTitle)->handle(new EntrySaved($entry));
}

it('skips when the suffix is empty', function (): void {
    disambiguatorMock()->shouldReceive('suffixForEntry')->once()->andReturn('');

    $entry = disambiguateEntry('Unique Title');
    $entry->shouldReceive('set')->never();
    $entry->shouldReceive('saveQuietly')->never();

    disambiguateFire($entry);
});

it('appends the suffix to the title and saves quietly', function (): void {
    $entry = disambiguateEntry('Annual Report');

    disambiguatorMock()->shouldReceive('suffixForEntry')
        ->once()
        ->with($entry, 'Annual Report')
        ->andReturn(' (2)');

    $entry->shouldReceive('set')->once()->with('title', 'Annual Report (2)')->ordered();
    $entry->shouldReceive('saveQuietly')->once()->ordered();

    disambiguateFire($entry);
});

it('skips an entry with no collection', function (): void {
    disambiguatorMock()->shouldReceive('suffixForEntry')->never();

    $entry = disambiguateEntry('Annual Report', inCollection: false);
    $entry->shouldReceive('saveQuietly')->never();

    disambiguateFire($entry);
});

it('skips a title that is empty or not text', function (mixed $title): void {
    disambiguatorMock()->shouldReceive('suffixForEntry')->never();

    $entry = disambiguateEntry($title);
    $entry->shouldReceive('saveQuietly')->never();

    disambiguateFire($entry);
})->with([
    'empty' => [''],
    'null' => [null],
    'number' => [42],
    'array' => [['Annual Report']],
]);

it('does not disambiguate again while its own quiet save runs', function (): void {
    $outer = disambiguateEntry('Annual Report', id: 'outer');
    $inner = disambiguateEntry('Annual Report', id: 'inner');
    $inner->shouldReceive('saveQuietly')->never();

    $disambiguator = disambiguatorMock();
    $disambiguator->shouldReceive('suffixForEntry')->once()->with($outer, 'Annual Report')->andReturn(' (2)');
    $disambiguator->shouldReceive('suffixForEntry')->with($inner, Mockery::any())->never();

    $outer->shouldReceive('set')->once()->with('title', 'Annual Report (2)');
    // A save that fires EntrySaved again lands back in the listener while the guard is up.
    $outer->shouldReceive('saveQuietly')->once()->andReturnUsing(function () use ($inner): bool {
        disambiguateFire($inner);

        return true;
    });

    disambiguateFire($outer);
});

it('lowers its guard after the save, so the next entry is disambiguated', function (): void {
    $first = disambiguateEntry('Annual Report', id: 'first');
    $second = disambiguateEntry('Annual Report', id: 'second');

    $disambiguator = disambiguatorMock();
    $disambiguator->shouldReceive('suffixForEntry')->once()->with($first, 'Annual Report')->andReturn(' (2)');
    $disambiguator->shouldReceive('suffixForEntry')->once()->with($second, 'Annual Report')->andReturn(' (3)');

    $first->shouldReceive('set')->once()->with('title', 'Annual Report (2)');
    $first->shouldReceive('saveQuietly')->once();
    $second->shouldReceive('set')->once()->with('title', 'Annual Report (3)');
    $second->shouldReceive('saveQuietly')->once();

    disambiguateFire($first);
    disambiguateFire($second);
});

it('logs a failed quiet save, and lowers its guard anyway', function (): void {
    $first = disambiguateEntry('Annual Report', id: 'first');
    $second = disambiguateEntry('Annual Report', id: 'second');

    $disambiguator = disambiguatorMock();
    $disambiguator->shouldReceive('suffixForEntry')->once()->with($first, 'Annual Report')->andReturn(' (2)');
    $disambiguator->shouldReceive('suffixForEntry')->once()->with($second, 'Annual Report')->andReturn(' (3)');

    $first->shouldReceive('set')->once()->with('title', 'Annual Report (2)');
    $first->shouldReceive('saveQuietly')->once()->andThrow(new RuntimeException('disk full'));
    $second->shouldReceive('set')->once()->with('title', 'Annual Report (3)');
    $second->shouldReceive('saveQuietly')->once();

    Log::shouldReceive('warning')
        ->once()
        ->with('DisambiguateEntryTitle: failed to disambiguate title', [
            'entry_id' => 'first',
            'message' => 'disk full',
        ]);

    disambiguateFire($first);
    disambiguateFire($second);
});

it('logs a failure from the disambiguator instead of breaking the save', function (): void {
    disambiguatorMock()->shouldReceive('suffixForEntry')->once()->andThrow(new RuntimeException('index offline'));

    $entry = disambiguateEntry('Annual Report', id: 'entry-9');
    $entry->shouldReceive('saveQuietly')->never();

    Log::shouldReceive('warning')
        ->once()
        ->with('DisambiguateEntryTitle: failed to disambiguate title', [
            'entry_id' => 'entry-9',
            'message' => 'index offline',
        ]);

    disambiguateFire($entry);
});
