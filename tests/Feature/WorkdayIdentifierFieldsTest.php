<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Fieldset as StatamicFieldset;
use Tests\TestCase;

/**
 * Resolution coverage for the four Workday identifier fields (UAMS-Web/uams-statamic-archive#2151).
 *
 * The identifiers are declared once in `uamsweb_common_identifiers.yaml` and
 * imported by handle into a `uams_workday_id` group on the `person` and
 * `provider` blueprints. An import is written `<fieldset>.<handle>`, so a
 * rename on either side resolves to nothing and Statamic drops the field
 * silently — no error, the field is simply absent. Asserting on the YAML text
 * would pass while the import was broken, so these tests resolve the group's
 * sub-fields and assert on the resulting `Statamic\Fields\Field` objects.
 *
 * The sub-fields are not reachable the obvious way: `Blueprint::fields()->all()`
 * returns top-level fields only, and `Fields::resolveFields()` does not flatten
 * a group. They live in the group field's own config, which is why
 * `workdayGroupFields()` exists rather than a one-line accessor.
 *
 * @command  php artisan test --compact tests/Feature/WorkdayIdentifierFieldsTest.php
 */
uses(TestCase::class);

// Flush Blink between tests so a cached blueprint path or imported fieldset
// cannot mask a genuine resolution loss.
beforeEach(fn () => Blink::flush());

/**
 * The Workday identifier handles, in the order they appear in the group.
 *
 * @var array<int, string> Field handles imported from the shared identifiers fieldset.
 */
$workdayHandles = [
    'workday_universal_id',
    'workday_employee_id',
    'workday_contingent_worker_id',
    'workday_student_id',
];

// The editor-facing description each handle must carry, character for character: the text
// agreed with two sibling repositories, then one sentence this repository appends (UAMS-Web/uams-statamic-archive#2469).
//
// The agreed part mirrors the WordPress field descriptions from UAMSWP-People-Showcase#56 and
// UAMSWP-Find-a-Doc#819, and the three repositories share it exactly. Measured 2026-10-06:
// Find-a-Doc's default branch (`master`, the ACF field group JSON) and People-Showcase's `dev`
// and `staging` (`advanced-options.php`) carry all four strings exactly. People-Showcase's
// default branch, `main`, does not yet, so reading it returns older text until `staging` is
// promoted and released. A reword upstream is what should drive a change to the agreed part here; this
// assertion is what makes such a reword visible instead of leaving the control panels
// quietly disagreeing.
//
// The appended sentence is this repository's own. UAMS-Web/uams-statamic-archive#2469's `## Decision` (decided 2026-10-06
// by the author of both upstream tickets) lets each repository append what is true only
// there, and the uniqueness rule is enforced only here (`app/Rules/UniqueIdentifier.php`,
// pooled across these four fields). It stays out of the agreed part, so the upstream text
// can still be compared against it.
$workdayAgreed = [
    'workday_universal_id' => 'A Workday Universal ID is a unique, immutable 32-character GUID (globally unique identifier) assigned to every object in Workday — including workers — at the time of creation. Unlike employee IDs, which may change during conversions, Workday Universal IDs remain consistent.',
    'workday_employee_id' => 'The Workday Employee ID for this person. Assigned to employees; a person hired through a contingent arrangement has a Workday Contingent Worker ID instead.',
    'workday_contingent_worker_id' => 'The Workday Contingent Worker ID for this person. Assigned to contractors and other non-employee workers rather than to employees.',
    'workday_student_id' => 'The Workday Student ID for this person. Assigned to students.',
];

/** The sentence this repository appends to every Workday description (UAMS-Web/uams-statamic-archive#2469). */
$workdayAppended = "A Workday ID must not be used by any other entry in the same collection, or in another of this person's Workday ID fields. Values are compared ignoring capitalization and surrounding spaces.";

$workdayInstructions = array_map(
    fn (string $agreed): string => $agreed."\n\n".$workdayAppended,
    $workdayAgreed
);

// Both blueprints carrying the `uams_workday_id` group, as namespace + handle.
dataset('workday blueprint targets', [
    'people/person' => ['collections/people', 'person'],
    'clinical_providers/provider' => ['collections/clinical_providers', 'provider'],
]);

