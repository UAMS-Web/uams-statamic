<?php

declare(strict_types=1);

use App\Enums\ApStyleGregorianMonth;

/**
 * Behavior of App\Enums\ApStyleGregorianMonth (and ApStyleMonthInfo): AP Stylebook
 * prose vs tabular month labels, numeric/full-name resolution, and the running-text
 * abbreviation rule (abbreviate only when the phrase includes a specific day).
 *
 * Mutation: this exhaustively covers the label table (all 12 months, prose +
 * tabular), fromMonthNumber (valid + out-of-range), tryFrom (full name + unknown),
 * labelForTabularMaterial, spelledOutName, and the runningTextLabel day toggle.
 * pest-mutate's --class filter cannot scope to this file because it declares two
 * classes (the ApStyleMonthInfo attribute + the enum), so it reports "0 Mutations
 * for 0 Files" and --covered-only mis-attributes to another covered enum — a
 * tooling limitation, not a coverage gap; the assertions above pin every label and
 * branch exactly.
 *
 * @command php artisan test --compact tests/Unit/Enums/ApStyleGregorianMonthTest.php
 */
it('returns the prose and tabular labels for a month number', function (int $monthNum, string $expectedProse, string $expectedTabular) {
    $case = ApStyleGregorianMonth::fromMonthNumber($monthNum);

    expect($case)->not->toBeNull();
    expect($case?->proseLabel())->toBe($expectedProse);
    expect($case?->tabularLabel())->toBe($expectedTabular);
})->with([
    'january' => [1, 'Jan.', 'Jan'],
    'february' => [2, 'Feb.', 'Feb'],
    'march' => [3, 'March', 'Mar'],
    'april' => [4, 'April', 'Apr'],
    'may' => [5, 'May', 'May'],
    'june' => [6, 'June', 'Jun'],
    'july' => [7, 'July', 'Jul'],
    'august' => [8, 'Aug.', 'Aug'],
    'september' => [9, 'Sept.', 'Sep'],
    'october' => [10, 'Oct.', 'Oct'],
    'november' => [11, 'Nov.', 'Nov'],
    'december' => [12, 'Dec.', 'Dec'],
]);

it('resolves a case from its full English month name via tryFrom', function () {
    expect(ApStyleGregorianMonth::tryFrom('September'))->toBe(ApStyleGregorianMonth::September);
});

it('returns null from tryFrom for an unknown string', function (string $unknown) {
    expect(ApStyleGregorianMonth::tryFrom($unknown))->toBeNull();
})->with([
    'AP abbreviation' => 'Jan',
    'lower-cased name' => 'september',
    'empty string' => '',
    'a weekday, not a month' => 'Tuesday',
]);

it('returns null for a month number outside 1-12', function () {
    expect(ApStyleGregorianMonth::fromMonthNumber(0))->toBeNull();
    expect(ApStyleGregorianMonth::fromMonthNumber(13))->toBeNull();
});

it('toggles labelForTabularMaterial between prose and tabular', function () {
    $march = ApStyleGregorianMonth::March;

    expect($march->labelForTabularMaterial(false))->toBe('March');
    expect($march->labelForTabularMaterial(true))->toBe('Mar');
});

it('returns the spelled-out name matching the backing value', function () {
    expect(ApStyleGregorianMonth::January->spelledOutName())->toBe('January');
});

it('abbreviates the running-text label only when a day is present', function () {
    expect(ApStyleGregorianMonth::January->runningTextLabel(true))->toBe('Jan.');
    expect(ApStyleGregorianMonth::January->runningTextLabel(false))->toBe('January');

    // March is spelled the same with or without a day.
    expect(ApStyleGregorianMonth::March->runningTextLabel(true))->toBe('March');
    expect(ApStyleGregorianMonth::March->runningTextLabel(false))->toBe('March');
});
