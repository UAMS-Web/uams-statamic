<?php

declare(strict_types=1);

use App\Modifiers\EnsureLeftBard;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Tests\TestCase;

uses(TestCase::class);

covers(EnsureLeftBard::class);

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
 * Behavior of the `ensure_left_bard` Antlers modifier.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/EnsureLeftBardTest.php
 */
$passthrough = new stdClass;

it('injects after the first opening tag, matching input shape', function (mixed $value, array $params, mixed $expected): void {
    expect((new EnsureLeftBard)->index($value, $params, []))->toBe($expected);
})->with([
    // ── String fragments ──
    'empty prefix returns the value unchanged' => ['<p>Hello</p>', [''], '<p>Hello</p>'],
    'prepends inside the first paragraph' => ['<p>Hello</p>', ['LITTLE ROCK, Ark. — '], '<p>LITTLE ROCK, Ark. — Hello</p>'],
    'preserves attributes on the first <p>' => ['<p class="lead">Hello</p>', ['X — '], '<p class="lead">X — Hello</p>'],
    'preserves leading whitespace before the first <p>' => ["\n<p>Hi</p>", ['D — '], "\n<p>D — Hi</p>"],
    'second parameter selects the element type' => ['<div><p>Inside</p></div>', ['PREFIX ', 'div'], '<div>PREFIX <p>Inside</p></div>'],
    'element matching is case-insensitive' => ['<P>Hi</P>', ['Z ', 'p'], '<P>Z Hi</P>'],
    'invalid element name falls back to p' => ['<p>A</p>', ['X ', '9bad'], '<p>X A</p>'],
    'empty second parameter uses p' => ['<p>B</p>', ['Y ', ''], '<p>Y B</p>'],

    // ── Array / Collection rows (mutate only the first text row) ──
    'mutates the first text row, leaving later paragraphs' => [
        [['type' => 'text', 'text' => '<p>First</p><p>Second</p>']],
        ['PREFIX — '],
        [['type' => 'text', 'text' => '<p>PREFIX — First</p><p>Second</p>']],
    ],
    'skips non-text rows until the first text block' => [
        [['type' => 'image', 'image' => 'asset'], ['type' => 'text', 'text' => '<p>Body</p>']],
        ['D — '],
        [['type' => 'image', 'image' => 'asset'], ['type' => 'text', 'text' => '<p>D — Body</p>']],
    ],
    'accepts a Collection of rows' => [
        new Collection([['type' => 'text', 'text' => '<p>A</p>']]),
        ['Z — '],
        [['type' => 'text', 'text' => '<p>Z — A</p>']],
    ],
    'mutates a Statamic Values text row' => [
        [new Values(['type' => 'text', 'text' => '<p>V</p>'])],
        ['Q — '],
        [['type' => 'text', 'text' => '<p>Q — V</p>']],
    ],
    'reads a Value-wrapped text field in an array row' => [
        [['type' => 'text', 'text' => new Value('<p>W</p>')]],
        ['R — '],
        [['type' => 'text', 'text' => '<p>R — W</p>']],
    ],

    // ── Param coercion (scalar/Stringable-safe) ──
    'a Stringable inject param is rendered' => ['<p>Hi</p>', [Str::of('S — ')], '<p>S — Hi</p>'],
    // Integer must be cast to string before the typed helper: RemoveStringCast fatals.
    'an integer inject param is cast' => ['<p>Hi</p>', [42], '<p>42Hi</p>'],
    'absent inject param returns the value unchanged' => ['<p>Hi</p>', [], '<p>Hi</p>'],
    'non-scalar inject param returns the value unchanged' => ['<p>Hi</p>', [[1, 2]], '<p>Hi</p>'],

    // ── Passthrough ──
    'non-iterable non-string value passes through unchanged' => [$passthrough, ['x'], $passthrough],
]);
