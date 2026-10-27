<?php

declare(strict_types=1);

use App\Services\Locations\SystemLocationClosures;
use Illuminate\Support\Carbon;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Tests\Support\StacheIsolatedTestCase;

// Isolated Stache so the seeded `uams_fad_settings` global persists to a throwaway
// directory (never the real `content/` submodule) yet still reads back via findByHandle.
uses(StacheIsolatedTestCase::class);

/**
 * Unit tests for the org-wide holiday-closure merge (UAMS-Web/uams-statamic-archive#1327).
 *
 * The pure `merge()` is exercised directly with plain replicator-row arrays (no
 * global / entry reads), and the global-read glue (`centralClosureRows()` /
 * `effectiveSpecialHours()`) against a created `uams_fad_settings` global. The
 * merge is date-relative (coverage compares calendar dates), not today-relative —
 * auto-expiry is the consumers' job — so these need no time travel.
 *
 * @command php artisan test --compact tests/Feature/Services/Locations/SystemLocationClosuresTest.php
 */

/**
 * A `holiday`-shaped special-hours entry.
 *
 * @param  array<int, array<string, mixed>>  $rules
 * @return array<string, mixed>
 */
function closureHoliday(string $title, array $rules): array
{
    return ['type' => 'holiday', 'title' => $title, 'description' => null, 'rules' => $rules];
}

/**
 * A single hours rule within a holiday / closure.
 *
 * @return array<string, mixed>
 */
function closureRule(string $date, ?string $through = null, string $status = 'closed', string $mode = 'in_person'): array
{
    return ['type' => 'rule', 'date' => $date, 'through' => $through, 'service_mode' => $mode, 'status' => $status, 'time_span' => []];
}

/**
 * A `temporary_change`-shaped entry.
 *
 * @param  array<int, array<string, mixed>>  $rules
 * @return array<string, mixed>
 */
function closureTempChange(string $title, string $validFrom, ?string $validThrough, array $rules): array
{
    return ['type' => 'temporary_change', 'title' => $title, 'valid_from' => $validFrom, 'valid_through' => $validThrough, 'rules' => $rules];
}

function closures(): SystemLocationClosures
{
    return app(SystemLocationClosures::class);
}

it('returns the location rows unchanged when there are no central closures', function () {
    $local = [closureHoliday('Local', [closureRule('2026-12-24')])];

    expect(closures()->merge($local, [], true))->toBe($local);
});

it('ignores central closures when the location opts out (not honoring)', function () {
    $local = [closureHoliday('Local', [closureRule('2026-12-24')])];
    $central = [closureHoliday('Thanksgiving', [closureRule('2026-11-26')])];

    expect(closures()->merge($local, $central, false))->toBe($local);
});

