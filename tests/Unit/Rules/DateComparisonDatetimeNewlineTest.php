<?php

declare(strict_types=1);

use App\Rules\After;
use App\Rules\BaseRules\BaseDateComparisonRule;
use App\Rules\Before;

/**
 * UAMS-Web/uams-statamic-archive#1017 follow-up: the `datetime`-type strict-format check in
 * {@see BaseDateComparisonRule} (shared by After/Before/
 * DateEquals/AfterOrEqual/BeforeOrEqual) was missing the PCRE `D` modifier, so its
 * `/^…$/` pattern accepted a trailing newline. With `D`, a `<valid-datetime>\n`
 * value is rejected by the format gate before any comparison.
 *
 * @command  php artisan test --compact tests/Unit/Rules/DateComparisonDatetimeNewlineTest.php
 */
it('rejects a trailing newline in a datetime value via the strict-format gate', function (): void {
    // Without the D modifier, "2024-12-31 12:00:00\n" matched /^…$/ and then
    // compared greater-than -> passed. With D, the format gate rejects it.
    $after = new After('2024-01-01 12:00:00', 'datetime');
    expect($after->passes('d', "2024-12-31 12:00:00\n"))->toBeFalse();

    $before = new Before('2024-12-31 12:00:00', 'datetime');
    expect($before->passes('d', "2024-01-01 12:00:00\n"))->toBeFalse();
});

it('still accepts a clean datetime value', function (): void {
    $after = new After('2024-01-01 12:00:00', 'datetime');
    expect($after->passes('d', '2024-12-31 12:00:00'))->toBeTrue();
});
