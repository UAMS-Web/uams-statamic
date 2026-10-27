<?php

declare(strict_types=1);

use App\Rules\DunsNumber;
use App\Rules\Npi;
use App\Rules\RxCui;
use App\Rules\VaStationId;
use App\Rules\ZipCode;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression coverage for the PCRE trailing-newline anchoring bug (UAMS-Web/uams-statamic-archive#1017).
 *
 * Without the `D` (PCRE_DOLLAR_ENDONLY) modifier, `$` in a PCRE pattern matches
 * at the absolute end of the string OR immediately before a single trailing
 * `\n`, so `preg_match('/^\d+$/', "123\n")` wrongly returns 1. Every
 * `$`-anchored identifier rule now appends `D`, so a value with a trailing
 * newline (`\n`), CRLF (`\r\n`), or carriage return (`\r`) must be rejected even
 * though the leading content is otherwise valid.
 *
 * These representative digit-only rules each use the hardened `/^\d…$/D` form;
 * the assertion below proves a valid value passes while its newline-terminated
 * variants fail. `Npi` performs a live NPPES API lookup *after* the format
 * check, so HTTP is faked to keep the suite hermetic — the rejected cases short-
 * circuit at the format gate and never reach the network.
 *
 * @command  php artisan test --compact tests/Feature/Rules/IdentifierTrailingNewlineRuleTest.php
 */
it('rejects digit-only identifiers carrying a trailing newline, CRLF, or carriage return', function (string $rule, string $valid) {
    // Keep the NPPES lookup off the network for the one rule that performs it.
    Http::fake([
        'npiregistry.cms.hhs.gov/*' => Http::response(['result_count' => 1], 200),
    ]);

    $factory = [
        'npi' => fn (): Npi => new Npi,
        'rxcui' => fn (): RxCui => new RxCui,
        'va_station_id' => fn (): VaStationId => new VaStationId,
        'duns_number' => fn (): DunsNumber => new DunsNumber,
        'zip_code' => fn (): ZipCode => new ZipCode,
    ];

    // The bare valid value passes the format gate.
    expect(validator(['id' => $valid], ['id' => [$factory[$rule]()]])->passes())
        ->toBeTrue("expected {$rule} to accept '{$valid}'");

    // The same value with a trailing newline / CRLF / CR must now be rejected.
    expect(validator(['id' => $valid."\n"], ['id' => [$factory[$rule]()]])->passes())
        ->toBeFalse("expected {$rule} to reject a trailing newline");

    expect(validator(['id' => $valid."\r\n"], ['id' => [$factory[$rule]()]])->passes())
        ->toBeFalse("expected {$rule} to reject a trailing CRLF");

    expect(validator(['id' => $valid."\r"], ['id' => [$factory[$rule]()]])->passes())
        ->toBeFalse("expected {$rule} to reject a trailing carriage return");
})->with([
    'Npi (10 digits, Luhn-valid)' => ['npi', '1234567893'],
    'RxCui (unbounded digits)' => ['rxcui', '12345'],
    'VaStationId (3 digits)' => ['va_station_id', '358'],
    'DunsNumber (9 digits)' => ['duns_number', '150483782'],
    'ZipCode (5 digits)' => ['zip_code', '72205'],
]);