it('broadcasts a central closure to a location with no special hours of its own', function () {
    $central = [closureHoliday('Thanksgiving', [closureRule('2026-11-26')])];

    /** @var array<int, array{type: string, title: string, rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge([], $central, true);

    expect($merged)->toHaveCount(1)
        ->and($merged[0]['type'])->toBe('holiday')
        ->and($merged[0]['title'])->toBe('Thanksgiving')
        ->and($merged[0]['rules'])->toHaveCount(1)
        ->and($merged[0]['rules'][0]['date'])->toBe('2026-11-26')
        ->and($merged[0]['rules'][0]['through'])->toBeNull()   // single day
        ->and($merged[0]['rules'][0]['status'])->toBe('closed');
});

it('lets a location override a central closure on the same date (local wins)', function () {
    // The location sets its own (open) Thanksgiving hours...
    $local = [closureHoliday('Our Thanksgiving hours', [closureRule('2026-11-26', status: 'open')])];
    // ...while the org-wide default closes that day.
    $central = [closureHoliday('Thanksgiving', [closureRule('2026-11-26')])];

    /** @var array<int, array{type: string, title: string, rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge($local, $central, true);

    // Only the location's own entry survives; the central closure is suppressed.
    expect($merged)->toHaveCount(1)
        ->and($merged[0]['title'])->toBe('Our Thanksgiving hours');
});

it('keeps a central range only for the dates the location has not covered', function () {
    // Central closes Nov 25–27...
    $central = [closureHoliday('Long weekend', [closureRule('2026-11-25', '2026-11-27')])];
    // ...but the location addresses Nov 26 itself.
    $local = [closureHoliday('Local Nov 26', [closureRule('2026-11-26', status: 'open')])];

    /** @var array<int, array{type: string, title: string, rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge($local, $central, true);

    // Local entry + the central closure split around the covered middle day.
    expect($merged)->toHaveCount(2)
        ->and($merged[1]['title'])->toBe('Long weekend')
        ->and($merged[1]['rules'])->toHaveCount(2)
        ->and($merged[1]['rules'][0])->toMatchArray(['date' => '2026-11-25', 'through' => null])
        ->and($merged[1]['rules'][1])->toMatchArray(['date' => '2026-11-27', 'through' => null]);
});

it('suppresses a central closure inside an ongoing (open-ended) local temporary change', function () {
    // The location is under an ongoing temporary change from Nov 1 with no end date.
    $local = [closureTempChange('Renovation', '2026-11-01', null, [closureRule('2026-11-01', status: 'closed')])];
    $central = [closureHoliday('Thanksgiving', [closureRule('2026-11-26')])];

    /** @var array<int, array{type: string, title: string, rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge($local, $central, true);

    // The open-ended window covers Nov 26, so only the local change remains.
    expect($merged)->toHaveCount(1)
        ->and($merged[0]['title'])->toBe('Renovation');
});

it('does not let a telemedicine-only local rule suppress a physical closure', function () {
    // A telemedicine-only local rule addresses no physical premises...
    $local = [closureHoliday('Telehealth Thanksgiving', [closureRule('2026-11-26', status: 'open', mode: 'telemedicine')])];
    // ...so the physical closure must still broadcast.
    $central = [closureHoliday('Thanksgiving', [closureRule('2026-11-26')])];

    /** @var array<int, array{type: string, title: string, rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge($local, $central, true);

    // Both the local telemedicine entry and the surviving physical closure appear.
    expect($merged)->toHaveCount(2)
        ->and($merged[1]['title'])->toBe('Thanksgiving')
        ->and($merged[1]['rules'][0]['date'])->toBe('2026-11-26');
});

it('handles several central closures independently — a covered one does not swallow a later surviving one', function () {
    // The central source normally holds several holidays (Thanksgiving, Christmas, …).
    $central = [
        closureHoliday('Thanksgiving', [closureRule('2026-11-26')]),
        closureHoliday('Christmas', [closureRule('2026-12-25')]),
    ];
    // The location addresses only Thanksgiving itself.
    $local = [closureHoliday('Local Thanksgiving', [closureRule('2026-11-26', status: 'open')])];

    /** @var array<int, array{type: string, title: string, rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge($local, $central, true);

    // Thanksgiving is dropped (locally covered) yet Christmas still surfaces — the
    // per-entry loop neither short-circuits on the covered entry nor keeps only one.
    expect($merged)->toHaveCount(2)
        ->and($merged[0]['title'])->toBe('Local Thanksgiving')
        ->and($merged[1]['title'])->toBe('Christmas')
        ->and($merged[1]['rules'][0]['date'])->toBe('2026-12-25');
});

it('preserves a telemedicine central rule through the merge (emission filtering is the consumers\' job)', function () {
    // A telemedicine central closure is authorable (the central rule set exposes
    // service_mode). The merge is mode-agnostic: it keeps the rule and its mode, and
    // leaves it to the consumers (JSON-LD skips telemedicine; the notice renders it).
    $central = [closureHoliday('Telehealth closure', [closureRule('2026-11-26', mode: 'telemedicine')])];

    /** @var array<int, array{type: string, title: string, rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge([], $central, true);

    expect($merged)->toHaveCount(1)
        ->and($merged[0]['rules'][0]['service_mode'])->toBe('telemedicine')
        ->and($merged[0]['rules'][0]['date'])->toBe('2026-11-26');
});

it('drops a central closure whose only rules are unparseable or dateless', function () {
    $central = [closureHoliday('Broken', [closureRule('not-a-date'), ['type' => 'rule', 'status' => 'closed']])];

    // The unparseable rule yields no runs; the dateless rule is skipped → nothing survives.
    expect(closures()->merge([], $central, true))->toBe([]);
});

it('reads the central closures from the Find-a-Doc Settings global', function () {
    seedCentralClosures([closureHoliday('Thanksgiving', [closureRule('2026-11-26')])]);

    /** @var array<int, array{type: string, title: string}> $rows */
    $rows = closures()->centralClosureRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['type'])->toBe('holiday')
        ->and($rows[0]['title'])->toBe('Thanksgiving');
});

