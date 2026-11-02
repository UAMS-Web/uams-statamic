<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\PendingAwaitablePage;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;

/*
 | Does a conditional rebuild of a replicator restore the sets of a replicator NESTED inside it
 | (UAMS-Web/uams-statamic-archive#2612)?
 |
 | ConditionalRebuildStateSweepTest proves the single-level restore (UAMS-Web/uams-statamic-archive#2253):
 | `<template x-if>` destroys the replicator's subtree, and `replicatorField` re-creates the
 | missing sets from a snapshot kept on the form scope. NestedReplicatorSubfieldConditionsTest
 | proves nested sets render and submit (UAMS-Web/uams-statamic-archive#2133). Neither tears down a replicator that holds a
 | nested one, so this file does: an outer `groups` replicator shown only while `trigger` is
 | `Yes`, holding a nested `members` replicator. The nested replicator has a user-ADDED row
 | beyond its `min_sets`, because that row is the one the server render never contained.
 |
 | No form blueprint nests a replicator today (UAMS-Web/uams-statamic-archive#2133's Decision), so the form is a fixture.
 |
 | @command vendor/bin/pest tests/Browser/NestedReplicatorConditionalRebuildTest.php
 */

const NESTED_REPLICATOR_REBUILD_HANDLE = 'nested_replicator_rebuild_browser';

$nrrFormsDir = '';
$nrrViewsDir = '';

/**
 * The fixture's public path, fixed so afterEach can delete it without a by-ref handle.
 */
function nestedReplicatorRebuildFixturePath(): string
{
    return public_path('__nested_replicator_rebuild_fixture.html');
}

/**
 * Write the fixture page and return its public path.
 */
function writeNestedReplicatorRebuildFixture(string $viewsDir): string
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.NESTED_REPLICATOR_REBUILD_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

    $fixture = nestedReplicatorRebuildFixturePath();

    File::put($fixture, implode('', [
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
        '<title>Nested replicator rebuild fixture</title>',
        '<script defer src="/vendor/statamic/frontend/js/helpers.js"></script><script defer src="/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
        '</head><body><h1>Nested replicator rebuild fixture</h1>',
        (string) View::make('nested_replicator_rebuild_browser::form')->render(),
        '</body></html>',
    ]));

    return $fixture;
}

/**
 * Drive the trigger field, which is what shows and hides the outer replicator.
 */
function setNestedReplicatorRebuildTrigger(ArrayablePendingAwaitablePage|PendingAwaitablePage $page, string $value): void
{
    $page->script(
        '(() => {'
        .' const t = document.querySelector(\'input[name="trigger"]\');'
        .' if (t) { t.value = '.json_encode($value).';'
        .'   t.dispatchEvent(new Event("input", { bubbles: true })); }'
        .' return 1; })()'
    );
}

/**
 * What the form would submit for the nested rows, read structurally: for each outer set, its
 * `group_name` and the `member_name` of each nested set in DOM order. Returns `absent` when the
 * outer replicator is not in the DOM at all.
 */
function readNestedReplicatorSubmission(ArrayablePendingAwaitablePage|PendingAwaitablePage $page): string
{
    return mustBeString($page->script(
        '(() => {'
        .' const outer = document.querySelector(\'[data-replicator-handle="groups"]\');'
        .' if (!outer) { return "absent"; }'
        .' const sets = outer.querySelectorAll(":scope > [data-replicator-sets] > [data-replicator-set]");'
        .' return JSON.stringify(Array.from(sets).map((set) => {'
        .'   const name = set.querySelector(\'input[name$="[group_name]"]\');'
        .'   const nested = set.querySelector("[data-replicator-handle]");'
        .'   const rows = nested ? nested.querySelectorAll(":scope > [data-replicator-sets] > [data-replicator-set]") : [];'
        .'   return { group: name ? name.value : null, members: Array.from(rows).map((row) => {'
        .'     const m = row.querySelector(\'input[name$="[member_name]"]\');'
        .'     return m ? m.value : null; }) }; })); })()',
    ), 'the nested replicator submission read from the page');
}

/**
 * Register the fixture form's blueprint: a `trigger` text field and an outer `groups` replicator
 * shown while it is `Yes`, whose `group` set holds a nested `members` replicator.
 *
 * @param  array<string, mixed>  $groupsOverrides  Keys merged over the `groups` field config.
 * @param  array<string, mixed>  $extraSets  Further outer set types, keyed by handle.
 */
