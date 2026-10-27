<?php

declare(strict_types=1);

use App\Services\PageBuilder\SideBySideAlignmentResolver;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;
use Statamic\Fields\Value;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

// Returned to the parallel band on UAMS-Web/uams-statamic-archive#2503. This file was gated by UAMS-Web/uams-statamic-archive#1447 because the markup
// assertions flaked under `--parallel` while passing in isolation (UAMS-Web/uams-statamic-archive#1443). The gate attributed
// that to "the shared, paratest-non-isolated compiled-view cache", which was already per
// worker under Laravel's `TestViews` when the flake was observed, so the cause was never
// established; the UAMS-Web/uams-statamic-archive#1300 Stache warm-up race UAMS-Web/uams-statamic-archive#1590 closed is the likeliest closer, and that
// remains a hypothesis. As an observed flake it came off only on the README's ten-run bar: ten
// consecutive `--parallel --exclude-group=serial` bands on a no-snapshot checkout with this
// file ungated produced zero failures (macOS, 2026-09-25). If it recurs, restore the gate with
// the observed failure as its reason. tests/README.md carries the audit row.
uses(TestCase::class);

/**
 * Coverage for the Side-by-Side Image & Text block's text-layout options (UAMS-Web/uams-statamic-archive#1193):
 * the relabeled Paragraph options plus the new paragraph-and-simple-list and
 * simple-list-only layouts (plain, non-linked lists). Structural assertions pin
 * the fieldset; render assertions prove each layout emits the right markup.
 *
 * @command  php artisan test --compact tests/Feature/Views/SideBySideBlockTest.php
 */
beforeEach(fn () => Blink::flush());

/**
 * The side-by-side fieldset fields, keyed by handle.
 *
 * @return array<string, array{handle: string, field: array<string, mixed>}>
 */
function sideBySideFields(): array
{
    /** @var non-empty-string $path */
    $path = realpath(__DIR__.'/../../../resources/fieldsets/uamsweb_block_image_side_by_side.yaml');

    /** @var array{fields: array<int, array{handle: string, field: array<string, mixed>}>} $fieldset */
    $fieldset = Yaml::parseFile($path);

    $keyed = [];
    foreach ($fieldset['fields'] as $field) {
        $keyed[$field['handle']] = $field;
    }

    return $keyed;
}

/**
 * A valid side-by-side block, overridable per layout.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sideBySideBlock(array $overrides = []): array
{
    return array_merge([
        'block_heading' => 'Heading',
        'image' => ['url' => '/img/x.jpg'],
        'image_crop' => 'center',
        'image_anchor' => 'center',
        'image_position' => 'left',
        'background_color' => 'bg-white',
    ], $overrides);
}

/**
 * Render the side-by-side partial through a scoped page-builder-style loop.
 *
 * @param  array<int, array<string, mixed>>  $blocks
 */
function renderSideBySide(array $blocks): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    return (string) Antlers::parse(
        '{{ blocks scope="block" }}{{ partial:page_builder/uamsweb_block_image_side_by_side }}{{ /blocks }}'
        ."\n{{# render-{$render} #}}",
        ['blocks' => $blocks],
    );
}

// ---------------------------------------------------------------------------
// Fieldset
// ---------------------------------------------------------------------------

it('relabels the layout options to Paragraph and adds the simple-list layouts (#1193)', function (): void {
    /** @var array<int, array{key: string, value: string}> $options */
    $options = sideBySideFields()['text_layout']['field']['options'];

    $labels = [];
    foreach ($options as $option) {
        $labels[$option['key']] = $option['value'];
    }

    // Existing keys preserved (existing content still resolves), labels updated.
    expect($labels)->toBe([
        'body-only' => 'Paragraph only',
        'paragraph_and_button' => 'Paragraph and button',
        'link-list' => 'Paragraph and link list',
        'paragraph_and_simple_list' => 'Paragraph and simple list',
        'simple_list_only' => 'Simple list only',
        'blockquote_with_citation' => 'Blockquote with Citation',
        'blockquote_with_citation_and_link' => 'Blockquote with Citation and Link',
    ]);
});

