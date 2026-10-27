<?php

declare(strict_types=1);

use App\Listeners\SyncAreaOfExpertiseSectionSearchIndex;
use App\Search\AreaOfExpertiseSectionDocument;
use App\Search\AreaOfExpertiseSectionProvider;
use App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver;
use Statamic\Entries\Collection;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the reindex-on-save/delete sync of the synthetic Area-of-Expertise section
 * search documents (UAMS-Web/uams-statamic-archive#1527). The `local` driver's search JSON lives in shared, non-isolated
 * storage, so this drives the listener with a real AoE entry + the real provider but a mocked
 * search index — asserting the exact delete/insert calls without writing an index file.
 *
 * @command  php artisan test --compact tests/Unit/Listeners/SyncAreaOfExpertiseSectionSearchIndexTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    /** @var Collection $expertise */
    $expertise = CollectionFacade::make('clinical_expertise');
    /** @var Collection $expertise */
    $expertise = $expertise->title('Areas of Expertise');
    /** @var Collection $expertise */
    $expertise = $expertise->routes('{slug}');
    /** @var Collection $expertise */
    $expertise = $expertise->structureContents(['root' => false]);
    $expertise->save();

    /** @var Collection $locations */
    $locations = CollectionFacade::make('clinical_locations');
    /** @var Collection $locations */
    $locations = $locations->title('Clinical Locations');
    $locations->save();

    /** @var Statamic\Fields\Blueprint $expertiseBlueprint */
    $expertiseBlueprint = Blueprint::make('clinical_expertise');
    /** @var Statamic\Fields\Blueprint $expertiseBlueprint */
    $expertiseBlueprint = $expertiseBlueprint->setNamespace('collections.clinical_expertise');
    /** @var Statamic\Fields\Blueprint $expertiseBlueprint */
    $expertiseBlueprint = $expertiseBlueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'clinical_locations', 'field' => ['type' => 'entries', 'collections' => ['clinical_locations']]],
        ]]]]],
    ]);
    $expertiseBlueprint->save();

    /** @var Statamic\Fields\Blueprint $locationBlueprint */
    $locationBlueprint = Blueprint::make('location');
    /** @var Statamic\Fields\Blueprint $locationBlueprint */
    $locationBlueprint = $locationBlueprint->setNamespace('collections.clinical_locations');
    /** @var Statamic\Fields\Blueprint $locationBlueprint */
    $locationBlueprint = $locationBlueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]],
    ]);
    $locationBlueprint->save();

    $this->listener = new SyncAreaOfExpertiseSectionSearchIndex(app(AreaOfExpertiseSectionProvider::class));
});

/** A published AoE with its `locations` section populated by one real location. */
function aoeWithLocations(): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    /** @var Statamic\Entries\Entry $location */
    $location = $location->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->blueprint('location');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->slug('main-campus');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->published(true);
    /** @var Statamic\Entries\Entry $location */
    $location = $location->data(['title' => 'Main Campus']);
    $location->saveQuietly();

    /** @var Statamic\Entries\Entry $aoe */
    $aoe = Entry::make();
    /** @var Statamic\Entries\Entry $aoe */
    $aoe = $aoe->collection('clinical_expertise');
    /** @var Statamic\Entries\Entry $aoe */
    $aoe = $aoe->blueprint('clinical_expertise');
    /** @var Statamic\Entries\Entry $aoe */
    $aoe = $aoe->slug('cancer-care');
    /** @var Statamic\Entries\Entry $aoe */
    $aoe = $aoe->published(true);
    /** @var Statamic\Entries\Entry $aoe */
    $aoe = $aoe->data(['title' => 'Cancer Care', 'clinical_locations' => [$location->id()]]);
    $aoe->saveQuietly();

    return $aoe;
}

/**
 * The five `clinical_expertise_section::<id>::<token>` references a full purge deletes.
 *
 * @return array<int, string>
 */
function expectedSectionDeletes(string $id): array
{
    return array_map(
        fn (string $token): string => AreaOfExpertiseSectionDocument::reference($id, $token),
        ClinicalExpertiseSectionResolver::tokens(),
    );
}

