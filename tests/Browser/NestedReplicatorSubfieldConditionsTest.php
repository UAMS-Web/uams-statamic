<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;

/*
 | Browser proof for field conditions on a subfield inside a NESTED replicator (UAMS-Web/uams-statamic-archive#2133).
 |
 | ReplicatorSubfieldConditionsTest proves a condition inside a single-level set. This file
 | proves the second level: an outer `groups` set holding a nested `members` replicator whose
 | `guardian` subfield is shown only when its own row's `is_minor` toggle is on. No form
 | blueprint in the repository nests a replicator today, so the form is a fixture.
 |
 | Both levels auto-fill (outer min_sets 1, nested min_sets 2), so the nested sets are cloned
 | client-side from a template that sat inside the cloned outer set — the path the per-depth
 | index token exists for. The assertions run against the live post-Alpine DOM.
 |
 | @command vendor/bin/pest tests/Browser/NestedReplicatorSubfieldConditionsTest.php
 */

const NESTED_REPLICATOR_CONDITIONS_HANDLE = 'nested_replicator_conditions_browser';

$nrcFormsDir = '';
$nrcViewsDir = '';

/**
 * The fixture's public path, fixed so afterEach can delete it without a by-ref handle.
 */
function nestedReplicatorConditionsFixturePath(): string
{
    return public_path('__nested_replicator_conditions_fixture.html');
}

/**
 * Write the fixture page and return its public path.
 */
function writeNestedReplicatorConditionsFixture(string $viewsDir): string
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.NESTED_REPLICATOR_CONDITIONS_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}',
    );

    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

    $fixture = nestedReplicatorConditionsFixturePath();

    File::put($fixture, implode('', [
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
        '<title>Nested replicator conditions fixture</title>',
        '<script defer src="/vendor/statamic/frontend/js/helpers.js"></script><script defer src="/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
        '</head><body><h1>Nested replicator conditions fixture</h1>',
        (string) View::make('nested_replicator_conditions_browser::form')->render(),
        '</body></html>',
    ]));

    return $fixture;
}

beforeEach(function () use (&$nrcFormsDir, &$nrcViewsDir): void {
    $nrcFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($nrcFormsDir);
    Config::set('statamic.forms.forms', $nrcFormsDir);

    app(BlueprintRepository::class)->setFallback(
        'forms.'.NESTED_REPLICATOR_CONDITIONS_HANDLE,
        fn () => Blueprint::makeFromFields([
            'groups' => [
                'type' => 'replicator',
                'display' => 'Groups',
                'min_sets' => 1,
                'sets' => ['main' => ['sets' => ['group' => [
                    'display' => 'Group',
                    'fields' => [
                        // Unconditional, so a total Alpine failure stays distinguishable.
                        ['handle' => 'group_name', 'field' => ['type' => 'text', 'display' => 'Group Name Field']],
                        ['handle' => 'members', 'field' => [
                            'type' => 'replicator',
                            'display' => 'Members',
                            'min_sets' => 2,
                            'sets' => ['main' => ['sets' => ['member' => [
                                'display' => 'Member',
                                'fields' => [
                                    ['handle' => 'is_minor', 'field' => ['type' => 'toggle', 'display' => 'Member Is A Minor']],
                                    // Hidden until the toggle in its OWN nested row is on.
                                    ['handle' => 'guardian', 'field' => ['type' => 'text', 'display' => 'Member Guardian Name', 'if' => ['is_minor' => 'equals true']]],
                                ],
                            ]]]],
                        ]],
                    ],
                ]]]],
            ],
        ]),
    );

    $form = makeForm(NESTED_REPLICATOR_CONDITIONS_HANDLE);
    $form->title('Nested Replicator Conditions');
    $form->save();

    $nrcViewsDir = storage_path('framework/testing/nrc-views-'.uniqid('', true));
    File::ensureDirectoryExists($nrcViewsDir);
    View::addNamespace('nested_replicator_conditions_browser', $nrcViewsDir);
});

