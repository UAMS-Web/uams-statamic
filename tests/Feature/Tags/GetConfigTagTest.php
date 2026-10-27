<?php

declare(strict_types=1);

use App\Tags\GetConfig;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `get_config` Antlers tag.
 *
 * Mutation: 100% on App\Tags\GetConfig (56 mutants, --covered-only; every line covered). Nine
 * equivalents are annotated in the class.
 *
 * @command  php artisan test --compact tests/Feature/Tags/GetConfigTagTest.php
 */
it('returns value for allowlisted crosswalk key', function () {
    // Allowlisted crosswalk leaf key returns the configured scalar.
    $tag = new GetConfig;
    $tag->setContext([]);
    $tag->setParameters([
        'key' => 'crosswalk.mappings.CredentialRegistry.AssociateDegree.IscedA2011',
    ]);

    expect($tag->index())->toBe('5');
});

it('strips the optional config prefix', function () {
    // Leading `config.` segment is stripped before allowlist and lookup.
    $tag = new GetConfig;
    $tag->setContext([]);
    $tag->setParameters([
        'key' => 'config.crosswalk.mappings.CredentialRegistry.AssociateDegree.IscedA2011',
    ]);

    expect($tag->index())->toBe('5');
});

it('returns empty for a disallowed key', function () {
    // Keys not matching allowed patterns return empty string (no fallback read).
    $tag = new GetConfig;
    $tag->setContext([]);
    $tag->setParameters([
        'key' => 'app.name',
    ]);

    expect($tag->index())->toBe('');
});

it('returns empty when the key is missing', function () {
    // Missing or empty key parameter yields empty output.
    $tag = new GetConfig;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});

it('returns empty for an array value without json format', function () {
    // Array values return empty unless format=json.
    $tag = new GetConfig;
    $tag->setContext([]);
    $tag->setParameters([
        'key' => 'crosswalk.mappings.CredentialRegistry.AssociateDegree',
    ]);

    expect($tag->index())->toBe('');
});

it('encodes an array value as json when requested', function () {
    // Array values encode as JSON when format=json.
    $tag = new GetConfig;
    $tag->setContext([]);
    $tag->setParameters([
        'key' => 'crosswalk.mappings.CredentialRegistry.AssociateDegree',
        'format' => 'json',
    ]);

    $output = $tag->index();

    expect($output)->toBeString();

    /** @var array<string,mixed> $decoded */
    $decoded = json_decode(
        (string) $output,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($decoded)->toHaveKey('IscedA2011');
    expect(data_get($decoded, 'IscedA2011'))->toBe('5');
});

/**
 * A `get_config` tag with the given parameters.
 *
 * @param  array<string, mixed>  $parameters
 */
function getConfigTag(array $parameters): GetConfig
{
    $tag = new GetConfig;
    $tag->setContext([]);
    $tag->setParameters($parameters);

    return $tag;
}

it('returns empty for a key that is only whitespace or only the config prefix', function () {
    // Both normalize to '' before the allowlist is consulted.
    config(['get_config.allowed_key_patterns' => ['*']]);

    expect(getConfigTag(['key' => '   '])->index())->toBe('')
        ->and(getConfigTag(['key' => 'config.'])->index())->toBe('');
});

it('trims the key and the remainder after the config prefix', function () {
    config(['crosswalk.probe' => 'trimmed']);

    expect(getConfigTag(['key' => '  crosswalk.probe  '])->index())->toBe('trimmed')
        ->and(getConfigTag(['key' => 'config.  crosswalk.probe'])->index())->toBe('trimmed');
});

it('returns empty for an allowlisted key with no value', function () {
    expect(getConfigTag(['key' => 'crosswalk.no_such_key'])->index())->toBe('');
});

it('returns booleans, integers and floats unchanged', function (mixed $value) {
    config(['crosswalk.probe' => $value]);

    expect(getConfigTag(['key' => 'crosswalk.probe'])->index())->toBe($value);
})->with([
    'true' => [true],
    'false' => [false],
    'zero' => [0],
    'integer' => [42],
    'float' => [1.5],
]);

it('returns an empty string as an empty string', function () {
    config(['crosswalk.probe' => '']);

    expect(getConfigTag(['key' => 'crosswalk.probe'])->index())->toBe('');
});

it('returns empty for a value that is neither scalar nor array', function () {
    config(['crosswalk.probe' => new stdClass]);

    expect(getConfigTag(['key' => 'crosswalk.probe'])->index())->toBe('');
});

it('accepts the json format case-insensitively and with surrounding space', function () {
    config(['crosswalk.probe' => ['a' => 'b/c', 'd' => 'é']]);

    expect(getConfigTag(['key' => 'crosswalk.probe', 'format' => '  JSON '])->index())
        ->toBe('{"a":"b/c","d":"é"}');
});

it('returns empty for an array when format is not a string or not json', function (mixed $format) {
    config(['crosswalk.probe' => ['a' => 'b']]);

    expect(getConfigTag(['key' => 'crosswalk.probe', 'format' => $format])->index())->toBe('');
})->with([
    'yaml' => ['yaml'],
    'array' => [['json']],
    'boolean' => [true],
]);

it('returns empty when an array cannot be encoded as json', function () {
    // Invalid UTF-8 makes json_encode throw under JSON_THROW_ON_ERROR.
    config(['crosswalk.probe' => ['bad' => "\xB1\x31"]]);

    expect(getConfigTag(['key' => 'crosswalk.probe', 'format' => 'json'])->index())->toBe('');
});

it('denies every key when the allowlist is missing, not an array, or empty', function (mixed $patterns) {
    config(['crosswalk.probe' => 'visible', 'get_config.allowed_key_patterns' => $patterns]);

    expect(getConfigTag(['key' => 'crosswalk.probe'])->index())->toBe('');
})->with([
    'null' => [null],
    'string' => ['crosswalk.*'],
    'empty' => [[]],
]);

it('skips non-string and empty patterns and still matches a later one', function () {
    config(['crosswalk.probe' => 'visible', 'get_config.allowed_key_patterns' => [42, '', 'crosswalk.*']]);

    expect(getConfigTag(['key' => 'crosswalk.probe'])->index())->toBe('visible');
});

it('denies a key that only a skipped pattern would have matched', function () {
    // An empty pattern is skipped rather than matched, so it never allows a key.
    config(['crosswalk.probe' => 'visible', 'get_config.allowed_key_patterns' => [42, '']]);

    expect(getConfigTag(['key' => 'crosswalk.probe'])->index())->toBe('');
});

it('returns empty for a bare config prefix even when every key and json output are allowed', function () {
    // Pins the end result rather than one guard: Config::get('') returns null, so
    // the empty key is refused whichever guard catches it.
    config(['get_config.allowed_key_patterns' => ['*']]);

    expect(getConfigTag(['key' => 'config.', 'format' => 'json'])->index())->toBe('');
});

it('strips the config prefix after leading whitespace', function () {
    config(['crosswalk.probe' => 'trimmed']);

    expect(getConfigTag(['key' => '  config.crosswalk.probe'])->index())->toBe('trimmed');
});

it('accepts a non-string key by casting it', function () {
    // Antlers can pass a number; the cast keeps it from reaching a string parameter.
    config(['get_config.allowed_key_patterns' => ['*']]);
    config()->set('42', 'answer');

    expect(getConfigTag(['key' => 42])->index())->toBe('answer');
});
