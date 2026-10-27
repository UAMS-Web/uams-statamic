<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Statamic\Auth\User;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form;
use Statamic\Facades\Stache;

/*
 * Pins where the Control Panel DISPLAYS a form blueprint's `instructions`: on a SECTION, yes;
 * on a TAB, no.
 *
 * `UAMS-Web/wordpress-importer#831` writes a trace — a note naming the fields an import had to
 * drop — into blueprint metadata rather than into a field, precisely so it cannot reach the
 * public form: the renderer iterates fields and never reads tab or section metadata. That
 * makes the Control Panel the note's ONLY audience, so WHERE the note is written decides
 * whether it has a reader at all. `UAMS-Web/wordpress-importer#857` and `#859` are built on
 * the answer below — qualified because both numbers also exist in THIS repo, on unrelated
 * blueprint and test tickets that read as plausible destinations.
 *
 * The importer cannot check this for itself. It can save a blueprint, but it has no Control
 * Panel to open one in, so the half that matters is unreachable from that package. This test
 * exists because that measurement has to live somewhere it will fail when it stops being true.
 *
 * All three arms are asserted, and the control is the load-bearing one: a run where the note
 * appears on the control form is measuring the note's presence in the DOM rather than the
 * feature, and would report `section=YES` whatever the CP did.
 *
 * @command  vendor/bin/pest tests/Browser/CpFormBlueprintInstructionsTest.php
 */

$note = 'IMPORT TRACE one field could not be imported';

beforeEach(function () use ($note) {
    $this->cpEmail = 'instructions-'.uniqid().'@example.test';
    $this->cpPassword = 'instructions-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    foreach (['instructions_none' => null, 'instructions_tab' => 'tab', 'instructions_section' => 'section'] as $handle => $where) {
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = Blueprint::makeFromFields([
            'name' => ['type' => 'text', 'display' => 'Name'],
        ]);
        $blueprint->setHandle($handle);
        $blueprint->setNamespace('forms');

        if ($where !== null) {
            // `makeFromFields()` yields a flat field map that Statamic wraps in a
            // tabs -> sections -> fields skeleton, so the note lands in metadata one
            // level above the fields rather than beside them. Written with `Arr::set`
            // so the path is stated once and reads as the thing being measured.
            // Not `data_set`: it takes `mixed &$target` and widens the variable it is
            // given, which then fails the `array` parameter of `setContents()`.
            $contents = $blueprint->contents();
            $path = $where === 'tab'
                ? 'tabs.main.instructions'
                : 'tabs.main.sections.0.instructions';
            Arr::set($contents, $path, $note);
            $blueprint->setContents($contents);
        }

        $blueprint->save();

        /** @var Statamic\Forms\Form $form */
        $form = Form::make($handle);
        $form->title(ucfirst($handle));
        $form->save();
    }

    Stache::refresh();
});

afterEach(function () {
    foreach (['instructions_none', 'instructions_tab', 'instructions_section'] as $handle) {
        /** @var Statamic\Forms\Form|null $form */
        $form = Form::find($handle);
        $form?->delete();

        Blueprint::find('forms.'.$handle)?->delete();
    }

    /** @var User|null $cpUser */
    $cpUser = $this->cpUser;
    $cpUser?->delete();
    Stache::refresh();
});

/*
 * One login, one page, three navigations. Two `loginToCp` calls in a single test fail at the
 * second `fill()`, and a dropped CP session lands on `/cp/auth/login` — where the note is
 * legitimately absent, so a session failure and a real regression produce the identical
 * result. `waitToSee($page, 'Name')` is what separates them: the field label only renders on
 * the editor, so reaching it proves the navigation arrived somewhere authenticated.
 */
it('shows blueprint instructions on a section and not on a tab', function () use ($note) {
    $page = loginToCp($this->cpEmail, $this->cpPassword);
    $found = [];

    foreach (['none', 'tab', 'section'] as $arm) {
        $page->navigate('/cp/fields/blueprints/forms/instructions_'.$arm.'/edit');
        waitToSee($page, 'Name');
        $found[$arm] = str_contains($page->content(), $note);
    }

    assertNoAppJavaScriptErrors($page);

    fwrite(STDERR, sprintf(
        "RESULT blueprint-editor  control=%s tab=%s section=%s\n",
        $found['none'] ? 'YES' : 'no',
        $found['tab'] ? 'YES' : 'no',
        $found['section'] ? 'YES' : 'no'
    ));

    // Control: a blueprint carrying no instructions must not show the note. If this fails the
    // other two arms mean nothing, because the note is reaching the page some other way.
    expect($found['none'])->toBeFalse();

    // A note written to TAB metadata has no reader in the CP.
    expect($found['tab'])->toBeFalse();

    // A note written to SECTION metadata does. This is what UAMS-Web/uams-statamic-archive#831 depends on.
    expect($found['section'])->toBeTrue();
});
