<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Location JSON-LD: regular, special and engine-path opening hours (UAMS-Web/uams-statamic-archive#967).
 *
 * One of five suites split from the former single `SchemaJsonLdLocationTest`
 * (UAMS-Web/uams-statamic-archive#1797) — that class ran 1085s of a 1306s parallel band, and paratest
 * distributes by class, so it bounded the band on its own. Shared fixtures and
 * node builders live in `tests/Support/SchemaLocationFixtures.php`.
 *
 * Renders the node directly via
 * `SchemaOutputService::buildStandaloneSchemaObjectNode()` against an in-test
 * fixture location entry built in memory, so no content is saved. The
 * schema-template entries it resolves ship in the `content/` submodule, so this
 * suite is content-dependent: it runs green in CI / locally where `content/` is
 * present.
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdLocationHoursTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();

    // Bind a request so @id resolution has a host to anchor on.
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));

    // Pin "today" so the fixed future-dated special-hours fixtures below stay
    // deterministic against the UAMS-Web/uams-statamic-archive#1328 auto-expiry filter regardless of the real
    // calendar date (a fixture dated 2026-11-26 must not silently expire once
    // that day passes).
    $this->travelTo(Carbon::parse('2026-07-11 09:00:00'));
});

it('emits openingHoursSpecification from the regular weekly rules', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'hours_variable_query' => false,
        'regular_hours' => [
            [
                'type' => 'weekday',
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'service_mode' => 'in_person',
                'status' => 'open',
                'time_span' => [
                    ['type' => 'new_set', 'open' => '08:00', 'close' => '17:00', 'enabled' => true],
                ],
                'enabled' => true,
            ],
            [
                'type' => 'weekend',
                'days' => ['saturday', 'sunday'],
                'service_mode' => 'in_person',
                'status' => 'closed',
                'enabled' => true,
            ],
        ],
    ]);

    // The closed weekend rule is omitted; only the open weekday spec remains
    // (single spec → flattened to the bare object).
    expect($node['openingHoursSpecification'])->toBe([
        '@type' => 'https://schema.org/OpeningHoursSpecification',
        'dayOfWeek' => [
            'https://schema.org/Monday',
            'https://schema.org/Tuesday',
            'https://schema.org/Wednesday',
            'https://schema.org/Thursday',
            'https://schema.org/Friday',
        ],
        'opens' => '08:00',
        'closes' => '17:00',
    ]);
});

it('emits specialOpeningHoursSpecification from a holiday closure', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'special_hours' => [
            [
                'type' => 'holiday',
                'title' => 'Thanksgiving',
                'rules' => [
                    [
                        'type' => 'rule',
                        'date' => '2026-11-26',
                        'service_mode' => 'in_person',
                        'status' => 'closed',
                        'enabled' => true,
                    ],
                ],
                'enabled' => true,
            ],
        ],
    ]);

    expect($node['specialOpeningHoursSpecification'])->toBe([
        '@type' => 'https://schema.org/OpeningHoursSpecification',
        'opens' => '00:00',
        'closes' => '00:00',
        'validFrom' => '2026-11-26',
        'validThrough' => '2026-11-26',
    ]);
});

it('omits both hours properties when the location has no hours rules', function (): void {
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
    ]);

    expect($node)->not->toHaveKey('openingHoursSpecification');
    expect($node)->not->toHaveKey('specialOpeningHoursSpecification');
});

it('emits a round-the-clock openingHoursSpecification from an open_24h rule (engine path)', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS 24-Hour Clinic',
        'hours_variable_query' => false,
        'regular_hours' => [
            [
                'type' => 'daily',
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'service_mode' => 'in_person',
                'status' => 'open_24h',
                'enabled' => true,
            ],
        ],
    ]);

    expect($node['openingHoursSpecification'])->toBe([
        '@type' => 'https://schema.org/OpeningHoursSpecification',
        'dayOfWeek' => [
            'https://schema.org/Monday',
            'https://schema.org/Tuesday',
            'https://schema.org/Wednesday',
            'https://schema.org/Thursday',
            'https://schema.org/Friday',
            'https://schema.org/Saturday',
            'https://schema.org/Sunday',
        ],
        'opens' => '00:00',
        'closes' => '23:59',
    ]);
});

it('skips a telemedicine rule and emits a single-day dayOfWeek scalar (engine path)', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'hours_variable_query' => false,
        'regular_hours' => [
            [
                'type' => 'custom',
                'days' => ['monday'],
                'service_mode' => 'in_person',
                'status' => 'open',
                'time_span' => [['type' => 'new_set', 'open' => '08:00', 'close' => '17:00', 'enabled' => true]],
                'enabled' => true,
            ],
            [
                'type' => 'custom',
                'days' => ['monday'],
                'service_mode' => 'telemedicine',
                'status' => 'open',
                'time_span' => [['type' => 'new_set', 'open' => '18:00', 'close' => '20:00', 'enabled' => true]],
                'enabled' => true,
            ],
        ],
    ]);

    // Only the in-person rule survives; a single day emits a scalar dayOfWeek.
    expect($node['openingHoursSpecification'])->toBe([
        '@type' => 'https://schema.org/OpeningHoursSpecification',
        'dayOfWeek' => 'https://schema.org/Monday',
        'opens' => '08:00',
        'closes' => '17:00',
    ]);
});

it('emits a temporary_change specialOpeningHoursSpecification with dayOfWeek and a Carbon window (engine path)', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'special_hours' => [
            [
                'type' => 'temporary_change',
                'title' => 'Renovation',
                'valid_from' => '2026-07-01',
                'valid_through' => '2026-07-15',
                'rules' => [
                    [
                        'type' => 'weekday',
                        'days' => ['monday', 'tuesday'],
                        'service_mode' => 'in_person',
                        'status' => 'open',
                        'time_span' => [['type' => 'new_set', 'open' => '09:00', 'close' => '12:00', 'enabled' => true]],
                        'enabled' => true,
                    ],
                ],
                'enabled' => true,
            ],
        ],
    ]);

    expect($node['specialOpeningHoursSpecification'])->toBe([
        '@type' => 'https://schema.org/OpeningHoursSpecification',
        'dayOfWeek' => ['https://schema.org/Monday', 'https://schema.org/Tuesday'],
        'opens' => '09:00',
        'closes' => '12:00',
        'validFrom' => '2026-07-01',
        'validThrough' => '2026-07-15',
    ]);
});

it('carries a multi-day holiday closure window from date + through (engine path)', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'special_hours' => [
            [
                'type' => 'holiday',
                'title' => 'Winter Break',
                'rules' => [
                    ['type' => 'rule', 'date' => '2026-12-24', 'through' => '2026-12-26', 'service_mode' => 'in_person', 'status' => 'closed', 'enabled' => true],
                ],
                'enabled' => true,
            ],
        ],
    ]);

    expect($node['specialOpeningHoursSpecification'])->toBe([
        '@type' => 'https://schema.org/OpeningHoursSpecification',
        'opens' => '00:00',
        'closes' => '00:00',
        'validFrom' => '2026-12-24',
        'validThrough' => '2026-12-26',
    ]);
});
