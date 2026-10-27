<?php

declare(strict_types=1);

use App\Tags\SchemaLocationContactPoints;
use Statamic\Facades\Antlers;
use Statamic\Facades\GlobalSet;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Globals\Variables;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_location_contact_points` Antlers tag.
 *
 * The Arkansas Children's global-fallback read is exercised here via the
 * `ac_*_fallback` injection parameters; the live global path and engine
 * augmented shapes are covered end-to-end in SchemaJsonLdLocationTest.
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaLocationContactPointsTest.php
 */
const CP_TAG = '{{ schema_location_contact_points '
    .':appointment_enabled="appointment_enabled" '
    .':phone_appointment_new="phone_appointment_new" '
    .':phone_appointment_return="phone_appointment_return" '
    .':ac_enabled="ac_enabled" '
    .':ac_primary="ac_primary" :ac_primary_fallback="ac_primary_fallback" '
    .':ac_specialty="ac_specialty" :ac_specialty_fallback="ac_specialty_fallback" '
    .':phone_additional="phone_additional" }}';

const CP_TYPE = 'https://schema.org/ContactPoint';

/**
 * @param  array<string, mixed>  $vars
 */
function renderContactPoints(array $vars): mixed
{
    $context = array_merge([
        'appointment_enabled' => null,
        'phone_appointment_new' => null,
        'phone_appointment_return' => null,
        'ac_enabled' => null,
        'ac_primary' => null,
        'ac_primary_fallback' => null,
        'ac_specialty' => null,
        'ac_specialty_fallback' => null,
        'phone_additional' => null,
    ], $vars);

    return json_decode(trim((string) Antlers::parse(CP_TAG, $context, true)), true);
}

it('exposes schema_location_contact_points as the tag handle', function (): void {
    expect(SchemaLocationContactPoints::handle())->toBe('schema_location_contact_points');
});

it('emits an additional-phone ContactPoint with label, telephone and note', function (): void {
    $points = renderContactPoints([
        'phone_additional' => [
            ['text' => 'After-hours nurse line', 'phone' => '501-555-0003', 'description' => 'Staffed 24/7'],
        ],
    ]);

    expect($points)->toBe([
        [
            '@type' => CP_TYPE,
            'name' => 'After-hours nurse line',
            'telephone' => '501-555-0003',
            'description' => 'Staffed 24/7',
        ],
    ]);
});

it('omits the description when an additional phone has no note', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'phone_additional' => [['text' => 'Billing', 'phone' => '501-555-0004']],
    ]);

    expect($points[0])->not->toHaveKey('description');
    expect($points[0]['name'])->toBe('Billing');
});

it('skips an additional-phone row that has no telephone', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'phone_additional' => [
            ['text' => 'Empty', 'phone' => ''],
            ['text' => 'Real', 'phone' => '501-555-0005'],
        ],
    ]);

    expect($points)->toHaveCount(1);
    expect($points[0]['telephone'])->toBe('501-555-0005');
});

it('names the appointments ContactPoint plainly when there is no returning number', function (): void {
    $points = renderContactPoints([
        'phone_appointment_new' => '501-555-1000',
    ]);

    expect($points)->toBe([
        [
            '@type' => CP_TYPE,
            'contactType' => 'appointments',
            'name' => 'Appointments',
            'telephone' => '501-555-1000',
        ],
    ]);
});

it('suppresses the appointment numbers when the appointment gate is explicitly false', function (): void {
    $points = renderContactPoints([
        'appointment_enabled' => false,
        'phone_appointment_new' => '501-555-1000',
        'phone_appointment_return' => '501-555-2000',
    ]);

    expect($points)->toBeNull();
});

it('treats an absent appointment gate as open', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'phone_appointment_new' => '501-555-1000',
    ]);

    expect($points)->toHaveCount(1);
    expect($points[0]['name'])->toBe('Appointments');
});

it('emits appointment numbers when the appointment gate is a LabeledValue true', function (): void {
    $points = renderContactPoints([
        'appointment_enabled' => new LabeledValue(true, 'Yes'),
        'phone_appointment_new' => '501-555-1000',
    ]);

    expect($points)->toHaveCount(1);
});

it('splits new and returning appointment numbers when both are present', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'phone_appointment_new' => '501-555-1000',
        'phone_appointment_return' => '501-555-2000',
    ]);

    expect(array_column($points, 'name'))->toBe([
        'New Patient Appointments',
        'Returning Patient Appointments',
    ]);
    expect(array_column($points, 'telephone'))->toBe(['501-555-1000', '501-555-2000']);
});

it('emits Arkansas Children\'s appointment numbers from the location override', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'ac_enabled' => true,
        'ac_primary' => '501-555-3000',
        'ac_specialty' => '501-555-4000',
    ]);

    expect(array_column($points, 'name'))->toBe([
        'Primary Care Appointments',
        'Specialty Care Appointments',
    ]);
    expect(array_column($points, 'telephone'))->toBe(['501-555-3000', '501-555-4000']);
});

it('falls back to the global default when the AC override is empty', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'ac_enabled' => true,
        'ac_primary' => '',
        'ac_primary_fallback' => '501-555-9001',
        'ac_specialty_fallback' => '501-555-9002',
    ]);

    expect(array_column($points, 'telephone'))->toBe(['501-555-9001', '501-555-9002']);
});

it('prefers the AC override over the global fallback', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'ac_enabled' => true,
        'ac_primary' => '501-555-3000',
        'ac_primary_fallback' => '501-555-9001',
    ]);

    expect($points)->toHaveCount(1);
    expect($points[0]['telephone'])->toBe('501-555-3000');
});

it('suppresses AC appointment numbers when the AC gate is closed', function (): void {
    $points = renderContactPoints([
        'ac_enabled' => false,
        'ac_primary' => '501-555-3000',
        'ac_primary_fallback' => '501-555-9001',
    ]);

    expect($points)->toBeNull();
});

it('reads a LabeledValue AC gate', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'ac_enabled' => new LabeledValue(true, 'Yes'),
        'ac_primary' => '501-555-3000',
    ]);

    expect($points)->toHaveCount(1);
    expect($points[0]['name'])->toBe('Primary Care Appointments');
});

it('orders AC, new/return and additional ContactPoints', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'ac_enabled' => true,
        'ac_primary' => '501-555-3000',
        'ac_specialty' => '501-555-4000',
        'phone_appointment_new' => '501-555-1000',
        'phone_appointment_return' => '501-555-2000',
        'phone_additional' => [['text' => 'Nurse line', 'phone' => '501-555-0003']],
    ]);

    expect(array_column($points, 'telephone'))->toBe([
        '501-555-3000',
        '501-555-4000',
        '501-555-1000',
        '501-555-2000',
        '501-555-0003',
    ]);
});

it('strips tags from a phone number and label for defense in depth', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'phone_additional' => [
            ['text' => '<b>Nurse</b> line', 'phone' => '501<script>x</script>-555-0003'],
        ],
    ]);

    expect($points[0]['name'])->toBe('Nurse line');
    expect($points[0]['telephone'])->toBe('501x-555-0003');
});

it('emits nothing when no phone is present', function (): void {
    expect(trim((string) Antlers::parse(CP_TAG, [
        'phone_appointment_new' => null,
        'phone_appointment_return' => null,
        'ac_enabled' => null,
        'ac_primary' => null,
        'ac_primary_fallback' => null,
        'ac_specialty' => null,
        'ac_specialty_fallback' => null,
        'phone_additional' => null,
    ], true)))->toBe('');
});

/**
 * Render the tag and return its RAW string output (not JSON-decoded), for
 * assertions about the serialization itself.
 *
 * @param  array<string, mixed>  $vars
 */
function renderContactPointsRaw(array $vars): string
{
    $context = array_merge([
        'appointment_enabled' => null,
        'phone_appointment_new' => null,
        'phone_appointment_return' => null,
        'ac_enabled' => null,
        'ac_primary' => null,
        'ac_primary_fallback' => null,
        'ac_specialty' => null,
        'ac_specialty_fallback' => null,
        'phone_additional' => null,
    ], $vars);

    return trim((string) Antlers::parse(CP_TAG, $context, true));
}

// --- Edge branches (mutation hardening) --------------------------------------

// appointmentGateOpen(): the integer-1 truthy arm.
it('opens the appointment gate for an integer-1 toggle', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'appointment_enabled' => 1,
        'phone_appointment_new' => '501-555-2000',
    ]);

    expect($points[0]['telephone'])->toBe('501-555-2000');
});

// appointmentGateOpen(): an explicit false suppresses the numbers (bounds the
// gate on the other side).
it('closes the appointment gate when explicitly false', function (): void {
    $raw = renderContactPointsRaw([
        'appointment_enabled' => false,
        'phone_appointment_new' => '501-555-2000',
    ]);

    expect($raw)->toBe('');
});

// acGateOpen(): the integer-1 truthy arm.
it('opens the AC gate for an integer-1 toggle', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'ac_enabled' => 1,
        'ac_primary_fallback' => '501-555-9001',
    ]);

    expect($points[0]['telephone'])->toBe('501-555-9001');
});

// scalarValue(): strips markup and trims surrounding whitespace.
it('strips markup and trims whitespace from a resolved phone', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'ac_enabled' => true,
        'ac_primary' => '  <b>501-555-1000</b>  ',
    ]);

    expect($points[0]['telephone'])->toBe('501-555-1000');
});

// scalarValue(): a non-string scalar coerces to a string rather than being
// passed raw to `strip_tags()` (a TypeError under strict types).
it('coerces a non-string scalar phone to a string', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'ac_enabled' => true,
        'ac_primary' => 5015551000,
    ]);

    expect($points[0]['telephone'])->toBe('5015551000');
});

// scalarValue(): a non-scalar value yields null (the early return), so no point
// resolves rather than coercing an array to a string.
it('rejects a non-scalar phone value rather than coercing it to a string', function (): void {
    $raw = renderContactPointsRaw([
        'ac_enabled' => true,
        'ac_primary' => ['not', 'scalar'],
    ]);

    expect($raw)->toBe('');
});

// scalarValue(): the `$raw instanceof LabeledValue` unwrap.
it('unwraps a LabeledValue phone value', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'ac_enabled' => true,
        'ac_primary' => new LabeledValue('501-555-1000', 'Primary'),
    ]);

    expect($points[0]['telephone'])->toBe('501-555-1000');
});

// additionalRows(): the `$raw instanceof Value` unwrap of the whole repeater.
it('unwraps a Value-wrapped additional-phone repeater', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'phone_additional' => new Value([
            ['text' => 'Nurse line', 'phone' => '501-555-0003', 'description' => null],
        ]),
    ]);

    expect($points[0]['telephone'])->toBe('501-555-0003');
});

// rowField(): a row exposed as a `Values` set is read via array access, not
// dropped as unreadable.
it('reads an additional-phone row exposed as a Values set', function (): void {
    /** @var list<array<string, mixed>> $points */
    $points = renderContactPoints([
        'phone_additional' => [new Values(['text' => 'Nurse line', 'phone' => '501-555-0003'])],
    ]);

    expect($points[0]['telephone'])->toBe('501-555-0003');
});

// index(): `JSON_UNESCAPED_SLASHES` keeps a slash as `/`, not `\/`.
it('emits unescaped slashes in the JSON output', function (): void {
    $raw = renderContactPointsRaw([
        'phone_additional' => [
            ['text' => 'Line', 'phone' => '501-555-0003', 'description' => 'Mon/Wed/Fri'],
        ],
    ]);

    expect($raw)->toContain('Mon/Wed/Fri')
        ->and($raw)->not->toContain('Mon\/Wed\/Fri');
});

// index(): a value that cannot be JSON-encoded (invalid UTF-8) yields '' rather
// than the raw `false`.
it('returns empty output when a value cannot be JSON-encoded', function (): void {
    $raw = renderContactPointsRaw([
        'phone_additional' => [
            ['text' => "Nurse\xB1\x31 line", 'phone' => '501-555-0003', 'description' => null],
        ],
    ]);

    expect($raw)->toBe('');
});

// globalPhone(): the live global read — the AC fallback resolves from the
// Find-a-Doc global's default origin when no override is given. Mutated in
// memory and restored so no content is persisted (mirrors SchemaJsonLdLocationTest).
it('reads the AC fallback from the Find-a-Doc global when no override is given', function (): void {
    /** @var Statamic\Globals\GlobalSet $globalSet */
    $globalSet = GlobalSet::findByHandle('uams_fad_settings');
    /** @var Variables $variables */
    $variables = $globalSet->inDefaultSite();
    $original = $variables->get('location_ac_appointments_primary');

    try {
        $variables->set('location_ac_appointments_primary', '501-555-7777');

        /** @var list<array<string, mixed>> $points */
        $points = renderContactPoints(['ac_enabled' => true]);

        expect($points[0]['telephone'])->toBe('501-555-7777');
    } finally {
        $variables->set('location_ac_appointments_primary', $original);
    }
})->group('serial');

// globalPhone(): the `?->inDefaultSite()` null-safe and the `instanceof` guard —
// an absent global (fresh install / uninitialized content) must resolve to null,
// not fatal, so the AC fallback simply yields nothing.
it('resolves the AC fallback to nothing when the global is absent', function (): void {
    GlobalSet::shouldReceive('findByHandle')->andReturnNull();

    $result = app(SchemaLocationContactPoints::class);
    $result->setContext([]);
    $result->setParameters(['ac_enabled' => true]);

    expect(trim($result->index()))->toBe('');
});

// --- Documented equivalent survivors (mutation) ------------------------------
//
// `vendor/bin/pest --mutate` leaves five survivors on this class (score 96.03%),
// all on the defensive field-value unwrap guards. Each is a genuine EQUIVALENT
// mutant — no path this tag is invoked through can make it change observable
// behavior — verified against the code, not assumed:
//
//   - `$raw`/`$gate instanceof Value` -> `false` in additionalRows() (src L215),
//     appointmentGateOpen() (L328), acGateOpen() (L356) and scalarValue()
//     (L377): Antlers unwraps a plain `Value` param to its scalar BEFORE the tag
//     reads it, so a raw `Value` never reaches these guards. Proven: forcing the
//     guard `false` and rendering a plain-`Value(true)` gate still emits the
//     ContactPoint (the param arrives already unwrapped to the scalar). Kept as
//     defense-in-depth for any non-Antlers caller.
//   - `rowField()` `$row instanceof Values` -> `true` (src L248): when the row is
//     NOT a `Values` set it is a plain array, and the true-branch (`$row[$key]`)
//     reads a plain array identically to the array branch below it, so forcing
//     the guard true cannot change the result.
//
// NOT equivalent (do not delete): the sibling `$gate`/`$raw instanceof
// LabeledValue` guards (src L334, L362, L383) are LOAD-BEARING and fully killed.
// `LabeledValue` extends `ArrayableString`, not `Value`, so Antlers delivers it
// RAW; a toggle augments to a `LabeledValue`, and removing the unwrap fails the
// 'emits appointment numbers when the appointment gate is a LabeledValue true',
// 'reads a LabeledValue AC gate', and 'unwraps a LabeledValue phone value' cases
// above (0 vs 1 point / raw-object vs scalar).
