<?php

declare(strict_types=1);

use App\Tags\LocationRegularHours;
use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the visitor-facing `location_regular_hours` tag (UAMS-Web/uams-statamic-archive#2390): the
 * human-readable companion to the `schema_location_hours` JSON-LD emission of
 * the same `regular_hours` rules.
 *
 * UAMS-Web/uams-statamic-archive#2405 added the telemedicine group. The load-bearing property across that
 * change is that a location with in-person hours alone renders exactly what it
 * rendered before -- so most of the cases below are unchanged regression cover
 * for the premises path, and the telemedicine cases sit alongside them.
 *
 * @command  php artisan test --compact tests/Feature/Tags/LocationRegularHoursTest.php
 */
const REGULAR_HOURS_TAG = '{{ location_regular_hours :regular_hours="regular_hours" }}';

/**
 * AP-style times carry a non-breaking space before the period, so build the
 * expected copy the same way rather than pasting an invisible character.
 */
function apTime(string $clock, string $period): string
{
    return $clock."\u{00A0}".$period;
}

/**
 * @param  array<int, mixed>  $rules
 */
function renderLocationRegularHours(array $rules): string
{
    return trim((string) Antlers::parse(REGULAR_HOURS_TAG, ['regular_hours' => $rules]));
}

/**
 * One open rule over the given days, 8:00 to 17:00 unless overridden.
 *
 * @param  list<string>  $days
 * @return array<string, mixed>
 */
function openRule(array $days, string $open = '08:00', string $close = '17:00'): array
{
    return [
        'type' => 'custom',
        'days' => $days,
        'service_mode' => 'in_person',
        'status' => 'open',
        'time_span' => [['open' => $open, 'close' => $close]],
    ];
}

/**
 * One open telemedicine rule over the given days.
 *
 * @param  list<string>  $days
 * @return array<string, mixed>
 */
function telemedicineRule(array $days, string $open = '20:00', string $close = '22:00'): array
{
    return [
        'type' => 'custom',
        'days' => $days,
        'service_mode' => 'telemedicine',
        'status' => 'open',
        'time_span' => [['open' => $open, 'close' => $close]],
    ];
}

it('exposes location_regular_hours as the tag handle', function (): void {
    expect(LocationRegularHours::handle())->toBe('location_regular_hours');
});

it('renders nothing when there are no hours rules', function (): void {
    expect(renderLocationRegularHours([]))->toBe('');
});

it('renders no section and no heading when every rule resolves to nothing', function (): void {
    // An open rule whose only span is missing its close time contributes no copy
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => ['monday'],
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [['open' => '08:00', 'close' => null]],
        ],
    ]);

    expect($html)->toBe('');
});

