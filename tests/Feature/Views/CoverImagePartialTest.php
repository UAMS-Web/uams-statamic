<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for the cover-image partial (layout/page-header/cover-image): renders a
 * figure from the asset url + alt, honors alt/caption overrides, emits an empty
 * alt for alt-exempt assets, renders nothing without an asset, and resolves
 * cover_image from featured_image_16_9 only when cover_image_query is true.
 * Antlers template partial — no mutable PHP surface.
 *
 * @command php artisan test --compact tests/Feature/Views/CoverImagePartialTest.php
 */

/**
 * Build the cover-image partial context.
 *
 * @param  array<string, mixed>  $asset
 * @return array<string, mixed>
 */
function coverImageContext(array $asset, ?string $altOverride = null, ?string $captionOverride = null): array
{
    return [
        'cover_image' => $asset,
        'cover_image_alt_text_override' => $altOverride,
        'cover_image_caption_override' => $captionOverride,
    ];
}

it('renders figure markup with the asset url and alt', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/cover-image }}', coverImageContext([
        'url' => '/assets/example.jpg',
        'alt' => 'Example alt',
        'exempt_from_alt' => false,
        'caption' => null,
    ]), true);

    expect($out)->toContain('class="entry-cover-image alignwide"');
    expect($out)->toContain('src="/assets/example.jpg"');
    expect($out)->toContain('alt="Example alt"');
});

it('uses the alt override when provided', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/cover-image }}', coverImageContext([
        'url' => '/a.jpg',
        'alt' => 'Ignored',
        'exempt_from_alt' => false,
        'caption' => null,
    ], 'Custom alt for test'), true);

    expect($out)->toContain('alt="Custom alt for test"');
});

it('renders a figcaption when a caption override is provided', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/cover-image }}', coverImageContext([
        'url' => '/a.jpg',
        'alt' => 'A',
        'exempt_from_alt' => false,
        'caption' => null,
    ], null, 'Caption line one'), true);

    expect($out)->toContain('<figcaption>');
    // typography/caption runs the text through widont (last space becomes &nbsp;).
    expect($out)->toContain('Caption line&nbsp;one');
});

it('emits an empty alt for an alt-exempt asset without an override', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/cover-image }}', coverImageContext([
        'url' => '/a.jpg',
        'alt' => 'Ignored when exempt',
        'exempt_from_alt' => true,
        'caption' => null,
    ]), true);

    expect($out)->toContain('alt=""');
});

it('renders nothing without an asset', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/cover-image }}', [], true);

    expect($out)->not->toContain('<figure');
});

it('resolves cover_image from featured_image_16_9 when cover_image_query is true', function () {
    $out = (string) Antlers::parse(
        '{{ cover_image = cover_image_query ? featured_image_16_9 : null }}'."\n".'{{ partial:layout/page-header/cover-image }}',
        [
            'cover_image_query' => true,
            'featured_image_16_9' => ['url' => '/assets/featured.jpg', 'alt' => 'Featured alt', 'exempt_from_alt' => false, 'caption' => null],
            'cover_image_alt_text_override' => null,
            'cover_image_caption_override' => null,
        ],
        true,
    );

    expect($out)->toContain('src="/assets/featured.jpg"');
    expect($out)->toContain('class="entry-cover-image alignwide"');
});

it('renders no cover when cover_image_query is false even if featured is set', function () {
    $out = (string) Antlers::parse(
        '{{ cover_image = cover_image_query ? featured_image_16_9 : null }}'."\n".'{{ partial:layout/page-header/cover-image }}',
        [
            'cover_image_query' => false,
            'featured_image_16_9' => ['url' => '/assets/featured.jpg', 'alt' => 'Featured alt', 'exempt_from_alt' => false, 'caption' => null],
            'cover_image_alt_text_override' => null,
            'cover_image_caption_override' => null,
        ],
        true,
    );

    expect($out)->not->toContain('<figure');
});

it('renders the credit when the asset has a creator but no caption', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/cover-image }}', coverImageContext([
        'url' => '/assets/credited.jpg',
        'alt' => 'Credited',
        'exempt_from_alt' => false,
        'caption' => null,
        'creator' => 'Jean-Luc Picard',
    ]), true);

    expect($out)->toContain('<figcaption>');
    expect($out)->toContain('photo-credit');
    expect($out)->toContain('Jean-Luc Picard');
    expect($out)->not->toContain('caption-text');
});

it('renders no figcaption when the asset has neither a caption nor a creator', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/cover-image }}', coverImageContext([
        'url' => '/assets/bare.jpg',
        'alt' => 'Bare',
        'exempt_from_alt' => false,
        'caption' => null,
        'creator' => null,
    ]), true);

    expect($out)->toContain('class="entry-cover-image alignwide"');
    expect($out)->not->toContain('<figcaption>');
    expect($out)->not->toContain('photo-credit');
});
