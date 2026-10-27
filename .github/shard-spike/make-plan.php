<?php

declare(strict_types=1);

/**
 * Build the job matrix for the #384 sharding spike from the mode in plan.txt.
 *
 * Modes: `count` (whole-band baseline, count-based shards at N=2, 3 and 4, and
 * the serial tail as its own job) and `timed` (the same with
 * tests/.pest/shards.json present, so Pest balances shards by time).
 */
$mode = trim((string) file_get_contents(__DIR__.'/plan.txt'));

if (! in_array($mode, ['count', 'timed'], true)) {
    fwrite(STDERR, "Unknown mode [{$mode}] in plan.txt\n");
    exit(2);
}

// Start with the whole-band baseline and the serial tail
$include = [
    ['id' => 'baseline', 'kind' => 'band', 'shard' => '', 'n' => 1],
    ['id' => 'serial', 'kind' => 'serial', 'shard' => '', 'n' => 1],
];

// Add every shard of every shard count under test
foreach ([2, 3, 4] as $total) {
    for ($index = 1; $index <= $total; $index++) {
        $include[] = ['id' => "n{$total}-s{$index}", 'kind' => 'band', 'shard' => "{$index}/{$total}", 'n' => $total];
    }
}

echo 'mode='.$mode.PHP_EOL;
echo 'matrix='.json_encode(['include' => $include], JSON_UNESCAPED_SLASHES).PHP_EOL;
