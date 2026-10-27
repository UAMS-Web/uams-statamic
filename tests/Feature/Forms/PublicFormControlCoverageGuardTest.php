<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guards the two mistakes that made a public-form control silently dead (UAMS-Web/uams-statamic-archive#2060).
 *
 * A control that does not bind under the `@alpinejs/csp` build fails with no visual
 * difference in the served document, so neither a rendered-markup assertion nor a human
 * skim catches it. These are mechanical checks for the shapes that caused it, so a new
 * control cannot reintroduce either without the suite going red.
 *
 * @command  php artisan test --compact tests/Feature/Forms/PublicFormControlCoverageGuardTest.php
 */
uses(TestCase::class);

/**
 * The public-form view tree this guard governs.
 */
const PUBLIC_FORM_VIEW_DIR = 'resources/views/vendor/statamic/forms';

/**
 * The browser suite that must exercise each control's behavior.
 */
const PUBLIC_FORM_CONTROL_SUITE = 'tests/Browser/PublicFormControlsTest.php';

/**
 * Every partial under the public-form view tree.
 *
 * @return list<string> Absolute paths.
 */
function publicFormPartials(): array
{
    $dir = base_path(PUBLIC_FORM_VIEW_DIR);

    return File::isDirectory($dir)
        ? array_values(array_map(fn (SplFileInfo $f): string => $f->getPathname(), File::allFiles($dir)))
        : [];
}

it('has partials to guard', function (): void {
    // A guard over an empty set is not a guard. If the view tree moves, this fails first
    // and says so, rather than the checks below passing vacuously.
    expect(publicFormPartials())->not->toBeEmpty();
});

it('declares no inline x-data object literal on a public form', function (): void {
    // ---
    // The defect: `x-data='{ items: [], add() {...} }'` is a CSP PARSE ERROR — the parser
    // accepts property/value pairs, and method shorthand yields
    //   CSP Parser Error: Expected PUNCTUATION ":" but got PUNCTUATION "("
    // With x-data unparseable the whole component is gone, so the Lists fieldtype rendered
    // ZERO inputs and submitted nothing. State belongs in a registered `Alpine.data()`
    // component, which puts the function bodies in module scope where JS is allowed.
    // ---
    $offenders = [];

    foreach (publicFormPartials() as $path) {
        $source = File::get($path);

        if (preg_match('/x-data\s*=\s*([\'"])\s*\{/', $source) === 1) {
            $offenders[] = str_replace(base_path().'/', '', $path);
        }
    }

    expect($offenders)->toBe([], 'Inline x-data object literals are unparseable under @alpinejs/csp. Register an Alpine.data() component instead.');
});

it('exercises every fieldtype that emits an event handler', function (): void {
    // ---
    // A rendered attribute is not evidence that it binds. Any partial emitting an event
    // handler must have its behavior exercised in the browser suite — clicked or dispatched,
    // then asserted on what changed. Asserting the handler string is what let "Add Set" stay
    // broken (UAMS-Web/uams-statamic-archive#2056): the assertion passed *because* the dead attribute was present.
    // ---
    $suite = File::get(base_path(PUBLIC_FORM_CONTROL_SUITE));
    $uncovered = [];

    foreach (publicFormPartials() as $path) {
        $source = File::get($path);

        // Event handlers only — `@click`, `@change`, `@input`, `x-on:*`. Not `x-model`/`x-show`,
        // which are bindings rather than controls a user actuates.
        if (preg_match('/(?<![a-z-])(@(click|change|input|submit|keydown|keyup)|x-on:)/', $source) !== 1) {
            continue;
        }

        $fieldtype = basename($path, '.antlers.html');

        // The replicator's controls are exercised by ReplicatorSubfieldConditionsTest.
        if ($fieldtype === 'replicator') {
            continue;
        }

        if (! str_contains($suite, "'type' => '{$fieldtype}'")) {
            $uncovered[] = $fieldtype;
        }
    }

    expect($uncovered)->toBe([], 'Each fieldtype emitting an event handler must be exercised in '.PUBLIC_FORM_CONTROL_SUITE.'.');
});