it('groups consecutive days sharing the same range into a single row', function (): void {
    /** @var string $html */
    $html = renderLocationRegularHours([
        openRule(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
    ]);

    expect($html)
        ->toContain('location-hours')
        ->toContain('Hours')
        ->toContain('Monday &ndash; Friday')
        ->toContain(apTime('8', 'a.m.').' to '.apTime('5', 'p.m.'));

    // The grouped row replaces the per-day rows rather than joining them
    expect(substr_count($html, '<dt'))->toBe(1);
    expect($html)->not->toContain('Wednesday');
});

it('groups five separate single-day rules exactly as it groups one weekday rule', function (): void {
    // The reader should not be able to tell which editor affordance was used
    $perDay = renderLocationRegularHours([
        openRule(['monday']),
        openRule(['tuesday']),
        openRule(['wednesday']),
        openRule(['thursday']),
        openRule(['friday']),
    ]);

    $oneRule = renderLocationRegularHours([
        openRule(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
    ]);

    expect($perDay)->toBe($oneRule);
});

it('renders a single day without a range', function (): void {
    /** @var string $html */
    $html = renderLocationRegularHours([openRule(['wednesday'])]);

    expect($html)->toContain('Wednesday');
    expect($html)->not->toContain('&ndash;');
    expect(substr_count($html, '<dt'))->toBe(1);
});

it('joins exactly two consecutive days with a conjunction rather than a range', function (): void {
    $html = renderLocationRegularHours([openRule(['saturday', 'sunday'], '09:00', '12:00')]);

    expect($html)->toContain('Saturday and Sunday');
    expect($html)->not->toContain('&ndash;');
});

it('breaks a run where the week has a gap', function (): void {
    // Monday to Wednesday and Friday share hours, but Thursday is absent
    /** @var string $html */
    $html = renderLocationRegularHours([
        openRule(['monday', 'tuesday', 'wednesday']),
        openRule(['friday']),
    ]);

    expect($html)
        ->toContain('Monday &ndash; Wednesday')
        ->toContain('Friday');

    // Two rows, so Friday never folds into the Monday run
    expect(substr_count($html, '<dt'))->toBe(2);
});

it('states a closed day rather than omitting it', function (): void {
    $html = renderLocationRegularHours([
        openRule(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
        [
            'type' => 'weekend',
            'days' => ['saturday', 'sunday'],
            'service_mode' => 'in_person',
            'status' => 'closed',
        ],
    ]);

    expect($html)
        ->toContain('Monday &ndash; Friday')
        ->toContain('Saturday and Sunday')
        ->toContain('Closed');
});

it('renders a round-the-clock rule without times', function (): void {
    $html = renderLocationRegularHours([
        [
            'type' => 'daily',
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'service_mode' => 'in_person',
            'status' => 'open_24h',
        ],
    ]);

    expect($html)
        ->toContain('Monday &ndash; Sunday')
        ->toContain('Open 24 hours');
});

it('joins multiple spans on one day, such as a midday closure', function (): void {
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => ['monday'],
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [
                ['open' => '08:00', 'close' => '12:00'],
                ['open' => '13:00', 'close' => '17:00'],
            ],
        ],
    ]);

    expect($html)->toContain(
        apTime('8', 'a.m.').' to noon, '.apTime('1', 'p.m.').' to '.apTime('5', 'p.m.')
    );
});

/**
 * Criterion: a location with only telemedicine rules renders a coherent hours
 * section rather than none.
 *
 * This inverts the characterization `UAMS-Web/uams-statamic-archive#2390` left behind, which asserted the
 * empty string here. The skip was deliberate and correct for the JSON-LD; what
 * it left was a stored, editor-entered value with no reader at all, which is
 * the whole of `UAMS-Web/uams-statamic-archive#2405`.
 */
it('renders a telemedicine-only location as a labeled section', function (): void {
    $html = renderLocationRegularHours([telemedicineRule(['monday'])]);

    expect($html)->toBe(
        '<section class="location-hours" aria-label="Hours">'
        .'<h2>Hours</h2>'
        .'<div class="location-hours-group location-hours-group-telemedicine">'
        .'<h3 class="location-hours-mode">Telemedicine</h3>'
        .'<dl class="location-hours-list">'
        .'<dt class="location-hours-days">Monday</dt>'
        .'<dd class="location-hours-times">'.apTime('8', 'p.m.').' to '.apTime('10', 'p.m.').'</dd>'
        .'</dl>'
        .'</div>'
        .'</section>'
    );
});

/**
 * Criterion: the in-person hours remain distinguishable from the telemedicine
 * hours. Pinned on the whole markup rather than on the presence of both labels,
 * because "distinguishable" is a claim about the structure a reader meets.
 */
