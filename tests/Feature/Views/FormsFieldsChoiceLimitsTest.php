<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests that the choice templates disable + flag a full option when the fieldtype
 * exposes it in `full_choices` (UAMS-Web/uams-statamic-archive#292). The list is computed at render by
 * App\Support\Forms\ChoiceSubmissionLimits and merged into the field's
 * renderable data; server-side enforcement of the same set is covered by
 * ChoiceSubmissionLimitsEnforcementTest. When `full_choices` is absent (a field
 * without limits, or the quiz field delegating to these partials) the markup is
 * inert.
 *
 * @command php artisan test --compact tests/Feature/Views/FormsFieldsChoiceLimitsTest.php
 */

/**
 * Render a choice template against a hand-built context.
 *
 * @param  array<string, mixed>  $overrides
 */
function renderChoiceField(string $fieldtype, array $overrides = []): string
{
    return (string) Antlers::parse(
        File::get(resource_path("views/vendor/statamic/forms/fields/{$fieldtype}.antlers.html")),
        [
            'handle' => 'slot',
            'id' => 'form-slot',
            'visibility' => 'visible',
            'value' => '',
            'validate' => [],
            'error' => null,
            'options' => ['morning' => 'Morning', 'evening' => 'Evening'],
            ...$overrides,
        ],
        true,
    );
}

it('disables and flags a full choice while leaving others available', function (string $fieldtype) {
    $out = renderChoiceField($fieldtype, ['full_choices' => ['morning']]);

    // The full option is disabled and flagged; the other is not.
    expect($out)->toContain('disabled');
    expect($out)->toContain('Full');
    // Exactly one option carries `disabled` (the full one).
    expect(substr_count($out, 'disabled'))->toBe(1);
})->with(['radio', 'checkboxes', 'select']);

it('renders no disabled or full markup when no choices are full', function (string $fieldtype) {
    $out = renderChoiceField($fieldtype, ['full_choices' => []]);

    expect($out)->not->toContain('disabled');
    expect($out)->not->toContain('Full');
})->with(['radio', 'checkboxes', 'select']);

it('is inert when the field exposes no full_choices at all', function (string $fieldtype) {
    // A field without per-choice limits (or the quiz field delegating here) never
    // sets full_choices; the contains checks must resolve falsy, not error.
    $out = renderChoiceField($fieldtype);

    expect($out)->not->toContain('disabled');
    expect($out)->not->toContain('Full');
})->with(['radio', 'checkboxes', 'select']);
