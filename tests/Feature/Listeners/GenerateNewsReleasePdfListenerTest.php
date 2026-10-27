<?php

declare(strict_types=1);

use App\Listeners\GenerateNewsReleasePdf;
use App\Services\NewsRelease\NewsReleasePdfGenerator;
use Illuminate\Support\Facades\Event;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Events\EntrySaved;
use Statamic\Fields\Blueprint;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Listeners\GenerateNewsReleasePdf
 *
 * Mutation: 100% on App\Listeners\GenerateNewsReleasePdf (9 mutants, --covered-only;
 * shared coverage with NewsReleasePdfPublishGateTest).
 *
 * @command  php artisan test --compact tests/Feature/Listeners/GenerateNewsReleasePdfListenerTest.php
 */
it('invokes the generator for a published news_release entry', function () {
    // Build a synthetic posts entry that uses the news_release blueprint.
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('news_release');

    $entry = Mockery::mock(StatamicEntry::class);
    $entry->shouldReceive('collectionHandle')->andReturn('posts');
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('id')->andReturn('44444444-4444-4444-8444-444444444444');
    $entry->shouldReceive('root')->andReturnSelf();
    // Publicly live entry → the listener generates its PDF.
    $entry->shouldReceive('status')->andReturn('published');

    // Replace the generator with a mock expecting one sync call.
    $generator = Mockery::mock(NewsReleasePdfGenerator::class);
    $generator->shouldReceive('appliesTo')->andReturn(true);
    $generator->shouldReceive('syncPdfForEntry')
        ->once()
        ->with($entry);
    app()->instance(NewsReleasePdfGenerator::class, $generator);

    // Run the listener directly with a synthetic EntrySaved event.
    app(GenerateNewsReleasePdf::class)->handle(new EntrySaved($entry));
});

it('skips entries that are not news releases', function () {
    // Build a synthetic posts entry that does not use the news_release blueprint.
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('posts');

    $entry = Mockery::mock(StatamicEntry::class);
    $entry->shouldReceive('collectionHandle')->andReturn('posts');
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('id')->andReturn('55555555-5555-4555-8555-555555555555');
    $entry->shouldReceive('root')->andReturnSelf();

    // Replace the generator with a mock that must never run.
    $generator = Mockery::mock(NewsReleasePdfGenerator::class);
    // The generator reports the entry is not one it manages.
    $generator->shouldReceive('appliesTo')->andReturn(false);
    $generator->shouldReceive('syncPdfForEntry')->never();
    $generator->shouldReceive('removePdfForEntry')->never();
    app()->instance(NewsReleasePdfGenerator::class, $generator);

    // Run the listener; it should return before resolving the generator's work.
    app(GenerateNewsReleasePdf::class)->handle(new EntrySaved($entry));
});

it('is registered once for EntrySaved, so a save renders the PDF once (#3240)', function () {
    // Read the raw registrations: discovery used to add a second copy, which ran
    // the generator a second time on every save
    $registrations = array_filter(
        mustBeArray(Event::getRawListeners()[EntrySaved::class] ?? [], 'the EntrySaved listeners'),
        fn (mixed $listener): bool => is_string($listener) && str_starts_with($listener, GenerateNewsReleasePdf::class),
    );

    expect(array_values($registrations))->toBe([GenerateNewsReleasePdf::class]);
});
