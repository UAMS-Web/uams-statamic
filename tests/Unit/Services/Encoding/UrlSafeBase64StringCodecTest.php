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
 * Mutation: every killable mutant is dead (score ~91%). The residual survivors are
 * equivalents — the decode empty-token guard (an empty string decodes to '' via the
 * normal path too), the null guard in plainStringFromAntlersValue (null falls to the
 * final '' return regardless), and the `(string)` cast on the already-scalar value
 * (redundant with the string return type). Left clean rather than annotated with
 * `@pest-mutate-ignore`.
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
    'false becomes empty' => [false, ''],
    'an array becomes empty' => [['x'], ''],
    'a non-stringable object becomes empty' => [new stdClass, ''],
]);
