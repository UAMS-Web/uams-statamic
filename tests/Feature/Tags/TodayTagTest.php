<?php

declare(strict_types=1);

use App\Tags\Today;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

covers(Today::class);

/**
 * Feature tests for the Today tag (Gravity Forms {today} merge-tag emulation:
 * AP-style default date, AP-style time with conditional minutes, and Unix timestamp).
 *
 * Mutation: tracked under App\Tags\Today (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/TodayTagTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

afterEach(function () {
    Carbon::setTestNow();
});

it('uses ap style month for the default index format', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-09 10:00:00', 'UTC'));

    $tag = new Today;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('Aug. 9, 2026');
});

it('respects the format parameter on index', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-02 03:04:05', 'UTC'));

    $tag = new Today;
    $tag->setContext([]);
    $tag->setParameters([
        'format' => 'Y-m-d H:i:s',
    ]);

    expect($tag->index())->toBe('2026-01-02 03:04:05');
});

it('omits minutes from time when on the hour', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tag = new Today;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->time())->toBe('9 a.m.');
});

it('includes minutes in time when not on the hour', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 15:07:00', 'UTC'));

    $tag = new Today;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->time())->toBe('3:07 p.m.');
});

it('returns the unix timestamp from timestamp', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00', 'UTC'));

    $tag = new Today;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->timestamp())->toBe(Carbon::parse('2026-01-01 00:00:00', 'UTC')->timestamp);
});

it('stringifies a Stringable format parameter', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 03:04:05', 'UTC'));

    $tag = new Today;
    $tag->setContext([]);
    $tag->setParameters([
        // InstanceOfToFalse would skip Stringable and format with ''.
        'format' => new class implements Stringable
        {
            public function __toString(): string
            {
                return 'Y-m-d';
            }
        },
    ]);

    expect($tag->index())->toBe('2026-01-02');
});

it('uses an empty format string for non-scalar non-Stringable params', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 03:04:05', 'UTC'));

    $tag = new Today;
    $tag->setContext([]);
    $tag->setParameters([
        // InstanceOfToTrue would cast the array and throw; EmptyStringToNotEmpty
        // would format with a non-empty bait string.
        'format' => ['Y-m-d'],
    ]);

    expect($tag->index())->toBe('');
});

it('casts a scalar format parameter to string', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-02 03:04:05', 'UTC'));

    $tag = new Today;
    $tag->setContext([]);
    $tag->setParameters([
        // RemoveStringCast leaves an int; Carbon::format still accepts it here,
        // so this pins the coerce path rather than a behavioral delta.
        'format' => 'Y',
    ]);

    expect($tag->index())->toBe('2026');
});
