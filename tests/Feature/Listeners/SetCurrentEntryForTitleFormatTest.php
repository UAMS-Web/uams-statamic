<?php

declare(strict_types=1);

use App\Listeners\SetCurrentEntryForTitleFormat;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Blink;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SetCurrentEntryForTitleFormat::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

afterEach(function (): void {
    Blink::flush();
});

/**
 * Mutation: App\Listeners\SetCurrentEntryForTitleFormat.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/SetCurrentEntryForTitleFormatTest.php
 */
it('stores the entry in Blink while saving and clears it once saved', function (): void {
    $root = Mockery::mock(Entry::class);
    $root->shouldReceive('id')->andReturn('root-id');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('root')->andReturn($root);

    $listener = new SetCurrentEntryForTitleFormat;

    $listener->handle(new EntrySaving($entry));
    expect(SetCurrentEntryForTitleFormat::currentEntry())->toBe($entry);

    $listener->handle(new EntrySaved($entry));
    expect(SetCurrentEntryForTitleFormat::currentEntry())->toBeNull();
});

it('returns null from currentEntry when Blink holds a non-Entry', function (): void {
    Blink::put('disambiguate_title_current_entry', 'not-an-entry');

    expect(SetCurrentEntryForTitleFormat::currentEntry())->toBeNull();
});
