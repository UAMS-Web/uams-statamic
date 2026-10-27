<?php

declare(strict_types=1);

use App\Listeners\GenerateNewsReleasePdf;
use App\Listeners\RemoveNewsReleasePdfOnDelete;
use App\Services\NewsRelease\NewsReleasePdfGenerator;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Entries\Entry;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * An entry double exposing the surface the publish gate and the event
 * constructors read: status() (the gate), plus id()/root() (EntrySaved's
 * InitiatorStack resolves $entry->root()->id()). Concrete class because the
 * generator's typed methods require Statamic\Entries\Entry.
 */
function newsReleasePdfEntryWithStatus(string $status): Entry
{
    $entry = Mockery::mock(ConcreteEntry::class);
    $entry->shouldReceive('status')->andReturn($status);
    $entry->shouldReceive('id')->andReturn('entry-1');
    $entry->shouldReceive('root')->andReturnSelf();

    return $entry;
}

/**
 * Bind a generator double into the container and return it for expectations.
 *
 * @return NewsReleasePdfGenerator&MockInterface
 */
function newsReleasePdfFakeGenerator(): NewsReleasePdfGenerator
{
    $generator = Mockery::mock(NewsReleasePdfGenerator::class);
    app()->instance(NewsReleasePdfGenerator::class, $generator);

    return $generator;
}

/**
 * Regression coverage for the news-release PDF publish gate (UAMS-Web/uams-statamic-archive#921).
 *
 * The PDF is fully rendered into a public, guessable webroot URL. It must only
 * exist while the entry is publicly live; saving a draft / scheduled / expired
 * release, or deleting one, must remove the PDF rather than publish it early.
 *
 * @covers \App\Listeners\GenerateNewsReleasePdf
 * @covers \App\Listeners\RemoveNewsReleasePdfOnDelete
 *
 * Mutation: 100% on App\Listeners\GenerateNewsReleasePdf (13 mutants) and RemoveNewsReleasePdfOnDelete
 * (3 mutants), measured on macOS on 2026-10-08, serial, --covered-only, with every test file that
 * names each class. App\Services\NewsRelease\NewsReleasePdfGenerator (10 mutants) was measured
 * earlier and not re-run then.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/NewsReleasePdfPublishGateTest.php
 */
it('generates the PDF for a published entry', function () {
    $entry = newsReleasePdfEntryWithStatus('published');

    $generator = newsReleasePdfFakeGenerator();
    $generator->shouldReceive('appliesTo')->with($entry)->andReturn(true);
    $generator->shouldReceive('syncPdfForEntry')->with($entry)->once();
    $generator->shouldReceive('removePdfForEntry')->never();

    (new GenerateNewsReleasePdf)->handle(new EntrySaved($entry));
});

it('removes any existing PDF for a draft entry instead of generating', function () {
    $entry = newsReleasePdfEntryWithStatus('draft');

    $generator = newsReleasePdfFakeGenerator();
    $generator->shouldReceive('appliesTo')->with($entry)->andReturn(true);
    $generator->shouldReceive('removePdfForEntry')->with($entry)->once();
    $generator->shouldReceive('syncPdfForEntry')->never();

    (new GenerateNewsReleasePdf)->handle(new EntrySaved($entry));
});

it('removes the PDF for a scheduled future entry', function () {
    $entry = newsReleasePdfEntryWithStatus('scheduled');

    $generator = newsReleasePdfFakeGenerator();
    $generator->shouldReceive('appliesTo')->with($entry)->andReturn(true);
    $generator->shouldReceive('removePdfForEntry')->with($entry)->once();
    $generator->shouldReceive('syncPdfForEntry')->never();

    (new GenerateNewsReleasePdf)->handle(new EntrySaved($entry));
});