afterEach(function () use (&$nrcFormsDir, &$nrcViewsDir): void {
    if (File::exists($fixture = nestedReplicatorConditionsFixturePath())) {
        File::delete($fixture);
    }

    foreach ([$nrcFormsDir, $nrcViewsDir, storage_path('forms/'.NESTED_REPLICATOR_CONDITIONS_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('hides a second-level conditional subfield until its own row toggle is on', function () use (&$nrcViewsDir): void {
    writeNestedReplicatorConditionsFixture($nrcViewsDir);

    $page = visit('/__nested_replicator_conditions_fixture.html');

    $page->assertSee('Nested replicator conditions fixture');

    // Both levels rendered and Alpine started: the outer set and its nested sets exist.
    $page->assertSee('Group Name Field');
    $page->assertSee('Member Is A Minor');

    $page->assertDontSee('Member Guardian Name');

    assertNoAppJavaScriptErrors($page);
});

it('gives every nested set its own submission name', function () use (&$nrcViewsDir): void {
    writeNestedReplicatorConditionsFixture($nrcViewsDir);

    $page = visit('/__nested_replicator_conditions_fixture.html');

    // With a shared index token the outer clone consumed the nested placeholder, so both nested
    // rows posted under one name and would merge into a single row on submit.
    $names = mustBeString($page->script(
        '(() => JSON.stringify(Array.from(document.querySelectorAll(\'input[type=checkbox][name$="[is_minor]"]\')).map(el => el.getAttribute("name"))))()'
    ), 'the nested is_minor names');

    /** @var list<string> $decoded */
    $decoded = json_decode($names, true);

    expect($decoded)->toHaveCount(2)
        ->and(array_unique($decoded))->toHaveCount(2);

    foreach ($decoded as $name) {
        expect($name)->toMatch('/^groups\[\d+\]\[members\]\[\d+\]\[is_minor\]$/');
    }

    // Each nested name carries its enclosing outer set's index. With a shared token the nested
    // engine took the outer index itself as its token, so every nested clone rewrote the outer
    // index too, and the names disagreed with the set they sit in.
    $mismatches = mustBeString($page->script(
        '(() => JSON.stringify(Array.from(document.querySelectorAll(\'input[type=checkbox][name$="[is_minor]"]\')).filter((el) => {'
        .' const outerSet = el.closest(\'[data-replicator-handle="groups"] > [data-replicator-sets] > [data-replicator-set]\');'
        .' const outerType = outerSet.querySelector(\':scope > input[name$="[type]"]\').getAttribute("name");'
        .' return el.getAttribute("name").split("[members]")[0] !== outerType.split("[type]")[0];'
        .' }).map((el) => el.getAttribute("name"))))()'
    ), 'the nested names whose outer index disagrees with their set');

    expect($mismatches)->toBe('[]');

    assertNoAppJavaScriptErrors($page);
});

it('evaluates the second-level condition per nested row', function () use (&$nrcViewsDir): void {
    writeNestedReplicatorConditionsFixture($nrcViewsDir);

    $page = visit('/__nested_replicator_conditions_fixture.html');

    $firstRowName = mustBeString($page->script(
        '(() => { const el = document.querySelector(\'input[type=checkbox][name$="[is_minor]"]\'); return el ? el.getAttribute("name") : ""; })()'
    ), 'the first nested is_minor input name');

    expect($firstRowName)->not->toBe('');

    $page->check('input[type=checkbox][name="'.$firstRowName.'"]');

    // Exactly one guardian field appears, in the nested row whose toggle was flipped.
    $count = mustBeString($page->script(
        '(() => String(document.querySelectorAll(\'input[name$="[guardian]"]\').length))()'
    ), 'the guardian input count');

    expect($count)->toBe('1');

    $expectedGuardian = str_replace('[is_minor]', '[guardian]', $firstRowName);

    $inToggledRow = mustBeString($page->script(
        '(() => String(!!document.querySelector(\'input[name="'.$expectedGuardian.'"]\')))()'
    ), 'whether the toggled nested row carries the guardian field');

    expect($inToggledRow)->toBe('true');

    assertNoAppJavaScriptErrors($page);
});
