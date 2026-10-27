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
 | Do the OTHER stateful public-form components lose state to a conditional
 | rebuild, the way the signature field did (UAMS-Web/uams-statamic-archive#2232, generalizing UAMS-Web/uams-statamic-archive#2063)?
 |
 | UAMS-Web/uams-statamic-archive#2063 established the mechanism: `<template x-if="{{ show_field }}">` DESTROYS its
 | subtree when the expression turns falsy, so a field whose condition goes false and
 | true again is re-created from the original server render. It deliberately fixed one
 | field and did not generalize.
 |
 | This file measures rather than reasons. The ticket's exposure list was read off the
 | `x-data` declarations, which is exactly the reasoning UAMS-Web/uams-statamic-archive#2063 was filed to replace —
 | and in that case the reasoning was right about the destination and wrong about the
 | trigger. So each component is DRIVEN through a real teardown here, and what the
 | form would actually SUBMIT is read back.
 |
 | @command vendor/bin/pest tests/Browser/ConditionalRebuildStateSweepTest.php
 */

const REBUILD_SWEEP_HANDLE = 'conditional_rebuild_sweep';

$sweepFormsDir = '';
$sweepViewsDir = '';

/** The fixture's public path, fixed so afterEach can delete it. */
function rebuildSweepFixturePath(): string
{
    return public_path('__conditional_rebuild_sweep_fixture.html');
}

/** Write the fixture page and return its public path. */
function writeRebuildSweepFixture(string $viewsDir): string
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.REBUILD_SWEEP_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

    $fixture = rebuildSweepFixturePath();

    File::put($fixture, implode('', [
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
        '<title>Conditional rebuild sweep fixture</title>',
        '<script defer src="/vendor/statamic-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
        '</head><body><h1>Conditional rebuild sweep fixture</h1>',
        (string) View::make('rebuild_sweep::form')->render(),
        '</body></html>',
    ]));

    return $fixture;
}

