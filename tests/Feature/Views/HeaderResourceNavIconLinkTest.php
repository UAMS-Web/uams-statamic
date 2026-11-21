<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression coverage for #1209: the header's icon resource links are stored
 * through `uamsweb_common_link.icon_link_group`, whose handles are `link` and
 * `link_text`, while the text resource links use `text_link_group` with `url` and
 * `text`. The icon loop read the text-link handles, so every icon link rendered
 * with no `href` and an empty accessible name.
 *
 * The partial is rendered with injected `uams_multisite_settings` data, the same
 * approach FooterResourceNavLegalNoticesTest takes for the footer.
 *
 * @command php artisan test --compact tests/Feature/Views/HeaderResourceNavIconLinkTest.php
 */

/**
 * Render the header resource-nav partial with the given icon and text link sets.
 *
 * @param  array<int, array<string, mixed>>  $iconLinks
 * @param  array<int, array<string, mixed>>  $textLinks
 */
function renderHeaderResourceNav(array $iconLinks, array $textLinks = []): string
{
    return (string) Antlers::parse('{{ partial:layout/header/navbar/resource-nav }}', [
        'uams_multisite_settings' => [
            'global_header_resource_navigation_icon' => $iconLinks,
            'global_header_resource_navigation' => $textLinks,
        ],
    ], true);
}

/**
 * The opening `<a>` tag of the icon link carrying the given class.
 */
function iconLinkTag(string $html, string $class): string
{
    if (preg_match('/<a\b[^>]*\b'.preg_quote($class, '/').'\b[^>]*>/s', $html, $match) !== 1) {
        throw new RuntimeException("No <a> tag with class {$class} in the rendered resource nav.");
    }

    return $match[0];
}

it('renders an icon link with its stored link as the href and its label as the accessible name', function (): void {
    $html = renderHeaderResourceNav([[
        'type' => 'new_icon_link',
        'enabled' => true,
        'link_group' => [
            'link_text' => 'Emergency Room',
            'link' => 'https://uamshealth.com/location/uams-emergency-room/',
            'icon' => 'fas fa-ambulance',
            'new_window' => true,
            'link_class' => 'emergency-link',
        ],
    ]]);

    $tag = iconLinkTag($html, 'emergency-link');

    expect($tag)->toContain('href="https://uamshealth.com/location/uams-emergency-room/"')
        ->toContain('aria-label="Emergency Room"')
        ->toContain('target="_blank"')
        ->and($html)->toContain('<span class="sr-only">Emergency Room</span>');
});

it('escapes the icon link label in its screen-reader text', function (): void {
    $html = renderHeaderResourceNav([[
        'type' => 'new_icon_link',
        'link_group' => [
            'link_text' => 'Kirk <b>& Spock</b>',
            'link' => 'https://example.test/',
            'icon' => 'fas fa-star',
            'new_window' => false,
            'link_class' => 'enterprise-link',
        ],
    ]]);

    expect($html)->toContain('<span class="sr-only">Kirk &lt;b&gt;&amp; Spock&lt;/b&gt;</span>')
        ->not->toContain('<b>& Spock</b>');
});

it('still renders the text resource links from their url and text', function (): void {
    $html = renderHeaderResourceNav([], [[
        'type' => 'new_text_link',
        'link_group' => [
            'text' => 'MyChart',
            'url' => 'https://mychart.example.test/',
            'new_window' => false,
        ],
    ]]);

    expect($html)->toContain('href="https://mychart.example.test/"')
        ->toContain('>MyChart</a>');
});
