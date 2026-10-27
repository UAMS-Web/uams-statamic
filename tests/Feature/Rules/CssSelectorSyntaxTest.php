<?php

declare(strict_types=1);

use App\Rules\CssSelectorSyntax;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

covers(CssSelectorSyntax::class);

/**
 * `CssSelectorSyntax` fails with a line from Statamic's validation file, so its message is
 * asserted as the sentence an editor sees through `Validator::make()`, never against the same
 * `__()` call the rule makes (UAMS-Web/uams-statamic-archive#2997).
 *
 * @command  php artisan test --compact tests/Feature/Rules/CssSelectorSyntaxTest.php
 */

/**
 * Validate a `selector` field holding the given value, returning the messages it failed with.
 *
 * @return array<string>
 */
function cssSelectorSyntaxErrors(mixed $value): array
{
    return Validator::make(['selector' => $value], ['selector' => [new CssSelectorSyntax]])->errors()->all();
}

it('shows the editor a sentence naming the field, not a translation key', function (string $selector): void {
    expect(cssSelectorSyntaxErrors($selector))->toBe(['The selector is not valid CSS selector syntax.']);
})->with([
    'an unclosed attribute selector' => ['div['],
    'a doubled bracket' => ['div[['],
    'a dangling combinator' => ['a >'],
    'an unclosed pseudo-class' => [':not('],
    'a bare id marker' => ['#'],
]);

it('passes valid selector syntax, including a pseudo-element XPath cannot express', function (string $selector): void {
    expect(cssSelectorSyntaxErrors($selector))->toBe([]);
})->with([
    'a pseudo-element' => ['div::before'],
    'a child combinator with a class and pseudo-class' => ['a > b.c:hover'],
    'a functional pseudo-class' => ['li:nth-child(2n+1)'],
    'an attribute selector' => ['[data-x="y"]'],
    'the universal selector' => ['*'],
]);

it('fails a value that is not a string with the string message', function (mixed $value): void {
    expect(cssSelectorSyntaxErrors($value))->toBe(['The selector field must be a string.']);
})->with([
    'a number' => [5],
    'a list' => [['div']],
]);

it('names the field in the message it fails with itself', function (): void {
    $messages = [];

    // The validator fills in :attribute on its own, so only a direct call shows the rule
    // passing the field name through.
    (new CssSelectorSyntax)->validate('selector', 'div[', function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($messages)->toBe(['The selector is not valid CSS selector syntax.']);
});

it('passes an empty string without parsing it', function (): void {
    $messages = [];

    // The validator skips an empty value before a non-implicit rule runs, so the rule's own
    // empty-string return is reached only by calling it directly.
    (new CssSelectorSyntax)->validate('selector', '', function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($messages)->toBe([]);
});