it('queues only for an Area-of-Expertise save or delete', function (): void {
    $aoe = aoeWithLocations();

    /** @var Statamic\Entries\Entry $other */
    $other = Entry::make();
    /** @var Statamic\Entries\Entry $other */
    $other = $other->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $other */
    $other = $other->blueprint('location');
    /** @var Statamic\Entries\Entry $other */
    $other = $other->slug('other-loc');
    /** @var Statamic\Entries\Entry $other */
    $other = $other->published(true);
    /** @var Statamic\Entries\Entry $other */
    $other = $other->data(['title' => 'Other']);
    $other->saveQuietly();

    /** @var SyncAreaOfExpertiseSectionSearchIndex $listener */
    $listener = $this->listener;

    // The listener is ShouldQueue but gates itself so a non-AoE save never enqueues a no-op job.
    expect($listener->shouldQueue(new EntrySaved($aoe)))->toBeTrue()
        ->and($listener->shouldQueue(new EntryDeleted($aoe)))->toBeTrue()
        ->and($listener->shouldQueue(new EntrySaved($other)))->toBeFalse();
});

it('ignores a save on a non-Area-of-Expertise collection', function (): void {
    Search::shouldReceive('index')->never();

    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    /** @var Statamic\Entries\Entry $location */
    $location = $location->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->blueprint('location');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->slug('main-campus');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->published(true);
    /** @var Statamic\Entries\Entry $location */
    $location = $location->data(['title' => 'Main Campus']);
    $location->saveQuietly();

    /** @var SyncAreaOfExpertiseSectionSearchIndex $listener */
    $listener = $this->listener;

    $listener->handle(new EntrySaved($location));
});

it('does nothing until the index has been built', function (): void {
    $index = Mockery::mock();
    $index->shouldReceive('exists')->once()->andReturn(false);
    $index->shouldReceive('delete')->never();
    $index->shouldReceive('insert')->never();
    Search::shouldReceive('index')->with('default')->once()->andReturn($index);

    /** @var SyncAreaOfExpertiseSectionSearchIndex $listener */
    $listener = $this->listener;

    $listener->handle(new EntrySaved(aoeWithLocations()));
});

it('purges every section document then re-inserts the valid ones on save', function (): void {
    $aoe = aoeWithLocations();
    /** @var string $aoeId */
    $aoeId = $aoe->id();
    $deletes = expectedSectionDeletes($aoeId);

    $index = Mockery::mock();
    $index->shouldReceive('exists')->once()->andReturn(true);
    // Every possible section document for this AoE is deleted (an idempotent purge).
    $index->shouldReceive('delete')
        ->with(Mockery::on(fn ($doc): bool => $doc instanceof AreaOfExpertiseSectionDocument
            && in_array($doc->getSearchReference(), $deletes, true)))
        ->times(count($deletes));
    // Then only the currently valid section reference (the populated `locations`) is re-inserted.
    $index->shouldReceive('insert')->with('clinical_expertise_section::'.$aoeId.'::locations')->once();
    Search::shouldReceive('index')->with('default')->once()->andReturn($index);

    /** @var SyncAreaOfExpertiseSectionSearchIndex $listener */
    $listener = $this->listener;

    $listener->handle(new EntrySaved($aoe));
});

it('purges every section document and inserts nothing on delete', function (): void {
    $aoe = aoeWithLocations();
    /** @var string $aoeId */
    $aoeId = $aoe->id();
    $deletes = expectedSectionDeletes($aoeId);

    $index = Mockery::mock();
    $index->shouldReceive('exists')->once()->andReturn(true);
    $index->shouldReceive('delete')
        ->with(Mockery::on(fn ($doc): bool => $doc instanceof AreaOfExpertiseSectionDocument
            && in_array($doc->getSearchReference(), $deletes, true)))
        ->times(count($deletes));
    $index->shouldReceive('insert')->never();
    Search::shouldReceive('index')->with('default')->once()->andReturn($index);

    /** @var SyncAreaOfExpertiseSectionSearchIndex $listener */
    $listener = $this->listener;

    $listener->handle(new EntryDeleted($aoe));
});
