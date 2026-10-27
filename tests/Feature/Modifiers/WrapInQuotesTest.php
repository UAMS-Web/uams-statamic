<?php

declare(strict_types=1);

use App\Modifiers\WrapInQuotes;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

covers(WrapInQuotes::class);

/**
 * The quote wrapping for the side-by-side block's Blockquote with Citation layout (UAMS-Web/uams-statamic-archive#2757).
 *
 * @command  php artisan test --compact tests/Feature/Modifiers/WrapInQuotesTest.php
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

it('wraps the value in curly double quotes, removing any marks at its ends', function (mixed $value, string $expected): void {
    expect((new WrapInQuotes)->index($value, [], []))->toBe($expected);
})->with([
    'no marks' => ['Great results.', '“Great results.”'],
    'straight double' => ['"Great results."', '“Great results.”'],
    'curly double' => ['“Great results.”', '“Great results.”'],
    'straight single' => ["'Great results.'", '“Great results.”'],
    'curly single' => ['‘Great results.’', '“Great results.”'],
    'mismatched marks' => ['"Great results.’', '“Great results.”'],
    'nested marks at both ends' => ['"‘Great results.’"', '“Great results.”'],
    'marks and whitespace at the ends' => [' " Great results. " ', '“Great results.”'],
    'an opening mark only' => ['"Great results.', '“Great results.”'],
    'an apostrophe inside' => ["We didn't stop.", "“We didn't stop.”"],
    'a curly apostrophe inside' => ['A Razorback’s drive.', '“A Razorback’s drive.”'],
    'a quote within the quote' => ['They said "never" to us.', '“They said "never" to us.”'],
    'a quote within the quote ending the value' => ['They told us "never"', '“They told us "never”'],
    'an augmented value' => [new Value('"Great results."'), '“Great results.”'],
    // RemoveStringCast on ``(string) $value`` would leave an int and break concat.
    'integer scalar' => [42, '“42”'],
    'true scalar' => [true, '“1”'],
]);

it('returns an empty string when nothing but marks and whitespace remains', function (mixed $value): void {
    expect((new WrapInQuotes)->index($value, [], []))->toBe('');
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'marks only' => ['" “ ’'],
    'null' => [null],
    'augmented null' => [new Value(null)],
    'an array' => [['Great results.']],
    'stdClass' => [new stdClass],
]);
