<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * The Control Panel dashboard widget list in `config/statamic/cp.php`.
 *
 * UAMS-Web/uams-statamic-archive#2507 disabled `incomplete_published_entries` because it scanned inside the
 * dashboard request and every load returned 504, and this file pinned that absence.
 * UAMS-Web/uams-statamic-archive#2506 moved the scan off-request — the widget now only reads the result of the
 * scheduled `cp:scan-incomplete-published-entries` run — so the guard is inverted:
 * the widget is registered again, and the property that made its absence necessary
 * is asserted where it lives, in `IncompletePublishedEntriesOffRequestTest`.
 */
it('registers the incomplete-published-entries widget', function (): void {
    /** @var list<string> $widgets */
    $widgets = (array) config('statamic.cp.widgets');

    expect($widgets)->toContain('incomplete_published_entries');
});

it('still registers the duplicate-people widget', function (): void {
    /** @var list<string> $widgets */
    $widgets = (array) config('statamic.cp.widgets');

    expect($widgets)->toContain('duplicate_people');
});

it('registers the gender-override widget', function (): void {
    /** @var list<string> $widgets */
    $widgets = (array) config('statamic.cp.widgets');

    expect($widgets)->toContain('workday_gender_overrides');
});