function useNestedReplicatorRebuildBlueprint(array $groupsOverrides = [], array $extraSets = []): void
{
    app(BlueprintRepository::class)->setFallback(
        'forms.'.NESTED_REPLICATOR_REBUILD_HANDLE,
        fn () => Blueprint::makeFromFields([
            // Drives the outer replicator's condition. The default satisfies it, so the
            // replicator is present on load.
            'trigger' => ['type' => 'text', 'display' => 'Trigger', 'default' => 'Yes'],
            'groups' => array_merge([
                'type' => 'replicator',
                'display' => 'Groups',
                'min_sets' => 1,
                'if' => ['trigger' => 'equals Yes'],
                'sets' => ['main' => ['sets' => array_merge(['group' => [
                    'display' => 'Group',
                    'fields' => [
                        ['handle' => 'group_name', 'field' => ['type' => 'text', 'display' => 'Group Name Field']],
                        ['handle' => 'members', 'field' => [
                            'type' => 'replicator',
                            'display' => 'Members',
                            'min_sets' => 1,
                            'sets' => ['main' => ['sets' => ['member' => [
                                'display' => 'Member',
                                'fields' => [
                                    ['handle' => 'member_name', 'field' => ['type' => 'text', 'display' => 'Member Name Field']],
                                ],
                            ]]]],
                        ]],
                    ],
                ]], $extraSets)]],
            ], $groupsOverrides),
        ]),
    );
}

/**
 * Flash old input for the fixture form, so its replicator rows are server-rendered the way a
 * re-render after a validation error renders them. `App\Fieldtypes\Replicator` renders existing
 * rows from old input or a bound value only; a blueprint `default` renders none.
 *
 * @param  array<string, mixed>  $input  The posted form input.
 */
function flashNestedReplicatorRebuildOldInput(array $input): void
{
    $session = app('session.store');
    request()->setLaravelSession($session);
    $session->flashInput($input);
}

beforeEach(function () use (&$nrrFormsDir, &$nrrViewsDir): void {
    $nrrFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($nrrFormsDir);
    Config::set('statamic.forms.forms', $nrrFormsDir);

    useNestedReplicatorRebuildBlueprint();

    $form = makeForm(NESTED_REPLICATOR_REBUILD_HANDLE);
    $form->title('Nested Replicator Rebuild');
    $form->save();

    $nrrViewsDir = storage_path('framework/testing/nrr-views-'.uniqid('', true));
    File::ensureDirectoryExists($nrrViewsDir);
    View::addNamespace('nested_replicator_rebuild_browser', $nrrViewsDir);
});

