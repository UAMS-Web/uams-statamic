<?php

declare(strict_types=1);

use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\CollectionTreeIsolatedTestCase;
use Tests\Support\ContentSubmodule;

uses(CollectionTreeIsolatedTestCase::class)->group('serial');

/*
|--------------------------------------------------------------------------
| Recognition-list membership stored bidirectional sync (UAMS-Web/uams-statamic-archive#1546)
|--------------------------------------------------------------------------
|
| stillat/relationships keeps `recognition_lists.members` and
| `people.recognition_lists` in sync: adding a person to a list/category entry's
| members writes that person's back-reference, authoring from the person side
| writes the entry's members, clearing one side removes the inverse, and a roster
| can be authored in bulk from the list side. The `members` handle is shared by the
| `recognition_list` and `recognition_list_category` blueprints, so a category
| child syncs through the same mapping as a whole-list entry.
|
| A stale mapping (wrong handle) passes `relate:list` but silently never syncs, so
| exercising the real handles through actual saves is the load-bearing check. Uses
| the real Stache (serial, self-cleaning); skipped when content/ is absent.
*/

const REC_SYNC_PREFIX = 'zz-rec-sync-';

function recSyncMakePerson(string $suffix): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('people');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint('person');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('default');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug(REC_SYNC_PREFIX.$suffix);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => 'Test Person '.$suffix]);
    $entry->save();

    return $entry;
}

function recSyncMakeList(string $suffix): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('recognition_lists');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint('recognition_list');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('default');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug(REC_SYNC_PREFIX.$suffix);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => 'Test List '.$suffix]);
    $entry->save();

    return $entry;
}

function recSyncMakeCategory(string $suffix): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('recognition_lists');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint('recognition_list_category');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('default');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug(REC_SYNC_PREFIX.$suffix);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => 'Test Category '.$suffix]);
    $entry->save();

    return $entry;
}

function recSyncTearDown(): void
{
    foreach (['recognition_lists', 'people'] as $handle) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', $handle);
        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();
        $entries
            ->filter(function (Statamic\Entries\Entry $entry): bool {
                /** @var string|null $slug */
                $slug = $entry->slug();

                return str_starts_with($slug ?? '', REC_SYNC_PREFIX);
            })
            ->each(fn (Statamic\Entries\Entry $entry) => $entry->delete());
    }
}

beforeEach(function () {
    foreach (['recognition_lists', 'people'] as $handle) {
        if (! Collection::find($handle)) {
            ContentSubmodule::reportMissing('recognition_lists + people collections');
        }
    }

    recSyncTearDown();
});

afterEach(fn () => recSyncTearDown());

describe('recognition-list membership sync (#1546)', function () {
    it('writes the person back-reference when a list authors the member', function () {
        $person = recSyncMakePerson('barnes');
        $list = recSyncMakeList('best-docs');

        $list->set('members', [$person->id()]);
        $list->save();

        /** @var Statamic\Entries\Entry $foundPerson */
        $foundPerson = Entry::find($person->id());
        /** @var array<int, string> $recognitionLists */
        $recognitionLists = $foundPerson->get('recognition_lists');
        expect($recognitionLists)->toContain($list->id());
    });

    it('writes the entry members when the person authors the membership', function () {
        $person = recSyncMakePerson('barnes');
        $list = recSyncMakeList('best-docs');

        $person->set('recognition_lists', [$list->id()]);
        $person->save();

        /** @var Statamic\Entries\Entry $foundList */
        $foundList = Entry::find($list->id());
        /** @var array<int, string> $members */
        $members = $foundList->get('members');
        expect($members)->toContain($person->id());
    });

    it('relates every member when a roster is bulk-authored from the list side', function () {
        $one = recSyncMakePerson('one');
        $two = recSyncMakePerson('two');
        $three = recSyncMakePerson('three');
        $list = recSyncMakeList('best-docs');

        $list->set('members', [$one->id(), $two->id(), $three->id()]);
        $list->save();

        /** @var Statamic\Entries\Entry $foundOne */
        $foundOne = Entry::find($one->id());
        /** @var array<int, string> $oneLists */
        $oneLists = $foundOne->get('recognition_lists');
        expect($oneLists)->toContain($list->id());

        /** @var Statamic\Entries\Entry $foundTwo */
        $foundTwo = Entry::find($two->id());
        /** @var array<int, string> $twoLists */
        $twoLists = $foundTwo->get('recognition_lists');
        expect($twoLists)->toContain($list->id());

        /** @var Statamic\Entries\Entry $foundThree */
        $foundThree = Entry::find($three->id());
        /** @var array<int, string> $threeLists */
        $threeLists = $foundThree->get('recognition_lists');
        expect($threeLists)->toContain($list->id());
    });

    it('removes the inverse when the authored membership is cleared', function () {
        $person = recSyncMakePerson('barnes');
        $list = recSyncMakeList('best-docs');
        $list->set('members', [$person->id()]);
        $list->save();
        /** @var Statamic\Entries\Entry $foundPerson */
        $foundPerson = Entry::find($person->id());
        /** @var array<int, string> $recognitionLists */
        $recognitionLists = $foundPerson->get('recognition_lists');
        expect($recognitionLists)->toContain($list->id());

        $list->set('members', []);
        $list->save();

        /** @var Statamic\Entries\Entry $clearedPerson */
        $clearedPerson = Entry::find($person->id());
        /** @var array<int, string>|null $clearedLists */
        $clearedLists = $clearedPerson->get('recognition_lists');
        expect($clearedLists ?? [])->not->toContain($list->id());
    });

    it('syncs a category child through the shared members mapping', function () {
        $person = recSyncMakePerson('barnes');
        $category = recSyncMakeCategory('ortho');

        $category->set('members', [$person->id()]);
        $category->save();

        /** @var Statamic\Entries\Entry $foundPerson */
        $foundPerson = Entry::find($person->id());
        /** @var array<int, string> $recognitionLists */
        $recognitionLists = $foundPerson->get('recognition_lists');
        expect($recognitionLists)->toContain($category->id());
    });
});
