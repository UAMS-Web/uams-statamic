<?php

declare(strict_types=1);

/**
 * Build the job matrix for the #384 sharding spike from plan.txt.
 *
 * plan.txt holds `<mode> <total> [serial]`: mode `count` shards by position,
 * mode `timed` places tests/.pest/shards.json so Pest balances shards by time.
 * Each run measures one shard configuration beside a whole-band baseline, so a
 * run occupies at most six runners at once; `serial` adds the serial tail as a
 * job of its own.
 */
$words = preg_split('/\s+/', trim((string) file_get_contents(__DIR__.'/plan.txt'))) ?: [];
[$mode, $total] = [$words[0] ?? '', (int) ($words[1] ?? 0)];

if (! in_array($mode, ['count', 'timed'], true) || $total < 2 || $total > 4) {
    fwrite(STDERR, 'plan.txt must read `<count|timed> <2-4> [serial]`'.PHP_EOL);
    exit(2);
}

// Start with the whole-band baseline the union is checked against
$include = [['id' => 'baseline', 'kind' => 'band', 'shard' => '']];

// Add the serial tail as its own job when asked for
if (in_array('serial', $words, true)) {
    $include[] = ['id' => 'serial', 'kind' => 'serial', 'shard' => ''];
}

// Add every shard of the shard count under test
for ($index = 1; $index <= $total; $index++) {
    $include[] = ['id' => "n{$total}-s{$index}", 'kind' => 'band', 'shard' => "{$index}/{$total}"];
}

echo 'mode='.$mode.PHP_EOL;
echo 'total='.$total.PHP_EOL;
echo 'matrix='.json_encode(['include' => $include], JSON_UNESCAPED_SLASHES).PHP_EOL;
