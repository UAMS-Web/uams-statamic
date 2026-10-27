<?php

declare(strict_types=1);

use App\Tags\LocationModifiedHoursNotice;
use App\Tags\SchemaLocationHours;
use Illuminate\Support\Carbon;
use Statamic\Facades\Antlers;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Tests\Support\StacheIsolatedTestCase;

// Isolated Stache so the seeded `uams_fad_settings` global persists to a throwaway
// directory (never the real `content/` submodule) yet still reads back via findByHandle.
uses(StacheIsolatedTestCase::class);

/**
 * End-to-end wiring for the org-wide holiday-closure broadcast (UAMS-Web/uams-statamic-archive#1327): both hours
 * consumers read the central closures from the Find-a-Doc Settings global and the
 * per-location opt-out from the render cascade, so a system-wide closure fans out
 * to the JSON-LD ({@see SchemaLocationHours}) and the visitor notice
 * ({@see LocationModifiedHoursNotice}) while an opted-out location is
 * unaffected. The merge algorithm itself is covered in SystemLocationClosuresTest.
 *
 * @command php artisan test --compact tests/Feature/Tags/SystemLocationClosuresBroadcastTest.php
 */
const SCHEMA_SPECIAL_TAG = '{{ schema_location_hours output="special" :special_hours="special_hours" }}';
const MODIFIED_HOURS_NOTICE_TAG = '{{ location_modified_hours_notice :special_hours="special_hours" }}';

beforeEach(function (): void {
    // Pin "today" so the future-dated closure never flips to expired.
    $this->travelTo(Carbon::parse('2026-07-11 09:00:00'));

    // Seed a single system-wide closure (Thanksgiving 2026) on the FaD global.
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('uams_fad_settings');
    $set->title('Find-a-Doc Settings');
    $set->save();
    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();
    /** @var Variables $vars */
    $vars = $set->makeLocalization($defaultSite->handle());
    $vars->data(['location_closures' => [[
        'type' => 'holiday',
        'title' => 'Thanksgiving',
        'rules' => [[
            'type' => 'rule',
            'date' => '2026-11-26',
            'through' => null,
            'service_mode' => 'in_person',
            'status' => 'closed',
            'time_span' => [],
        ]],
    ]]]);
    $vars->save();
});

/**
 * @param  array<string, mixed>  $context
 */
function renderBroadcast(string $tag, array $context): string
{
    return trim((string) Antlers::parse($tag, $context, true));
}

/**
 * Overwrite the seeded global's closures with the given rows (default site).
 *
 * @param  array<int, array<string, mixed>>  $rows
 */
function reseedClosures(array $rows): void
{
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('uams_fad_settings');
    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();
    /** @var Variables $vars */
    $vars = $set->in($defaultSite->handle());
    $vars->data(['location_closures' => $rows]);
    $vars->save();
}

it('emits the central closure in a location\'s JSON-LD when it honors the broadcast', function () {
    /** @var array<int, array<string, mixed>> $json */
    $json = json_decode(renderBroadcast(SCHEMA_SPECIAL_TAG, [
        'special_hours' => [],
        'system_closures' => 'honor',
    ]), true);

    // The Thanksgiving all-day closure appears as a date-specific spec.
    expect($json)->toHaveCount(1)
        ->and($json[0]['validFrom'])->toBe('2026-11-26')
        ->and($json[0]['validThrough'])->toBe('2026-11-26')
        ->and($json[0]['opens'])->toBe('00:00')
        ->and($json[0]['closes'])->toBe('00:00');
});

it('emits no central closure in the JSON-LD when the location opts out', function () {
    $out = renderBroadcast(SCHEMA_SPECIAL_TAG, [
        'special_hours' => [],
        'system_closures' => 'suppress',
    ]);

    // Nothing local, broadcast suppressed → the property is omitted entirely.
    expect($out)->toBe('');
});

it('honors the broadcast by default (no opt-out value set)', function () {
    /** @var array<int, array<string, mixed>> $json */
    $json = json_decode(renderBroadcast(SCHEMA_SPECIAL_TAG, [
        'special_hours' => [],
    ]), true);

    expect($json)->toHaveCount(1)
        ->and($json[0]['validFrom'])->toBe('2026-11-26');
});

it('surfaces the central closure in the visitor-facing modified-hours notice', function () {
    $html = renderBroadcast(MODIFIED_HOURS_NOTICE_TAG, [
        'special_hours' => [],
        'system_closures' => 'honor',
    ]);

    expect($html)->toContain('Modified Hours')
        ->toContain('Thanksgiving')
        ->toContain('Closed')
        ->toContain('Nov 26, 2026');
});

it('shows no central closure in the notice when the location opts out', function () {
    $html = renderBroadcast(MODIFIED_HOURS_NOTICE_TAG, [
        'special_hours' => [],
        'system_closures' => 'suppress',
    ]);

    expect($html)->toBe('');
});

it('lets a location override the central closure on the same date (notice shows only the local entry)', function () {
    // The location closes for its own reason on the same date the org broadcasts.
    $html = renderBroadcast(MODIFIED_HOURS_NOTICE_TAG, [
        'special_hours' => [[
            'type' => 'holiday',
            'title' => 'Local Facilities Closure',
            'rules' => [[
                'type' => 'rule',
                'date' => '2026-11-26',
                'through' => null,
                'service_mode' => 'in_person',
                'status' => 'closed',
                'time_span' => [],
            ]],
        ]],
        'system_closures' => 'honor',
    ]);

    // The location's own entry wins; the central "Thanksgiving" is not duplicated.
    expect($html)->toContain('Local Facilities Closure')
        ->and($html)->not->toContain('Thanksgiving');
});

it('applies each consumer\'s own service-mode filter to a telemedicine central closure', function () {
    // A telemedicine-only central closure is authorable; the merge preserves it and
    // each consumer decides. The JSON-LD describes the physical premises, so it skips
    // telemedicine; the visitor notice renders every mode (its pre-existing behavior).
    reseedClosures([[
        'type' => 'holiday',
        'title' => 'Telehealth Holiday',
        'rules' => [[
            'type' => 'rule',
            'date' => '2026-11-26',
            'through' => null,
            'service_mode' => 'telemedicine',
            'status' => 'closed',
            'time_span' => [],
        ]],
    ]]);

    // JSON-LD omits it (physical premises only).
    expect(renderBroadcast(SCHEMA_SPECIAL_TAG, ['special_hours' => [], 'system_closures' => 'honor']))->toBe('');

    // The visitor notice renders it.
    expect(renderBroadcast(MODIFIED_HOURS_NOTICE_TAG, ['special_hours' => [], 'system_closures' => 'honor']))
        ->toContain('Telehealth Holiday')
        ->toContain('Nov 26, 2026');
});