it('ignores a non-news-release entry', function () {
    $entry = newsReleasePdfEntryWithStatus('published');

    $generator = newsReleasePdfFakeGenerator();
    $generator->shouldReceive('appliesTo')->with($entry)->andReturn(false);
    $generator->shouldReceive('syncPdfForEntry')->never();
    $generator->shouldReceive('removePdfForEntry')->never();

    (new GenerateNewsReleasePdf)->handle(new EntrySaved($entry));
});

it('removes its PDF when an entry is deleted', function () {
    $entry = newsReleasePdfEntryWithStatus('published');

    $generator = newsReleasePdfFakeGenerator();
    $generator->shouldReceive('appliesTo')->with($entry)->andReturn(true);
    $generator->shouldReceive('removePdfForEntry')->with($entry)->once();

    (new RemoveNewsReleasePdfOnDelete)->handle(new EntryDeleted($entry));
});

it('deletes only the assets tagged for that entry when removing a PDF', function () {
    $owned = Mockery::mock();
    $owned->shouldReceive('get')->with('news_release_entry')->andReturn('entry-1');
    $owned->shouldReceive('id')->andReturn('owned');
    $owned->shouldReceive('deleteQuietly')->once();

    $other = Mockery::mock();
    $other->shouldReceive('get')->with('news_release_entry')->andReturn('different-entry');
    $other->shouldReceive('deleteQuietly')->never();

    $container = Mockery::mock(Statamic\Contracts\Assets\AssetContainer::class);
    $container->shouldReceive('assets')->andReturn(collect([$owned, $other]));

    AssetContainer::shouldReceive('find')->with('news_releases')->andReturn($container);

    $entry = Mockery::mock(ConcreteEntry::class);
    $entry->shouldReceive('id')->andReturn('entry-1');

    app(NewsReleasePdfGenerator::class)->removePdfForEntry($entry);
});

it('leaves a deleted entry that is not a news release alone', function () {
    $entry = newsReleasePdfEntryWithStatus('published');

    $generator = newsReleasePdfFakeGenerator();
    $generator->shouldReceive('appliesTo')->with($entry)->andReturn(false);
    $generator->shouldReceive('removePdfForEntry')->never();

    (new RemoveNewsReleasePdfOnDelete)->handle(new EntryDeleted($entry));
});

it('skips a save that fires while its own PDF sync is running', function () {
    $outer = newsReleasePdfEntryWithStatus('published');
    $inner = newsReleasePdfEntryWithStatus('published');

    $generator = newsReleasePdfFakeGenerator();
    $generator->shouldReceive('appliesTo')->with($outer)->once()->andReturn(true);
    $generator->shouldReceive('appliesTo')->with($inner)->never();
    // The generator's own saveQuietly() can land back in the listener while the guard is up.
    $generator->shouldReceive('syncPdfForEntry')->with($outer)->once()->andReturnUsing(
        fn () => (new GenerateNewsReleasePdf)->handle(new EntrySaved($inner))
    );

    (new GenerateNewsReleasePdf)->handle(new EntrySaved($outer));
});

it('logs a failed sync without breaking the save, and lowers its guard', function () {
    $failing = newsReleasePdfEntryWithStatus('published');
    $next = newsReleasePdfEntryWithStatus('draft');

    $generator = newsReleasePdfFakeGenerator();
    $generator->shouldReceive('appliesTo')->andReturn(true);
    $generator->shouldReceive('syncPdfForEntry')->with($failing)->once()->andThrow(new RuntimeException('renderer offline'));
    $generator->shouldReceive('removePdfForEntry')->with($next)->once();

    Log::shouldReceive('warning')
        ->once()
        ->with('GenerateNewsReleasePdf: listener failed', [
            'entry_id' => 'entry-1',
            'message' => 'renderer offline',
        ]);

    (new GenerateNewsReleasePdf)->handle(new EntrySaved($failing));

    // The guard is lowered in `finally`, so the next save is handled rather than skipped.
    (new GenerateNewsReleasePdf)->handle(new EntrySaved($next));
});