it('honors the global closures by default and merges them into a location', function () {
    seedCentralClosures([closureHoliday('Thanksgiving', [closureRule('2026-11-26')])]);

    // A null opt-out (existing locations) honors the broadcast.
    /** @var array<int, array{type: string, title: string, rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->effectiveSpecialHours([], null);

    expect($merged)->toHaveCount(1)
        ->and($merged[0]['title'])->toBe('Thanksgiving');
});

it('suppresses the global closures when the location opts out', function () {
    seedCentralClosures([closureHoliday('Thanksgiving', [closureRule('2026-11-26')])]);

    $local = [closureHoliday('Local', [closureRule('2026-12-24')])];

    // Only the explicit `suppress` opts out.
    expect(closures()->effectiveSpecialHours($local, 'suppress'))->toBe($local);
});

/**
 * Create the `uams_fad_settings` global carrying the given central closures on the
 * default site, so the service's global read resolves them.
 *
 * @param  array<int, array<string, mixed>>  $rows
 */
function seedCentralClosures(array $rows): void
{
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('uams_fad_settings');
    $set->title('Find-a-Doc Settings');
    $set->save();

    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();

    /** @var Variables $vars */
    $vars = $set->makeLocalization($defaultSite->handle());
    $vars->data(['location_closures' => $rows]);
    $vars->save();
}

// --- Edge branches (mutation hardening) --------------------------------------

// centralClosureRows(): the `$variables?->get(...)` null-safe — a global with no
// default localization (only `health`) resolves to null via `inDefaultSite()`, so
// the read must short-circuit to no rows rather than fatal.
it('yields no central closures when the global has no default localization', function () {
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('uams_fad_settings');
    $set->title('Find-a-Doc Settings');
    $set->sites(['health']);
    $set->save();

    /** @var Variables $vars */
    $vars = $set->makeLocalization('health');
    $vars->data(['location_closures' => [closureHoliday('Thanksgiving', [closureRule('2026-11-26')])]]);
    $vars->save();

    expect(closures()->centralClosureRows())->toBe([]);
});

// localCoverage(): the loop `continue`s past a telemedicine rule to read a LATER
// in-person rule in the same holiday (not `break` out of the rule loop).
it('processes an in-person rule that follows a telemedicine rule in one holiday', function () {
    $local = [closureHoliday('Mixed', [
        closureRule('2026-11-26', mode: 'telemedicine'),
        closureRule('2026-11-27', status: 'open', mode: 'in_person'),
    ])];
    $central = [closureHoliday('Long weekend', [closureRule('2026-11-26', '2026-11-27')])];

    /** @var array<int, array{title: string, rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge($local, $central, true);

    // The in-person Nov 27 rule covers Nov 27, so the central survives only Nov 26.
    expect($merged[1]['title'])->toBe('Long weekend')
        ->and($merged[1]['rules'])->toHaveCount(1)
        ->and($merged[1]['rules'][0])->toMatchArray(['date' => '2026-11-26', 'through' => null]);
});

// localCoverage(): a local rule's window spans its full `[date, through]` range —
// the `through ?? from` coalesce, not just the start date.
it('covers a local rule\'s full range, not just its start date', function () {
    $local = [closureHoliday('Local range', [closureRule('2026-11-25', '2026-11-27', status: 'open')])];
    $central = [closureHoliday('Long weekend', [closureRule('2026-11-25', '2026-11-27')])];

    // Fully covered by the local Nov 25–27 range → the central closure is dropped.
    expect(closures()->merge($local, $central, true))->toHaveCount(1);
});

// localCoverage(): the entry loop `continue`s past a holiday entry to read a LATER
// temporary-change entry (not `break`).
it('processes a temporary-change entry that follows a holiday entry', function () {
    $local = [
        closureHoliday('Holiday', [closureRule('2026-11-26', status: 'open')]),
        closureTempChange('Change', '2026-11-27', '2026-11-27', [closureRule('2026-11-27')]),
    ];
    $central = [closureHoliday('Long weekend', [closureRule('2026-11-26', '2026-11-27')])];

    // Both local entries cover their day → the central is fully suppressed (two rows).
    expect(closures()->merge($local, $central, true))->toHaveCount(2);
});

// centralRowUncovered(): the rule loop `continue`s past a dateless rule to a LATER
// dated one (not `break`).
it('processes a dated central rule that follows a dateless one', function () {
    $central = [closureHoliday('Mixed', [
        ['type' => 'rule', 'date' => null, 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed', 'time_span' => []],
        closureRule('2026-11-26'),
    ])];

    /** @var array<int, array{rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge([], $central, true);

    expect($merged)->toHaveCount(1)
        ->and($merged[0]['rules'])->toHaveCount(1)
        ->and($merged[0]['rules'][0]['date'])->toBe('2026-11-26');
});

// centralRowUncovered(): the synthetic rule carries `type` and `time_span` verbatim.
it('emits a synthetic rule carrying its type and time_span', function () {
    $central = [closureHoliday('Thanksgiving', [
        ['type' => 'rule', 'date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed', 'time_span' => [['from' => '09:00', 'to' => '17:00']]],
    ])];

    /** @var array<int, array{rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge([], $central, true);
    $rule = $merged[0]['rules'][0];

    expect($rule['type'])->toBe('rule')
        ->and($rule)->toHaveKey('time_span')
        ->and($rule['time_span'])->toBe([['from' => '09:00', 'to' => '17:00']]);
});

// centralRowUncovered(): an explicit rule `status` survives the merge — the
// `... ?? 'closed'` coalesce keeps the left side when present.
it('preserves a central rule explicit status rather than forcing closed', function () {
    $central = [closureHoliday('Reduced', [closureRule('2026-11-26', status: 'reduced_hours')])];

    /** @var array<int, array{rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge([], $central, true);

    expect($merged[0]['rules'][0]['status'])->toBe('reduced_hours');
});

// centralRowUncovered(): the holiday `description` is carried through.
it('carries the central holiday description through the merge', function () {
    $central = [['type' => 'holiday', 'title' => 'Thanksgiving', 'description' => 'Closed for the holiday', 'rules' => [closureRule('2026-11-26')]]];

    /** @var array<int, array{description: ?string}> $merged */
    $merged = closures()->merge([], $central, true);

    expect($merged[0]['description'])->toBe('Closed for the holiday');
});

// survivingRuns(): the RANGE_DAY_CAP guard bounds the day walk. An absurd range is
// truncated at exactly 1500 days (the run ends at from + 1499).
it('caps a single central range at the RANGE_DAY_CAP walk limit', function () {
    $from = '2026-01-01';
    $through = Carbon::parse($from)->addDays(1600)->format('Y-m-d');
    $central = [closureHoliday('Absurd', [closureRule($from, $through)])];

    /** @var array<int, array{rules: array<int, array<string, mixed>>}> $merged */
    $merged = closures()->merge([], $central, true);
    $rule = $merged[0]['rules'][0];

    expect($rule['date'])->toBe($from)
        ->and($rule['through'])->toBe(Carbon::parse($from)->addDays(1499)->format('Y-m-d'));
});