it('adds a simple_list field gated to the simple-list layouts (#1193)', function (): void {
    /** @var array{type: string, if: array{text_layout: string}} $field */
    $field = sideBySideFields()['simple_list']['field'];

    expect($field['type'])->toBe('grid')
        ->and($field['if']['text_layout'])->toBe('contains_any paragraph_and_simple_list, simple_list_only');
});

it('limits the simple list to six one-line items (#2767)', function (): void {
    // Measured in a browser on 2026-10-07 at every width from 992 to 1452 px, at a
    // viewport height of 488, by UAMS-Web/uams-statamic-archive#2757's method. The list renders in two columns, like
    // the link list, and Paragraph and simple list shares it with Simple list only, so
    // it carries the link list's shape: with the heading at 2 lines and the button at 1,
    // a seventh item or a 23rd character overflows beside a 4-line paragraph. 22 is the
    // smallest of the ten Blind Text Generator sample texts; the PR for UAMS-Web/uams-statamic-archive#2767 records
    // the counts. A `list` field carries neither limit, so the list is a one-column grid.
    /** @var array{max_rows: int, fields: array<int, array{handle: string, field: array{type: string, character_limit: int, instructions: string, validate: array<int, string>}}>} $field */
    $field = sideBySideFields()['simple_list']['field'];

    expect($field['max_rows'])->toBe(6)
        ->and(array_column($field['fields'], 'handle'))->toBe(['text'])
        ->and($field['fields'][0]['field']['type'])->toBe('text')
        ->and($field['fields'][0]['field']['character_limit'])->toBe(22)
        ->and($field['fields'][0]['field']['instructions'])->toBe('There is a 22-character limit.')
        ->and($field['fields'][0]['field']['validate'])->toBe(['required']);
});

it('keeps Paragraph only on its own paragraph field (#2767)', function (): void {
    // body_body_only once doubled as the paragraph for Paragraph and simple list. At
    // its 449 even one list item overflows the text box, so that layout has its own
    // paragraph field and this one serves Paragraph only.
    /** @var array{if: array{text_layout: string}, character_limit: int} $field */
    $field = sideBySideFields()['body_body_only']['field'];

    expect($field['if']['text_layout'])->toBe('equals body-only')
        ->and($field['character_limit'])->toBe(449);
});

it('gives Paragraph and simple list its own required paragraph limited to 173 (#2767)', function (): void {
    // The 4 whole lines left beside six one-line items, with the heading at 2 lines and
    // the button at 1; the same shape as the link list's paragraph (UAMS-Web/uams-statamic-archive#2766).
    /** @var array{type: string, if: array<string, string>, validate: array<int, string>, character_limit: int, instructions: string} $field */
    $field = sideBySideFields()['body_simple_list']['field'];

    expect($field['type'])->toBe('textarea')
        ->and($field['if'])->toBe(['text_layout' => 'equals paragraph_and_simple_list'])
        ->and($field['validate'])->toBe(['required', 'sometimes'])
        ->and($field['character_limit'])->toBe(173)
        ->and($field['instructions'])->toBe('There is a 173-character limit.');
});

it('gives each blockquote layout its own required quote and shares the speaker name (#2757)', function (string $handle, string $condition): void {
    /** @var array{if: array<string, string>, validate: array<int, string>} $field */
    $field = sideBySideFields()[$handle]['field'];

    expect($field['if'])->toBe(['text_layout' => $condition])
        ->and($field['validate'])->toBe(['required', 'sometimes']);
})->with([
    'quote, without a link' => ['quote_text', 'equals blockquote_with_citation'],
    'quote, with a link' => ['quote_text_with_link', 'equals blockquote_with_citation_and_link'],
    'speaker name, both' => ['speaker_name', 'contains_any blockquote_with_citation, blockquote_with_citation_and_link'],
]);

