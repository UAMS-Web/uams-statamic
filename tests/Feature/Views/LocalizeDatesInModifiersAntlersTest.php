<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Statamic's `format` modifier runs values through `carbon()`, which optionally
 * calls `setTimezone(Statamic::displayTimezone())` when
 * `statamic.system.localize_dates_in_modifiers` is true. Date-only strings are
 * parsed as instants; shifting into zones west of the parsed zone can move the
 * calendar day backward. Civil dates (certifications, expiries) must keep
 * `localize_dates_in_modifiers` false so `| format('Y-m-d')` matches stored dates.
 * For date-only civil fields with localization left on, use `| format_civil_date`
 * (see App\Modifiers\FormatCivilDate).
 *
 * This characterizes Statamic core's `format` modifier, not an app class, so there
 * is no app PHP mutable surface to mutation-test.
 *
 * @command  php artisan test --compact tests/Feature/Views/LocalizeDatesInModifiersAntlersTest.php
 */
it('keeps the calendar day when localization is disabled', function () {
    config()->set('statamic.system.display_timezone', 'America/Chicago');
    config()->set('statamic.system.localize_dates_in_modifiers', false);

    $out = trim((string) Antlers::parse("{{ activity_date_issued | format('Y-m-d') }}", [
        'activity_date_issued' => '1994-07-15',
    ], true));

    expect($out)->toBe('1994-07-15');
});

it('shifts the calendar day backward when localization is enabled', function () {
    config()->set('statamic.system.display_timezone', 'America/Chicago');
    config()->set('statamic.system.localize_dates_in_modifiers', true);

    $out = trim((string) Antlers::parse("{{ activity_date_issued | format('Y-m-d') }}", [
        'activity_date_issued' => '1994-07-15',
    ], true));

    expect($out)->toBe('1994-07-14');
});
