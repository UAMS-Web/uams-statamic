<?php

declare(strict_types=1);

use App\Fieldtypes\TextPhoneticPair;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Feature tests for the TextPhoneticPair fieldtype (text plus IPA phonetic pair
 * normalization and nested validation attribute keys).
 *
 * Mutation: 100% on App\Fieldtypes\TextPhoneticPair (32 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/TextPhoneticPairFieldtypeTest.php
 */
it('process normalizes pair', function () {
    $field = new Field(
        'label',
        [
            'type' => 'text_phonetic_pair',
        ]
    );

    // Assert on the call, not the annotated variable: the `@var` below is what makes
    // an instanceof over `$fieldtype` statically redundant.
    expect($field->fieldtype())->toBeInstanceOf(TextPhoneticPair::class);

    /** @var TextPhoneticPair $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'text' => 'Hello',
        'phonetic_text' => 'həˈloʊ',
    ]);

    expect(data_get($out, 'text'))->toBe('Hello');
    expect(data_get($out, 'phonetic_text'))->toBe('həˈloʊ');
});

it('process non array returns empty pair', function () {
    $field = new Field(
        'label',
        [
            'type' => 'text_phonetic_pair',
        ]
    );

    // Assert on the call, not the annotated variable: the `@var` below is what makes
    // an instanceof over `$fieldtype` statically redundant.
    expect($field->fieldtype())->toBeInstanceOf(TextPhoneticPair::class);

    /** @var TextPhoneticPair $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $out = $fieldtype->process(null);

    expect(data_get($out, 'text'))->toBe('');
    expect(data_get($out, 'phonetic_text'))->toBe('');
});

it('extra validation attributes include nested keys', function () {
    $field = new Field(
        'pair_field',
        [
            'type' => 'text_phonetic_pair',
        ]
    );

    /** @var TextPhoneticPair $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $attrs = $fieldtype->extraValidationAttributes();

    expect($attrs)->toHaveKey('pair_field.text');
    expect($attrs)->toHaveKey('pair_field.phonetic_text');
});

/**
 * Build a TextPhoneticPair bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function textPhoneticPairFieldtype(array $config = []): TextPhoneticPair
{
    // Bind a Field named pair so validation keys are prefixed with it
    $fieldtype = new TextPhoneticPair;
    $fieldtype->setField(new Field('pair', array_merge(['type' => 'text_phonetic_pair'], $config)));

    return $fieldtype;
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = new TextPhoneticPair;

    expect(TextPhoneticPair::title())->toBe('Text & Phonetic Pair')
        ->and($fieldtype->component())->toBe('text_phonetic_pair')
        ->and($fieldtype->icon())->toBe('text-formatting')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeTrue()
        ->and($fieldtype->keywords())->toBe(['text', 'phonetic', 'ipa', 'pair'])
        ->and($fieldtype->categories())->toBe(['text'])
        ->and($fieldtype->rules())->toBe(['array']);
});

it('appends its own config section to the parent config', function (): void {
    expect((new ReflectionMethod(TextPhoneticPair::class, 'configFieldItems'))->invoke(new TextPhoneticPair))->toBe([
        [
            'display' => 'Text & phonetic pair',
            'fields' => [
                'text_label' => [
                    'type' => 'text',
                    'display' => 'Text label',
                    'instructions' => 'Label shown above the plain text input. Leave empty to use the default.',
                    'default' => '',
                ],
                'phonetic_label' => [
                    'type' => 'text',
                    'display' => 'Phonetic label',
                    'instructions' => 'Label shown for the IPA phonetic input. Leave empty to use the default.',
                    'default' => '',
                ],
                'quote_straight_on_text' => [
                    'type' => 'toggle',
                    'display' => 'Straight quotes on text',
                    'instructions' => 'Show straight double quotes as decorative prepend/append on the text field (not stored).',
                    'default' => false,
                ],
                'split_inputs' => [
                    'type' => 'toggle',
                    'display' => 'Split layout (md+)',
                    'instructions' => 'When enabled, text and phonetic inputs sit side by side from the medium breakpoint; when disabled, they stack full width.',
                    'default' => true,
                ],
                'border' => [
                    'type' => 'toggle',
                    'display' => 'Bordered container',
                    'instructions' => 'Wrap the pair in the same bordered card shell as a Group field.',
                    'default' => true,
                ],
                'body_class' => [
                    'type' => 'text',
                    'display' => 'Body class',
                    'instructions' => 'Optional Tailwind / utility classes for the inner wrapper (default: vertical spacing).',
                    'default' => 'space-y-6',
                ],
                'require_text' => [
                    'type' => 'toggle',
                    'display' => 'Require text',
                    'instructions' => 'When enabled, the text value must be filled (or the field is marked required in the blueprint).',
                    'default' => true,
                ],
                'require_phonetic' => [
                    'type' => 'toggle',
                    'display' => 'Require phonetic text',
                    'instructions' => 'When enabled, the phonetic (IPA) value must be filled.',
                    'default' => false,
                ],
                'include_phonetic_instructions' => [
                    'type' => 'toggle',
                    'display' => 'Include phonetic text instructions',
                    'instructions' => 'When enabled, the IPA help alert is shown above the pair. When disabled, that alert is hidden.',
                    'default' => true,
                ],
            ],
        ],
    ]);
});

it('keeps the parent config sections ahead of its own', function (): void {
    // A subclass with inherited config sections still gets them first
    $fieldtype = new class extends TextPhoneticPair
    {
        /** @var list<array<string, mixed>> */
        protected $configFields = [['display' => 'Inherited', 'fields' => []]];
    };

    /** @var array<int, array<string, mixed>> $items */
    $items = (new ReflectionMethod(TextPhoneticPair::class, 'configFieldItems'))->invoke($fieldtype);

    expect($items)->toHaveCount(2)
        ->and($items[0])->toBe(['display' => 'Inherited', 'fields' => []])
        ->and($items[1]['display'])->toBe('Text & phonetic pair');
});