/**
 * Whether a `contains_any` condition shows a field for a layout value, under the two readings
 * the Control Panel's evaluator could take: exact membership of the listed values, or a
 * substring test of each (UAMS-Web/uams-statamic-archive#2761 records that nothing pins which one it uses).
 *
 * @return array{exact: bool, substring: bool}
 */
function containsAnyShows(string $condition, string $value): array
{
    $listed = array_map(trim(...), explode(',', substr($condition, strlen('contains_any '))));

    return [
        'exact' => in_array($value, $listed, true),
        'substring' => array_filter($listed, fn (string $item): bool => str_contains($value, $item)) !== [],
    ];
}

it('shows the call-to-action button fields only in layouts with a button (#2757, #2759)', function (string $handle): void {
    /** @var array{field?: array<string, mixed>, config?: array<string, mixed>} $entry */
    $entry = sideBySideFields()[$handle];
    /** @var array{if: array{text_layout: string}} $field */
    $field = $entry['config'] ?? $entry['field'] ?? [];
    $condition = $field['if']['text_layout'];

    expect($condition)->toStartWith('contains_any ');

    /** @var array<int, array{key: string, value: string}> $options */
    $options = sideBySideFields()['text_layout']['field']['options'];
    $withoutButton = ['body-only', 'blockquote_with_citation'];

    foreach ($options as $option) {
        $shown = ! in_array($option['key'], $withoutButton, true);

        // Both readings agree for every layout, so the result does not depend on which one
        // the evaluator takes. `blockquote_with_citation` is a prefix of the with-link key,
        // which is why the condition lists the layouts that have a button, not those without.
        expect(containsAnyShows($condition, $option['key']))
            ->toBe(['exact' => $shown, 'substring' => $shown]);
    }
})->with(['cta', 'button_text', 'button_url', 'button_target', 'button_link_description']);

it('gives the Paragraph and button layout its own required paragraph limited to 359 (#2759)', function (): void {
    /** @var array{type: string, if: array<string, string>, validate: array<int, string>, character_limit: int, instructions: string} $field */
    $field = sideBySideFields()['body_with_button']['field'];

    expect($field['type'])->toBe('textarea')
        ->and($field['if'])->toBe(['text_layout' => 'equals paragraph_and_button'])
        ->and($field['validate'])->toBe(['required', 'sometimes'])
        ->and($field['character_limit'])->toBe(359)
        ->and($field['instructions'])->toBe('There is a 359-character limit.');
});

it('keeps the block heading at 50 characters in every layout, the blockquote one included (#2757)', function (): void {
    /** @var array{config: array<string, mixed>} $heading */
    $heading = sideBySideFields()['block_heading'];

    // The heading carries no `if:` condition, so its limit applies whichever layout is selected.
    expect($heading['config']['character_limit'] ?? null)->toBe(50)
        ->and($heading['config'])->not->toHaveKey('if')
        ->and($heading)->not->toHaveKey('if');
});

it('limits the quote, speaker name and speaker title to their measured lengths (#2757)', function (string $handle, int $limit): void {
    // Measured in a browser on 2026-10-01 at every width from 992 to 1452 px, at a
    // viewport height of 488: the most characters of each of the ten Blind Text
    // Generator sample texts that stay within the field's line budget, smallest of the
    // ten. Name 1 line, title 2, and the quote the whole lines left with the heading at
    // 2 lines (and, with a link, the button at 1). The PR for UAMS-Web/uams-statamic-archive#2757 records them.
    /** @var array{character_limit: int, instructions: string} $field */
    $field = sideBySideFields()[$handle]['field'];

    expect($field['character_limit'])->toBe($limit)
        ->and($field['instructions'])->toContain("There is a {$limit}-character limit.");
})->with([
    'quote text, without a link (7 lines)' => ['quote_text', 306],
    'quote text, with a link (5 lines)' => ['quote_text_with_link', 217],
    'speaker name' => ['speaker_name', 58],
    'speaker title' => ['speaker_title', 121],
]);

