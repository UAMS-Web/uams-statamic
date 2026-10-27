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
 | Does a row added to each of the role title's four unstructured replicators
 | survive a Control Panel save (UAMS-Web/uams-statamic-archive#3254)?
 |
 | Unstructured mode shows four replicators, one per list: ISCO-08 codes,
 | O*NET-SOC codes, occupation reference webpages and occupational field
 | reference webpages. `RoleTitleUnstructuredReplicatorSupport` builds a
 | separate config payload for each, so a defect can reach one without the
 | others. Each has a single set, so its add button inserts a row through
 | Statamic's replicator-set endpoint with that set's handle. A handle that does
 | not resolve sends `set: null`, the endpoint rejects it, and the row never
 | appears. The Feature tests pin the payloads; this file drives the save.
 |
 | It adds one row to each replicator, fills it, saves, and reads the stored
 | lists back from the entry. It observes the page and the saved entry, never
 | the support class's internals.
 |
 | @command vendor/bin/pest tests/Browser/CpRoleTitleUnstructuredReplicatorSaveTest.php
 */

$collectionHandle = 'role_title_unstructured_save';

/**
 * Snooze Statamic's trial-mode licensing alert when it opens. It is a modal
 * over the publish form, so every click behind it times out.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from navigate().
 */
function roleTitleDismissLicensingAlert($page): void
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
 * Add a row to one of the role title's unstructured replicators by its add button.
 *
 * Each replicator has a single set, so its add button inserts the row with no
 * set picker. The two reference-webpage replicators share a label, so the
 * button is chosen by its position among buttons with that exact text.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from navigate().
 * @param  string  $label  The add button's text
 * @param  int  $index  Which button with that text, counted from 0 in page order
 */
function roleTitleAddRow($page, string $label, int $index): void
{
    $clicked = $page->script('(() => { const button = [...document.querySelectorAll("button")]'
        .'.filter((e) => e.textContent.trim() === '.json_encode($label).')['.$index.'];'
        .' if (! button) { return false; } button.click(); return true; })()');

    expect($clicked)->toBeTrue("the {$label} button at position {$index}");

    $page->wait(0.5);
}

/**
 * Read the entry's stored `job_title`, waiting for a save to land.
 *
 * The app is served from this process, so the wait goes through the page, which
 * lets the server handle the save; a sleep here would block the request it waits on.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page that submitted the save
 * @param  string  $entryId  The entry the Control Panel saved
 * @return array<array-key, mixed> The stored role title value, as the entry holds it
 */
function roleTitleSavedJobTitle($page, string $entryId): array
{
    $jobTitle = [];

    for ($waited = 0.0; $waited <= 15.0; $waited += 0.5) {
        $page->wait(0.5);

        $entry = Entry::find($entryId);
        $stored = $entry instanceof EntryModel ? $entry->get('job_title') : null;
        $jobTitle = \is_array($stored) ? $stored : [];

        if (data_get($jobTitle, 'occupational_field.same_as') !== null) {
            break;
        }
    }

    return $jobTitle;
}

beforeEach(function () use ($collectionHandle) {
    $this->cpEmail = 'role-title-save-'.uniqid().'@example.test';
    $this->cpPassword = 'role-title-save-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make($collectionHandle);
    $collection->title('Role Title Unstructured Save');
    $collection->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::makeFromFields([
        'title' => [
            'type' => 'text',
            'display' => 'Title',
        ],
        'job_title' => [
            'type' => 'role_title',
            'display' => 'Job Title',
            'enabled_title_structures' => ['unstructured'],
            'default_title_structure' => 'unstructured',
        ],
    ]);
    $blueprint->setHandle($collectionHandle);
    $blueprint->setNamespace('collections.'.$collectionHandle);
    $blueprint->save();

    // Start in unstructured mode, so the four replicators render on load
    /** @var EntryModel $entry */
    $entry = Entry::make();
    $entry->collection($collectionHandle);
    $entry->slug('role-title-unstructured-save');
    $entry->data([
        'title' => 'Role Title Unstructured Save',
        'job_title' => ['mode' => 'unstructured', 'value' => 'Staff Physician'],
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

it('keeps a row added to each of the four unstructured replicators through a Control Panel save (#3254)', function () use ($collectionHandle) {
    /** @var string $cpEmail */
    $cpEmail = $this->cpEmail;
    /** @var string $cpPassword */
    $cpPassword = $this->cpPassword;
    /** @var string $entryId */
    $entryId = $this->entryId;

    $page = loginToCp($cpEmail, $cpPassword)
        ->navigate('/cp/collections/'.$collectionHandle.'/entries/'.$entryId);

    waitToSee($page, 'Add ISCO-08 Code', 60.0);
    roleTitleDismissLicensingAlert($page);

    // One row in each replicator; the two reference-webpage replicators share a button label
    roleTitleAddRow($page, 'Add ISCO-08 Code', 0);
    roleTitleAddRow($page, 'Add O*NET-SOC Code', 0);
    roleTitleAddRow($page, 'Add Reference Webpage', 0);
    roleTitleAddRow($page, 'Add Reference Webpage', 1);

    $page->click('#field_base_job_title___cp_role_title_unstructured_isco_08_0_code-major_group');
    $page->wait(0.5);
    $chosen = $page->script('(() => { const option = [...document.querySelectorAll("[role=option]")]'
        .'.find((e) => e.textContent.trim() === "2 Professionals");'
        .' if (! option) { return false; } option.click(); return true; })()');
    expect($chosen)->toBeTrue('the ISCO-08 major group option');
    $page->wait(0.5);
    $page->type('#field_base_job_title___cp_role_title_unstructured_onet_soc_0_code', '29-1141.00');
    $page->type('#field_base_job_title___cp_role_title_unstructured_occ_same_as_0_url', 'https://example.test/occupation');
    $page->type('#field_base_job_title___cp_role_title_unstructured_field_same_as_0_url', 'https://example.test/field');

    $page->click('Save & Publish');

    $jobTitle = roleTitleSavedJobTitle($page, $entryId);

    // A row whose set did not resolve never reaches the save, so each list would come back empty
    expect(data_get($jobTitle, 'occupation.category_code.isco_08'))->toBe(['2'])
        ->and(data_get($jobTitle, 'occupation.category_code.onet_soc'))->toBe(['29-1141.00'])
        ->and(data_get($jobTitle, 'occupation.same_as'))->toBe(['https://example.test/occupation'])
        ->and(data_get($jobTitle, 'occupational_field.same_as'))->toBe(['https://example.test/field']);

    assertNoAppJavaScriptErrors($page);
});
