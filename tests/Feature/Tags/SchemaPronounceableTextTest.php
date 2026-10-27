<?php

declare(strict_types=1);

use App\Tags\SchemaPronounceableText;
use Statamic\Facades\Antlers;
use Statamic\Fields\Values;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_pronounceable_text` Antlers tag.
 *
 * Mutation: 100% on App\Tags\SchemaPronounceableText (188 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaPronounceableTextTest.php
 */
it('phonetic emits an ipa object and its derived ssml sibling', function () {
    // Parse the pronounceable text tag with phonetic and language context
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text in_language="en" phonetic_text="ʃɪˈvɔːn" speech_to_text_markup="IPA" text_value="Siobhan" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert one IPA object and one SSML object derived from the same input
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(2);

    // Assert the IPA fields
    expect(data_get($decoded, '0.@type'))->toBe('PronounceableText');
    expect(data_get($decoded, '0.textValue'))->toBe('Siobhan');
    expect(data_get($decoded, '0.speechToTextMarkup'))->toBe('IPA');
    expect(data_get($decoded, '0.phoneticText'))->toBe('/ʃɪˈvɔːn/');
    expect(data_get($decoded, '0.inLanguage.@type'))->toBe('Language');
    expect(data_get($decoded, '0.inLanguage.alternateName'))->toBe('en');

    // Assert the SSML sibling carries the same text and language
    expect(data_get($decoded, '1.@type'))->toBe('PronounceableText');
    expect(data_get($decoded, '1.textValue'))->toBe('Siobhan');
    expect(data_get($decoded, '1.speechToTextMarkup'))->toBe('SSML');
    expect(data_get($decoded, '1.inLanguage.@type'))->toBe('Language');
    expect(data_get($decoded, '1.inLanguage.alternateName'))->toBe('en');

    // Assert the SSML document wraps the surface text in an IPA phoneme
    expect(data_get($decoded, '1.phoneticText'))->toBe(
        '<speak><phoneme alphabet="ipa" ph="ʃɪˈvɔːn">Siobhan</phoneme></speak>'
    );

    // Assert the SSML phoneme carries the IPA WITHOUT the slash wrapping
    expect(data_get($decoded, '1.phoneticText'))->not->toContain('/ʃɪˈvɔːn/');
});

it('a non-ipa markup emits one object and derives no ssml', function () {
    // Parse the tag with an alphabet SSML cannot be derived from
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text phonetic_text="dZAS" speech_to_text_markup="X-SAMPA" text_value="Josh" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert the input's own form is emitted alone
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);
    expect(data_get($decoded, '0.speechToTextMarkup'))->toBe('X-SAMPA');
    expect(data_get($decoded, '0.phoneticText'))->toBe('/dZAS/');
});

it('ssml derivation escapes the attribute and the surface text', function () {
    // Parse with characters that would otherwise break the SSML XML
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text :text_value="tv" :phonetic_text="pt" }}',
        [
            'pt' => '"x"',
            'tv' => 'A & B',
        ],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert the quote cannot terminate the ph attribute early
    expect(data_get($decoded, '1.phoneticText'))->toBe(
        '<speak><phoneme alphabet="ipa" ph="&quot;x&quot;">A &amp; B</phoneme></speak>'
    );

    // Assert the SSML document still parses as XML after escaping
    $ssml = data_get($decoded, '1.phoneticText');
    expect($ssml)->toBeString();
    expect(simplexml_load_string(is_string($ssml) ? $ssml : ''))->not->toBeFalse();
});

it('language standard iso639', function () {
    // Parse the tag with an ISO 639-3 code and matching standard
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text text_value="Hi" in_language="eng" phonetic_text="haɪ" language_standard="iso639" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert Language node uses the stored ISO code as alternateName
    expect(data_get($decoded, '0.inLanguage.@type'))->toBe('Language');
    expect(data_get($decoded, '0.inLanguage.alternateName'))->toBe('eng');
});

it('empty text value outputs empty string', function () {
    // Parse the tag with an empty text value
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text text_value="" phonetic_text="x" in_language="en" }}',
        [],
        true
    );

    // Assert the template produced nothing
    expect($output)->toBe('');
});

it('no phonetic or markup emits json string', function () {
    // Parse the tag without phonetic or markup (in_language ignored for Text)
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text text_value="Hello" in_language="en" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert the payload is a plain string, not a PronounceableText object
    expect($decoded)->toBe('Hello');
});

it('markup without phonetic emits json text string', function () {
    // Parse the tag with markup only (no phonetic; markup does not force structure)
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text text_value="Hello" speech_to_text_markup="IPA" in_language="en" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert the payload is a plain string, not a PronounceableText object
    expect($decoded)->toBe('Hello');
});

it('unknown language code uses text fallback', function () {
    // Parse the tag with an unknown BCP 47 code
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text text_value="Hi" in_language="__bad__" phonetic_text="hʌɪ" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert both derived objects carry the Text fallback language
    expect($decoded)->toHaveCount(2);
    expect(data_get($decoded, '0.inLanguage'))->toBe('__bad__');
    expect(data_get($decoded, '1.inLanguage'))->toBe('__bad__');
});

it('bound values in text and phonetic', function () {
    // Parse the tag with bound values so quotes and ampersands are literal
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text :text_value="tv" :phonetic_text="pt" in_language="en" }}',
        [
            'pt' => '"x"',
            'tv' => 'A & B',
        ],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert the IPA object carries surface text and phonetic unescaped
    expect($decoded)->toHaveCount(2);
    expect(data_get($decoded, '0.textValue'))->toBe('A & B');
    expect(data_get($decoded, '0.phoneticText'))->toBe('/"x"/');

    // Assert the SSML sibling escapes both for XML while textValue does not
    expect(data_get($decoded, '1.textValue'))->toBe('A & B');
    expect(data_get($decoded, '1.phoneticText'))->toBe(
        '<speak><phoneme alphabet="ipa" ph="&quot;x&quot;">A &amp; B</phoneme></speak>'
    );
});

it('identical tag calls in one parse use request cache', function () {
    // Repeat the same pronounceable tag so only one cache entry is needed
    $tagRepeated = str_repeat(
        '{{ schema_pronounceable_text text_value="Welch" phonetic_text="wɛlʧ" in_language="en_US" }}',
        4
    );

    // Render the template so the tag runs multiple times
    (string) Antlers::parse(
        $tagRepeated,
        [],
        true
    );

    // Read the private request-cache attribute name via reflection
    $reflection = new ReflectionClass(SchemaPronounceableText::class);
    $attrName = $reflection->getConstant('REQUEST_CACHE_ATTRIBUTE');

    // Assert the constant is the expected string before using it as a key
    expect($attrName)->toBeString();

    // Read the memo table from the current request
    $cache = request()->attributes->get(is_string($attrName) ? $attrName : '');

    // Assert one memo key covers all four invocations
    expect($cache)->toBeArray();
    expect($cache)->toHaveCount(1);
});

it('pairs text is stripped and sanitized', function () {
    // Bind HTML and an ampersand so tags are removed and entities escaped
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text :pairs="row" }}',
        [
            'row' => [
                'phonetic_text' => 'fu',
                'text' => '<em>Tom</em> & Jerry',
            ],
        ],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert strip_tags removed markup and sanitize escaped the ampersand
    expect(data_get($decoded, '0.textValue'))->toBe('Tom &amp; Jerry');
});

it('pairs only markup row is skipped', function () {
    // First row strips to empty; second row remains
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text :pairs="rows" }}',
        [
            'rows' => [
                [
                    'text' => '<p></p>',
                ],
                [
                    'phonetic_text' => 'ɛks',
                    'text' => 'X',
                ],
            ],
        ],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert only the non-empty row survives, as its IPA and SSML pair
    expect($decoded)->toHaveCount(2);
    expect(data_get($decoded, '0.textValue'))->toBe('X');
    expect(data_get($decoded, '0.speechToTextMarkup'))->toBe('IPA');
    expect(data_get($decoded, '1.textValue'))->toBe('X');
    expect(data_get($decoded, '1.speechToTextMarkup'))->toBe('SSML');
});

it('pairs single associative row', function () {
    // Bind one map so the tag reads text/phonetic from a single structure
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text :pairs="row" in_language="en" }}',
        [
            'row' => [
                'phonetic_text' => 'fu',
                'text' => 'foo',
            ],
        ],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert the same IPA-plus-SSML pair as flat parameters produce
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(2);
    expect(data_get($decoded, '0.@type'))->toBe('PronounceableText');
    expect(data_get($decoded, '0.textValue'))->toBe('foo');
    expect(data_get($decoded, '0.phoneticText'))->toBe('/fu/');
    expect(data_get($decoded, '1.speechToTextMarkup'))->toBe('SSML');
    expect(data_get($decoded, '1.phoneticText'))->toBe(
        '<speak><phoneme alphabet="ipa" ph="fu">foo</phoneme></speak>'
    );
});

it('pairs list emits two pronounceable objects', function () {
    // Bind two rows for one tag invocation
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text :pairs="rows" in_language="en" }}',
        [
            'rows' => [
                [
                    'phonetic_text' => 'fu',
                    'text' => 'foo',
                ],
                [
                    'phonetic_text' => 'bɑr',
                    'text' => 'bar',
                ],
            ],
        ],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert two pronounceable objects in order
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(4);
    expect(data_get($decoded, '0.textValue'))->toBe('foo');
    expect(data_get($decoded, '0.phoneticText'))->toBe('/fu/');
    expect(data_get($decoded, '1.speechToTextMarkup'))->toBe('SSML');
    expect(data_get($decoded, '2.textValue'))->toBe('bar');
    expect(data_get($decoded, '2.phoneticText'))->toBe('/bɑr/');
    expect(data_get($decoded, '3.speechToTextMarkup'))->toBe('SSML');

    // Assert each row's SSML sibling follows its own IPA object, in order
    expect(data_get($decoded, '1.textValue'))->toBe('foo');
    expect(data_get($decoded, '3.textValue'))->toBe('bar');
});

it('pairs row accepts text value key', function () {
    // Use text_value inside the pair map instead of text
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text :pairs="row" phonetic_text="ignored" }}',
        [
            'row' => [
                'phonetic_text' => 'haɪ',
                'text_value' => 'Hi',
            ],
        ],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert flat phonetic_text on the tag was ignored in pairs mode
    expect($decoded)->toHaveCount(2);
    expect(data_get($decoded, '0.textValue'))->toBe('Hi');
    expect(data_get($decoded, '0.phoneticText'))->toBe('/haɪ/');
    expect(data_get($decoded, '1.speechToTextMarkup'))->toBe('SSML');
});

it('pairs single row without phonetic emits json string', function () {
    // Omit phonetic_text on the single row map
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text :pairs="row" }}',
        [
            'row' => [
                'text' => 'Hello',
            ],
        ],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert plain string output
    expect($decoded)->toBe('Hello');
});

it('pairs mixed text and pronounceable fragments', function () {
    // First row has no phonetic; second has IPA
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text :pairs="rows" in_language="en" }}',
        [
            'rows' => [
                [
                    'text' => 'plain',
                ],
                [
                    'phonetic_text' => 'aɪ',
                    'text' => 'eye',
                ],
            ],
        ],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert string then object ordering
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(3);
    expect(data_get($decoded, '0'))->toBe('plain');
    expect(data_get($decoded, '1.@type'))->toBe('PronounceableText');
    expect(data_get($decoded, '1.textValue'))->toBe('eye');
    expect(data_get($decoded, '1.speechToTextMarkup'))->toBe('IPA');

    // Assert the plain Text row gains no SSML sibling, only the phonetic row
    expect(data_get($decoded, '2.@type'))->toBe('PronounceableText');
    expect(data_get($decoded, '2.speechToTextMarkup'))->toBe('SSML');
});

it('pairs empty list outputs empty string', function () {
    // Pass an empty list with pairs bound so the pairs branch runs
    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text :pairs="rows" }}',
        [
            'rows' => [],
        ],
        true
    );

    // Assert nothing is emitted
    expect($output)->toBe('');
});

it('identical pairs tag calls use request cache', function () {
    // Repeat the same pairs tag so only one cache entry is needed
    $rows = [
        [
            'phonetic_text' => 'wɛlʧ',
            'text' => 'Welch',
        ],
    ];
    $tagRepeated = str_repeat(
        '{{ schema_pronounceable_text :pairs="rows" in_language="en_US" }}',
        3
    );

    // Render the template so the tag runs multiple times
    (string) Antlers::parse(
        $tagRepeated,
        [
            'rows' => $rows,
        ],
        true
    );

    // Read the private request-cache attribute name via reflection
    $reflection = new ReflectionClass(SchemaPronounceableText::class);
    $attrName = $reflection->getConstant('REQUEST_CACHE_ATTRIBUTE');

    // Assert the constant is the expected string before using it as a key
    expect($attrName)->toBeString();

    // Read the memo table from the current request
    $cache = request()->attributes->get(is_string($attrName) ? $attrName : '');

    // Assert one memo key covers all three invocations
    expect($cache)->toBeArray();
    expect($cache)->toHaveCount(1);
});

it('pairs accepts values row objects', function () {
    $rows = [
        new Values([
            'text' => 'Josh',
            'phonetic_text' => 'dʒɑʃ',
            'id' => null,
        ]),
    ];

    $output = (string) Antlers::parse(
        '{{ schema_pronounceable_text :pairs="rows" in_language="en_US" speech_to_text_markup="IPA" }}',
        [
            'rows' => $rows,
        ],
        true
    );

    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(2);
    expect(data_get($decoded, '0.@type'))->toBe('PronounceableText');
    expect(data_get($decoded, '0.textValue'))->toBe('Josh');
    expect(data_get($decoded, '0.phoneticText'))->toBe('/dʒɑʃ/');
    expect(data_get($decoded, '1.speechToTextMarkup'))->toBe('SSML');
    expect(data_get($decoded, '1.phoneticText'))->toBe(
        '<speak><phoneme alphabet="ipa" ph="dʒɑʃ">Josh</phoneme></speak>'
    );
});
