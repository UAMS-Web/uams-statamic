<?php

declare(strict_types=1);

use App\Enums\Language;
use App\Enums\LanguageBCP47;

/**
 * Behavior of Language::fieldValueForLanguageCode() and
 * LanguageBCP47::fieldValueForLanguageCode(): allowlisted field keys, lazy
 * registry map reads, and null when the code or field name is not valid for the
 * enum. The enums are large machine-generated data tables (excluded from PHPStan
 * analysis and impractical to mutation-test in full); these cover the lookup
 * method's logic, an accepted data-enum coverage boundary.
 *
 * @command  php artisan test --compact tests/Unit/Enums/LanguageFieldValueForLanguageCodeTest.php
 */
it('resolves the BCP 47 description for en', function () {
    $value = LanguageBCP47::fieldValueForLanguageCode('en', 'description');

    expect($value)->toBeString();
    expect($value)->toContain('English');
});

it('returns null for an unknown BCP 47 code', function () {
    expect(LanguageBCP47::fieldValueForLanguageCode('__missing__', 'description'))->toBeNull();
});

it('returns null for a BCP 47 field outside the allowlist', function () {
    expect(LanguageBCP47::fieldValueForLanguageCode('en', 'not_a_field'))->toBeNull();
});

it('resolves the ISO 639 name for eng', function () {
    $value = Language::fieldValueForLanguageCode('eng', 'name');

    expect($value)->toBeString();
    expect($value)->toContain('English');
});

it('returns null for an unknown ISO 639 code', function () {
    expect(Language::fieldValueForLanguageCode('zzz_missing', 'name'))->toBeNull();
});