it('limits the call-to-action button text to the one line it was measured for (#2759)', function (): void {
    // `button_text` imports `uamsweb_common_link.link_text`, which sets no limit, so the
    // limit is a config override here. Read through Statamic so the merged field is what
    // is asserted. 42 is the most characters of the ten Blind Text Generator sample texts
    // that stay on one button line at every width from 992 to 1452 px (UAMS-Web/uams-statamic-archive#2757's method).
    /** @var Statamic\Fields\Fieldset $fieldset */
    $fieldset = Fieldset::find('uamsweb_block_image_side_by_side');
    /** @var Field $field */
    $field = $fieldset->fields()->get('button_text');

    /** @var Statamic\Fields\Fieldset $common */
    $common = Fieldset::find('uamsweb_common_link');
    /** @var Field $sharedField */
    $sharedField = $common->fields()->get('link_text');

    expect($field->get('character_limit'))->toBe(42)
        ->and($field->get('instructions'))->toBe('There is a 42-character limit.')
        // The shared link field keeps no limit of its own, so other blocks are unaffected.
        ->and($sharedField->get('character_limit'))->toBeNull();
});

it('limits the Paragraph and link list layout to the lines its text box holds (#2766)', function (): void {
    // Measured in a browser on 2026-10-07 at every width from 992 to 1452 px, at a
    // viewport height of 488, by UAMS-Web/uams-statamic-archive#2757's method. With the heading at 2 lines and the
    // button at 1, six links of 2 lines overflow even beside a 1-line paragraph, so each
    // link gets 1 line of its column and the paragraph the 4 whole lines left. Each limit
    // is the smallest of the ten Blind Text Generator sample texts; the PR for UAMS-Web/uams-statamic-archive#2766
    // records the counts.
    $fields = sideBySideFields();

    /** @var array{character_limit: int, instructions: string} $paragraph */
    $paragraph = $fields['body_link_list']['field'];

    /** @var array{max_sets: int, sets: array{new_set_group: array{sets: array{new_set: array{fields: array<int, array{handle: string, config: array{character_limit: int, instructions: string}}>}}}}} $linkList */
    $linkList = $fields['link_list']['field'];
    $linkText = collect($linkList['sets']['new_set_group']['sets']['new_set']['fields'])
        ->firstWhere('handle', 'text');

    expect($paragraph['character_limit'])->toBe(173)
        ->and($paragraph['instructions'])->toBe('There is a 173-character limit.')
        ->and($linkText['config']['character_limit'] ?? null)->toBe(22)
        ->and($linkText['config']['instructions'] ?? null)->toBe('There is a 22-character limit.')
        // Six links, three to a column: the box was measured with no more than that.
        ->and($linkList['max_sets'])->toBe(6);
});

it('shows the optional speaker title only once a speaker name is set (#2757)', function (): void {
    /** @var array<string, mixed> $field */
    $field = sideBySideFields()['speaker_title']['field'];

    expect($field['if'])->toBe([
        'text_layout' => 'contains_any blockquote_with_citation, blockquote_with_citation_and_link',
        'speaker_name' => 'not empty',
    ])->and($field)->not->toHaveKey('validate');
});

/**
 * The validation errors the side-by-side fieldset reports for the given values.
 *
 * @param  array<string, mixed>  $values
 * @return array<mixed>
 */
function sideBySideValidationErrors(array $values): array
{
    try {
        /** @var Statamic\Fields\Fieldset $fieldset */
        $fieldset = Fieldset::find('uamsweb_block_image_side_by_side');
        /** @var Fields $fields */
        $fields = $fieldset->fields()->addValues($values);
        /** @var Validator $validator */
        $validator = $fields->validator();
        $validator->validate();

        return [];
    } catch (ValidationException $e) {
        return $e->errors();
    }
}

