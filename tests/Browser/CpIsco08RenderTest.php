<?php

declare(strict_types=1);

use Statamic\Auth\User;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;

/*
 | Does an `isco_08` field render in the Control Panel from `uams-web/aether-people`
 | (UAMS-Web/uams-statamic#1143)?
 |
 | The site no longer registers an `isco_08-fieldtype` component or carries the
 | fieldtype's lang keys, so the field renders only if the add-on's bundle and its
 | `aether-people::fieldtypes.isco_08.*` keys reach the page. The check opens an entry
 | with one `isco_08` field, waits for its major group control, and reads that no raw
 | lang key is on the page.
 |
 | @command vendor/bin/pest tests/Browser/CpIsco08RenderTest.php
 */

$collectionHandle = 'isco_08_render';

beforeEach(function () use ($collectionHandle) {
    $this->cpEmail = 'isco-08-render-'.uniqid().'@example.test';
    $this->cpPassword = 'isco-08-render-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make($collectionHandle);
    $collection->title('ISCO-08 Render');
    $collection->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::makeFromFields([
        'title' => [
            'type' => 'text',
            'display' => 'Title',
        ],
        'occupation_code' => [
            'type' => 'isco_08',
            'display' => 'Occupation Code',
        ],
    ]);
    $blueprint->setHandle($collectionHandle);
    $blueprint->setNamespace('collections.'.$collectionHandle);
    $blueprint->save();

    /** @var EntryModel $entry */
    $entry = Entry::make();
    $entry->collection($collectionHandle);
    $entry->slug('isco-08-render');
    $entry->data(['title' => 'ISCO-08 Render']);
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

it('renders an isco_08 field from the add-on, with its labels translated', function () use ($collectionHandle) {
    /** @var string $cpEmail */
    $cpEmail = $this->cpEmail;
    /** @var string $cpPassword */
    $cpPassword = $this->cpPassword;
    /** @var string $entryId */
    $entryId = $this->entryId;

    $page = loginToCp($cpEmail, $cpPassword)
        ->navigate('/cp/collections/'.$collectionHandle.'/entries/'.$entryId);

    waitToSee($page, 'Major Group', 60.0);

    // A missing add-on lang key would render as the key itself
    $rawKeys = $page->script('(() => document.body.innerText.includes("fieldtypes.isco_08"))()');

    expect($rawKeys)->toBeFalse();

    assertNoAppJavaScriptErrors($page);
});
