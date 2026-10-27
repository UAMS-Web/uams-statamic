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
 | Three gaps in how `replicatorField` restores a public-form replicator after a conditional
 | teardown (UAMS-Web/uams-statamic-archive#2687), each kept out of UAMS-Web/uams-statamic-archive#2612's scope and each driven through a real rebuild here:
 |
 | - radio, checkbox and toggle subfields, whose restored state has to reach `x-model`, which
 |   listens for `change` on those inputs rather than `input`;
 | - a server-rendered set the visitor removed, which the rebuild renders again;
 | - a nested replicator behind a condition on a sibling field in its own set, which is not in
 |   the DOM when its set is restored.
 |
 | The outer `people` replicator is shown only while `trigger` is `Yes`, so setting it to `No`
 | and back tears the whole replicator down and rebuilds it. No form blueprint reaches any of
 | the three today, so the form is a fixture.
 |
 | @command vendor/bin/pest tests/Browser/ReplicatorRebuildStateGapsTest.php
 */

const REPLICATOR_REBUILD_GAPS_HANDLE = 'replicator_rebuild_gaps_browser';

$rrgFormsDir = '';
$rrgViewsDir = '';

/**
 * The fixture's public path, fixed so afterEach can delete it without a by-ref handle.
 */
function replicatorRebuildGapsFixturePath(): string
{
    return public_path('__replicator_rebuild_gaps_fixture.html');
}

/**
 * Write the fixture page and return its public path.
 */
function writeReplicatorRebuildGapsFixture(string $viewsDir): string
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.REPLICATOR_REBUILD_GAPS_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

    $fixture = replicatorRebuildGapsFixturePath();

    File::put($fixture, implode('', [
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
        '<title>Replicator rebuild gaps fixture</title>',
        '<script defer src="/vendor/statamic-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
        '</head><body><h1>Replicator rebuild gaps fixture</h1>',
        (string) View::make('replicator_rebuild_gaps_browser::form')->render(),
        '</body></html>',
    ]));

    return $fixture;
}

/**
 * Register the fixture form's blueprint: a `trigger` text field and a `people` replicator shown
 * while it is `Yes`. Its `person` set holds a radio and a select, each with a text field
 * conditioned on it, a checkbox group, and a toggle that a nested `members` replicator is
 * conditioned on.
 *
 * @param  array<string, mixed>  $peopleOverrides  Keys merged over the `people` field config.
 */
function useReplicatorRebuildGapsBlueprint(array $peopleOverrides = []): void
{
    app(BlueprintRepository::class)->setFallback(
        'forms.'.REPLICATOR_REBUILD_GAPS_HANDLE,
        fn () => Blueprint::makeFromFields([
            // Drives the replicator's condition. The default satisfies it, so the replicator is
            // present on load.
            'trigger' => ['type' => 'text', 'display' => 'Trigger', 'default' => 'Yes'],
            'people' => array_merge([
                'type' => 'replicator',
                'display' => 'People',
                'min_sets' => 1,
                'if' => ['trigger' => 'equals Yes'],
                'sets' => ['main' => ['sets' => ['person' => [
                    'display' => 'Person',
                    'fields' => [
                        ['handle' => 'person_name', 'field' => ['type' => 'text', 'display' => 'Person Name Field']],
                        ['handle' => 'color', 'field' => [
                            'type' => 'radio',
                            'display' => 'Color',
                            'options' => ['red' => 'Red', 'green' => 'Green', 'blue' => 'Blue'],
                        ]],
                        // Shown only while the radio in its own set is `green`, so it is present
                        // exactly when the radio's restored choice reached the set's state.
                        ['handle' => 'shade', 'field' => [
                            'type' => 'text',
                            'display' => 'Shade',
                            'if' => ['color' => 'equals green'],
                        ]],
                        ['handle' => 'size', 'field' => [
                            'type' => 'select',
                            'display' => 'Size',
                            'options' => ['small' => 'Small', 'large' => 'Large'],
                        ]],
                        // Shown only while the select in its own set is `large`, for the same
                        // reason as `shade`.
                        ['handle' => 'size_note', 'field' => [
                            'type' => 'text',
                            'display' => 'Size Note',
                            'if' => ['size' => 'equals large'],
                        ]],
                        ['handle' => 'pets', 'field' => [
                            'type' => 'checkboxes',
                            'display' => 'Pets',
                            'options' => ['cat' => 'Cat', 'dog' => 'Dog', 'fish' => 'Fish'],
                        ]],
                        ['handle' => 'has_members', 'field' => ['type' => 'toggle', 'display' => 'Has Members']],
                        ['handle' => 'members', 'field' => [
                            'type' => 'replicator',
                            'display' => 'Members',
                            'min_sets' => 1,
                            'if' => ['has_members' => 'equals true'],
                            'sets' => ['main' => ['sets' => ['member' => [
                                'display' => 'Member',
                                'fields' => [
                                    ['handle' => 'member_name', 'field' => ['type' => 'text', 'display' => 'Member Name Field']],
                                ],
                            ]]]],
                        ]],
                    ],
                ]]]],
            ], $peopleOverrides),
        ]),
    );
}