it('will not save a blockquote block without its quote and speaker name, but will without a title (#2757)', function (): void {
    $base = sideBySideBlock(['text_layout' => 'blockquote_with_citation']);

    // The Control Panel submits a visible field's value even when it is empty.
    expect(sideBySideValidationErrors($base + ['quote_text' => null, 'speaker_name' => null]))
        ->toHaveKeys(['quote_text', 'speaker_name']);

    expect(sideBySideValidationErrors($base + ['quote_text' => 'Great results manifest.', 'speaker_name' => 'Michael Miller']))
        ->not->toHaveKeys(['quote_text', 'speaker_name', 'speaker_title']);
});

it('will not save a Paragraph and button block without its paragraph (#2759)', function (): void {
    $base = sideBySideBlock(['text_layout' => 'paragraph_and_button']);

    expect(sideBySideValidationErrors($base + ['body_with_button' => null]))->toHaveKey('body_with_button')
        ->and(sideBySideValidationErrors($base + ['body_with_button' => 'Short paragraph.']))->not->toHaveKey('body_with_button');
});

it('will not save a blockquote-and-link block without its own quote and speaker name (#2757)', function (): void {
    $base = sideBySideBlock(['text_layout' => 'blockquote_with_citation_and_link']);

    expect(sideBySideValidationErrors($base + ['quote_text_with_link' => null, 'speaker_name' => null]))
        ->toHaveKeys(['quote_text_with_link', 'speaker_name']);

    expect(sideBySideValidationErrors($base + ['quote_text_with_link' => 'Great results manifest.', 'speaker_name' => 'Michael Miller']))
        ->not->toHaveKeys(['quote_text_with_link', 'speaker_name', 'speaker_title']);
});

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------

/**
 * The block's `<figure>` element, with the template's indentation around each tag removed.
 */
function renderedFigure(string $html): string
{
    if (preg_match('#<figure>.*?</figure>#s', $html, $match) !== 1) {
        throw new RuntimeException('The block rendered no <figure>.');
    }

    return (string) preg_replace('/\s*(<[^>]+>)\s*/', '$1', $match[0]);
}

it('renders a quote and a two-line citation for blockquote_with_citation (#2757)', function (): void {
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'blockquote_with_citation',
        'quote_text' => 'Great results manifest.',
        'speaker_name' => 'Michael Miller, DC, MS',
        'speaker_title' => 'Chiropractor',
    ])]);

    expect(renderedFigure($html))->toBe(
        '<figure><blockquote><p>“Great results&nbsp;manifest.”</p></blockquote>'
        .'<figcaption class="blockquote-footer">Michael Miller, DC, MS<br>Chiropractor</figcaption></figure>'
    );
});

it('renders the citation as the name alone, with no trailing break, without a title (#2757)', function (): void {
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'blockquote_with_citation',
        'quote_text' => 'Great results manifest.',
        'speaker_name' => 'Michael Miller, DC, MS',
    ])]);

    expect(renderedFigure($html))->toContain('<figcaption class="blockquote-footer">Michael Miller, DC, MS</figcaption>')
        ->and($html)->not->toContain('<br>');
});

it('suppresses a blockquote block missing its quote or its speaker name (#2757)', function (array $missing): void {
    $html = renderSideBySide([sideBySideBlock(array_merge([
        'text_layout' => 'blockquote_with_citation',
        'quote_text' => 'Great results manifest.',
        'speaker_name' => 'Michael Miller, DC, MS',
    ], $missing))]);

    expect($html)->not->toContain('side-by-side')
        ->and($html)->not->toContain('<figure>');
})->with([
    'no quote' => [['quote_text' => null]],
    'no speaker name' => [['speaker_name' => null]],
    // The shape an empty field augments to at render time, as opposed to a bare null.
    'augmented empty quote' => [['quote_text' => new Value(null)]],
    'augmented empty speaker name' => [['speaker_name' => new Value(null)]],
    'a quote of quotation marks only' => [['quote_text' => '""']],
]);

