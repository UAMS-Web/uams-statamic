<?php

declare(strict_types=1);

use App\Rules\XpathSyntax;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

covers(XpathSyntax::class);

/**
 * `XpathSyntax` decides validity from what `DOMXPath::query()` returns, so its answer does
 * not depend on whether something earlier in the process left libxml's internal error mode
 * on (UAMS-Web/uams-statamic-archive#2994). Each case runs with the mode off and on, and restores the mode it found.
 *
 * @command  php artisan test --compact tests/Feature/Rules/XpathSyntaxTest.php
 */

/**
 * Run the rule with libxml's internal error mode set as given, returning the messages it
 * failed with.
 *
 * @return list<mixed>
 */
function xpathSyntaxMessages(mixed $value, bool $internalErrors): array
{
    $previous = libxml_use_internal_errors($internalErrors);
    $messages = [];

    try {
        (new XpathSyntax)->validate('selector', $value, function (mixed $message) use (&$messages): void {
            $messages[] = $message;
        });
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return $messages;
}

it('fails an invalid expression whatever libxml\'s error mode', function (string $expression, bool $internalErrors): void {
    expect(xpathSyntaxMessages($expression, $internalErrors))->toBe(['The selector is not valid XPath syntax.']);
})->with([
    'an unclosed predicate' => ['//div['],
    'a dangling operator' => ['//div[@class=]'],
    'an unknown axis' => ['nope::div'],
])->with([
    'internal errors off' => [false],
    'internal errors on' => [true],
]);

it('passes a valid expression whatever libxml\'s error mode', function (string $expression, bool $internalErrors): void {
    expect(xpathSyntaxMessages($expression, $internalErrors))->toBe([]);
})->with([
    'a path with a predicate' => ['//div[@class="card"]'],
    'a function returning a number' => ['count(//r)'],
    'an arithmetic expression' => ['1 + 1'],
])->with([
    'internal errors off' => [false],
    'internal errors on' => [true],
]);

it('passes an empty string, and fails a value that is not a string', function (mixed $value, bool $fails): void {
    $expected = $fails ? [__('validation.string')] : [];

    expect(xpathSyntaxMessages($value, false))->toBe($expected)
        ->and(xpathSyntaxMessages($value, true))->toBe($expected);
})->with([
    'an empty string' => ['', false],
    'a number' => [5, true],
    'a list' => [['//div'], true],
]);

it('shows the editor a sentence naming the field, not a translation key', function (): void {
    $validator = Validator::make(['selector' => '//div['], ['selector' => [new XpathSyntax]]);

    expect($validator->errors()->first('selector'))->toBe('The selector is not valid XPath syntax.');
});
