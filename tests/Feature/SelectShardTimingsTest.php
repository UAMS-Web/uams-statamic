<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for choosing the shard timings entry (#519). Runs
 * `scripts/select-shard-timings.php` over throwaway cache listings and asserts that
 * shards of one run choose the same entry even when a `main` push saved a newer one
 * between their lookups, the race that failed run #41440485266 on #514.
 *
 * Driven as a subprocess, like `MergeShardReportsTest`, so the CLI contract ci.yml
 * depends on (what it prints and its exit code) is what is pinned. Framework-free,
 * with fixtures under a per-run `sys_get_temp_dir()` path, so it is parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/SelectShardTimingsTest.php
 */
$script = dirname(__DIR__, 2).'/scripts/select-shard-timings.php';

/**
 * Run the script over a listing of cache entries, given as key => created_at.
 *
 * @param  array<mixed>  $entries  Key => created_at, in the order the listing returns them
 */
$select = function (array $entries, string $started) use ($script): Process {
    $caches = [];
    foreach ($entries as $key => $createdAt) {
        $caches[] = ['id' => count($caches) + 1, 'ref' => 'refs/heads/main', 'key' => $key, 'created_at' => $createdAt];
    }

    $file = sys_get_temp_dir().'/select-shard-timings-'.bin2hex(random_bytes(6)).'.json';
    file_put_contents($file, (string) json_encode(['total_count' => count($caches), 'actions_caches' => $caches]));

    try {
        $process = new Process([PHP_BINARY, $script, "--caches={$file}", "--started={$started}"]);
        $process->run();
    } finally {
        unlink($file);
    }

    return $process;
};

it('gives two shards the same entry when a newer one is saved between their lookups', function () use ($select): void {
    $started = '2026-11-02T02:10:00Z';
    $before = [
        'pest-shard-timings-100-1' => '2026-11-02T01:00:00Z',
        'pest-shard-timings-200-1' => '2026-11-02T02:00:00Z',
    ];

    // The second shard looks after a main push saved a newer entry
    $first = $select($before, $started);
    $second = $select(['pest-shard-timings-300-1' => '2026-11-02T02:12:00Z', ...$before], $started);

    expect($first->getExitCode())->toBe(0)
        ->and($first->getOutput())->toBe('pest-shard-timings-200-1')
        ->and($second->getExitCode())->toBe(0)
        ->and($second->getOutput())->toBe('pest-shard-timings-200-1');
});

it('chooses the newest entry before the start whatever order the listing returns', function () use ($select): void {
    $entries = [
        'pest-shard-timings-200-1' => '2026-11-02T02:00:00Z',
        'pest-shard-timings-100-1' => '2026-11-02T01:00:00Z',
        'pest-shard-timings-150-1' => '2026-11-02T01:30:00Z',
    ];

    expect($select($entries, '2026-11-02T03:00:00Z')->getOutput())->toBe('pest-shard-timings-200-1')
        ->and($select(array_reverse($entries, true), '2026-11-02T03:00:00Z')->getOutput())->toBe('pest-shard-timings-200-1');
});

it('compares times to the fraction of a second', function () use ($select): void {
    $entries = [
        'pest-shard-timings-100-1' => '2026-11-02T02:09:59.900Z',
        'pest-shard-timings-200-1' => '2026-11-02T02:10:00.500Z',
    ];

    // Compared as text, 02:10:00.500Z sorts before 02:10:00Z and would be chosen
    expect($select($entries, '2026-11-02T02:10:00Z')->getOutput())->toBe('pest-shard-timings-100-1');
});

it('breaks a tie on time by key, so the order of the listing never decides', function () use ($select): void {
    $entries = [
        'pest-shard-timings-100-1' => '2026-11-02T02:00:00Z',
        'pest-shard-timings-100-2' => '2026-11-02T02:00:00Z',
    ];

    expect($select($entries, '2026-11-02T03:00:00Z')->getOutput())->toBe('pest-shard-timings-100-2')
        ->and($select(array_reverse($entries, true), '2026-11-02T03:00:00Z')->getOutput())->toBe('pest-shard-timings-100-2');
});

it('ignores entries without the timings prefix', function () use ($select): void {
    $entries = [
        'composer-files-abc' => '2026-11-02T02:05:00Z',
        'pest-shard-timings-100-1' => '2026-11-02T01:00:00Z',
    ];

    expect($select($entries, '2026-11-02T03:00:00Z')->getOutput())->toBe('pest-shard-timings-100-1');
});

it('prints nothing when no entry was saved before the run started', function () use ($select): void {
    $process = $select(['pest-shard-timings-300-1' => '2026-11-02T02:12:00Z'], '2026-11-02T02:10:00Z');

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toBe('');
});

it('fails, naming the problem, when the listing or the start time cannot be read', function () use ($script): void {
    $file = sys_get_temp_dir().'/select-shard-timings-'.bin2hex(random_bytes(6)).'.json';
    $listing = sys_get_temp_dir().'/select-shard-timings-'.bin2hex(random_bytes(6)).'.json';
    file_put_contents($file, '{"message":"Resource not accessible by integration"}');
    file_put_contents($listing, '{"total_count":0,"actions_caches":[]}');

    try {
        $notAListing = new Process([PHP_BINARY, $script, "--caches={$file}", '--started=2026-11-02T02:10:00Z']);
        $notAListing->run();

        $badStart = new Process([PHP_BINARY, $script, "--caches={$listing}", '--started=not a time']);
        $badStart->run();
    } finally {
        unlink($file);
        unlink($listing);
    }

    $missing = new Process([PHP_BINARY, $script, '--started=2026-11-02T02:10:00Z']);
    $missing->run();

    expect($notAListing->getExitCode())->toBe(2)
        ->and($notAListing->getErrorOutput())->toContain('is not an Actions cache listing')
        ->and($badStart->getExitCode())->toBe(2)
        ->and($badStart->getErrorOutput())->toContain('unreadable --started time')
        ->and($missing->getExitCode())->toBe(2)
        ->and($missing->getErrorOutput())->toContain('usage:');
});