it('wraps the rendered quote in curly quotes, replacing any the editor typed (#2757)', function (string $quote): void {
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'blockquote_with_citation',
        'quote_text' => $quote,
        'speaker_name' => 'Michael Miller',
    ])]);

    expect(renderedFigure($html))->toContain('<p>“Great&nbsp;results.”</p>');
})->with([
    'straight double' => ['"Great results."'],
    'curly double' => ['“Great results.”'],
    'straight single' => ["'Great results.'"],
]);

it('keeps an apostrophe inside the rendered quote (#2757)', function (): void {
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'blockquote_with_citation',
        'quote_text' => "We didn't stop.",
        'speaker_name' => 'Michael Miller',
    ])]);

    expect(renderedFigure($html))->toContain("<p>“We didn't&nbsp;stop.”</p>");
});

it('renders the quote, citation and button for blockquote_with_citation_and_link (#2757)', function (): void {
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'blockquote_with_citation_and_link',
        'quote_text_with_link' => 'Great results manifest.',
        'speaker_name' => 'Michael Miller',
        'button_text' => 'Meet the team',
        'button_url' => '/team',
    ])]);

    expect(renderedFigure($html))->toBe(
        '<figure><blockquote><p>“Great results&nbsp;manifest.”</p></blockquote>'
        .'<figcaption class="blockquote-footer">Michael Miller</figcaption></figure>'
    )->and($html)->toContain('>Meet the team</a>');
});

it('reads the linked layout\'s quote from its own field, not the other layout\'s (#2757)', function (): void {
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'blockquote_with_citation_and_link',
        'quote_text' => 'LeftOverQuote',
        'speaker_name' => 'Michael Miller',
    ])]);

    // Only the other layout's quote is set, so this layout has no quote and renders nothing.
    expect($html)->not->toContain('LeftOverQuote')
        ->and($html)->not->toContain('side-by-side');
});

it('ignores button values left over from another layout in blockquote_with_citation (#2757)', function (array $button): void {
    $html = renderSideBySide([sideBySideBlock(array_merge([
        'text_layout' => 'blockquote_with_citation',
        'quote_text' => 'Great results manifest.',
        'speaker_name' => 'Michael Miller',
    ], $button))]);

    // The block still renders, and without the button its hidden fields describe.
    expect($html)->toContain('<figure>')
        ->and($html)->not->toContain('LeftOverButton');
})->with([
    'complete button' => [['button_text' => 'LeftOverButton', 'button_url' => '/x']],
    // Without a URL the button would otherwise invalidate the whole block.
    'button text without a URL' => [['button_text' => 'LeftOverButton']],
]);

it('renders a paragraph and a plain (non-linked) list for paragraph_and_simple_list', function (): void {
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'paragraph_and_simple_list',
        'body_simple_list' => 'IntroParagraph',
        'body_body_only' => 'LeftOverParagraph',
        'simple_list' => [['text' => 'One'], ['text' => 'Two'], ['text' => 'Three']],
    ])]);

    // The paragraph comes from the layout's own field (UAMS-Web/uams-statamic-archive#2767), never Paragraph only's.
    expect($html)->toContain('<p>IntroParagraph</p>')
        ->and($html)->not->toContain('LeftOverParagraph')
        ->and(substr_count($html, '<li>'))->toBe(3)
        ->and($html)->toContain('<li>Three</li>')
        ->and($html)->not->toContain('<a ');
});

it('requires the layout\'s own paragraph for paragraph_and_simple_list (#2767)', function (): void {
    // A paragraph left in Paragraph only's field does not stand in for it.
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'paragraph_and_simple_list',
        'body_body_only' => 'LeftOverParagraph',
        'simple_list' => [['text' => 'One']],
    ])]);

    expect($html)->not->toContain('side-by-side')
        ->and($html)->not->toContain('LeftOverParagraph');
});

