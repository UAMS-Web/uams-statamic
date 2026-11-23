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
 * #1212 then gave the ER entry (class `emergency-link`) the hospital glyph, a visible
 * "ER" label and one accessible name starting with it, while every other icon link
 * stays icon-only.
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
            'link_text' => 'Find a Location',
            'link' => 'https://uamshealth.com/location/',
            'icon' => 'fas fa-map-marker-alt',
            'new_window' => true,
            'link_class' => 'location-link',
        ],
    ]]);

    $tag = iconLinkTag($html, 'location-link');

    expect($tag)->toContain('href="https://uamshealth.com/location/"')
        ->toContain('aria-label="Find a Location"')
        ->toContain('target="_blank"')
        ->and(preg_replace('/\s+/', ' ', $html))->toContain('<i class="fas fa-map-marker-alt"></i><span class="sr-only">Find a Location</span></a>')
        ->not->toContain('resource-nav-label');
});

/**
 * The ER entry as the health site stores it, plus one ordinary icon link beside it.
 *
 * @return array<int, array<string, mixed>>
 */
function emergencyAndOtherIconLinks(string $erClass = 'emergency-link'): array
{
    return [
        [
            'type' => 'new_icon_link',
            'link_group' => [
                'link_text' => 'Emergency Room',
                'link' => 'https://uamshealth.com/location/uams-emergency-room/',
                'icon' => 'fas fa-ambulance',
                'new_window' => true,
                'link_class' => $erClass,
            ],
        ],
        [
            'type' => 'new_icon_link',
            'link_group' => [
                'link_text' => 'Find a Location',
                'link' => 'https://uamshealth.com/location/',
                'icon' => 'fas fa-map-marker-alt',
                'new_window' => false,
                'link_class' => 'location-link',
            ],
        ],
    ];
}

it('shows the ER entry with the hospital glyph, a visible "ER" and one name that starts with it', function (string $erClass): void {
    $html = renderHeaderResourceNav(emergencyAndOtherIconLinks($erClass));
    $tag = iconLinkTag($html, 'emergency-link');
    $link = str($html)->after($tag)->before('</a>')->toString();

    expect($tag)->toContain('href="https://uamshealth.com/location/uams-emergency-room/"')
        ->toContain('target="_blank"')
        // The name comes from the link text alone, so it is set once.
        ->not->toContain('aria-label')
        ->and($link)->toBe('<i class="fas fa-hospital-symbol" aria-hidden="true"></i><span class="resource-nav-label">ER</span><span class="sr-only">, Emergency Room</span>')
        ->and($html)->not->toContain('fa-ambulance');

    // The text a screen reader reads as the link's name: every text node, tags removed.
    expect(trim(strip_tags($link)))->toStartWith('ER')
        ->toBe('ER, Emergency Room');
})->with([
    'class alone' => 'emergency-link',
    'among other classes' => 'highlight emergency-link',
]);

it('keeps the other icon links icon-only beside the ER entry', function (): void {
    $html = renderHeaderResourceNav(emergencyAndOtherIconLinks());
    $tag = iconLinkTag($html, 'location-link');
    $link = preg_replace('/\s+/', ' ', str($html)->after($tag)->before('</a>')->toString());

    expect($tag)->toContain('aria-label="Find a Location"')
        ->and($link)->toBe('<i class="fas fa-map-marker-alt"></i><span class="sr-only">Find a Location</span>')
        ->and(substr_count($html, 'resource-nav-label'))->toBe(1);
});

it('does not treat a class that only contains the ER class name as the ER entry', function (): void {
    $html = renderHeaderResourceNav([[
        'type' => 'new_icon_link',
        'link_group' => [
            'link_text' => 'Non-emergency care',
            'link' => 'https://example.test/',
            'icon' => 'fas fa-clinic-medical',
            'new_window' => false,
            'link_class' => 'non-emergency-link',
        ],
    ]]);

    expect(preg_replace('/\s+/', ' ', $html))->toContain('<i class="fas fa-clinic-medical"></i>')
        ->toContain('aria-label="Non-emergency care"')
        ->not->toContain('resource-nav-label')
        ->not->toContain('fa-hospital-symbol');
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
