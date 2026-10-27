<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Renders the Table of Contents block partial (UAMS-Web/uams-statamic-archive#1283).
 *
 * The list itself is built client-side and is covered by `resources/js/toc-engine.test.js`.
 * What can only be checked here is the CONTRACT BETWEEN THE TWO: the engine finds its navs by
 * `[data-toc]`, fills `[data-toc-list]`, and clears `hidden` once it has headings. If the
 * template stops emitting any of those three, every JavaScript test still passes and the block
 * silently never appears on a page.
 *
 * @command  php artisan test --compact tests/Feature/PageBuilder/TableOfContentsBlockTest.php
 */
/**
 * Render the block partial with a default context one override at a time can vary.
 *
 * @param  array<string, mixed>  $overrides  Context values replacing the defaults
 * @return string The rendered markup
 */
function tocBlockRender(array $overrides = []): string
{
    $context = array_merge([
        'block_heading' => 'On this page',
        'hide_block_heading' => false,
        'background_color' => 'bg-white',
        'block' => ['id' => 'abc123'],
        'id' => 'abc123',
    ], $overrides);

    return (string) Antlers::parse(
        '{{ partial:page_builder/uamsweb_block_table_of_contents }}',
        $context,
        true
    );
}

it('emits the three hooks the front-end engine binds to', function (): void {
    $html = tocBlockRender();

    expect($html)->toContain('data-toc')
        ->and($html)->toContain('data-toc-list')
        ->and($html)->toContain('hidden');
});

it('renders hidden, so a page with no headings shows no table of contents', function (): void {
    // The third acceptance criterion. The attribute sits on the OUTERMOST element: hiding an
    // inner list would still leave a padded block and its heading on the page.
    $html = tocBlockRender();

    expect($html)->toMatch('/<nav\b[^>]*\bhidden\b/');
});

it('renders no list of its own, because the engine builds it', function (): void {
    $html = tocBlockRender();

    expect($html)->not->toContain('<a href="#');
});

it('labels the nav by its own heading', function (): void {
    // The heading id is what `aria-labelledby` points at, and it follows the same
    // `<block-id>-title` shape `components/block_header` uses on every other block.
    $html = tocBlockRender();

    expect($html)->toContain('aria-labelledby="module-abc123-title"')
        ->and($html)->toContain('id="module-abc123-title"');
});

it('uses the supplied heading text', function (): void {
    // `widont` joins the last two words with a non-breaking space, as it does on every other
    // block heading -- so the literal string never appears and asserting on it would fail for
    // a reason that has nothing to do with this block.
    $html = tocBlockRender(['block_heading' => 'Jump to a section']);

    expect($html)->toContain('Jump to a&nbsp;section');
});

it('escapes a heading carrying markup', function (): void {
    // Antlers is raw by default, so an editor- or importer-supplied heading would otherwise
    // emit live HTML on a public page. Same sink, same reasoning as components/block_header.
    $html = tocBlockRender(['block_heading' => 'Safe <img src=x onerror=alert(1)>']);

    expect($html)->not->toContain('<img src=x');
});

it('renders nothing at all without a heading', function (): void {
    expect(trim(tocBlockRender(['block_heading' => ''])))->toBe('');
});