it('suppresses a simple_list_only block whose list is an absent augmented field (#1673)', function (): void {
    // `new Value(null)` specifically, NOT an omitted key. An omitted key is a *bare*
    // null, which `ModifierManager::evaluate()` has always short-circuited before the
    // modifier loop is entered — it would pass identically with or without the patch
    // and prove nothing. Only a null-bearing `Value`, the shape an absent `simple_list`
    // augments to, reaches the in-loop guard the patch reorders.
    //
    // Unpatched, `SafeCount::index(null)` returned 1, so `( simple_list | safe_count )
    // >= 1` held and the block rendered its wrapper with no list items at all.
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'simple_list_only',
        'simple_list' => new Value(null),
    ])]);

    expect($html)->not->toContain('<li>')
        ->and($html)->not->toContain('side-by-side');
});

it('renders only a plain list and no paragraph for simple_list_only', function (): void {
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'simple_list_only',
        'simple_list' => [['text' => 'Only one'], ['text' => 'Only two']],
    ])]);

    expect(substr_count($html, '<li>'))->toBe(2)
        ->and($html)->toContain('<li>Only&nbsp;two</li>')
        ->and($html)->not->toContain('<p>')
        ->and($html)->not->toContain('<a ');
});

it('renders a paragraph and the button for paragraph_and_button (#2759)', function (): void {
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'paragraph_and_button',
        'body_with_button' => 'WithTheButton',
        'body_body_only' => 'LeftOverParagraph',
        'button_text' => 'Learn More',
        'button_url' => '/more',
    ])]);

    expect($html)->toContain('<p>WithTheButton</p>')
        ->and($html)->toContain('>Learn More</a>')
        ->and($html)->not->toContain('LeftOverParagraph');
});

it('ignores button values left over from another layout in body-only (#2759)', function (array $button): void {
    $html = renderSideBySide([sideBySideBlock(array_merge([
        'text_layout' => 'body-only',
        'body_body_only' => 'JustTheBody',
    ], $button))]);

    expect($html)->toContain('<p>JustTheBody</p>')
        ->and($html)->not->toContain('LeftOverButton');
})->with([
    'complete button' => [['button_text' => 'LeftOverButton', 'button_url' => '/x']],
    // Without a URL the button would otherwise invalidate the whole block.
    'button text without a URL' => [['button_text' => 'LeftOverButton']],
]);

it('renders the existing body-only layout unchanged (no regression)', function (): void {
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'body-only',
        'body_body_only' => 'JustTheBody',
    ])]);

    expect($html)->toContain('JustTheBody')
        ->and($html)->not->toContain('<li>')
        ->and($html)->not->toContain('<figure>');
});

it('renders the existing link-list layout as a linked list (no regression)', function (): void {
    $html = renderSideBySide([sideBySideBlock([
        'text_layout' => 'link-list',
        'body_link_list' => 'WithLinks',
        'link_list' => [['text' => 'A link', 'url' => '/somewhere', 'target' => false]],
        'include_more' => true,
    ])]);

    expect($html)->toContain('WithLinks')
        ->and($html)->toContain('<li><a href="/somewhere"');
});

// ---------------------------------------------------------------------------
// Auto-alternating alignment (UAMS-Web/uams-statamic-archive#1192)
// ---------------------------------------------------------------------------

/**
 * Render the side-by-side partial through a scoped loop with a supplied
 * `side_by_side_alignments` computed map (block id => resolved side), mirroring
 * how the entry computed reaches the block partial at render time.
 *
 * @param  array<int, array<string, mixed>>  $blocks
 * @param  array<string, string>  $alignments
 */
function renderSideBySideWithAlignments(array $blocks, array $alignments): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    return (string) Antlers::parse(
        '{{ blocks scope="block" }}{{ partial:page_builder/uamsweb_block_image_side_by_side }}{{ /blocks }}'
        ."\n{{# align-render-{$render} #}}",
        ['blocks' => $blocks, 'side_by_side_alignments' => $alignments],
    );
}

/**
 * The image-side tokens emitted, in render order.
 *
 * @return array<int, string>
 */
function renderedImageSides(string $html): array
{
    preg_match_all('/image-on-(left|right)/', $html, $matches);

    return $matches[1];
}