/**
 * Flash old input for the fixture form, so its rows are server-rendered the way a re-render after
 * a validation error renders them.
 *
 * @param  array<string, mixed>  $input  The posted form input.
 */
function flashReplicatorRebuildGapsOldInput(array $input): void
{
    $session = app('session.store');
    request()->setLaravelSession($session);
    $session->flashInput($input);
}

/**
 * Tear the `people` replicator down and rebuild it, by driving its condition off and on.
 * Returns what was on the page while it was off, so a test can prove the teardown happened.
 */
function rebuildReplicatorRebuildGapsPeople(ArrayablePendingAwaitablePage|PendingAwaitablePage $page): string
{
    $set = fn (string $value) => $page->script(
        '(() => {'
        .' const t = document.querySelector(\'input[name="trigger"]\');'
        .' t.value = '.json_encode($value).'; t.dispatchEvent(new Event("input", { bubbles: true }));'
        .' return 1; })()'
    );

    $set('No');
    $during = readReplicatorRebuildGapsState($page);
    $set('Yes');

    return $during;
}

/**
 * Run a script with `sets` bound to the outer replicator's sets, in DOM order.
 */
function driveReplicatorRebuildGaps(ArrayablePendingAwaitablePage|PendingAwaitablePage $page, string $body): void
{
    $page->script(
        '(() => {'
        .' const sets = Array.from(document.querySelectorAll(\'[data-replicator-handle="people"] > [data-replicator-sets] > [data-replicator-set]\'));'
        .' const click = (el) => el.click();'
        .$body
        .' return 1; })()'
    );
}

/**
 * What each outer set would submit, beside what its own Alpine scope holds, read structurally.
 * `absent` when the replicator is not in the DOM at all.
 *
 * The DOM half is what the form posts; the scope half is what the set's conditions read. The
 * defect this file pins is the two disagreeing, so both are read.
 */
function readReplicatorRebuildGapsState(ArrayablePendingAwaitablePage|PendingAwaitablePage $page): string
{
    return mustBeString($page->script(
        '(() => {'
        .' const outer = document.querySelector(\'[data-replicator-handle="people"]\');'
        .' if (!outer) { return "absent"; }'
        .' const sets = outer.querySelectorAll(":scope > [data-replicator-sets] > [data-replicator-set]");'
        .' return JSON.stringify(Array.from(sets).map((set) => {'
        .'   const own = (selector) => Array.from(set.querySelectorAll(selector))'
        .'     .filter((el) => el.closest("[data-replicator-set]") === set);'
        .'   const scope = Alpine.$data(set);'
        .'   const name = own(\'input[name$="[person_name]"]\')[0];'
        .'   const color = own(\'input[type="radio"][name$="[color]"]:checked\').map((r) => r.value);'
        .'   const size = own(\'select[name$="[size]"]\')[0];'
        .'   const pets = own(\'input[type="checkbox"][name$="[pets][]"]:checked\').map((c) => c.value);'
        .'   const toggle = own(\'input[type="checkbox"][name$="[has_members]"]\')[0];'
        .'   const nested = set.querySelector("[data-replicator-handle]");'
        .'   const rows = nested ? nested.querySelectorAll(":scope > [data-replicator-sets] > [data-replicator-set]") : null;'
        .'   return {'
        .'     name: name ? name.value : null,'
        .'     color: color, colorState: scope.color ?? null,'
        .'     shade: own(\'input[name$="[shade]"]\').length > 0,'
        .'     size: size ? size.value : null, sizeState: scope.size ?? null,'
        .'     sizeNote: own(\'input[name$="[size_note]"]\').length > 0,'
        .'     pets: pets, petsState: Array.isArray(scope.pets) ? [...scope.pets].sort() : (scope.pets ?? null),'
        .'     toggle: toggle ? toggle.checked : null, toggleState: scope.has_members ?? null,'
        .'     members: rows ? Array.from(rows).map((row) => {'
        .'       const m = row.querySelector(\'input[name$="[member_name]"]\');'
        .'       return m ? m.value : null; }) : null,'
        .'   }; })); })()',
    ), 'the replicator state read from the page');
}

