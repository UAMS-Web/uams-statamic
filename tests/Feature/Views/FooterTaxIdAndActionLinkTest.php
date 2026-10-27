<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests the footer information block additions in
 * resources/views/layout/footer/contact/_contact.antlers.html (UAMS-Web/uams-statamic-archive#1351):
 *
 *   - `tax_id` renders only when the per-site `display_tax_id` toggle is on.
 *     The value is inherited by every site, so the *display* — not the value —
 *     is what each site opts into.
 *   - `footer_action_link` renders by presence (the "Give Now" CTA on the
 *     giving subsite), after the tax ID, honoring the new-window option.
 *
 * A control site that sets neither renders neither.
 *
 * @command php artisan test --compact tests/Feature/Views/FooterTaxIdAndActionLinkTest.php
 */

/**
 * Render the global footer contact partial with the given multisite settings.
 *
 * Every key the partial reads is passed explicitly so each case is hermetic.
 *
 * @param  array<string, mixed>  $settings  Overrides merged into the global set.
 */
function renderFooterInformationBlock(array $settings): string
{
    return (string) Antlers::parse('{{ partial:layout/footer/contact/contact }}', [
        'uams_multisite_settings' => array_merge([
            'physical_address' => [],
            'direct_contact' => [],
            'tax_id' => null,
            'display_tax_id' => false,
            'footer_action_link' => null,
        ], $settings),
    ], true);
}

it('renders the tax-ID line when the display toggle is on', function () {
    $out = renderFooterInformationBlock([
        'tax_id' => '71-6056774',
        'display_tax_id' => true,
    ]);

    expect($out)->toContain('71-6056774');
    expect($out)->toContain('Tax ID:');
});

it('suppresses the tax-ID line when the toggle is off even though the value is inherited', function () {
    // The gating behavior that matters: the value is present (inherited from
    // root) but the site has not opted into displaying it.
    $out = renderFooterInformationBlock([
        'tax_id' => '71-6046242',
        'display_tax_id' => false,
    ]);

    expect($out)->not->toContain('71-6046242');
    expect($out)->not->toContain('Tax ID:');
});

it('renders no tax-ID line when the toggle is on but no value is set', function () {
    $out = renderFooterInformationBlock([
        'tax_id' => null,
        'display_tax_id' => true,
    ]);

    expect($out)->not->toContain('Tax ID:');
});

it('renders the footer action link by presence, opening in a new window with the WCAG indicator', function () {
    $out = renderFooterInformationBlock([
        'footer_action_link' => [
            'text' => 'Give Now',
            'url' => 'https://uams.info/give',
            'new_window' => true,
        ],
    ]);

    expect($out)->toContain('href="https://uams.info/give"');
    expect($out)->toContain('Give Now');
    expect($out)->toContain('target="_blank"');
    expect($out)->toContain('rel="noopener noreferrer"');
    // WCAG 3.2.5 "opens in new window" cue must accompany a target="_blank" link.
    expect($out)->toContain('opens in new window');
});

it('omits the new-window attributes and indicator when the action link opens in the same tab', function () {
    $out = renderFooterInformationBlock([
        'footer_action_link' => [
            'text' => 'Learn More',
            'url' => 'https://uams.edu/learn',
            'new_window' => false,
        ],
    ]);

    expect($out)->toContain('href="https://uams.edu/learn"');
    expect($out)->toContain('Learn More');
    expect($out)->not->toContain('target="_blank"');
    expect($out)->not->toContain('opens in new window');
});

it('does not render an action link with a URL but no text (no anchor without an accessible name)', function () {
    // The group's subfields are optional so the whole link can be left empty;
    // that makes a URL-without-text state reachable, which must not emit an
    // anchor whose only content is the decorative new-window indicator.
    $out = renderFooterInformationBlock([
        'footer_action_link' => [
            'text' => null,
            'url' => 'https://uams.info/give',
            'new_window' => true,
        ],
    ]);

    expect($out)->not->toContain('schema-action-link');
    expect($out)->not->toContain('https://uams.info/give');
});

it('escapes the tax ID so an editor value cannot break out into markup', function () {
    // Antlers is raw by default; the `| sanitize` must be load-bearing.
    $out = renderFooterInformationBlock([
        'tax_id' => '<img src=x onerror=alert(1)>',
        'display_tax_id' => true,
    ]);

    expect($out)->toContain('&lt;img src=x onerror=alert(1)&gt;');
    expect($out)->not->toContain('<img src=x');
});

it('escapes the action-link URL and text so a quote cannot break out of the attribute', function () {
    $out = renderFooterInformationBlock([
        'footer_action_link' => [
            'text' => '<b>Give</b>',
            'url' => 'https://uams.info/give"onmouseover=alert(1)',
            'new_window' => false,
        ],
    ]);

    // The double quote in the URL must be entity-encoded, not close the href.
    expect($out)->toContain('&quot;onmouseover=alert(1)');
    expect($out)->not->toContain('give"onmouseover');
    // The link text is escaped too.
    expect($out)->toContain('&lt;b&gt;Give&lt;/b&gt;');
    expect($out)->not->toContain('<b>Give</b>');
});

it('neutralizes a dangerous action-link URL scheme (href uses sanitize_url, not sanitize)', function () {
    // `sanitize` alone would render `javascript:` verbatim into the href;
    // `sanitize_url` collapses any non-http(s)/mailto/tel scheme to an inert `#`.
    $out = renderFooterInformationBlock([
        'footer_action_link' => [
            'text' => 'Give Now',
            'url' => 'javascript:alert(document.cookie)',
            'new_window' => false,
        ],
    ]);

    expect($out)->toContain('href="#"');
    expect($out)->not->toContain('javascript:alert');
});

it('renders neither the tax ID nor the action link on a control site', function () {
    // A site that opts into neither — e.g. any non-giving subsite.
    $out = renderFooterInformationBlock([]);

    expect($out)->not->toContain('Tax ID:');
    expect($out)->not->toContain('schema-action-link');
});
