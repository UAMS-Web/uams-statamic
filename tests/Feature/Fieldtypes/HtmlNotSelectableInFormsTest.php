<?php

declare(strict_types=1);

use App\Fieldtypes\Html;
use Statamic\Fields\Fieldtype;
use Tests\TestCase;

uses(TestCase::class);

covers(Html::class);

/**
 * Guards that the application `Html` fieldtype is not selectable in form
 * blueprints (UAMS-Web/uams-statamic-archive#869). The published form partial `html.antlers.html` emits its
 * value raw (Antlers `{{ }}` does not escape), so letting the non-admin
 * `form_editor` role add an `Html` field would open an editor-reachable
 * stored-XSS surface on public forms. A super-admin can still add raw HTML via a
 * direct blueprint edit.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/HtmlNotSelectableInFormsTest.php
 */
it('is not selectable in form blueprints', function () {
    expect((new Html)->selectableInForms())->toBeFalse();
});

it('registers the application class as the html handle', function () {
    // The fieldtypes repository resolves a handle to its class name (a string);
    // confirming the app class overrides Statamic's base html fieldtype so the
    // dropped `selectableInForms` actually governs the registered `html` field.
    expect(app('statamic.fieldtypes')->get('html'))->toBe(Html::class);
});

it('keeps the registered html fieldtype out of the form-selectable set', function () {
    /** @var class-string<Fieldtype> $class */
    $class = app('statamic.fieldtypes')->get('html');

    expect((new $class)->selectableInForms())->toBeFalse();
});
