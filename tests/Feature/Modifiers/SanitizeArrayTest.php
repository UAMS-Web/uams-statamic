<?php

declare(strict_types=1);

use App\Modifiers\SanitizeArray;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Behavior of the `sanitize_array` Antlers modifier (App\Modifiers\SanitizeArray):
 * htmlspecialchars each list item with ENT_QUOTES and the Statamic charset,
 * accepting an array / Collection / scalar (one-element list), unwrapping Value
 * items, with an optional double_encode first argument. Mirrors core `sanitize`
 * per item.
 *
 * @command  php artisan test --compact tests/Feature/Modifiers/SanitizeArrayTest.php
 */
it('encodes special characters in each string', function () {
    $result = (new SanitizeArray)->index(['a < b & c', '"quotes" \'apostrophe\''], [], []);

    expect($result)->toBe([
        'a &lt; b &amp; c',
        '&quot;quotes&quot; &#039;apostrophe&#039;',
    ]);
});

it('accepts a Collection', function () {
    expect((new SanitizeArray)->index(collect(['<em>x</em>']), [], []))
        ->toBe(['&lt;em&gt;x&lt;/em&gt;']);
});

it('wraps a scalar as a single element', function () {
    expect((new SanitizeArray)->index('<a href="#">x</a>', [], []))
        ->toBe(['&lt;a href=&quot;#&quot;&gt;x&lt;/a&gt;']);
});

it('treats empty sentinels as an empty array', function (mixed $value) {
    expect((new SanitizeArray)->index($value, [], []))->toBe([]);
})->with([
    'null' => [null],
    'false' => [false],
    'empty string' => [''],
]);

it('double-encodes entities only when the first argument is true', function () {
    $already = '&lt;em&gt;x&lt;/em&gt;';

    expect((new SanitizeArray)->index([$already], [], []))->toBe([$already]);
    expect((new SanitizeArray)->index([$already], [true], []))
        ->toBe(['&amp;lt;em&amp;gt;x&amp;lt;/em&amp;gt;']);
});

it('unwraps Value items before encoding', function () {
    expect((new SanitizeArray)->index([new Value('<i>x</i>')], [], []))
        ->toBe(['&lt;i&gt;x&lt;/i&gt;']);
});

it('coerces non-scalar items to an empty string', function () {
    expect((new SanitizeArray)->index([['nested'], 'ok'], [], []))
        ->toBe(['', 'ok']);
});

it('reads double_encode from a string first argument, as Antlers passes it', function () {
    expect((new SanitizeArray)->index(['&amp;'], ['1'], []))->toBe(['&amp;amp;']);
});

it('encodes with the configured Statamic charset', function () {
    config(['statamic.system.charset' => 'ISO-8859-1']);

    // 0xE9 is a valid ISO-8859-1 byte but an invalid UTF-8 sequence, which UTF-8 encoding drops.
    expect((new SanitizeArray)->index(["caf\xE9 <b>"], [], []))->toBe(["caf\xE9 &lt;b&gt;"]);
});

it('falls back to UTF-8 when the configured charset is not a string', function () {
    config(['statamic.system.charset' => ['ISO-8859-1']]);

    expect((new SanitizeArray)->index(["caf\xC3\xA9 <b>"], [], []))->toBe(["caf\xC3\xA9 &lt;b&gt;"]);
});

it('coerces integer, float and boolean items to strings', function () {
    expect((new SanitizeArray)->index([42, 1.5, true], [], []))->toBe(['42', '1.5', '1']);
});

it('keeps list order for associative arrays and keyed Collections', function (mixed $value) {
    expect((new SanitizeArray)->index($value, [], []))->toBe(['&lt;a&gt;', 'b']);
})->with([
    'associative array' => [['x' => '<a>', 'y' => 'b']],
    'keyed Collection' => [collect([5 => '<a>', 2 => 'b'])],
]);
