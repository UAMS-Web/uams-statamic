<?php

declare(strict_types=1);

use App\Modifiers\Base64Encode;

/**
 * Behavior of the `base64_encode` Antlers modifier
 * (app/Modifiers/Base64Encode.php): a thin wrapper that coerces the Antlers
 * value and encodes it as URL-safe Base64 without padding via
 * UrlSafeBase64StringCodec. The codec itself is exercised by
 * UrlSafeBase64StringCodecTest; this covers the modifier's coercion + wiring.
 *
 * Mutation: the wrapper has no mutable surface (0 mutations — pure delegation, no
 * branches/literals/casts); the codec's logic is mutation-tested under
 * UrlSafeBase64StringCodecTest.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/Base64EncodeTest.php
 */
it('encodes a value as URL-safe Base64 without padding', function (mixed $value, string $expected) {
    expect((new Base64Encode)->index($value, [], []))->toBe($expected);
})->with([
    'encodes a path' => ['subject-matter/cellulitis', 'c3ViamVjdC1tYXR0ZXIvY2VsbHVsaXRpcw'],
    'uses the URL-safe alphabet and strips padding' => ["\xfb\xff", '-_8'],
    'empty string encodes to empty' => ['', ''],
    'null encodes to empty' => [null, ''],
]);
