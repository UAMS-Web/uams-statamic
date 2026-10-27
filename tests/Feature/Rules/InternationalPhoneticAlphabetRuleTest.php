<?php

declare(strict_types=1);

use App\Rules\InternationalPhoneticAlphabet;
use Illuminate\Validation\Validator;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Behavior of the App\Rules\InternationalPhoneticAlphabet validation rule: accept
 * IPA strings against the Unicode inventory, optionally allowing /…/ or […]
 * delimiters, normalization, and Google-TTS normalization. Empty values skip;
 * disallowed delimiters and out-of-inventory scalars fail with distinct messages;
 * the registered `international_phonetic_alphabet` string rule mirrors the object
 * rule; and contradictory options (Google TTS without normalize) throw.
 *
 * @command  php artisan test --compact tests/Feature/Rules/InternationalPhoneticAlphabetRuleTest.php
 */
it('passes for an empty string (optional field)', function () {
    $validator = validator(['ipa' => ''], ['ipa' => [new InternationalPhoneticAlphabet]]);

    expect($validator->passes())->toBeTrue();
});

it('passes for bare IPA when delimiters are disallowed (default)', function () {
    $validator = validator(['ipa' => 'pʰə̥ˈkj̊uːliɚ'], ['ipa' => [new InternationalPhoneticAlphabet]]);

    expect($validator->passes())->toBeTrue();
});

it('fails for slashed IPA when delimiters are disallowed (default)', function () {
    /** @var Validator $validator */
    $validator = validator(['ipa' => '/pʰə̥ˈkj̊uːliɚ/'], ['ipa' => [new InternationalPhoneticAlphabet]]);

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->first('ipa'))->toBe(__(
        'statamic::validation.international_phonetic_alphabet.delimiters_disallowed',
        ['attribute' => $validator->getDisplayableAttribute('ipa')],
    ));
});

it('uses custom attribute labels for nested-field delimiter and invalid errors', function () {
    $validator = validator(
        ['last_name' => ['phonetic_text' => '/ˈtɛst/']],
        ['last_name.phonetic_text' => [new InternationalPhoneticAlphabet]],
        [],
        ['last_name.phonetic_text' => 'Phonetic Transcription'],
    );

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->first('last_name.phonetic_text'))->toBe(__(
        'statamic::validation.international_phonetic_alphabet.delimiters_disallowed',
        ['attribute' => 'Phonetic Transcription'],
    ));

    $validatorInvalid = validator(
        ['last_name' => ['phonetic_text' => "tɛst\u{2603}"]],
        ['last_name.phonetic_text' => [new InternationalPhoneticAlphabet]],
        [],
        ['last_name.phonetic_text' => 'Phonetic Transcription'],
    );

    expect($validatorInvalid->passes())->toBeFalse();
    expect($validatorInvalid->errors()->first('last_name.phonetic_text'))->toBe(__(
        'statamic::validation.international_phonetic_alphabet.invalid',
        ['attribute' => 'Phonetic Transcription'],
    ));
});

it('resolves nested attribute labels through the registered string rule', function () {
    $validator = validator(
        ['last_name' => ['phonetic_text' => '/ˈtɛst/']],
        ['last_name.phonetic_text' => ['international_phonetic_alphabet']],
        [],
        ['last_name.phonetic_text' => 'Phonetic Transcription'],
    );

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->first('last_name.phonetic_text'))->toBe(__(
        'statamic::validation.international_phonetic_alphabet.delimiters_disallowed',
        ['attribute' => 'Phonetic Transcription'],
    ));
});

it('passes for slashed IPA when delimiters are allowed', function () {
    $validator = validator(
        ['ipa' => '/pʰə̥ˈkj̊uːliɚ/'],
        ['ipa' => [new InternationalPhoneticAlphabet(allowDelimiters: true)]],
    );

    expect($validator->passes())->toBeTrue();
});

it('fails for a scalar outside the inventory', function () {
    $validator = validator(['ipa' => "tɛst\u{2603}"], ['ipa' => [new InternationalPhoneticAlphabet]]);

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->toArray())->toHaveKey('ipa');
});

it('passes for the tesh digraph (U+02A7), which is in the inventory', function () {
    $validator = validator(['ipa' => "t\u{02A7}ɪn"], ['ipa' => [new InternationalPhoneticAlphabet]]);

    expect($validator->passes())->toBeTrue();
});

it('normalizes a legacy ASCII apostrophe to primary stress when normalize is enabled', function () {
    $validator = validator(
        ['ipa' => "'tɛst"],
        ['ipa' => [new InternationalPhoneticAlphabet(allowDelimiters: false, normalize: true)]],
    );

    expect($validator->passes())->toBeTrue();
});

it('passes for slashed IPA in Google TTS mode', function () {
    $validator = validator(
        ['ipa' => '/ˈtɛst/'],
        ['ipa' => [new InternationalPhoneticAlphabet(allowDelimiters: true, normalize: true, googleTtsNormalization: true)]],
    );

    expect($validator->passes())->toBeTrue();
});

it('throws when Google TTS normalization is enabled without normalize', function () {
    expect(fn () => new InternationalPhoneticAlphabet(
        allowDelimiters: true,
        normalize: false,
        googleTtsNormalization: true,
    ))->toThrow(InvalidArgumentException::class);
});

it('passes for bare IPA through the registered string rule', function () {
    $validator = validator(['ipa' => 'ˈtɛst'], ['ipa' => ['international_phonetic_alphabet']]);

    expect($validator->passes())->toBeTrue();
});

it('enables allowDelimiters via comma-separated string-rule parameters', function () {
    $validator = validator(['ipa' => '/pʰə̥ˈkj̊uːliɚ/'], ['ipa' => ['international_phonetic_alphabet:1,0,0']]);

    expect($validator->passes())->toBeTrue();
});

it('throws for the string rule with Google TTS on but normalize off', function () {
    expect(fn () => validator(
        ['ipa' => 'ˈtɛst'],
        ['ipa' => ['international_phonetic_alphabet:1,0,1']],
    )->passes())->toThrow(InvalidArgumentException::class);
});