beforeEach(function () use (&$sweepFormsDir, &$sweepViewsDir): void {
    $sweepFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($sweepFormsDir);
    Config::set('statamic.forms.forms', $sweepFormsDir);

    app(BlueprintRepository::class)->setFallback(
        'forms.'.REBUILD_SWEEP_HANDLE,
        fn () => Blueprint::makeFromFields([
            // Drives every conditional field below. Default satisfies them, so each
            // is present on load.
            'trigger' => ['type' => 'text', 'display' => 'Trigger', 'default' => 'Yes'],
            'my_list' => [
                'type' => 'list',
                'display' => 'My List',
                'if' => ['trigger' => 'equals Yes'],
            ],
            'my_rep' => [
                'type' => 'replicator',
                'display' => 'My Replicator',
                'if' => ['trigger' => 'equals Yes'],
                // A default set, because the partial loops over EXISTING rows: an
                // empty replicator renders none, and the field would read `absent`
                // for a reason that has nothing to do with the rebuild.
                'default' => [
                    ['type' => 'note', 'enabled' => true, 'body' => ''],
                ],
                'sets' => [
                    'main' => [
                        'sets' => [
                            'note' => [
                                'display' => 'Note',
                                'fields' => [
                                    ['handle' => 'body', 'field' => ['type' => 'text', 'display' => 'Body']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            // The control: derives entirely from its own checkboxes' checked state,
            // so a rebuild recomputes the same answer and nothing is lost.
            'my_checks' => [
                'type' => 'checkboxes',
                'display' => 'My Checks',
                'options' => ['a' => 'Alpha', 'b' => 'Beta'],
                'if' => ['trigger' => 'equals Yes'],
            ],
        ]),
    );

    $form = makeForm(REBUILD_SWEEP_HANDLE);
    $form->title('Conditional Rebuild Sweep');
    $form->save();

    $sweepViewsDir = storage_path('framework/testing/sweep-views-'.uniqid('', true));
    File::ensureDirectoryExists($sweepViewsDir);
    View::addNamespace('rebuild_sweep', $sweepViewsDir);
});

afterEach(function () use (&$sweepFormsDir, &$sweepViewsDir): void {
    if (File::exists($fixture = rebuildSweepFixturePath())) {
        File::delete($fixture);
    }

    foreach ([$sweepFormsDir, $sweepViewsDir, storage_path('forms/'.REBUILD_SWEEP_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

/**
 * Drive the trigger field, which is what turns each condition true or false.
 */
function setRebuildSweepTrigger(ArrayablePendingAwaitablePage|PendingAwaitablePage $page, string $value): void
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
 * What the form would actually submit for the list field, as a JSON array of the
 * row inputs' values. Returns `absent` when the field is not in the DOM at all.
 */
function readListSubmission(ArrayablePendingAwaitablePage|PendingAwaitablePage $page): string
{
    return mustBeString($page->script(
        '(() => {'
        .' const rows = document.querySelectorAll(\'input[name^="my_list["]\');'
        .' if (rows.length === 0) { return "absent"; }'
        .' return JSON.stringify(Array.from(rows).map(r => r.value)); })()',
    ), 'the list field submission read from the page');
}

it('records whether a list field keeps its rows across a conditional rebuild', function () use (&$sweepViewsDir): void {
    /** @var string $sweepViewsDir */
    $fixture = writeRebuildSweepFixture($sweepViewsDir);

    $page = visit('/'.basename($fixture));

    // Fill the seeded row and add a second, so the state under test is rows the
    // user created rather than what the server rendered.
    $page->script(
        '(() => {'
        .' const rows = document.querySelectorAll(\'input[name^="my_list["]\');'
        .' if (rows[0]) { rows[0].value = "first row";'
        .'   rows[0].dispatchEvent(new Event("input", { bubbles: true })); }'
        .' const add = Array.from(document.querySelectorAll("button"))'
        .'   .find(b => /Add/i.test(b.textContent));'
        .' if (add) { add.click(); }'
        .' return 1; })()'
    );

    $page->script(
        '(() => {'
        .' const rows = document.querySelectorAll(\'input[name^="my_list["]\');'
        .' if (rows[1]) { rows[1].value = "second row";'
        .'   rows[1].dispatchEvent(new Event("input", { bubbles: true })); }'
        .' return 1; })()'
    );

    $before = readListSubmission($page);

    // Drive the condition FALSE — the teardown — then TRUE again — the rebuild.
    setRebuildSweepTrigger($page, 'No');
    $during = readListSubmission($page);
    setRebuildSweepTrigger($page, 'Yes');
    $after = readListSubmission($page);

    // Recorded, not asserted as a defect: this test's job is to establish what
    // happens. The expectation below pins the measurement so a later change to
    // either the wrapper or the component cannot silently move it.
    expect($before)->toBe('["first row","second row"]')
        ->and($during)->toBe('absent');

    // The measurement the ticket asks for.
    expect($after)->toBe('["first row","second row"]', 'list rows did not survive the rebuild; observed: '.$after);
});

/**
 * What the form would submit for the replicator: every set's `body` subfield value,
 * as JSON. `absent` when the field is not in the DOM at all.
 */
function readReplicatorSubmission(ArrayablePendingAwaitablePage|PendingAwaitablePage $page): string
{
    return mustBeString($page->script(
        '(() => {'
        .' const sets = document.querySelectorAll("[data-replicator-set]");'
        .' if (sets.length === 0) { return "absent"; }'
        .' return JSON.stringify(Array.from(sets).map(s => {'
        .'   const i = s.querySelector(\'input[name$="[body]"]\');'
        .'   return i ? i.value : null; })); })()',
    ), 'the replicator submission read from the page');
}

/**
 * The replicator's structural restore (UAMS-Web/uams-statamic-archive#2253), inverted from the characterization
 * this file carried while UAMS-Web/uams-statamic-archive#2232 shipped only the value carries.
 *
 * `listField` and the signature field are VALUE carries: the element survives the
 * `<template x-if>` teardown and only its contents needed restoring. This one is
 * structural — a user-added set's markup is cloned in by `addSet()` at runtime and
 * the server render never contained it, so a carried value had nowhere to land.
 * `replicatorField` now snapshots each set's type and field values into the
 * surviving form scope and re-creates the missing tail on `init()`.
 */
it('restores a user-added replicator set across a conditional rebuild', function () use (&$sweepViewsDir): void {
    /** @var string $sweepViewsDir */
    $fixture = writeRebuildSweepFixture($sweepViewsDir);

    $page = visit('/'.basename($fixture));

    // Add a set the way a user does. A create form renders no rows — the partial
    // loops over EXISTING values — so the set under test has to be added, which is
    // also the state most at risk: a rebuild restores the server's markup, which
    // never had it.
    $page->script(
        '(() => {'
        .' const add = document.querySelector("[data-replicator-add-type]");'
        .' if (add) { add.click(); }'
        .' return 1; })()'
    );

    $page->script(
        '(() => {'
        .' const i = document.querySelector(\'[data-replicator-set] input[name$="[body]"]\');'
        .' if (i) { i.value = "typed body";'
        .'   i.dispatchEvent(new Event("input", { bubbles: true })); }'
        .' return 1; })()'
    );

    $before = readReplicatorSubmission($page);

    setRebuildSweepTrigger($page, 'No');
    $during = readReplicatorSubmission($page);
    setRebuildSweepTrigger($page, 'Yes');
    $after = readReplicatorSubmission($page);

    // Guard against a trivial pass: if the typing never landed, `before` and `after`
    // would both be null and compare equal while measuring nothing.
    // `toContain()` is variadic — a second string is another needle, not a message —
    // so the guard is written as an explicit boolean.
    expect(str_contains($before, 'typed body'))
        ->toBeTrue('the typed value never reached the DOM; before='.$before)
        ->and($during)->toBe('absent', 'the replicator was not torn down, so no rebuild occurred');

    // The set and its typed value both come back. Before UAMS-Web/uams-statamic-archive#2253 this read `absent`:
    // the rebuild restored the server's markup, which never contained the set.
    expect($after)->toBe($before, 'the replicator set did not survive the rebuild; before='.$before.' after='.$after);
});

it('confirms a checkboxes field derives from the DOM and needs no carry', function () use (&$sweepViewsDir): void {
    /** @var string $sweepViewsDir */
    $fixture = writeRebuildSweepFixture($sweepViewsDir);

    $page = visit('/'.basename($fixture));

    $read = static fn (): string => mustBeString($page->script(
        '(() => {'
        .' const b = document.querySelectorAll(\'input[name^="my_checks"]\');'
        .' if (b.length === 0) { return "absent"; }'
        .' return JSON.stringify(Array.from(b).filter(x => x.checked).map(x => x.value)); })()',
    ), 'the checkboxes submission read from the page');

    $page->script(
        '(() => {'
        .' const b = document.querySelector(\'input[name^="my_checks"][value="a"]\');'
        .' if (b && !b.checked) { b.click(); }'
        .' return 1; })()'
    );

    $before = $read();
    setRebuildSweepTrigger($page, 'No');
    setRebuildSweepTrigger($page, 'Yes');
    $after = $read();

    // The ticket asks for this to be CONFIRMED rather than assumed, so the sweep is
    // closed rather than partially done. A checked box is server-rendered state that
    // the rebuild restores from the same markup it came from — so the interesting
    // question is whether a USER's change survives, which is what this drives.
    expect($before)->not->toBe('absent')
        ->and($after)->toBe($before, 'checkboxes did not survive the rebuild; before='.$before.' after='.$after);
});

it('restores the number and order of sets, not only their contents', function () use (&$sweepViewsDir): void {
    /** @var string $sweepViewsDir */
    $fixture = writeRebuildSweepFixture($sweepViewsDir);

    $page = visit('/'.basename($fixture));

    // Three sets with distinguishable values, so a restore that returns the right
    // COUNT in the wrong ORDER is still a failure.
    $page->script(
        '(() => {'
        .' const add = document.querySelector("[data-replicator-add-type]");'
        // Two ADDED, plus the one the blueprint default renders, makes three.
        .' for (let i = 0; i < 2; i++) { if (add) { add.click(); } }'
        .' const rows = document.querySelectorAll(\'[data-replicator-set] input[name$="[body]"]\');'
        .' ["first", "second", "third"].forEach((v, i) => {'
        .'   if (rows[i]) { rows[i].value = v;'
        .'     rows[i].dispatchEvent(new Event("input", { bubbles: true })); } });'
        .' return 1; })()'
    );

    $before = readReplicatorSubmission($page);

    setRebuildSweepTrigger($page, 'No');
    setRebuildSweepTrigger($page, 'Yes');

    $after = readReplicatorSubmission($page);

    expect($before)->toBe('["first","second","third"]', 'the three sets were not set up; before='.$before)
        ->and($after)->toBe($before, 'count or order changed across the rebuild; after='.$after);
});

it('reindexes to sequential positions after a restore, so the submission has no gaps', function () use (&$sweepViewsDir): void {
    /** @var string $sweepViewsDir */
    $fixture = writeRebuildSweepFixture($sweepViewsDir);

    $page = visit('/'.basename($fixture));

    $page->script(
        '(() => {'
        .' const add = document.querySelector("[data-replicator-add-type]");'
        // One ADDED, plus the blueprint default, makes two.
        .' for (let i = 0; i < 1; i++) { if (add) { add.click(); } }'
        .' return 1; })()'
    );

    setRebuildSweepTrigger($page, 'No');
    setRebuildSweepTrigger($page, 'Yes');

    // `addSet()` stamps a monotonic index that is deliberately NOT the final
    // position — `reindex()` rewrites them on submit. A restore adds sets with
    // fresh indices, so this asserts the submitted names are still 0..n-1 with no
    // gaps and no duplicates rather than the restore's internal numbering.
    $indices = mustBeString($page->script(
        '(() => {'
        .' const form = document.querySelector("form");'
        .' if (form) { form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true })); }'
        .' const names = Array.from(document.querySelectorAll(\'[name^="my_rep["]\'))'
        .'   .map(i => (i.name.match(/^my_rep\\[([0-9]+)\\]/) || [])[1])'
        .'   .filter(v => v !== undefined);'
        .' return JSON.stringify(Array.from(new Set(names)).sort()); })()',
    ), 'the replicator submission indices read from the page');

    expect($indices)->toBe('["0","1"]', 'indices were not sequential after a restore; got '.$indices);
});
