<?php

declare(strict_types=1);

use App\Providers\ValidationServiceProvider;
use App\Rules\FirstPartyUrl;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The `first_party_url` and `non_first_party_url` aliases registered in
 * {@see ValidationServiceProvider} both back the single
 * {@see FirstPartyUrl} class, distinguished only by a baked constructor flag.
 * These cases prove both directions are wired through Laravel's validator from the
 * same class.
 *
 * @command php artisan test --compact tests/Feature/Rules/FirstPartyUrlAliasTest.php
 */
it('enforces the non_first_party_url alias', function (string $url, bool $shouldPass): void {
    $validator = Validator::make(
        ['external_website_url' => $url],
        ['external_website_url' => 'non_first_party_url']
    );

    expect($validator->passes())->toBe($shouldPass);
})->with([
    'UAMS URL fails' => ['https://www.uams.edu/x', false],
    'uamshealth URL fails' => ['https://uamshealth.com', false],
    'external URL passes' => ['https://example.org', true],
    'look-alike passes (not UAMS)' => ['https://uams.edu.evil.com', true],
]);

it('enforces the first_party_url alias (inverse of non_first_party_url)', function (string $url, bool $shouldPass): void {
    $validator = Validator::make(
        ['profile_url' => $url],
        ['profile_url' => 'first_party_url']
    );

    expect($validator->passes())->toBe($shouldPass);
})->with([
    'UAMS URL passes' => ['https://www.uams.edu/x', true],
    'external URL fails' => ['https://example.org', false],
    'look-alike fails (not UAMS)' => ['https://notuams.edu', false],
]);