it('renders both modes as separately labeled groups, premises first', function (): void {
    $html = renderLocationRegularHours([
        openRule(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
        telemedicineRule(['saturday']),
    ]);

    expect($html)->toBe(
        '<section class="location-hours" aria-label="Hours">'
        .'<h2>Hours</h2>'
        .'<div class="location-hours-group location-hours-group-in-person">'
        .'<h3 class="location-hours-mode">In-Person</h3>'
        .'<dl class="location-hours-list">'
        .'<dt class="location-hours-days">Monday &ndash; Friday</dt>'
        .'<dd class="location-hours-times">'.apTime('8', 'a.m.').' to '.apTime('5', 'p.m.').'</dd>'
        .'</dl>'
        .'</div>'
        .'<div class="location-hours-group location-hours-group-telemedicine">'
        .'<h3 class="location-hours-mode">Telemedicine</h3>'
        .'<dl class="location-hours-list">'
        .'<dt class="location-hours-days">Saturday</dt>'
        .'<dd class="location-hours-times">'.apTime('8', 'p.m.').' to '.apTime('10', 'p.m.').'</dd>'
        .'</dl>'
        .'</div>'
        .'</section>'
    );
});

it('expands the two modes independently, so one day can carry both', function (): void {
    // The blueprint asks for a telemedicine rule only where the hours DIFFER, so
    // the same day in both modes is the case the feature exists for. Expanding
    // each mode separately is what stops the later rule overwriting the earlier
    // one, which is how a single day-keyed schedule would have handled it.
    $html = renderLocationRegularHours([
        openRule(['monday'], '08:00', '17:00'),
        telemedicineRule(['monday'], '20:00', '22:00'),
    ]);

    expect($html)
        ->toContain('<dd class="location-hours-times">'.apTime('8', 'a.m.').' to '.apTime('5', 'p.m.').'</dd>')
        ->toContain('<dd class="location-hours-times">'.apTime('8', 'p.m.').' to '.apTime('10', 'p.m.').'</dd>');

    // Monday is named once per mode, and in no other row
    expect(substr_count($html, 'Monday'))->toBe(2);
});

it('groups telemedicine days into ranges exactly as it does in-person days', function (): void {
    $html = renderLocationRegularHours([
        telemedicineRule(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
    ]);

    expect($html)->toContain('<dt class="location-hours-days">Monday &ndash; Friday</dt>');
});

it('states a closed telemedicine day rather than omitting it', function (): void {
    $html = renderLocationRegularHours([
        telemedicineRule(['monday']),
        [
            'type' => 'custom',
            'days' => ['tuesday'],
            'service_mode' => 'telemedicine',
            'status' => 'closed',
        ],
    ]);

    expect($html)
        ->toContain('<dt class="location-hours-days">Tuesday</dt>')
        ->toContain('<dd class="location-hours-times">Closed</dd>');
});

it('omits the mode labels when a location has in-person hours alone', function (): void {
    // The blueprint asks for a telemedicine rule only when the hours differ, so a
    // location without one has no distinction to draw. Labeling it would invent
    // one -- and this is the assertion that pins UAMS-Web/uams-statamic-archive#2405 as additive for the
    // overwhelming majority of locations.
    $html = renderLocationRegularHours([openRule(['monday'])]);

    expect($html)
        ->not->toContain('location-hours-group')
        ->not->toContain('location-hours-mode')
        ->not->toContain('In-Person');
});

it('renders a telemedicine rule whose in-person counterpart resolves to nothing', function (): void {
    // An in-person rule that contributes no copy must not suppress the
    // telemedicine group, and must not leave an empty premises group behind
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => ['monday'],
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [],
        ],
        telemedicineRule(['monday']),
    ]);

    expect($html)
        ->toContain('location-hours-group-telemedicine')
        ->not->toContain('location-hours-group-in-person')
        ->not->toContain('<dl class="location-hours-list"></dl>');
});

it('treats an absent service mode as in-person', function (): void {
    // The blueprint defaults the field to in_person and requires it, but stored
    // rows predating that, and raw values read outside augmentation, may carry
    // nothing. Bucketing those with the premises keeps them visible and keeps
    // this tag's reading identical to the JSON-LD's, which skips only an
    // explicit telemedicine.
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => ['monday'],
            'status' => 'open',
            'time_span' => [['open' => '08:00', 'close' => '17:00']],
        ],
    ]);

    expect($html)
        ->toContain('<dt class="location-hours-days">Monday</dt>')
        ->not->toContain('location-hours-group');
});

