<?php

declare(strict_types=1);

use App\Services\Encoding\UrlSafeBase64StringCodec;
use App\Services\SchemaTemplates\SchemaJsonLdFragmentAtId;
use Tests\TestCase;

covers(SchemaJsonLdFragmentAtId::class);

uses(TestCase::class);

/**
 * Unit tests for `SchemaJsonLdFragmentAtId` canonical JSON-LD `@id` strings.
 *
 * Verifies root-relative paths encode to the same URL-safe Base64 fragment
 * as `UrlSafeBase64StringCodec` and that invalid paths throw.
 *
 * Mutation: not recorded. An earlier figure here (100% on App\Services\SchemaTemplates\SchemaJsonLdFragmentAtId,
 * 10 mutants, --covered-only) named no command or revision and was removed (UAMS-Web/uams-statamic-archive#2915).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaJsonLdFragmentAtIdTest.php
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

it('joins the base url, hash, and encoded path into a matching @id', function (): void {
    // `atIdFromRootRelativePath` joins `config('app.url')`, `#`, and the
    // encoded path identically to a manual stem plus codec encoding.
    $appUrl = config('app.url');
    $base = rtrim(
        is_scalar($appUrl) ? (string) $appUrl : '',
        '/',
    );
    $encoded = UrlSafeBase64StringCodec::encode('/country/us');
    $expected = "{$base}#{$encoded}";

    expect(SchemaJsonLdFragmentAtId::atIdFromRootRelativePath('/country/us'))->toBe($expected);
});

it('strips a trailing slash from app.url before joining the fragment', function (): void {
    config(['app.url' => 'https://example.test/']);

    $encoded = UrlSafeBase64StringCodec::encode('/country/us');

    expect(SchemaJsonLdFragmentAtId::atIdFromRootRelativePath('/country/us'))
        ->toBe("https://example.test#{$encoded}");
});

it('uses an empty stem when app.url is not scalar', function (): void {
    config(['app.url' => ['not', 'a', 'string']]);

    $encoded = UrlSafeBase64StringCodec::encode('/x');

    expect(SchemaJsonLdFragmentAtId::atIdFromRootRelativePath('/x'))
        ->toBe("#{$encoded}");
});

it('casts a scalar non-string app.url before rtrim', function (): void {
    // RemoveStringCast would pass an int into rtrim() and TypeError.
    config(['app.url' => 443]);

    $encoded = UrlSafeBase64StringCodec::encode('/x');

    expect(SchemaJsonLdFragmentAtId::atIdFromRootRelativePath('/x'))
        ->toBe("443#{$encoded}");
});

it('rejects an empty path', function (): void {
    expect(fn (): string => SchemaJsonLdFragmentAtId::atIdFromRootRelativePath(''))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects paths that are not root-relative', function (): void {
    // `atIdFromRootRelativePath` rejects paths that do not start with `/`.
    expect(fn (): string => SchemaJsonLdFragmentAtId::atIdFromRootRelativePath('country/us'))
        ->toThrow(InvalidArgumentException::class);
});