beforeEach(function () use (&$rrgFormsDir, &$rrgViewsDir): void {
    $rrgFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($rrgFormsDir);
    Config::set('statamic.forms.forms', $rrgFormsDir);

    useReplicatorRebuildGapsBlueprint();

    $form = makeForm(REPLICATOR_REBUILD_GAPS_HANDLE);
    $form->title('Replicator Rebuild Gaps');
    $form->save();

    $rrgViewsDir = storage_path('framework/testing/rrg-views-'.uniqid('', true));
    File::ensureDirectoryExists($rrgViewsDir);
    View::addNamespace('replicator_rebuild_gaps_browser', $rrgViewsDir);
});

afterEach(function () use (&$rrgFormsDir, &$rrgViewsDir): void {
    if (File::exists($fixture = replicatorRebuildGapsFixturePath())) {
        File::delete($fixture);
    }

    foreach ([$rrgFormsDir, $rrgViewsDir, storage_path('forms/'.REPLICATOR_REBUILD_GAPS_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('restores radio, select, checkbox and toggle choices into both the DOM and the set state', function () use (&$rrgViewsDir): void {
    writeReplicatorRebuildGapsFixture($rrgViewsDir);

    $page = visit('/__replicator_rebuild_gaps_fixture.html');

    // The first set is the min_sets auto-fill; the second is added the way a user adds one. Each
    // gets different choices, so a restore that shares one key per name shows up as a mix-up.
    driveReplicatorRebuildGaps($page, ' click(document.querySelector(\'[data-replicator-handle="people"] > [data-replicator-add] [data-replicator-add-type]\'));');
    driveReplicatorRebuildGaps(
        $page,
        ' const pick = (set, selector) => click(Array.from(set.querySelectorAll(selector)).find((el) => el.closest("[data-replicator-set]") === set));'
        .' pick(sets[0], \'input[type="radio"][value="green"]\');'
        .' pick(sets[0], \'input[type="checkbox"][value="cat"]\');'
        .' pick(sets[0], \'input[type="checkbox"][value="fish"]\');'
        .' pick(sets[1], \'input[type="radio"][value="blue"]\');'
        .' pick(sets[1], \'input[type="checkbox"][value="dog"]\');'
        .' pick(sets[1], \'input[type="checkbox"][name$="[has_members]"]\');'
        .' const size = sets[0].querySelector(\'select[name$="[size]"]\');'
        .' size.value = "large"; size.dispatchEvent(new Event("change", { bubbles: true }));'
        .' size.dispatchEvent(new Event("input", { bubbles: true }));',
    );

    $before = readReplicatorRebuildGapsState($page);
    $during = rebuildReplicatorRebuildGapsPeople($page);
    $after = readReplicatorRebuildGapsState($page);

    // Guards against a trivial pass: the clicks reached both sets, and the replicator really was
    // torn down.
    // The first set's toggle is never touched, so its state stays the null it was seeded with.
    expect($before)->toBe('[{"name":"","color":["green"],"colorState":"green","shade":true,"size":"large","sizeState":"large","sizeNote":true,"pets":["cat","fish"],"petsState":["cat","fish"],"toggle":false,"toggleState":null,"members":null},'
        .'{"name":"","color":["blue"],"colorState":"blue","shade":false,"size":"","sizeState":null,"sizeNote":false,"pets":["dog"],"petsState":["dog"],"toggle":true,"toggleState":true,"members":[""]}]')
        ->and($during)->toBe('absent');

    expect($after)->toBe($before);

    assertNoAppJavaScriptErrors($page);
});

it('keeps a removed server-rendered set removed across a rebuild', function () use (&$rrgViewsDir): void {
    flashReplicatorRebuildGapsOldInput(['trigger' => 'Yes', 'people' => [
        ['type' => 'person', 'enabled' => '1', 'person_name' => 'first'],
        ['type' => 'person', 'enabled' => '1', 'person_name' => 'second'],
        ['type' => 'person', 'enabled' => '1', 'person_name' => 'third'],
    ]]);

    writeReplicatorRebuildGapsFixture($rrgViewsDir);

    $page = visit('/__replicator_rebuild_gaps_fixture.html');

    // Remove the MIDDLE server-rendered set, so the survivors are not a prefix of the render and
    // a restore that trims by count alone would keep the wrong ones.
    driveReplicatorRebuildGaps($page, ' click(sets[1].querySelector(":scope > [data-replicator-remove]"));');

    $names = fn (): string => mustBeString($page->script(
        '(() => { const outer = document.querySelector(\'[data-replicator-handle="people"]\');'
        .' if (!outer) { return "absent"; }'
        .' return JSON.stringify(Array.from(outer.querySelectorAll(\':scope > [data-replicator-sets] > [data-replicator-set] input[name$="[person_name]"]\')).map((i) => i.value)); })()'
    ), 'the person names on the page');

    $before = $names();

    expect($before)->toBe('["first","third"]');

    rebuildReplicatorRebuildGapsPeople($page);

    expect($names())->toBe($before);

    assertNoAppJavaScriptErrors($page);
});

it('keeps every server-rendered set removed when the visitor removed them all', function () use (&$rrgViewsDir): void {
    // `min_sets: 0`, so every set can be removed and the mirrored snapshot is an empty list,
    // which is also what the form scope holds on a first load with no rows.
    useReplicatorRebuildGapsBlueprint(['min_sets' => 0]);

    flashReplicatorRebuildGapsOldInput(['trigger' => 'Yes', 'people' => [
        ['type' => 'person', 'enabled' => '1', 'person_name' => 'first'],
        ['type' => 'person', 'enabled' => '1', 'person_name' => 'second'],
    ]]);

    writeReplicatorRebuildGapsFixture($rrgViewsDir);

    $page = visit('/__replicator_rebuild_gaps_fixture.html');

    $count = fn (): string => mustBeString($page->script(
        '(() => { const outer = document.querySelector(\'[data-replicator-handle="people"]\');'
        .' return outer ? String(outer.querySelectorAll(":scope > [data-replicator-sets] > [data-replicator-set]").length) : "absent"; })()'
    ), 'the number of sets on the page');

    expect($count())->toBe('2');

    driveReplicatorRebuildGaps($page, ' sets.forEach((set) => click(set.querySelector(":scope > [data-replicator-remove]")));');

    expect($count())->toBe('0');

    rebuildReplicatorRebuildGapsPeople($page);

    expect($count())->toBe('0');

    assertNoAppJavaScriptErrors($page);
});

it('restores a nested replicator hidden behind a sibling condition in its own set', function () use (&$rrgViewsDir): void {
    writeReplicatorRebuildGapsFixture($rrgViewsDir);

    $page = visit('/__replicator_rebuild_gaps_fixture.html');

    // Turn the toggle on so the nested replicator renders, add a second nested row, and type into
    // both, so the rows exist only because of runtime clones and typing.
    driveReplicatorRebuildGaps($page, ' click(sets[0].querySelector(\'input[type="checkbox"][name$="[has_members]"]\'));');
    driveReplicatorRebuildGaps(
        $page,
        ' const nested = sets[0].querySelector("[data-replicator-handle]");'
        .' click(nested.querySelector(":scope > [data-replicator-add] [data-replicator-add-type]"));'
        .' nested.querySelectorAll(\'input[name$="[member_name]"]\').forEach((m, i) => {'
        .'   m.value = "member " + (i + 1); m.dispatchEvent(new Event("input", { bubbles: true })); });',
    );

    $before = readReplicatorRebuildGapsState($page);

    expect($before)->toContain('"toggle":true,"toggleState":true,"members":["member 1","member 2"]');

    rebuildReplicatorRebuildGapsPeople($page);

    expect(readReplicatorRebuildGapsState($page))->toBe($before);

    assertNoAppJavaScriptErrors($page);
});

it('restores a nested replicator its sibling condition hid and showed again', function () use (&$rrgViewsDir): void {
    writeReplicatorRebuildGapsFixture($rrgViewsDir);

    $page = visit('/__replicator_rebuild_gaps_fixture.html');

    $toggle = ' click(sets[0].querySelector(\'input[type="checkbox"][name$="[has_members]"]\'));';

    driveReplicatorRebuildGaps($page, $toggle);
    driveReplicatorRebuildGaps(
        $page,
        ' const nested = sets[0].querySelector("[data-replicator-handle]");'
        .' click(nested.querySelector(":scope > [data-replicator-add] [data-replicator-add-type]"));'
        .' nested.querySelectorAll(\'input[name$="[member_name]"]\').forEach((m, i) => {'
        .'   m.value = "member " + (i + 1); m.dispatchEvent(new Event("input", { bubbles: true })); });',
    );

    $before = readReplicatorRebuildGapsState($page);

    // Hide the nested replicator with its own sibling toggle, type elsewhere so the outer
    // snapshot is taken while it is gone, then show it again. Only the nested replicator is torn
    // down; the outer one is never rebuilt.
    driveReplicatorRebuildGaps($page, $toggle);

    $hidden = readReplicatorRebuildGapsState($page);

    driveReplicatorRebuildGaps(
        $page,
        ' const n = sets[0].querySelector(\'input[name$="[person_name]"]\');'
        .' n.value = "typed while hidden"; n.dispatchEvent(new Event("input", { bubbles: true }));',
    );
    driveReplicatorRebuildGaps($page, $toggle);

    expect($hidden)->toContain('"toggle":false,"toggleState":false,"members":null')
        ->and(readReplicatorRebuildGapsState($page))->toBe(str_replace('"name":""', '"name":"typed while hidden"', $before));

    assertNoAppJavaScriptErrors($page);
});

it('restores a set\'s other subfields when a file was chosen in it', function () use (&$rrgViewsDir): void {
    // UAMS-Web/uams-statamic-archive#2874. A `files` subfield ahead of a text one: writing the chosen file's value back threw,
    // which ended the set's restore at the file input and left the text field blank.
    useReplicatorRebuildGapsBlueprint([
        'sets' => ['main' => ['sets' => ['person' => [
            'display' => 'Person',
            'fields' => [
                ['handle' => 'attachment', 'field' => ['type' => 'files', 'display' => 'Attachment', 'max_files' => 1]],
                ['handle' => 'person_name', 'field' => ['type' => 'text', 'display' => 'Person Name Field']],
            ],
        ]]]],
    ]);

    writeReplicatorRebuildGapsFixture($rrgViewsDir);

    $page = visit('/__replicator_rebuild_gaps_fixture.html');

    // Script cannot type a path into a file input, but it can hand one a file, which is what
    // choosing one does.
    driveReplicatorRebuildGaps(
        $page,
        ' const file = sets[0].querySelector(\'input[type="file"][name$="[attachment]"]\');'
        .' const picked = new DataTransfer(); picked.items.add(new File(["x"], "a.pdf", { type: "application/pdf" }));'
        .' file.files = picked.files; file.dispatchEvent(new Event("change", { bubbles: true }));'
        .' const n = sets[0].querySelector(\'input[name$="[person_name]"]\');'
        .' n.value = "Ada"; n.dispatchEvent(new Event("input", { bubbles: true }));',
    );

    $read = fn (): string => mustBeString($page->script(
        '(() => { const outer = document.querySelector(\'[data-replicator-handle="people"]\');'
        .' if (!outer) { return "absent"; }'
        .' const set = outer.querySelector(":scope > [data-replicator-sets] > [data-replicator-set]");'
        .' const file = set.querySelector(\'input[type="file"]\');'
        .' const order = Array.from(set.querySelectorAll("input[name]")).map((i) => i.type).filter((t) => t === "file" || t === "text");'
        .' return JSON.stringify({ order: order, file: file ? file.files.length : null,'
        .'   name: set.querySelector(\'input[name$="[person_name]"]\').value }); })()'
    ), 'the set read from the page');

    // Guards against a trivial pass: the file input comes first and really holds a file, and
    // the replicator really was torn down.
    expect($read())->toBe('{"order":["file","text"],"file":1,"name":"Ada"}')
        ->and(rebuildReplicatorRebuildGapsPeople($page))->toBe('absent');

    // The chosen file itself cannot come back: a browser never lets script set a file input's
    // files. Everything after it in the set does.
    expect($read())->toBe('{"order":["file","text"],"file":0,"name":"Ada"}');

    assertNoAppJavaScriptErrors($page);
});
