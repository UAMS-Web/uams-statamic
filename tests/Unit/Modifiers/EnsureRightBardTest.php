<?php

declare(strict_types=1);

use App\Modifiers\EnsureRightBard;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Tests\TestCase;

uses(TestCase::class);

covers(EnsureRightBard::class);

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

/**
 * Behavior of the `ensure_right_bard` Antlers modifier.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/EnsureRightBardTest.php
 */
$passthrough = new stdClass;

it('injects before the last closing tag, matching input shape', function (mixed $value, array $params, mixed $expected): void {
    expect((new EnsureRightBard)->index($value, $params, []))->toBe($expected);
})->with([
    // ── String fragments ──
    'empty injected text returns the value unchanged' => ['<p>Hello</p>', [''], '<p>Hello</p>'],
    'appends before the last closing </p> among many' => ['<p>First</p><p>Second</p>', [' END'], '<p>First</p><p>Second END</p>'],
    'second parameter selects the closing element type' => ['<div><p>A</p></div><div><p>B</p></div>', ['X', 'div'], '<div><p>A</p></div><div><p>B</p>X</div>'],
    'closing tag match is case-insensitive' => ['<p>Hi</P>', [' Z'], '<p>Hi Z</P>'],
    'no closing tag leaves the fragment unchanged' => ['<p>Unclosed', ['x'], '<p>Unclosed'],

    // ── Array / Collection rows (mutate only the first text row) ──
    'mutates the first text row in an array' => [
        [['type' => 'text', 'text' => '<p>A</p><p>B</p>']],
        ['!'],
        [['type' => 'text', 'text' => '<p>A</p><p>B!</p>']],
    ],
    'accepts a Collection of rows' => [
        new Collection([['type' => 'text', 'text' => '<p>One</p>']]),
        ['.'],
        [['type' => 'text', 'text' => '<p>One.</p>']],
    ],
    'mutates a Statamic Values text row' => [
        [new Values(['type' => 'text', 'text' => '<p>A</p><p>B</p>'])],
        ['!'],
        [['type' => 'text', 'text' => '<p>A</p><p>B!</p>']],
    ],
    'reads a Value-wrapped text field in an array row' => [
        [['type' => 'text', 'text' => new Value('<p>One</p>')]],
        ['.'],
        [['type' => 'text', 'text' => '<p>One.</p>']],
    ],

    // ── Param coercion (scalar/Stringable-safe) ──
    'a Stringable inject param is rendered' => ['<p>Hi</p>', [Str::of(' S')], '<p>Hi S</p>'],
    // Integer must be cast to string before the typed helper: RemoveStringCast fatals.
    'an integer inject param is cast' => ['<p>Hi</p>', [42], '<p>Hi42</p>'],
    'absent inject param returns the value unchanged' => ['<p>Hi</p>', [], '<p>Hi</p>'],
    'non-scalar inject param returns the value unchanged' => ['<p>Hi</p>', [[1, 2]], '<p>Hi</p>'],

    // ── Passthrough ──
    'non-iterable non-string value passes through unchanged' => [$passthrough, ['x'], $passthrough],
]);
