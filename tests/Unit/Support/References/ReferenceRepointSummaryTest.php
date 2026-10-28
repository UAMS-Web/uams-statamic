<?php

declare(strict_types=1);

use App\Support\References\ReferenceRepointSummary;

it('counts entries, globals and terms as changed items, and not occurrences', function (): void {
    $summary = new ReferenceRepointSummary(entries: 2, globals: 3, terms: 5, occurrences: 40);

    expect($summary->itemsChanged())->toBe(10);
});

it('describes every count, with the items changed', function (): void {
    $summary = new ReferenceRepointSummary(entries: 1, globals: 0, terms: 4, occurrences: 7);

    expect($summary->toArray())->toBe([
        'entries' => 1,
        'globals' => 0,
        'terms' => 4,
        'occurrences' => 7,
        'items_changed' => 5,
    ]);
});
