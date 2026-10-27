<?php

declare(strict_types=1);

use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;
use Statamic\Auth\User;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;

/*
 | Does a set added through the schema object replicator's set picker survive a
 | Control Panel save (UAMS-Web/uams-statamic-archive#3250)?
 |
 | Adding a row depends on the chosen set's handle in the replicator config. A
 | set whose handle does not resolve sends `set: null` to Statamic's
 | replicator-set endpoint, which rejects it, and the row never reaches the
 | save. The Feature tests pin the config payload; this file drives
 | the save that payload feeds. It adds one set drawn from `setsForRangeTypes()`
 | (`text_short`, shown as "Text") and the `antlers` set, which the service keys
 | separately, then saves and reads the stored rows back from the entry.
 |
 | The entry starts with `Thing` selected and its `name` property in custom
 | mode, so the replicator is on screen when the page loads and the test drives
 | only the set picker and the save. It observes the page and the saved entry,
 | never the service's internals.
 |
 | @command vendor/bin/pest tests/Browser/CpSchemaObjectReplicatorSaveTest.php
 */

$collectionHandle = 'schema_object_replicator_save';

/**
 * Snooze Statamic's trial-mode licensing alert when it opens. It is a modal
 * over the publish form, so every click behind it times out.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from navigate().
 */
function schemaObjectDismissLicensingAlert($page): void
{
    for ($waited = 0.0; $waited <= 3.0; $waited += 0.2) {
        $open = $page->script('(() => [...document.querySelectorAll("button")].some((b) => b.textContent.trim() === "Snooze"))()');

        if ($open === true) {
            $page->click('Snooze');

            break;
        }

        $page->wait(0.2);
    }
}

/**
 * Open the replicator's set picker and choose the set shown as `$display`.
 *
 * The picker item is found by its exact text inside the open picker, since the
 * same words appear elsewhere on the page as field labels.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from navigate().
 * @param  string  $display  The set's display name in the picker
 */
function schemaObjectAddSet($page, string $display): void
{
    $page->click('Add Value');

    // Choose the set inside the open picker
    $clicked = $page->script('(() => { const item = [...document.querySelectorAll(".set-picker *")]'
        .'.find((e) => e.children.length === 0 && e.textContent.trim() === '.json_encode($display).');'
        .' if (! item) { return false; } item.click(); return true; })()');

    expect($clicked)->toBeTrue("the {$display} set in the set picker");

    $page->wait(0.5);
}

/**
 * Read the stored rows of the entry's `name` property, waiting for a save to land.
 *
 * The app is served from this process, so the wait goes through the page, which
 * lets the server handle the save; a sleep here would block the request it waits on.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page that submitted the save
 * @param  string  $entryId  The entry the Control Panel saved
 * @param  int  $expectedRows  The row count that marks the save as landed
 * @return array<int, mixed> The stored replicator rows
 */
function schemaObjectSavedNameRows($page, string $entryId, int $expectedRows): array
{
    $rows = [];

    for ($waited = 0.0; $waited <= 15.0; $waited += 0.5) {
        $page->wait(0.5);

        $entry = Entry::find($entryId);

        /** @var array{properties?: array{name?: array{value?: array<int, mixed>}}}|null $schema */
        $schema = $entry instanceof EntryModel ? $entry->get('schema') : null;
        $rows = $schema['properties']['name']['value'] ?? [];

        if (\count($rows) >= $expectedRows) {
            break;
        }
    }

    return $rows;
}

beforeEach(function () use ($collectionHandle) {
    $this->cpEmail = 'schema-object-save-'.uniqid().'@example.test';
    $this->cpPassword = 'schema-object-save-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make($collectionHandle);
    $collection->title('Schema Object Replicator Save');
    $collection->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::makeFromFields([
        'title' => [
            'type' => 'text',
            'display' => 'Title',
        ],
        'schema' => [
            'type' => 'schema_object',
            'display' => 'Schema',
        ],
    ]);
    $blueprint->setHandle($collectionHandle);
    $blueprint->setNamespace('collections.'.$collectionHandle);
    $blueprint->save();

    // Start with Thing selected and `name` in custom mode, so the replicator renders on load
    /** @var EntryModel $entry */
    $entry = Entry::make();
    $entry->collection($collectionHandle);
    $entry->slug('schema-object-replicator-save');
    $entry->data([
        'title' => 'Schema Object Replicator Save',
        'schema' => [
            'type' => ['Thing'],
            'properties' => [
                'name' => ['mode' => 'custom', 'value' => []],
            ],
        ],
    ]);
    $entry->save();
    $this->entryId = $entry->id();

    Stache::refresh();
});

afterEach(function () use ($collectionHandle) {
    /** @var string|null $entryId */
    $entryId = $this->entryId ?? null;
    if (\is_string($entryId)) {
        $entry = Entry::find($entryId);
        if ($entry instanceof EntryModel) {
            $entry->delete();
        }
    }

    Collection::find($collectionHandle)?->delete();
    Blueprint::find('collections.'.$collectionHandle.'.'.$collectionHandle)?->delete();
    /** @var User|null $user */
    $user = $this->cpUser;
    $user?->delete();
    Stache::refresh();
});

it('keeps a set from the range types and the antlers set through a Control Panel save (#3250)', function () use ($collectionHandle) {
    /** @var string $cpEmail */
    $cpEmail = $this->cpEmail;
    /** @var string $cpPassword */
    $cpPassword = $this->cpPassword;
    /** @var string $entryId */
    $entryId = $this->entryId;

    $page = loginToCp($cpEmail, $cpPassword)
        ->navigate('/cp/collections/'.$collectionHandle.'/entries/'.$entryId);

    waitToSee($page, 'Add Value', 60.0);
    schemaObjectDismissLicensingAlert($page);

    // Add a range-type set and the antlers set through the set picker, and give the first a value
    schemaObjectAddSet($page, 'Text');
    schemaObjectAddSet($page, 'Antlers');
    $page->type('#field_base_schema_properties_name_value_0_value', 'Saved through the set picker');

    $page->click('Save & Publish');

    $rows = schemaObjectSavedNameRows($page, $entryId, 2);

    // A row whose set resolved to null is dropped on save, so both must be stored with their handles
    expect(array_map(static fn (mixed $row): mixed => \is_array($row) ? ($row['type'] ?? null) : null, $rows))
        ->toBe(['text_short', 'antlers']);
    expect(\is_array($rows[0]) ? ($rows[0]['value'] ?? null) : null)->toBe('Saved through the set picker');

    assertNoAppJavaScriptErrors($page);
});