it('adds the two auto-alternating options to the image_position field (#1192)', function (): void {
    /** @var array<int, array{key: string, value: string}> $options */
    $options = sideBySideFields()['image_position']['field']['options'];

    $keys = array_map(
        /** @param array{key: string, value: string} $option */
        fn (array $option): string => $option['key'],
        $options,
    );

    // Existing explicit keys preserved (existing content still resolves) plus the
    // two new auto options.
    expect($keys)->toBe(['left', 'right', 'auto_default_left', 'auto_default_right']);
});

it('resolves an auto block to its computed alternating side (#1192)', function (): void {
    $html = renderSideBySideWithAlignments(
        [sideBySideBlock(['id' => 'a', 'image_position' => 'auto_default_left', 'text_layout' => 'body-only', 'body_body_only' => 'Body'])],
        ['a' => 'right'],
    );

    // The computed map flipped this block to the right despite its left default.
    expect(renderedImageSides($html))->toBe(['right']);
});

it('falls back to the stated default side when absent from the alignment map (#1192)', function (): void {
    $html = renderSideBySideWithAlignments(
        [sideBySideBlock(['id' => 'lonely', 'image_position' => 'auto_default_right', 'text_layout' => 'body-only', 'body_body_only' => 'Body'])],
        [],
    );

    expect(renderedImageSides($html))->toBe(['right']);
});

it('renders an explicit alignment unchanged, ignoring the alignment map (#1192)', function (): void {
    // Even with a map entry that says "right", an explicitly-left block stays left.
    $html = renderSideBySideWithAlignments(
        [sideBySideBlock(['id' => 'a', 'image_position' => 'left', 'text_layout' => 'body-only', 'body_body_only' => 'Body'])],
        ['a' => 'right'],
    );

    expect(renderedImageSides($html))->toBe(['left']);
});

it('keys the alignment lookup on the block id, not the surrounding entry id (#1192)', function (): void {
    // Mirror the real render: the entry carries its own top-level `id`, distinct
    // from the block ids. The lookup must use the block id (`block:id`), not the
    // shadowing entry `id`, or every auto block would miss the map and fall back.
    $blocks = [
        sideBySideBlock(['id' => 'a', 'image_position' => 'auto_default_left', 'text_layout' => 'body-only', 'body_body_only' => 'Body']),
        sideBySideBlock(['id' => 'b', 'image_position' => 'auto_default_left', 'text_layout' => 'body-only', 'body_body_only' => 'Body']),
    ];

    $html = (string) Antlers::parse(
        '{{ blocks scope="block" }}{{ partial:page_builder/uamsweb_block_image_side_by_side }}{{ /blocks }}'
        ."\n{{# entry-id-shadow #}}",
        ['id' => 'ENTRY-ID', 'blocks' => $blocks, 'side_by_side_alignments' => ['a' => 'left', 'b' => 'right']],
    );

    expect(renderedImageSides($html))->toBe(['left', 'right']);
});

it('zig-zags a run of auto blocks end-to-end through the resolver and partial (#1192)', function (): void {
    $blocks = [
        sideBySideBlock(['id' => 'a', 'type' => SideBySideAlignmentResolver::SET_TYPE, 'image_position' => 'auto_default_left', 'text_layout' => 'body-only', 'body_body_only' => 'Body']),
        sideBySideBlock(['id' => 'b', 'type' => SideBySideAlignmentResolver::SET_TYPE, 'image_position' => 'auto_default_left', 'text_layout' => 'body-only', 'body_body_only' => 'Body']),
        sideBySideBlock(['id' => 'c', 'type' => SideBySideAlignmentResolver::SET_TYPE, 'image_position' => 'auto_default_left', 'text_layout' => 'body-only', 'body_body_only' => 'Body']),
    ];

    // Feed the same one-pass the entry computed uses, then render with it.
    $alignments = (new SideBySideAlignmentResolver)->resolve($blocks);

    $html = renderSideBySideWithAlignments($blocks, $alignments);

    expect(renderedImageSides($html))->toBe(['left', 'right', 'left']);
});
