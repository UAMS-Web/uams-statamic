<?php

declare(strict_types=1);

use App\Modifiers\Base64Decode;

covers(Base64Decode::class);

/**
 * Behavior of the `base64_decode` Antlers modifier
 * (app/Modifiers/Base64Decode.php): a thin wrapper that coerces the Antlers
 * value and decodes URL-safe Base64 via UrlSafeBase64StringCodec, returning an
 * empty string for malformed tokens so templates never throw. The codec itself
 * is exercised by UrlSafeBase64StringCodecTest; this covers the modifier's own
 * coercion + try/catch wiring.
 *
 * Mutation: 100% of the wrapper (no survivors); the codec's logic is mutation-tested
 * under UrlSafeBase64StringCodecTest.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/Base64DecodeTest.php
 */
it('decodes a URL-safe Base64 token, or empty on failure', function (mixed $value, string $expected) {
    expect((new Base64Decode)->index($value, [], []))->toBe($expected);
})->with([
    'decodes a known token' => ['c3ViamVjdC1tYXR0ZXIvY2VsbHVsaXRpcw', 'subject-matter/cellulitis'],
    'malformed token returns empty' => ['@@@', ''],
    'empty string returns empty' => ['', ''],
    'null returns empty' => [null, ''],
]);
