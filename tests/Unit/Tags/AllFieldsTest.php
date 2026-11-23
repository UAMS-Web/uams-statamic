<?php

declare(strict_types=1);

use App\Tags\AllFields;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;

/**
 * Pure unit tests for the AllFields tag (Gravity Forms {all_fields} merge-tag parity).
 *
 * Covers the HTML table structure, parameter defaults, display fallbacks, value unwrapping,
 * hidden/empty filtering, escaping, and show_value formatting without booting Laravel.
 *
 * @command  vendor/bin/pest tests/Unit/Tags/AllFieldsTest.php
 */
covers(AllFields::class);

/**
 * Render AllFields with the given context and parameters.
 *
 * @param  array<array-key, mixed>  $context
 * @param  array<string, mixed>  $params
 */
function renderAllFieldsUnit(array $context, array $params = []): string
{
    $tag = new AllFields;
    $tag->setContext($context);
    $tag->setParameters($params);

    return $tag->index();
}

it('returns an empty string when fields are missing or empty in context', function (array $context): void {
    expect(renderAllFieldsUnit($context))->toBe('');
})->with([
    'missing fields key' => [[]],
    'empty fields collection' => [['fields' => collect()]],
]);

it('renders the exact table markup and escapes display labels', function (): void {
    $html = renderAllFieldsUnit([
        'fields' => collect([
            [
                'display' => 'A & <B>',
                'value' => 'Pat',
                'fieldtype' => 'text',
            ],
        ]),
    ]);

    expect($html)->toBe(
        '<table style="width: 100%; border-collapse: collapse; margin: 20px 0; border: 1px solid #ddd;">'
        .'<thead><tr>'
        .'<th style="padding: 12px; border: 1px solid #ddd; background-color: #f8f9fa; font-weight: bold; text-align: left; width: 30%;">Field</th>'
        .'<th style="padding: 12px; border: 1px solid #ddd; background-color: #f8f9fa; font-weight: bold; text-align: left; width: 70%;">Value</th>'
        .'</tr></thead>'
        .'<tbody>'
        .'<tr>'
        .'<td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; width: 30%;">A &amp; &lt;B&gt;</td>'
        .'<td style="padding: 12px; border: 1px solid #ddd; width: 70%;">Pat</td>'
        .'</tr>'
        .'</tbody></table>'
    );
});

it('falls back from display to handle to empty string and stringifies scalar labels', function (): void {
    $html = renderAllFieldsUnit([
        'fields' => collect([
            ['handle' => 'user_email', 'value' => 'a@example.test', 'fieldtype' => 'text'],
            ['value' => 'orphan', 'fieldtype' => 'text'],
            ['display' => ['not-scalar'], 'value' => 'non-scalar-label', 'fieldtype' => 'text'],
            ['display' => 42, 'value' => 'int-label', 'fieldtype' => 'text'],
            'not-an-array-field',
        ]),
    ], ['show_empty' => true]);

    expect($html)
        ->toContain('<td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; width: 30%;">user_email</td>')
        ->toContain('<td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; width: 30%;"></td><td style="padding: 12px; border: 1px solid #ddd; width: 70%;">orphan</td>')
        ->toContain('<td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; width: 30%;"></td><td style="padding: 12px; border: 1px solid #ddd; width: 70%;">non-scalar-label</td>')
        ->toContain('<td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; width: 30%;">42</td>')
        ->toContain('<td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; width: 30%;"></td><td style="padding: 12px; border: 1px solid #ddd; width: 70%;"></td>');
});

it('skips empty, missing, and non-array values unless show_empty is enabled and continues to later rows', function (): void {
    $fields = collect([
        'not-an-array',
        ['display' => 'Blank', 'value' => '', 'fieldtype' => 'text'],
        ['display' => 'AugmentedBlank', 'value' => new Value('', 'blank'), 'fieldtype' => 'text'],
        ['display' => 'MissingValue', 'fieldtype' => 'text'],
        ['display' => 'Filled', 'value' => new Value('Pat', 'filled'), 'fieldtype' => 'text'],
    ]);

    $defaultHtml = renderAllFieldsUnit(['fields' => $fields]);

    expect($defaultHtml)
        ->not->toContain('Blank')
        ->not->toContain('AugmentedBlank')
        ->not->toContain('MissingValue')
        ->toContain('Filled')
        ->toContain('Pat');

    $withEmptyHtml = renderAllFieldsUnit(['fields' => $fields], ['show_empty' => true]);

    expect($withEmptyHtml)
        ->toContain('Blank')
        ->toContain('AugmentedBlank')
        ->toContain('MissingValue')
        ->toContain('Filled');
});

it('hides hidden fields only when hide_hidden is true and continues to later rows', function (): void {
    $fields = collect([
        ['display' => 'HiddenByFieldtype', 'value' => 'x', 'fieldtype' => 'hidden', 'type' => 'text'],
        ['display' => 'HiddenByType', 'value' => 'y', 'type' => 'hidden'],
        ['display' => 'VisibleText', 'value' => 'z', 'fieldtype' => 'text', 'type' => 'hidden'],
    ]);

    $defaultHtml = renderAllFieldsUnit(['fields' => $fields]);

    expect($defaultHtml)
        ->toContain('HiddenByFieldtype')
        ->toContain('HiddenByType')
        ->toContain('VisibleText');

    $hiddenHtml = renderAllFieldsUnit(['fields' => $fields], ['hide_hidden' => true]);

    expect($hiddenHtml)
        ->not->toContain('HiddenByFieldtype')
        ->not->toContain('HiddenByType')
        ->toContain('VisibleText');
});

it('renders display HTML by default and escapes actual values under show_value', function (): void {
    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'stringed';
        }
    };

    $labeledField = [
        'display' => 'Color',
        'value' => new Value(new LabeledValue('red', 'Deep Red'), 'color'),
        'fieldtype' => 'radio',
    ];

    expect(renderAllFieldsUnit(['fields' => collect([$labeledField])]))
        ->toContain('Deep Red')
        ->not->toContain('>red</td>');

    $showValueHtml = renderAllFieldsUnit([
        'fields' => collect([
            $labeledField,
            ['display' => 'Braces', 'value' => '<b>{{ form:title }}</b>', 'fieldtype' => 'text'],
            ['display' => 'Count', 'value' => 42, 'fieldtype' => 'integer'],
            ['display' => 'Thing', 'value' => $stringable, 'fieldtype' => 'text'],
            ['display' => 'ArrayVal', 'value' => ['a'], 'fieldtype' => 'grid'],
            ['display' => 'AugmentedArray', 'value' => new Value(['a'], 'rows'), 'fieldtype' => 'grid'],
            ['display' => 'MissingVal', 'fieldtype' => 'text'],
        ]),
    ], ['show_value' => true, 'show_empty' => true]);

    expect($showValueHtml)
        ->toContain('>red</td>')
        ->not->toContain('Deep Red')
        ->toContain('&lt;b&gt;&#123;&#123; form:title &#125;&#125;&lt;/b&gt;')
        ->toContain('>42</td>')
        ->toContain('>stringed</td>')
        ->toContain('ArrayVal</td><td style="padding: 12px; border: 1px solid #ddd; width: 70%;"></td>')
        ->toContain('AugmentedArray</td><td style="padding: 12px; border: 1px solid #ddd; width: 70%;"></td>')
        ->toContain('MissingVal</td><td style="padding: 12px; border: 1px solid #ddd; width: 70%;"></td>');
});