it('treats an unrecognized service mode as in-person rather than dropping it', function (): void {
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => ['monday'],
            'service_mode' => 'drive_through',
            'status' => 'open',
            'time_span' => [['open' => '08:00', 'close' => '17:00']],
        ],
    ]);

    expect($html)
        ->toContain('<dt class="location-hours-days">Monday</dt>')
        ->not->toContain('location-hours-group');
});

it('lets a later rule win for a day an earlier rule already set', function (): void {
    /** @var string $html */
    $html = renderLocationRegularHours([
        [
            'type' => 'daily',
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [['open' => '08:00', 'close' => '17:00']],
        ],
        [
            'type' => 'custom',
            'days' => ['sunday'],
            'service_mode' => 'in_person',
            'status' => 'closed',
        ],
    ]);

    expect($html)
        ->toContain('Monday &ndash; Saturday')
        ->toContain('Sunday')
        ->toContain('Closed');

    // Sunday appears once, as the closed row, not twice
    expect(substr_count($html, 'Sunday'))->toBe(1);
});

it('escapes a day label rather than trusting the option key', function (): void {
    // An unknown key is dropped entirely, so no unescaped value can reach the page
    $html = renderLocationRegularHours([openRule(['<script>alert(1)</script>'])]);

    expect($html)->toBe('');
});

/**
 * Criterion: the JSON-LD `openingHoursSpecification` output is unchanged, asserted
 * rather than assumed, by `UAMS-Web/uams-statamic-archive#2390` and again by `UAMS-Web/uams-statamic-archive#2405`. Both add reader-facing
 * surfaces over the same stored rules and touch nothing `schema_location_hours`
 * reads, so the same fixture must still produce the same specs -- including the
 * semantics that deliberately differ between the two surfaces.
 *
 * The divergence `UAMS-Web/uams-statamic-archive#2405` introduces is the sharper one and is pinned at the end:
 * the telemedicine rule in this fixture now renders for a reader and still emits
 * no spec. That is deliberate. `openingHoursSpecification` describes when the
 * premises are open, so emitting a remote-visit window there would tell a search
 * engine the building is open at 8 p.m. -- a claim someone can act on by driving
 * to it.
 */
it('leaves the JSON-LD openingHoursSpecification output unchanged', function (): void {
    $rules = [
        openRule(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
        [
            'type' => 'weekend',
            'days' => ['saturday', 'sunday'],
            'service_mode' => 'in_person',
            'status' => 'closed',
        ],
        [
            'type' => 'custom',
            'days' => ['monday'],
            'service_mode' => 'telemedicine',
            'status' => 'open',
            'time_span' => [['open' => '20:00', 'close' => '22:00']],
        ],
    ];

    $json = trim((string) Antlers::parse(
        '{{ schema_location_hours :regular_hours="regular_hours" }}',
        ['regular_hours' => $rules]
    ));

    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    // One spec for the weekday rule; the closed weekend emits none (absence
    // implies closed) and the telemedicine rule is skipped as a non-premises mode
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);

    /** @var list<array{opens: string, closes: string, dayOfWeek: list<string>}> $specs */
    $specs = $decoded;
    expect($specs[0]['opens'])->toBe('08:00');
    expect($specs[0]['closes'])->toBe('17:00');
    expect($specs[0]['dayOfWeek'])->toContain('https://schema.org/Monday');
    expect($specs[0]['dayOfWeek'])->toContain('https://schema.org/Friday');

    // The reader-facing surface states the closure the JSON-LD omits, and renders
    // the telemedicine rule the JSON-LD skips -- both divergences are deliberate
    // and both are what these assertions pin
    expect(renderLocationRegularHours($rules))
        ->toContain('Closed')
        ->toContain('<h3 class="location-hours-mode">Telemedicine</h3>')
        ->toContain('<dd class="location-hours-times">'.apTime('8', 'p.m.').' to '.apTime('10', 'p.m.').'</dd>');

    // Control: the same fixture's JSON-LD names no evening window at all, so the
    // assertion above is about a genuine divergence rather than a shared value
    expect($json)->not->toContain('20:00');
});

/**
 * The cases below close mutation-testing survivors: each pins behavior that the
 * rest of the file left unasserted, so a mutant that changes it now fails.
 */
