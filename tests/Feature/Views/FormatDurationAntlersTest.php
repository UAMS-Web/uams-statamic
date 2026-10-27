<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Ensures App\Modifiers\FormatDuration works through valid Antlers pipe syntax
 * (positional colon segments for display mode and precision; the form
 * display="..." named parameter is not supported and breaks modifier resolution).
 * The modifier's value logic is owned by tests/Unit/Modifiers/FormatDurationTest,
 * so this characterizes only the Antlers pipe integration.
 *
 * @command  php artisan test --compact tests/Feature/Views/FormatDurationAntlersTest.php
 */
it('formats durations through Antlers pipe syntax', function (string $template, float|int $value, string $expected) {
    expect(trim((string) Antlers::parse($template, ['value' => $value], true)))->toBe($expected);
})->with([
    'human_long' => ['{{ value | format_duration:human_long }}', 3665, '1 hour, 1 minute, 5 seconds'],
    'iso_8601 precision -1' => ['{{ value | format_duration:iso_8601:-1 }}', 3723.5, 'PT1H2M3.5S'],
    'iso_8601 precision 1' => ['{{ value | format_duration:iso_8601:1 }}', 3723.556, 'PT1H2M3.6S'],
    'human_long precision 1' => ['{{ value | format_duration:human_long:1 }}', 3723.5, '1 hour, 2 minutes, 3.5 seconds'],
    'hms precision -1' => ['{{ value | format_duration:hms:-1 }}', 3723.5, '01:02:03.5'],
]);
