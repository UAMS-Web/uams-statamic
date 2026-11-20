<?php

declare(strict_types=1);

use App\Support\Classifieds\ClassifiedExpiry;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The listing period {@see ClassifiedExpiry::defaultExpirationDate()} adds to today.
 *
 * The feature tests compare a written listing's date with the same method, so they
 * cannot see which period it used. These freeze today in the display timezone and
 * read the configured period, its absence and a value that is not a whole number.
 *
 * @command  php artisan test --compact tests/Unit/Support/Classifieds/ClassifiedExpiryTest.php
 */
beforeEach(function (): void {
    config(['statamic.system.display_timezone' => 'America/Chicago']);

    // 21:00 in Little Rock on 15 June, already the 16th in UTC.
    Carbon::setTestNow(Carbon::parse('2026-06-16 02:00:00', 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('adds the configured listing period to today in the display timezone', function (): void {
    config(['classifieds.listing_days' => 30]);

    expect((new ClassifiedExpiry)->defaultExpirationDate())->toBe('2026-07-15');
});

it('adds 90 days when no listing period is configured', function (): void {
    config(['classifieds' => []]);

    expect((new ClassifiedExpiry)->defaultExpirationDate())->toBe('2026-09-13');
});

it('adds 90 days when the configured listing period is not a whole number', function (mixed $days): void {
    config(['classifieds.listing_days' => $days]);

    expect((new ClassifiedExpiry)->defaultExpirationDate())->toBe('2026-09-13');
})->with([
    'numeric string' => ['30'],
    'float' => [30.0],
    'null' => [null],
]);