it('renders a stable section structure', function (): void {
    // Pins the markup itself, so reordering or dropping a concatenated fragment fails
    $html = renderLocationRegularHours([openRule(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])]);

    expect($html)->toBe(
        '<section class="location-hours" aria-label="Hours">'
        .'<h2>Hours</h2>'
        .'<dl class="location-hours-list">'
        .'<dt class="location-hours-days">Monday &ndash; Friday</dt>'
        .'<dd class="location-hours-times">'.apTime('8', 'a.m.').' to '.apTime('5', 'p.m.').'</dd>'
        .'</dl>'
        .'</section>'
    );
});

it('keeps reading rules after skipping a telemedicine one', function (): void {
    // A skipped rule must not end the walk: the in-person rule after it still renders
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => ['monday'],
            'service_mode' => 'telemedicine',
            'status' => 'open',
            'time_span' => [['open' => '20:00', 'close' => '22:00']],
        ],
        openRule(['tuesday'], '09:00', '10:00'),
    ]);

    expect($html)->toContain('Tuesday');
});

it('keeps reading rules after one that resolves to nothing', function (): void {
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => ['monday'],
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [],
        ],
        openRule(['tuesday'], '09:00', '10:00'),
    ]);

    expect($html)->toContain('Tuesday');
});

it('keeps reading spans after one that is missing a time', function (): void {
    // Both guards must continue rather than abandon the remaining spans
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => ['monday'],
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [
                ['open' => '08:00', 'close' => null],
                ['open' => null, 'close' => '09:00'],
                ['open' => '13:00', 'close' => '17:00'],
            ],
        ],
    ]);

    expect($html)->toContain(apTime('1', 'p.m.').' to '.apTime('5', 'p.m.'));
});

it('reads days from an augmented field value', function (): void {
    // The template passes augmented Statamic values, not the plain arrays above
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => new Value(['monday', 'tuesday', 'wednesday']),
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [['open' => '08:00', 'close' => '17:00']],
        ],
    ]);

    expect($html)->toContain('Monday &ndash; Wednesday');
});

it('reads day keys from augmented labeled options', function (): void {
    // A checkboxes field augments each selection to a LabeledValue
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => [
                new LabeledValue('saturday', 'Saturday'),
                new Value('sunday'),
            ],
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [['open' => '09:00', 'close' => '12:00']],
        ],
    ]);

    expect($html)->toContain('Saturday and Sunday');
});

it('reads a day key from an augmented option row', function (): void {
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => [['key' => 'friday', 'value' => 'friday']],
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [['open' => '09:00', 'close' => '12:00']],
        ],
    ]);

    expect($html)->toContain('Friday');
});

it('trims whitespace around a day key rather than dropping the day', function (): void {
    $html = renderLocationRegularHours([openRule([' monday '])]);

    expect($html)->toContain('Monday');
});

it('ignores a day named twice rather than repeating it', function (): void {
    $html = renderLocationRegularHours([openRule(['monday', 'monday'])]);

    /** @var string $html */
    expect(substr_count($html, 'Monday'))->toBe(1);
});

it('prefers an option row key over its stored value', function (): void {
    // key and value deliberately differ, so reading the wrong one is observable
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => [['key' => 'friday', 'value' => 'not-a-day']],
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [['open' => '09:00', 'close' => '12:00']],
        ],
    ]);

    expect($html)->toContain('Friday');
});

it('falls back to an option row value when it carries no key', function (): void {
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => [['value' => 'thursday']],
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [['open' => '09:00', 'close' => '12:00']],
        ],
    ]);

    expect($html)->toContain('Thursday');
});

it('trims whitespace around an option row key', function (): void {
    $html = renderLocationRegularHours([
        [
            'type' => 'custom',
            'days' => [['key' => ' saturday ']],
            'service_mode' => 'in_person',
            'status' => 'open',
            'time_span' => [['open' => '09:00', 'close' => '12:00']],
        ],
    ]);

    expect($html)->toContain('Saturday');
});