/**
 * Resolve the sub-fields of a blueprint's `uams_workday_id` group.
 *
 * @param  string  $namespace  The `Blueprint::in()` namespace, e.g. `collections/people`
 * @param  string  $handle  The blueprint handle within that namespace
 * @return Collection<string, Field> Resolved sub-fields, keyed by field handle
 *
 * @throws RuntimeException When the blueprint, the group, or its field list is absent
 */
function workdayGroupFields(string $namespace, string $handle): Collection
{
    // Resolve the blueprint from its namespace
    $blueprint = Blueprint::in($namespace)->get($handle);

    // Check whether the blueprint resolved to a concrete instance
    if (! $blueprint instanceof StatamicBlueprint) {
        throw new RuntimeException("Blueprint $namespace/$handle did not resolve.");
    }

    // Find the Workday group among the blueprint's top-level fields
    $group = $blueprint->fields()->all()->get('uams_workday_id');

    // Check whether the group is present
    if (! $group instanceof Field) {
        throw new RuntimeException("The `uams_workday_id` group is absent from $namespace/$handle.");
    }

    // Confirm the field really is a group, so a retyped field fails here
    // rather than further downstream
    expect($group->type())->toBe('group');

    // Extract the group's declared sub-field definitions
    $subFields = $group->config()['fields'] ?? null;

    // Check whether the group declares any fields at all
    if (! is_array($subFields)) {
        throw new RuntimeException("The `uams_workday_id` group in $namespace/$handle declares no fields.");
    }

    // Resolve the definitions into Field objects the way the group fieldtype does
    /** @var Collection<string, Field> $resolved */
    $resolved = (new Fields($subFields))->all();

    return $resolved;
}

it('resolves every Workday identifier inside the group', function (string $namespace, string $handle) use ($workdayHandles): void {
    // Resolve the group's sub-fields for this blueprint
    $fields = workdayGroupFields($namespace, $handle);

    // Assert each expected handle survived the fieldset import
    foreach ($workdayHandles as $workdayHandle) {
        expect($fields->has($workdayHandle))
            ->toBeTrue("`$workdayHandle` did not resolve in $namespace/$handle — check the fieldset import.");
    }
})->with('workday blueprint targets');

it('resolves the Workday Student ID as an optional text field', function (string $namespace, string $handle): void {
    // Resolve the newly added field from the group
    $field = workdayGroupFields($namespace, $handle)->get('workday_student_id');

    // Check whether the field resolved before reading its configuration
    if (! $field instanceof Field) {
        throw new RuntimeException("`workday_student_id` did not resolve in $namespace/$handle.");
    }

    // Assert the type, label and optionality the ticket specified
    expect($field->type())->toBe('text')
        ->and($field->display())->toBe('Workday Student ID')
        ->and($field->isRequired())->toBeFalse();
})->with('workday blueprint targets');

it('declares every Workday identifier in the shared fieldset as a text field', function (string $handle): void {
    // Load the shared identifiers fieldset
    $fieldset = Fieldset::find('uamsweb_common_identifiers');

    // Check whether the fieldset resolved
    if (! $fieldset instanceof StatamicFieldset) {
        throw new RuntimeException('The `uamsweb_common_identifiers` fieldset did not resolve.');
    }

    // Find the declaration for this handle
    $field = $fieldset->fields()->all()->get($handle);

    // Check whether the handle is declared
    if (! $field instanceof Field) {
        throw new RuntimeException("`$handle` is absent from the shared fieldset.");
    }

    // Assert the declared fieldtype
    expect($field->type())->toBe('text');
})->with($workdayHandles);

it('carries the agreed instructions on every resolved Workday identifier', function (string $namespace, string $handle) use ($workdayHandles, $workdayInstructions): void {
    // Resolve the group's sub-fields for this blueprint
    $fields = workdayGroupFields($namespace, $handle);

    // Assert each handle resolves and carries its expected description
    foreach ($workdayHandles as $workdayHandle) {
        $field = $fields->get($workdayHandle);

        // Check whether the field resolved before reading its configuration
        if (! $field instanceof Field) {
            throw new RuntimeException("`$workdayHandle` did not resolve in $namespace/$handle.");
        }

        // Assert the instructions inherited from the shared fieldset, character for character
        expect($field->instructions())
            ->toBe(
                $workdayInstructions[$workdayHandle],
                "`$workdayHandle` in $namespace/$handle does not carry the agreed description and the appended uniqueness sentence — see UAMSWP-People-Showcase#56, UAMSWP-Find-a-Doc#819 and #2469."
            );
    }
})->with('workday blueprint targets');
