<?php

declare(strict_types=1);

use App\Tags\SchemaLocationHours;
use Illuminate\Support\Carbon;
use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

// Pin "today" so the fixed future/past date literals below (and the UAMS-Web/uams-statamic-archive#1328
// auto-expiry filter that reads `Carbon::today()`) are deterministic regardless
// of the real calendar date — otherwise a fixture dated 2026-11-26 silently
// flips from "future" to "past" once that day arrives.
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-07-11 09:00:00'));
});

/**
 * Unit tests for the `schema_location_hours` Antlers tag.
 *
 * Engine-path augmented shapes (checkbox day rows, button-group LabeledValues,
 * Carbon dates, replicator `Values`) are covered end-to-end in
 * SchemaJsonLdLocationTest.
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaLocationHoursTest.php
 */
const HOURS_REGULAR_TAG = '{{ schema_location_hours :regular_hours="regular_hours" }}';
const HOURS_SPECIAL_TAG = '{{ schema_location_hours output="special" :special_hours="special_hours" }}';
const SPEC_TYPE = 'https://schema.org/OpeningHoursSpecification';

/**
 * @param  array<int, mixed>  $rules
 */
function renderRegularHours(array $rules): mixed
{
    return json_decode(trim((string) Antlers::parse(
        HOURS_REGULAR_TAG,
        ['regular_hours' => $rules],
        true
    )), true);
}

/**
 * @param  array<int, mixed>  $special
 */
function renderSpecialHours(array $special): mixed
{
    return json_decode(trim((string) Antlers::parse(
        HOURS_SPECIAL_TAG,
        ['special_hours' => $special],
        true
    )), true);
}

/**
 * @param  array<int, string>  $days
 * @param  array<int, array<string, string|null>>  $timeSpan
 * @return array<string, mixed>
 */
function regularRule(array $days, string $status, array $timeSpan = [], string $serviceMode = 'in_person'): array
{
    return [
        'type' => 'custom',
        'days' => $days,
        'service_mode' => $serviceMode,
        'status' => $status,
        'time_span' => $timeSpan,
    ];
}

it('exposes schema_location_hours as the tag handle', function (): void {
    expect(SchemaLocationHours::handle())->toBe('schema_location_hours');
});

it('emits an OpeningHoursSpecification from a weekday open rule', function (): void {
    $specs = renderRegularHours([
        regularRule(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], 'open', [
            ['open' => '08:00', 'close' => '17:00'],
        ]),
    ]);

    expect($specs)->toBe([
        [
            '@type' => SPEC_TYPE,
            'dayOfWeek' => [
                'https://schema.org/Monday',
                'https://schema.org/Tuesday',
                'https://schema.org/Wednesday',
                'https://schema.org/Thursday',
                'https://schema.org/Friday',
            ],
            'opens' => '08:00',
            'closes' => '17:00',
        ],
    ]);
});

it('emits one spec per time span', function (): void {
    /** @var array<int, array<string, mixed>> $specs */
    $specs = renderRegularHours([
        regularRule(['monday'], 'open', [
            ['open' => '08:00', 'close' => '11:30'],
            ['open' => '12:30', 'close' => '16:30'],
        ]),
    ]);

    expect($specs)->toHaveCount(2);
    expect(array_column($specs, 'opens'))->toBe(['08:00', '12:30']);
    expect(array_column($specs, 'closes'))->toBe(['11:30', '16:30']);
});

it('emits a single-day dayOfWeek as a scalar', function (): void {
    /** @var array<int, array<string, mixed>> $specs */
    $specs = renderRegularHours([
        regularRule(['sunday'], 'open', [['open' => '10:00', 'close' => '14:00']]),
    ]);

    expect($specs[0]['dayOfWeek'])->toBe('https://schema.org/Sunday');
});

it('emits a round-the-clock spec for an open_24h rule', function (): void {
    /** @var array<int, array<string, mixed>> $specs */
    $specs = renderRegularHours([
        regularRule(['monday'], 'open_24h'),
    ]);

    expect($specs[0]['opens'])->toBe('00:00');
    expect($specs[0]['closes'])->toBe('23:59');
});

