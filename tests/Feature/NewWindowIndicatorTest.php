<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#702: the shared "opens in new window" indicator partial, the
 * external-link wrapper, and the application of both across the `target="_blank"`
 * template set (with icon-only links carrying the cue in their accessible name).
 *
 * @command  php artisan test --compact tests/Feature/NewWindowIndicatorTest.php
 */
it('renders the WCAG 3.2.5 indicator per the UAMS convention', function (): void {
    $html = (string) Antlers::parse('{{ partial:components/new_window_indicator }}', []);

    expect($html)
        // Decorative, sized/colored per the convention.
        ->toContain('class="external-link-icon"')
        ->toContain('width="0.85em"')
        ->toContain('fill="currentColor"')
        ->toContain('margin-left:0.25em')
        ->toContain('aria-hidden="true"')
        ->toContain('focusable="false"')
        // Canonical screen-reader wording, no parentheses.
        ->toContain('<span class="sr-only">opens in new window</span>');
    expect($html)->not->toContain('(opens in new window)');
});

it('renders a full external-link wrapper with rel and the indicator', function (): void {
    $html = (string) Antlers::parse(
        '{{ partial:components/external_link url="https://x.test" text="Give Now" }}',
        [],
    );

    expect($html)
        ->toContain('href="https://x.test"')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('Give Now')
        ->toContain('opens in new window');
});

it('applies the indicator across the target=_blank template set', function (): void {
    $sites = [
        'sets/_file.antlers.html',
        'layout/footer/navigation/_resource.antlers.html',
        'layout/footer/contact/_contact.antlers.html',
        'page_builder/_uamsweb_block_rss.antlers.html',
        'page_builder/_uamsweb_block_people_item.antlers.html',
    ];

    foreach ($sites as $site) {
        expect(File::get(resource_path("views/{$site}")))
            ->toContain('partial:components/new_window_indicator');
    }
});

it('carries the cue in the accessible name for icon-only and aria-label links', function (): void {
    // Icon-only social link: cue lives in the sr-only accessible name, no glyph.
    $social = File::get(resource_path('views/layout/footer/navigation/social/_link-item.antlers.html'));
    expect($social)->toContain('opens in new window');
    expect($social)->not->toContain('partial:components/new_window_indicator');

    // Hero button has an aria-label, so the cue is appended to that label.
    $hero = File::get(resource_path('views/layout/page-header/_hero-content.antlers.html'));
    expect($hero)->toContain("', opens in new window'");
});
