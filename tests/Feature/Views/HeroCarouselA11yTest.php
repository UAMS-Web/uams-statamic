<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for resources/views/layout/page-header/_hero-content.antlers.html hero
 * carousel keyboard accessibility (UAMS-Web/uams-statamic-archive#736). A multi-slide hero binds Bootstrap's
 * `data-keyboard` Left/Right arrow handler, but the <section> root was not
 * focusable, so a keyboard user could never reach it. The fix adds `tabindex="0"`
 * to the root, gated on there being more than one slide, so single-slide heroes
 * stay out of the tab order. The visible focus ring lives in hero.scss as a
 * `:focus-visible` rule on the root (an inherently-visual property, so this guards
 * its presence; its appearance is verified by the SCSS build and review).
 *
 * @command php artisan test --compact tests/Feature/Views/HeroCarouselA11yTest.php
 */
/**
 * @return array<string, mixed>
 */
function heroSlide(int $n): array
{
    return [
        'heading' => "Slide $n",
        'body' => "Body $n",
        'button_text' => 'Learn more',
        'button_url' => "https://example.test/$n",
        'image_desktop' => ['url' => "https://img.test/$n.jpg", 'alt' => "Alt $n"],
        'image_tablet' => ['url' => "https://img.test/$n.jpg", 'alt' => "Alt $n"],
        'image_mobile' => ['url' => "https://img.test/$n.jpg", 'alt' => "Alt $n"],
        'background_color' => 'red',
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $slides
 */
function renderHero(array $slides): string
{
    return (string) Antlers::parse('{{ partial:layout/page-header/hero-content }}', [
        'header_hero' => $slides,
    ], true);
}

it('makes a multi-slide hero root focusable so keyboard arrow nav is reachable', function () {
    $out = renderHero([heroSlide(1), heroSlide(2)]);

    // The carousel root is rendered and reachable by Tab.
    expect($out)->toContain('aria-label="Hero banner"');
    expect($out)->toContain('tabindex="0"');
    // The keyboard arrow handler it makes reachable is still bound.
    expect($out)->toContain('data-keyboard="true"');
});

it('does not put a single-slide hero in the tab order', function () {
    $out = renderHero([heroSlide(1)]);

    // Still a hero, but not focusable (no tabindex) and no keyboard handler.
    expect($out)->toContain('uams-hero-block');
    expect($out)->not->toContain('tabindex');
    expect($out)->not->toContain('data-keyboard');
});

it('defines a dual-tone keyboard focus ring overlay on the hero root', function () {
    $scss = file_get_contents(
        resource_path('scss/bootstrap/uams_2020/uams-supporting/hero.scss')
    );

    // The ring is a :focus-visible::after overlay above the slide, with two
    // stacked inset bands (white outer + dark inner) for >=3:1 contrast on any
    // slide background. Assert the actual declarations — comment-immune, so
    // deleting the rule fails even though a comment also mentions :focus-visible.
    expect($scss)
        ->toContain('&:focus-visible::after {')
        ->toContain('inset 0 0 0 3px $white')
        ->toContain('inset 0 0 0 6px rgba(0, 0, 0, 0.7)')
        ->toContain('pointer-events: none');
});