afterEach(function () use (&$nrrFormsDir, &$nrrViewsDir): void {
    if (File::exists($fixture = nestedReplicatorRebuildFixturePath())) {
        File::delete($fixture);
    }

    foreach ([$nrrFormsDir, $nrrViewsDir, storage_path('forms/'.NESTED_REPLICATOR_REBUILD_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

/**
 * Run a script against the first outer set's nested replicator: `nested` is its root and `add`
 * its own "Add Member" control.
 */
function driveNestedReplicator(ArrayablePendingAwaitablePage|PendingAwaitablePage $page, string $body): void
{
    $page->script(
        '(() => {'
        .' const nested = document.querySelector(\'[data-replicator-handle="groups"] [data-replicator-set] [data-replicator-handle]\');'
        .' const add = nested.querySelector(":scope > [data-replicator-add] [data-replicator-add-type]");'
        .$body
        .' return 1; })()'
    );
}

/**
 * Type a value into every nested `member_name` input, `member 1` onwards in DOM order, and into
 * the outer set's `group_name`.
 */
function typeNestedReplicatorValues(ArrayablePendingAwaitablePage|PendingAwaitablePage $page): void
{
    $page->script(
        '(() => {'
        .' const g = document.querySelector(\'input[name$="[group_name]"]\');'
        .' g.value = "group one"; g.dispatchEvent(new Event("input", { bubbles: true }));'
        .' document.querySelectorAll(\'input[name$="[member_name]"]\').forEach((m, i) => {'
        .'   m.value = "member " + (i + 1); m.dispatchEvent(new Event("input", { bubbles: true })); });'
        .' return 1; })()'
    );
}

it('restores nested sets and their values across a conditional rebuild', function () use (&$nrrViewsDir): void {
    writeNestedReplicatorRebuildFixture($nrrViewsDir);

    $page = visit('/__nested_replicator_rebuild_fixture.html');

    // The nested replicator auto-fills one row; the second is added the way a user adds one, so
    // it exists only because of a runtime clone.
    driveNestedReplicator($page, ' add.click();');
    typeNestedReplicatorValues($page);

    $before = readNestedReplicatorSubmission($page);

    setNestedReplicatorRebuildTrigger($page, 'No');
    $during = readNestedReplicatorSubmission($page);
    setNestedReplicatorRebuildTrigger($page, 'Yes');
    $after = readNestedReplicatorSubmission($page);

    // Guards against a trivial pass: the typing reached both nested rows, and the replicator
    // really was torn down.
    expect($before)->toBe('[{"group":"group one","members":["member 1","member 2"]}]')
        ->and($during)->toBe('absent', 'the outer replicator was not torn down, so no rebuild occurred');

    // Before UAMS-Web/uams-statamic-archive#2612 this read `members: ["member 1"]`. The added row was never rebuilt, and the
    // first row's value survived only because its rebuilt index happened to repeat.
    expect($after)->toBe($before, 'the nested sets did not survive the rebuild; after='.$after);

    assertNoAppJavaScriptErrors($page);
});

it('restores a nested set that was added but never typed into', function () use (&$nrrViewsDir): void {
    writeNestedReplicatorRebuildFixture($nrrViewsDir);

    $page = visit('/__nested_replicator_rebuild_fixture.html');

    // No input event follows the click, so only the click itself can have recorded the new row.
    driveNestedReplicator($page, ' add.click(); add.click();');

    $before = readNestedReplicatorSubmission($page);

    setNestedReplicatorRebuildTrigger($page, 'No');
    setNestedReplicatorRebuildTrigger($page, 'Yes');

    expect($before)->toBe('[{"group":"","members":["","",""]}]')
        ->and(readNestedReplicatorSubmission($page))->toBe($before);

    assertNoAppJavaScriptErrors($page);
});

it('restores the nested rows that remain after one is removed, by position', function () use (&$nrrViewsDir): void {
    writeNestedReplicatorRebuildFixture($nrrViewsDir);

    $page = visit('/__nested_replicator_rebuild_fixture.html');

    driveNestedReplicator($page, ' add.click(); add.click();');
    typeNestedReplicatorValues($page);

    // Remove the FIRST nested row, so the survivors no longer sit at the indices they were
    // cloned with and a restore keyed by index would put the values in the wrong rows.
    driveNestedReplicator($page, ' nested.querySelector(":scope > [data-replicator-sets] > [data-replicator-set] [data-replicator-remove]").click();');

    $before = readNestedReplicatorSubmission($page);

    setNestedReplicatorRebuildTrigger($page, 'No');
    setNestedReplicatorRebuildTrigger($page, 'Yes');

    expect($before)->toBe('[{"group":"group one","members":["member 2","member 3"]}]')
        ->and(readNestedReplicatorSubmission($page))->toBe($before);

    assertNoAppJavaScriptErrors($page);
});

it('submits sequential names at both levels after a restore', function () use (&$nrrViewsDir): void {
    writeNestedReplicatorRebuildFixture($nrrViewsDir);

    $page = visit('/__nested_replicator_rebuild_fixture.html');

    driveNestedReplicator($page, ' add.click();');
    typeNestedReplicatorValues($page);

    setNestedReplicatorRebuildTrigger($page, 'No');
    setNestedReplicatorRebuildTrigger($page, 'Yes');

    // Each engine reindexes its own level on submit, so the posted names are positions rather
    // than the indices the restore cloned with.
    $names = mustBeString($page->script(
        '(() => {'
        .' const form = document.querySelector("form");'
        .' form.addEventListener("submit", (e) => e.preventDefault());'
        .' form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));'
        .' return JSON.stringify(Array.from(document.querySelectorAll(\'input[name$="[member_name]"]\')).map(i => i.name + "=" + i.value)); })()',
    ), 'the submitted nested names');

    expect($names)->toBe('["groups[0][members][0][member_name]=member 1","groups[0][members][1][member_name]=member 2"]');

    assertNoAppJavaScriptErrors($page);
});

it('restores a nested set added inside a server-rendered outer set', function () use (&$nrrViewsDir): void {
    // Old input makes the outer set part of the server render. Its nested replicator initializes
    // after the outer engine's own init, which is the timing a restore has to wait out.
    flashNestedReplicatorRebuildOldInput(['trigger' => 'Yes', 'groups' => [[
        'type' => 'group',
        'enabled' => '1',
        'group_name' => '',
        'members' => [['type' => 'member', 'enabled' => '1', 'member_name' => '']],
    ]]]);

    writeNestedReplicatorRebuildFixture($nrrViewsDir);

    $page = visit('/__nested_replicator_rebuild_fixture.html');

    // Server-rendered rows carry their position as the index; a clone carries 1000 or more.
    $outerTypeName = mustBeString($page->script(
        '(() => { const i = document.querySelector(\'[data-replicator-handle="groups"] > [data-replicator-sets] > [data-replicator-set] > input[name$="[type]"]\'); return i ? i.name : ""; })()'
    ), 'the outer set type input name');

    expect($outerTypeName)->toBe('groups[0][type]');

    driveNestedReplicator($page, ' add.click();');
    typeNestedReplicatorValues($page);

    $before = readNestedReplicatorSubmission($page);

    setNestedReplicatorRebuildTrigger($page, 'No');
    setNestedReplicatorRebuildTrigger($page, 'Yes');

    expect($before)->toBe('[{"group":"group one","members":["member 1","member 2"]}]')
        ->and(readNestedReplicatorSubmission($page))->toBe($before);

    assertNoAppJavaScriptErrors($page);
});

it('never gives a server-rendered set another set type after one is removed', function () use (&$nrrViewsDir): void {
    // Two server-rendered sets of different types. Removing the first leaves a one-entry
    // snapshot, while the rebuild re-renders both, so a restore that writes entries into present
    // sets by position would stamp the second set's type onto the first set's fields.
    useNestedReplicatorRebuildBlueprint(['min_sets' => 0], ['note' => ['display' => 'Note', 'fields' => [
        ['handle' => 'note_body', 'field' => ['type' => 'text', 'display' => 'Note Body Field']],
    ]]]);

    flashNestedReplicatorRebuildOldInput(['trigger' => 'Yes', 'groups' => [
        ['type' => 'note', 'enabled' => '1', 'note_body' => 'a note'],
        ['type' => 'group', 'enabled' => '1', 'group_name' => 'a group', 'members' => [
            ['type' => 'member', 'enabled' => '1', 'member_name' => 'a member'],
        ]],
    ]]);

    writeNestedReplicatorRebuildFixture($nrrViewsDir);

    $page = visit('/__nested_replicator_rebuild_fixture.html');

    expect(readNestedReplicatorSubmission($page))->toBe('[{"group":null,"members":[]},{"group":"a group","members":["a member"]}]');

    $page->script(
        '(() => {'
        .' document.querySelector(\'[data-replicator-handle="groups"] > [data-replicator-sets] > [data-replicator-set] > [data-replicator-remove]\').click();'
        .' return 1; })()'
    );

    setNestedReplicatorRebuildTrigger($page, 'No');
    setNestedReplicatorRebuildTrigger($page, 'Yes');

    // Each outer set's posted type, beside the type its own fields belong to.
    $pairs = mustBeString($page->script(
        '(() => JSON.stringify(Array.from(document.querySelectorAll(\'[data-replicator-handle="groups"] > [data-replicator-sets] > [data-replicator-set]\')).map((set) => {'
        .' const type = set.querySelector(\':scope > input[name$="[type]"]\').value;'
        .' const fieldsType = set.querySelector(\'input[name$="[note_body]"]\') ? "note" : "group";'
        .' return type + "/" + fieldsType; })))()'
    ), 'each outer set type beside its fields');

    /** @var list<string> $decoded */
    $decoded = json_decode($pairs, true);

    expect($decoded)->not->toBeEmpty();

    foreach ($decoded as $pair) {
        [$type, $fieldsType] = explode('/', $pair);
        expect($type)->toBe($fieldsType, 'a set posts a type its fields do not belong to: '.$pairs);
    }

    assertNoAppJavaScriptErrors($page);
});
