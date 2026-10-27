<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the Google Tag Manager head + body partials (UAMS-Web/uams-statamic-archive#83). The container
 * ID and the disable toggle are read per-site from `uams_multisite_settings`;
 * both partials emit only when GTM is enabled and a container ID is set, so
 * sites without GTM configured emit no broken tags.
 *
 * @command  php artisan test --compact tests/Feature/Seo/GoogleTagManagerPartialTest.php
 */

/**
 * @param  array<string, mixed>  $settings  The `uams_multisite_settings` GTM fields
 */
function renderGtmHead(array $settings): string
{
    return (string) Antlers::parse(
        (string) file_get_contents(resource_path('views/layout/header/_google_tag_manager.antlers.html')),
        ['uams_multisite_settings' => $settings],
        true
    );
}

/**
 * @param  array<string, mixed>  $settings  The `uams_multisite_settings` GTM fields
 */
function renderGtmNoscript(array $settings): string
{
    return (string) Antlers::parse(
        (string) file_get_contents(resource_path('views/layout/_google_tag_manager_noscript.antlers.html')),
        ['uams_multisite_settings' => $settings],
        true
    );
}

it('emits the GTM container script with the request CSP nonce when enabled', function (): void {
    // Bind a known per-request nonce so {{ csp_nonce }} resolves to it; asserting
    // the exact value proves a real nonce is emitted (not just the attribute),
    // since the CSP that allow-lists this inline script relies on it.
    app()->instance(SecurityHeaders::NONCE_KEY, 'test-nonce-abc123');

    $html = renderGtmHead([
        'uamsweb_google_tag_manager_id' => 'GTM-ABC123',
        'uamsweb_google_tag_manager_disable' => false,
    ]);

    expect($html)
        ->toContain("'GTM-ABC123'")
        ->toContain('googletagmanager.com/gtm.js')
        ->toContain('nonce="test-nonce-abc123"');
});

it('emits the GTM noscript iframe when enabled and configured', function (): void {
    $html = renderGtmNoscript([
        'uamsweb_google_tag_manager_id' => 'GTM-ABC123',
        'uamsweb_google_tag_manager_disable' => false,
    ]);

    expect($html)
        ->toContain('<noscript>')
        ->toContain('ns.html?id=GTM-ABC123');
});

it('emits nothing when GTM is disabled for the site', function (): void {
    $settings = [
        'uamsweb_google_tag_manager_id' => 'GTM-ABC123',
        'uamsweb_google_tag_manager_disable' => true,
    ];

    expect(trim(renderGtmHead($settings)))->toBe('');
    expect(trim(renderGtmNoscript($settings)))->toBe('');
});

it('emits nothing when no container ID is set', function (): void {
    $settings = ['uamsweb_google_tag_manager_disable' => false];

    expect(trim(renderGtmHead($settings)))->toBe('');
    expect(trim(renderGtmNoscript($settings)))->toBe('');
});
