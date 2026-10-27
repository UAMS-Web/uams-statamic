<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the branded UAMS Button page-builder set (UAMS-Web/uams-statamic-archive#1168): a single
 * Bootstrap-styled button rendered through the shared item_button component.
 *
 * @command  php artisan test --compact tests/Feature/Views/ButtonBlockTest.php
 */
beforeEach(fn () => Blink::flush());

/**
 * The Button set's fields, keyed by handle.
 *
 * @return array<string, array<string, mixed>>
 */
function buttonBlockFields(): array
{
    /** @var non-empty-string $path */
    $path = realpath(__DIR__.'/../../../resources/fieldsets/uamsweb_block_button.yaml');

    /** @var array{fields: list<array{handle: string}>} $fieldset */
    $fieldset = Yaml::parseFile($path);

    $keyed = [];
    foreach ($fieldset['fields'] as $field) {
        $keyed[$field['handle']] = $field;
    }

    return $keyed;
}

/**
 * The set handles registered in the page-builder replicator, flattened.
 *
 * @return array<int, string>
 */
function pageBuilderSetHandles(): array
{
    /** @var non-empty-string $path */
    $path = realpath(__DIR__.'/../../../resources/fieldsets/page_builder.yaml');

    /** @var array{fields: array{0: array{field: array{sets: array<string, array{sets?: array<string, mixed>}>}}}} $pb */
    $pb = Yaml::parseFile($path);

    $handles = [];
    foreach ($pb['fields'][0]['field']['sets'] as $group) {
        $handles = array_merge($handles, array_keys($group['sets'] ?? []));
    }

    return $handles;
}

/**
 * Render the Button partial with the given block data.
 *
 * @param  array<string, mixed>  $block
 */
function renderButtonBlock(array $block): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    return (string) Antlers::parse(
        '{{ blocks scope="block" }}{{ partial:page_builder/uamsweb_block_button }}{{ /blocks }}'
        ."\n{{# render-{$render} #}}",
        ['blocks' => [$block]],
    );
}

/**
 * A valid Button block, overridable.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function buttonBlock(array $overrides = []): array
{
    return array_merge([
        'block_heading' => 'Button',
        'hide_block_heading' => true,
        'button_text' => 'ClickHere',
        'button_url' => '/destination',
        'button_target' => false,
        'button_style' => 'solid',
        'button_alignment' => 'left',
        'background_color' => 'bg-white',
    ], $overrides);
}

it('registers the UAMS Button set in the page builder (#1168)', function (): void {
    expect(pageBuilderSetHandles())->toContain('uamsweb_block_button');
});

it('offers a solid/outline style selector', function (): void {
    /** @var array{type: mixed, options: list<array<string, mixed>>, default: mixed, ...} $style */
    $style = buttonBlockFields()['button_style']['field'];

    expect($style['type'])->toBe('radio')
        ->and(array_column($style['options'], 'key'))->toBe(['solid', 'outline'])
        ->and($style['default'])->toBe('solid');
});

it('requires a label and a link', function (): void {
    /** @var array<string, mixed> $textConfig */
    $textConfig = buttonBlockFields()['button_text']['config'];
    /** @var array<string, mixed> $urlConfig */
    $urlConfig = buttonBlockFields()['button_url']['config'];

    expect($textConfig['validate'])->toContain('required')
        ->and($urlConfig['validate'])->toContain('required');
});

it('renders a branded Bootstrap button linking to the configured URL', function (): void {
    $html = renderButtonBlock(buttonBlock(['button_text' => 'GoNow', 'button_url' => '/somewhere']));

    expect($html)->toContain('href="/somewhere"')
        ->and($html)->toContain('GoNow')
        ->and($html)->toContain('btn')
        ->and($html)->toContain('btn-primary');
});

it('renders the outline variant and the chosen alignment', function (): void {
    $html = renderButtonBlock(buttonBlock(['button_style' => 'outline', 'button_alignment' => 'center']));

    expect($html)->toContain('btn-outline-primary')
        ->and($html)->toContain('text-center');
});

it('renders no broken anchor when no link is configured', function (): void {
    $html = renderButtonBlock(buttonBlock(['button_text' => null, 'button_url' => null]));

    expect($html)->not->toContain('<a ')
        ->and($html)->not->toContain('<section');
});
