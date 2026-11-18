<?php

declare(strict_types=1);

use App\Services\Encoding\UrlSafeBase64StringCodec;
use Statamic\Fields\Value;

/**
 * Behavior of UrlSafeBase64StringCodec (app/Services/Encoding/UrlSafeBase64StringCodec.php):
 * URL-safe Base64 without padding (`-`/`_` for `+`/`/`), a strict decode that throws on
 * invalid input, and plainStringFromAntlersValue (unwrap a Value, stringify scalars,
 * empty for unsupported types). Used by the base64_encode / base64_decode modifiers.
 *
 * Mutation: no survivors and no `@pest-mutate-ignore` (#886). The decode empty-token
 * guard and the null guard in plainStringFromAntlersValue were removed as dead code: an
 * empty token decodes to '' through the strict `base64_decode`, and null falls through to
 * the final '' return. Both outcomes are pinned below. The every-length dataset kills the
 * padding mutants: a token whose length is a multiple of four needs no padding, and
 * over-padding it makes the strict decode fail (measured on PHP 8.4.27).
 *
 * @command  php artisan test --compact tests/Unit/Services/Encoding/UrlSafeBase64StringCodecTest.php
 */
it('encodes to a URL-safe token without padding', function (string $plain, string $expected) {
    expect(UrlSafeBase64StringCodec::encode($plain))->toBe($expected);
})->with([
    'a taxonomy-style path' => ['subject-matter/cellulitis', 'c3ViamVjdC1tYXR0ZXIvY2VsbHVsaXRpcw'],
    'empty string encodes to empty' => ['', ''],
    'uses the URL-safe alphabet and strips padding' => ["\xfb\xff", '-_8'],
]);

it('round-trips encode then decode', function (string $original) {
    expect(UrlSafeBase64StringCodec::decode(UrlSafeBase64StringCodec::encode($original)))->toBe($original);
})->with([
    'utf-8 text with an em dash' => ['subject-matter/cellulitis — test'],
    'a plain path' => ['subject-matter/cellulitis'],
    'empty string' => [''],
    'bytes needing url-safe replacement' => ["\xfb\xff"],
]);

it('decodes a known token back to the original', function () {
    expect(UrlSafeBase64StringCodec::decode('c3ViamVjdC1tYXR0ZXIvY2VsbHVsaXRpcw'))
        ->toBe('subject-matter/cellulitis');
});

it('decodes a token of every length without padding', function (string $token, string $expected) {
    expect(UrlSafeBase64StringCodec::decode($token))->toBe($expected);
})->with([
    'length a multiple of four, needing no padding' => ['YWJj', 'abc'],
    'two characters short of a multiple of four' => ['YWJjZA', 'abcd'],
    'one character short of a multiple of four' => ['YWJjZGU', 'abcde'],
    'empty token' => ['', ''],
]);

it('throws on a token one character past a multiple of four', function () {
    expect(fn () => UrlSafeBase64StringCodec::decode('YWJjZ'))
        ->toThrow(InvalidArgumentException::class);
});

it('throws on a payload that is not valid Base64', function () {
    expect(fn () => UrlSafeBase64StringCodec::decode('@@@'))
        ->toThrow(InvalidArgumentException::class);
});

it('coerces an Antlers value to a plain string', function (mixed $value, string $expected) {
    expect(UrlSafeBase64StringCodec::plainStringFromAntlersValue($value))->toBe($expected);
})->with([
    'a wrapped Value unwraps to its inner string' => [new Value('a/b'), 'a/b'],
    'an integer stringifies' => [42, '42'],
    'a bool true stringifies to "1"' => [true, '1'],
    'a plain string passes through' => ['hello', 'hello'],
    'null becomes empty' => [null, ''],
    'a wrapped Value holding null becomes empty' => [new Value(null), ''],
    'false becomes empty' => [false, ''],
    'an array becomes empty' => [['x'], ''],
    'a non-stringable object becomes empty' => [new stdClass, ''],
]);