it('omits a regular closed rule entirely', function (): void {
    $specs = renderRegularHours([
        regularRule(['saturday', 'sunday'], 'closed'),
    ]);

    expect($specs)->toBeNull();
});

it('skips telemedicine-mode rules', function (): void {
    $specs = renderRegularHours([
        regularRule(['monday'], 'open', [['open' => '08:00', 'close' => '17:00']], 'telemedicine'),
    ]);

    expect($specs)->toBeNull();
});

it('keeps in-person rules while skipping telemedicine rules', function (): void {
    /** @var array<int, array<string, mixed>> $specs */
    $specs = renderRegularHours([
        regularRule(['monday'], 'open', [['open' => '08:00', 'close' => '17:00']], 'in_person'),
        regularRule(['monday'], 'open', [['open' => '18:00', 'close' => '20:00']], 'telemedicine'),
    ]);

    expect($specs)->toHaveCount(1);
    expect($specs[0]['opens'])->toBe('08:00');
});

it('emits an explicit all-day closure for a holiday closed rule', function (): void {
    $specs = renderSpecialHours([
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving',
            'rules' => [
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($specs)->toBe([
        [
            '@type' => SPEC_TYPE,
            'opens' => '00:00',
            'closes' => '00:00',
            'validFrom' => '2026-11-26',
            'validThrough' => '2026-11-26',
        ],
    ]);
});

it('carries the holiday through-date as the validThrough window', function (): void {
    /** @var array<int, array<string, mixed>> $specs */
    $specs = renderSpecialHours([
        [
            'type' => 'holiday',
            'title' => 'Winter Break',
            'rules' => [
                ['date' => '2026-12-24', 'through' => '2026-12-26', 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($specs[0]['validFrom'])->toBe('2026-12-24');
    expect($specs[0]['validThrough'])->toBe('2026-12-26');
});

it('emits a temporary change spec with both a dayOfWeek and a validity window', function (): void {
    $specs = renderSpecialHours([
        [
            'type' => 'temporary_change',
            'title' => 'Renovation',
            'valid_from' => '2026-07-01',
            'valid_through' => '2026-07-15',
            'rules' => [
                regularRule(['monday', 'tuesday'], 'open', [['open' => '09:00', 'close' => '12:00']]),
            ],
        ],
    ]);

    expect($specs)->toBe([
        [
            '@type' => SPEC_TYPE,
            'dayOfWeek' => ['https://schema.org/Monday', 'https://schema.org/Tuesday'],
            'opens' => '09:00',
            'closes' => '12:00',
            'validFrom' => '2026-07-01',
            'validThrough' => '2026-07-15',
        ],
    ]);
});

it('omits validThrough for an ongoing temporary change', function (): void {
    /** @var array<int, array<string, mixed>> $specs */
    $specs = renderSpecialHours([
        [
            'type' => 'temporary_change',
            'title' => 'Ongoing',
            'valid_from' => '2026-07-01',
            'valid_through' => null,
            'rules' => [
                regularRule(['monday'], 'open', [['open' => '09:00', 'close' => '12:00']]),
            ],
        ],
    ]);

    expect($specs[0])->not->toHaveKey('validThrough');
    expect($specs[0]['validFrom'])->toBe('2026-07-01');
});

it('de-duplicates repeated days and skips unknown day keys', function (): void {
    /** @var array<int, array<string, mixed>> $specs */
    $specs = renderRegularHours([
        regularRule(['monday', 'monday', 'notaday', 'tuesday'], 'open', [['open' => '08:00', 'close' => '17:00']]),
    ]);

    expect($specs[0]['dayOfWeek'])->toBe([
        'https://schema.org/Monday',
        'https://schema.org/Tuesday',
    ]);
});

it('emits nothing for a rule whose days are all unknown', function (): void {
    $specs = renderRegularHours([
        regularRule(['notaday', 'alsonotaday'], 'open', [['open' => '08:00', 'close' => '17:00']]),
    ]);

    expect($specs)->toBeNull();
});

it('emits nothing when there are no hours rules', function (): void {
    expect(trim((string) Antlers::parse(HOURS_REGULAR_TAG, ['regular_hours' => []], true)))->toBe('');
    expect(trim((string) Antlers::parse(HOURS_SPECIAL_TAG, ['special_hours' => []], true)))->toBe('');
});

it('omits a holiday whose date window is entirely in the past (#1328)', function (): void {
    // Today is pinned to 2026-07-11; this holiday ended 2025-11-27.
    $specs = renderSpecialHours([
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving 2025',
            'rules' => [
                ['date' => '2025-11-27', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($specs)->toBeNull();
});

it('still emits a holiday active today or in the future (#1328)', function (): void {
    $specs = renderSpecialHours([
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving 2026',
            'rules' => [
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($specs)->toBe([
        [
            '@type' => SPEC_TYPE,
            'opens' => '00:00',
            'closes' => '00:00',
            'validFrom' => '2026-11-26',
            'validThrough' => '2026-11-26',
        ],
    ]);
});

it('omits an expired holiday but keeps a future one in the same block (#1328)', function (): void {
    $specs = renderSpecialHours([
        [
            'type' => 'holiday',
            'title' => 'Holidays',
            'rules' => [
                ['date' => '2025-01-01', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
                ['date' => '2026-12-25', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($specs)->toBe([
        [
            '@type' => SPEC_TYPE,
            'opens' => '00:00',
            'closes' => '00:00',
            'validFrom' => '2026-12-25',
            'validThrough' => '2026-12-25',
        ],
    ]);
});

it('keeps a holiday whose through-date is still in the future though it started in the past (#1328)', function (): void {
    /** @var array<int, array<string, mixed>> $specs */
    $specs = renderSpecialHours([
        [
            'type' => 'holiday',
            'title' => 'Extended closure',
            'rules' => [
                ['date' => '2026-07-05', 'through' => '2026-07-20', 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($specs[0]['validFrom'])->toBe('2026-07-05');
    expect($specs[0]['validThrough'])->toBe('2026-07-20');
});

it('omits a temporary change whose valid_through is past but keeps an ongoing one (#1328)', function (): void {
    // Expired temporary change (ended 2026-06-30) is dropped.
    expect(renderSpecialHours([
        [
            'type' => 'temporary_change',
            'title' => 'Old renovation',
            'valid_from' => '2026-06-01',
            'valid_through' => '2026-06-30',
            'rules' => [regularRule(['monday'], 'closed')],
        ],
    ]))->toBeNull();

    // An ongoing temporary change (blank valid_through) never expires.
    $ongoing = renderSpecialHours([
        [
            'type' => 'temporary_change',
            'title' => 'Ongoing change',
            'valid_from' => '2025-01-01',
            'valid_through' => null,
            'rules' => [regularRule(['monday'], 'closed')],
        ],
    ]);

    expect($ongoing)->toBe([
        [
            '@type' => SPEC_TYPE,
            'dayOfWeek' => 'https://schema.org/Monday',
            'opens' => '00:00',
            'closes' => '00:00',
            'validFrom' => '2025-01-01',
        ],
    ]);
});

it('still emits a holiday whose window ends exactly today (boundary, not expired) (#1328)', function (): void {
    // "today" is pinned to 2026-07-11; a spec ending today must still emit.
    $specs = renderSpecialHours([
        [
            'type' => 'holiday',
            'title' => 'Ends Today',
            'rules' => [
                ['date' => '2026-07-11', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($specs)->toBe([
        [
            '@type' => SPEC_TYPE,
            'opens' => '00:00',
            'closes' => '00:00',
            'validFrom' => '2026-07-11',
            'validThrough' => '2026-07-11',
        ],
    ]);
});

it('still emits a temporary change whose valid_through is exactly today (#1328)', function (): void {
    $specs = renderSpecialHours([
        [
            'type' => 'temporary_change',
            'title' => 'Ends Today',
            'valid_from' => '2026-07-05',
            'valid_through' => '2026-07-11',
            'rules' => [regularRule(['monday'], 'closed')],
        ],
    ]);

    expect($specs)->toBe([
        [
            '@type' => SPEC_TYPE,
            'dayOfWeek' => 'https://schema.org/Monday',
            'opens' => '00:00',
            'closes' => '00:00',
            'validFrom' => '2026-07-05',
            'validThrough' => '2026-07-11',
        ],
    ]);
});

/**
 * Run the tag directly with raw parameter values (so a Statamic Value or
 * LabeledValue reaches the tag as it is) and decode its JSON.
 *
 * @param  array<array-key, mixed>  $params
 */
function hoursSpecsFromParams(array $params): mixed
{
    $tag = new SchemaLocationHours;
    $tag->setContext([]);
    $tag->setParameters($params);

    return json_decode($tag->index(), true);
}

it('skips a holiday rule with no date but keeps a dated one', function (): void {
    $specs = renderSpecialHours([
        [
            'type' => 'holiday',
            'title' => 'Closures',
            'rules' => [
                ['date' => null, 'through' => '2026-11-27', 'service_mode' => 'in_person', 'status' => 'closed'],
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($specs)->toBe([
        ['@type' => SPEC_TYPE, 'opens' => '00:00', 'closes' => '00:00', 'validFrom' => '2026-11-26', 'validThrough' => '2026-11-26'],
    ]);
});

it('emits nothing for a temporary change with no start date', function (): void {
    expect(renderSpecialHours([
        [
            'type' => 'temporary_change',
            'title' => 'Undated',
            'valid_from' => null,
            'valid_through' => '2026-07-15',
            'rules' => [regularRule(['monday'], 'open', [['open' => '09:00', 'close' => '12:00']])],
        ],
    ]))->toBeNull();
});

it('skips telemedicine and day-less rules inside a temporary change', function (): void {
    $specs = renderSpecialHours([
        [
            'type' => 'temporary_change',
            'title' => 'Mixed',
            'valid_from' => '2026-07-01',
            'valid_through' => null,
            'rules' => [
                regularRule(['monday'], 'open', [['open' => '08:00', 'close' => '09:00']], 'telemedicine'),
                regularRule([], 'open', [['open' => '08:00', 'close' => '09:00']]),
                regularRule(['tuesday'], 'open', [['open' => '10:00', 'close' => '11:00']]),
            ],
        ],
    ]);

    expect($specs)->toBe([
        ['@type' => SPEC_TYPE, 'dayOfWeek' => 'https://schema.org/Tuesday', 'opens' => '10:00', 'closes' => '11:00', 'validFrom' => '2026-07-01'],
    ]);
});

it('skips a time span missing its open or close time', function (): void {
    expect(renderRegularHours([
        regularRule(['monday'], 'open', [
            ['open' => null, 'close' => '12:00'],
            ['open' => '08:00', 'close' => null],
            ['open' => '13:00', 'close' => '17:00'],
        ]),
    ]))->toBe([
        ['@type' => SPEC_TYPE, 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '13:00', 'closes' => '17:00'],
    ]);
});

it('reads days from Values, labeled options and option rows', function (): void {
    $specs = hoursSpecsFromParams(['regular_hours' => [[
        'type' => 'custom',
        'days' => new Value([
            new Value('monday'),
            new LabeledValue('tuesday', 'Tuesday'),
            ['key' => 'wednesday', 'value' => 'ignored'],
            ['value' => 'thursday'],
            ['key' => '   ', 'value' => 'friday'],
            ['key' => 5],
            7,
            ' saturday ',
            '',
        ]),
        'service_mode' => 'in_person',
        'status' => 'open_24h',
        'time_span' => [],
    ]]]);

    expect($specs)->toBe([[
        '@type' => SPEC_TYPE,
        'dayOfWeek' => [
            'https://schema.org/Monday',
            'https://schema.org/Tuesday',
            'https://schema.org/Wednesday',
            'https://schema.org/Thursday',
            'https://schema.org/Saturday',
        ],
        'opens' => '00:00',
        'closes' => '23:59',
    ]]);
});

it('emits nothing for days that are not a list', function (): void {
    expect(hoursSpecsFromParams(['regular_hours' => [[
        'type' => 'custom',
        'days' => 'monday',
        'service_mode' => 'in_person',
        'status' => 'open_24h',
        'time_span' => [],
    ]]]))->toBeNull();
});

it('emits unescaped slashes in its JSON', function (): void {
    $tag = new SchemaLocationHours;
    $tag->setContext([]);
    $tag->setParameters(['regular_hours' => [regularRule(['monday'], 'open_24h')]]);

    expect($tag->index())->toBe('[{"@type":"https://schema.org/OpeningHoursSpecification","dayOfWeek":"https://schema.org/Monday","opens":"00:00","closes":"23:59"}]');
});

it('emits an empty string when the specs cannot be encoded as JSON', function (): void {
    $tag = new SchemaLocationHours;
    $tag->setContext([]);
    $tag->setParameters(['regular_hours' => [
        regularRule(['monday'], 'open', [['open' => "08\xB1", 'close' => '17:00']]),
    ]]);

    expect($tag->index())->toBe('');
});

it('keeps reading regular rules after a skipped telemedicine or day-less one', function (): void {
    expect(renderRegularHours([
        regularRule(['monday'], 'open_24h', [], 'telemedicine'),
        regularRule([], 'open_24h'),
        regularRule(['tuesday'], 'open_24h'),
    ]))->toBe([
        ['@type' => SPEC_TYPE, 'dayOfWeek' => 'https://schema.org/Tuesday', 'opens' => '00:00', 'closes' => '23:59'],
    ]);
});

it('keeps reading special hours after a skipped holiday rule or temporary change', function (): void {
    $specs = renderSpecialHours([
        [
            'type' => 'holiday',
            'title' => 'Closures',
            'rules' => [
                ['date' => '2026-11-25', 'through' => null, 'service_mode' => 'telemedicine', 'status' => 'closed'],
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
        [
            'type' => 'temporary_change',
            'title' => 'Undated',
            'valid_from' => null,
            'valid_through' => null,
            'rules' => [regularRule(['monday'], 'open_24h')],
        ],
        [
            'type' => 'temporary_change',
            'title' => 'Expired',
            'valid_from' => '2026-06-01',
            'valid_through' => '2026-06-30',
            'rules' => [regularRule(['monday'], 'open_24h')],
        ],
        [
            'type' => 'temporary_change',
            'title' => 'Current',
            'valid_from' => '2026-07-01',
            'valid_through' => null,
            'rules' => [regularRule(['wednesday'], 'open_24h')],
        ],
    ]);

    expect($specs)->toBe([
        ['@type' => SPEC_TYPE, 'opens' => '00:00', 'closes' => '00:00', 'validFrom' => '2026-11-26', 'validThrough' => '2026-11-26'],
        ['@type' => SPEC_TYPE, 'dayOfWeek' => 'https://schema.org/Wednesday', 'opens' => '00:00', 'closes' => '23:59', 'validFrom' => '2026-07-01'],
    ]);
});

it('trims a padded option key before matching it to a day', function (): void {
    expect(hoursSpecsFromParams(['regular_hours' => [[
        'type' => 'custom',
        'days' => [['key' => ' friday ']],
        'service_mode' => 'in_person',
        'status' => 'open_24h',
        'time_span' => [],
    ]]]))->toBe([
        ['@type' => SPEC_TYPE, 'dayOfWeek' => 'https://schema.org/Friday', 'opens' => '00:00', 'closes' => '23:59'],
    ]);
});
