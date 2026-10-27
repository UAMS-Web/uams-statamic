<?php

declare(strict_types=1);

use App\Tags\AllFields;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Feature tests for the AllFields tag (Gravity Forms {all_fields} merge-tag parity).
 *
 * The cases from the augmented-value unwrap down pin the UAMS-Web/uams-statamic-archive#3149 changes: the `fieldtype` key
 * with its `type` fallback, the `Value` unwrap before the emptiness check, and the escaped
 * `show_value` cell.
 *
 * @command  php artisan test --compact tests/Feature/Tags/AllFieldsTagTest.php
 */
it('returns empty when no fields in context', function () {
    $tag = new AllFields;
    $tag->setContext([
        'fields' => collect(),
    ]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});

it('builds the table with one field', function () {
    $tag = new AllFields;
    $tag->setContext([
        'fields' => collect([
            [
                'display' => 'Name',
                'value' => 'Pat',
                'type' => 'text',
            ],
        ]),
    ]);
    $tag->setParameters([]);

    $html = $tag->index();

    expect($html)->toContain('<table');
    expect($html)->toContain('Name');
    expect($html)->toContain('Pat');
});

it('skips empty values unless show empty', function () {
    $tag = new AllFields;
    $tag->setContext([
        'fields' => collect([
            [
                'display' => 'Empty',
                'value' => '',
                'type' => 'text',
            ],
        ]),
    ]);
    $tag->setParameters([]);

    expect($tag->index())->not->toContain('Empty');

    $tag->setParameters([
        'show_empty' => true,
    ]);

    expect($tag->index())->toContain('Empty');
});

it('hides hidden when hide hidden true', function () {
    $tag = new AllFields;
    $tag->setContext([
        'fields' => collect([
            [
                'display' => 'Secret',
                'value' => 'x',
                'type' => 'hidden',
            ],
        ]),
    ]);
    $tag->setParameters([
        'hide_hidden' => true,
    ]);

    expect($tag->index())->not->toContain('Secret');
});

it('returns the option key when present for show value', function () {
    $tag = new AllFields;
    $tag->setContext([
        'fields' => collect([
            [
                'display' => 'Choice',
                'value' => 'a',
                'type' => 'select',
                'options' => [
                    'a' => 'Alpha',
                ],
            ],
        ]),
    ]);
    $tag->setParameters([
        'show_value' => true,
    ]);

    $html = $tag->index();

    expect($html)->toContain('a');
    expect($html)->not->toContain('Alpha');
});

/**
 * Render the tag over the given fields with the given parameters.
 *
 * @param  list<array<string, mixed>>  $fields  The field entries in the tag's context.
 * @param  array<string, mixed>  $params  The tag parameters.
 */
function allFieldsTagRender(array $fields, array $params = []): string
{
    $tag = new AllFields;
    $tag->setContext(['fields' => collect($fields)]);
    $tag->setParameters($params);

    return $tag->index();
}

it('skips an augmented value whose inner value is empty', function () {
    $html = allFieldsTagRender([
        ['display' => 'Blank', 'value' => new Value('', 'blank'), 'fieldtype' => 'text'],
        ['display' => 'Filled', 'value' => new Value('Pat', 'filled'), 'fieldtype' => 'text'],
    ]);

    expect($html)->not->toContain('Blank')
        ->and($html)->toContain('Filled')
        ->and($html)->toContain('Pat');
});

it('reads the fieldtype key ahead of the type key', function () {
    $fields = [
        ['display' => 'ByFieldtype', 'value' => 'x', 'fieldtype' => 'hidden', 'type' => 'text'],
        ['display' => 'ByType', 'value' => 'y', 'type' => 'hidden'],
        ['display' => 'Shown', 'value' => 'z', 'fieldtype' => 'text', 'type' => 'hidden'],
    ];

    $html = allFieldsTagRender($fields, ['hide_hidden' => true]);

    expect($html)->not->toContain('ByFieldtype')
        ->and($html)->not->toContain('ByType')
        ->and($html)->toContain('Shown');
});

it('keeps hidden fields when hide_hidden is not set', function () {
    expect(allFieldsTagRender([['display' => 'Secret', 'value' => 'x', 'fieldtype' => 'hidden']]))
        ->toContain('Secret');
});

it('renders the field list cell through the display resolver', function () {
    $html = allFieldsTagRender([
        ['display' => 'Color', 'value' => new LabeledValue('red', 'Deep Red'), 'fieldtype' => 'radio'],
    ]);

    expect($html)->toContain('Deep Red')
        ->and($html)->toEndWith('</tbody></table>');
});

it('escapes the stored value and its braces under show_value', function () {
    $html = allFieldsTagRender(
        [['display' => 'Note', 'value' => '<b>{{ form:title }}</b>', 'fieldtype' => 'text']],
        ['show_value' => true]
    );

    expect($html)->toContain('&lt;b&gt;&#123;&#123; form:title &#125;&#125;&lt;/b&gt;')
        ->and($html)->not->toContain('{{')
        ->and($html)->not->toContain('<b>');
});

it('shows an option key, a stringable and nothing for an array under show_value', function () {
    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'stringed';
        }
    };

    $html = allFieldsTagRender([
        ['display' => 'Choice', 'value' => new Value(new LabeledValue('red', 'Deep Red'), 'choice'), 'fieldtype' => 'radio'],
        ['display' => 'Thing', 'value' => $stringable, 'fieldtype' => 'text'],
        ['display' => 'List', 'value' => ['a'], 'fieldtype' => 'grid'],
    ], ['show_value' => true]);

    expect($html)->toContain('>red</td>')
        ->and($html)->not->toContain('Deep Red')
        ->and($html)->toContain('>stringed</td>')
        ->and($html)->toMatch('#List</td><td[^>]*></td>#');
});

it('skips a field with no value key unless show_empty is set', function () {
    $fields = [['display' => 'Missing', 'fieldtype' => 'text']];

    expect(allFieldsTagRender($fields))->not->toContain('Missing')
        ->and(allFieldsTagRender($fields, ['show_empty' => true, 'show_value' => true]))
        ->toMatch('#Missing</td><td[^>]*></td>#');
});

it('shows nothing for an augmented array under show_value', function () {
    $html = allFieldsTagRender(
        [['display' => 'Rows', 'value' => new Value(['a'], 'rows'), 'fieldtype' => 'grid']],
        ['show_value' => true]
    );

    expect($html)->toMatch('#Rows</td><td[^>]*></td>#');
});

it('escapes the display label', function () {
    expect(allFieldsTagRender([['display' => 'A & <B>', 'value' => 'x', 'fieldtype' => 'text']]))
        ->toContain('A &amp; &lt;B&gt;');
});