it('labels the nested text and phonetic attributes', function (): void {
    expect(textPhoneticPairFieldtype()->extraValidationAttributes())->toBe([
        'pair.text' => 'Text',
        'pair.phonetic_text' => 'Phonetic text',
    ]);
});

it('adds no nested attributes or rules without a bound field', function (): void {
    $fieldtype = new TextPhoneticPair;

    expect($fieldtype->extraValidationAttributes())->toBe([])
        ->and($fieldtype->extraRules())->toBe([]);
});

it('requires the text when the field, its validate list or require_text says so', function (array $config, array $textRules, array $phoneticRules): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(textPhoneticPairFieldtype($typedConfig)->extraRules())->toBe([
        'pair.text' => $textRules,
        'pair.phonetic_text' => $phoneticRules,
    ]);
})->with([
    'nothing required' => [[], ['nullable', 'string', 'name'], ['nullable', 'string', 'international_phonetic_alphabet']],
    'the field required' => [['required' => true], ['required', 'string', 'name'], ['nullable', 'string', 'international_phonetic_alphabet']],
    'required in the validate list' => [['validate' => ['string', 'required']], ['required', 'string', 'name'], ['nullable', 'string', 'international_phonetic_alphabet']],
    'a validate string, not a list' => [['validate' => 'required'], ['nullable', 'string', 'name'], ['nullable', 'string', 'international_phonetic_alphabet']],
    'a loose required match in the validate list' => [['validate' => [true]], ['nullable', 'string', 'name'], ['nullable', 'string', 'international_phonetic_alphabet']],
    'require_text' => [['require_text' => true], ['required', 'string', 'name'], ['nullable', 'string', 'international_phonetic_alphabet']],
    'require_phonetic' => [['require_phonetic' => true], ['nullable', 'string', 'name'], ['required', 'string', 'international_phonetic_alphabet']],
]);

it('defaults to an empty pair', function (): void {
    expect((new TextPhoneticPair)->defaultValue())->toBe(['text' => '', 'phonetic_text' => '']);
});

it('normalizes a stored pair to two strings on process and pre-process', function (mixed $data, array $pair): void {
    $fieldtype = new TextPhoneticPair;

    expect($fieldtype->process($data))->toBe($pair)
        ->and($fieldtype->preProcess($data))->toBe($pair);
})->with([
    'a full pair, extra keys dropped' => [['phonetic_text' => 'p', 'text' => 't', 'x' => 'y'], ['text' => 't', 'phonetic_text' => 'p']],
    'missing parts' => [[], ['text' => '', 'phonetic_text' => '']],
    'non-string parts' => [['text' => 5, 'phonetic_text' => ['p']], ['text' => '', 'phonetic_text' => '']],
    'a string' => ['Hello', ['text' => '', 'phonetic_text' => '']],
]);

it('fills a submitted pair\'s missing parts before validation', function (mixed $value, array $validatable): void {
    expect((new TextPhoneticPair)->preProcessValidatable($value))->toBe($validatable);
})->with([
    'a partial pair' => [['text' => 't'], ['text' => 't', 'phonetic_text' => '']],
    'extra keys and raw values kept' => [['phonetic_text' => 5, 'x' => 'y'], ['text' => '', 'phonetic_text' => 5, 'x' => 'y']],
    'a string' => ['Hello', ['text' => '', 'phonetic_text' => '']],
]);
