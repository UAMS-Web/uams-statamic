<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the Text & Image Overlay block (UAMS-Web/uams-statamic-archive#1194): the link (button) is now
 * optional. Structural assertions pin the relaxed fieldset validation; render
 * assertions prove the block renders with or without a link and never emits an
 * empty anchor (the shared item_button component self-guards on
 * `button_text && button_url`).
 *
 * @command  php artisan test --compact tests/Feature/Views/OverlayBlockTest.php
 */
beforeEach(fn () => Blink::flush());

/**
 * The overlay block's `new_set` fields, keyed by handle.
 *
 * @return array<string, array<string, mixed>>
 */
function overlayNewSetFields(): array
{
    /** @var non-empty-string $path */
    $path = realpath(__DIR__.'/../../../resources/fieldsets/uamsweb_block_overlay.yaml');

    /** @var array{fields: array{0: array{field: array{sets: array{new_set_group: array{sets: array{new_set: array{fields: list<array{handle: string}>}}}}}}}} $fieldset */
    $fieldset = Yaml::parseFile($path);
    $setFields = $fieldset['fields'][0]['field']['sets']['new_set_group']['sets']['new_set']['fields'];

    $keyed = [];
    foreach ($setFields as $field) {
        $keyed[$field['handle']] = $field;
    }

    return $keyed;
}

/**
 * A fixture overlay item; the link (button_*) is omitted unless supplied.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function overlayItem(array $overrides = []): array
{
    return array_merge([
        'heading' => 'Section Heading',
        'body' => 'Section body copy.',
        'background_color' => 'bg-red',
        'background_image' => ['url' => '/img/bg.jpg'],
    ], $overrides);
}

/**
 * Render the overlay partial for the given items.
 *
 * @param  array<int, array<string, mixed>>  $items
 */
function renderOverlay(array $items): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    /** @var non-empty-string $partialPath */
    $partialPath = realpath(__DIR__.'/../../../resources/views/page_builder/_uamsweb_block_overlay.antlers.html');
    $source = file_get_contents($partialPath)
        ."\n{{# render-{$render} #}}";

    return (string) Antlers::parse($source, ['items' => $items], true);
}

// ---------------------------------------------------------------------------
// Fieldset — link no longer required
// ---------------------------------------------------------------------------

it('no longer requires the overlay link fields (#1194)', function (string $handle): void {
    /** @var array{config?: array<string, mixed>} $field */
    $field = overlayNewSetFields()[$handle];

    expect($field['config']['validate'] ?? [])->not->toContain('required');
})->with(['button_text', 'button_url', 'button_target']);

it('still requires the overlay heading, body, and background', function (): void {
    /** @var array<string, array{config: array<string, mixed>}> $fields */
    $fields = overlayNewSetFields();

    expect($fields['heading']['config']['validate'])->toContain('required')
        ->and($fields['body']['config']['validate'])->toContain('required')
        ->and($fields['background_color']['config']['validate'])->toContain('required')
        ->and($fields['background_image']['config']['validate'])->toContain('required');
});

// ---------------------------------------------------------------------------
// Render — with and without a link
// ---------------------------------------------------------------------------

it('renders the overlay link when a button is configured', function (): void {
    $html = renderOverlay([overlayItem([
        'button_text' => 'Learn More',
        'button_url' => '/target',
        'button_target' => false,
    ])]);

    // NOT `toContain('<section')`. The shared block wrapper
    // (`page_builder/_uamsweb_block`) opens with `<{{ $html_tag }}`, whose default is
    // `'section'` — so that assertion matches the wrapper on every page-builder block and
    // can never fail from an overlay change. Verified: deleting this partial's own
    // `<section` left the test green, because the wrapper still emitted one (UAMS-Web/uams-statamic-archive#2045).
    //
    // `item bg-image` comes from this partial's own class list, so it fails if the item
    // stops rendering.
    expect($html)->toContain('item bg-image')
        ->and($html)->toContain('Learn More')
        ->and($html)->toContain('/target');
});

it('renders the overlay with no link element when no button is configured', function (): void {
    $html = renderOverlay([overlayItem()]);

    // The block is still valid (heading/body/background present) and renders,
    // but the item_button emits nothing without a link — no empty anchor.
    expect($html)->toContain('item bg-image')
        ->and($html)->toContain('Section Heading')
        ->and($html)->not->toContain('<a ');
});
