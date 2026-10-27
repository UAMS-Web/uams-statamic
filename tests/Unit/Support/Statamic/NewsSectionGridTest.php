<?php

declare(strict_types=1);

use App\Services\Posts\NewsSpringboardService;
use App\Support\Statamic\NewsSectionGrid;
use Statamic\Fields\Value;
use Statamic\Fields\Values;

/**
 * The news-magazine `sections` grid read as plain section configs, however Antlers hands it
 * over (#310 coverage).
 *
 * @command  php artisan test --compact tests/Unit/Support/Statamic/NewsSectionGridTest.php
 */
it('reads each row\'s heading, categories and count, in order', function (): void {
    $rows = [
        ['section_heading' => 'Research', 'category' => ['cat-research'], 'count' => 6],
        ['section_heading' => 'Campus', 'category' => ['cat-campus', 'cat-events'], 'count' => '3'],
    ];

    expect(NewsSectionGrid::normalize($rows))->toBe([
        ['heading' => 'Research', 'category' => ['cat-research'], 'count' => 6],
        ['heading' => 'Campus', 'category' => ['cat-campus', 'cat-events'], 'count' => 3],
    ]);
});

it('reads the same rows from a field value, a collection, or a field value around one', function (mixed $raw): void {
    expect(NewsSectionGrid::normalize($raw))->toBe([['heading' => 'Research', 'category' => ['cat-research'], 'count' => 6]]);
})->with([
    'a field value' => fn (): Value => new Value([['section_heading' => 'Research', 'category' => ['cat-research'], 'count' => 6]]),
    'a collection' => fn () => collect([['section_heading' => 'Research', 'category' => ['cat-research'], 'count' => 6]]),
    'a field value around a collection' => fn (): Value => new Value(collect([['section_heading' => 'Research', 'category' => ['cat-research'], 'count' => 6]])),
]);

it('reads a row augmented to a set, with each field wrapped in a field value', function (): void {
    $row = new Values([
        'section_heading' => new Value('Research'),
        'category' => new Value(['cat-research']),
        'count' => new Value(6),
    ]);

    expect(NewsSectionGrid::normalize([$row]))->toBe([['heading' => 'Research', 'category' => ['cat-research'], 'count' => 6]]);
});

it('has no sections when the grid is not a list of rows', function (mixed $raw): void {
    expect(NewsSectionGrid::normalize($raw))->toBe([]);
})->with([
    'null' => [null],
    'a string' => ['sections'],
    'a field value around null' => [new Value(null)],
    'an object, whose properties are not rows' => [(object) ['row' => ['section_heading' => 'Research', 'count' => 6]]],
]);

it('gives a row with nothing usable no heading, no categories and the default count', function (mixed $row): void {
    expect(NewsSectionGrid::normalize([$row]))->toBe([['heading' => null, 'category' => [], 'count' => NewsSpringboardService::DEFAULT_COUNT]]);
})->with([
    'an empty row' => [[]],
    'a row that is neither a set nor an array' => ['not a row'],
    'an empty heading, a non-numeric count' => [['section_heading' => '', 'count' => 'many']],
    'a non-scalar heading, a zero count' => [['section_heading' => ['Research'], 'count' => 0]],
    'a false heading' => [['section_heading' => false]],
    'a negative count' => [['count' => -2]],
]);

it('reads a numeric heading as text', function (): void {
    expect(NewsSectionGrid::normalize([['section_heading' => 2026, 'count' => 1]])[0])
        ->toBe(['heading' => '2026', 'category' => [], 'count' => 1]);
});
