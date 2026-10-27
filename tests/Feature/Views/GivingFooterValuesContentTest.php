<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Content coverage for the `giving` site's footer information values (UAMS-Web/uams-statamic-archive#1666),
 * populated in content/globals/giving/uams_multisite_settings.yaml.
 *
 * The render *mechanism* is proved hermetically by FooterTaxIdAndActionLinkTest
 * (UAMS-Web/uams-statamic-archive#1351 / PR UAMS-Web/uams-statamic-archive#1664); this test proves the giving site's real content carries the
 * correct values — guarding the specific tax ID against the university's own EIN
 * and the corrected-upstream WordPress typo — and that feeding those values through
 * the same footer partial renders the Foundation tax-ID line and the "Give Now"
 * action link, while a control (non-giving) site opts into neither.
 *
 * Self-skips without the content/ submodule, like the other content-gated tests,
 * and runs in CI.
 *
 * @command php artisan test --compact tests/Feature/Views/GivingFooterValuesContentTest.php
 */

/**
 * The `uams_multisite_settings` variables for a site, or skip without the content submodule.
 */
function multisiteSettingsForSite(string $site): Variables
{
    // `GlobalSet::find()?->in()` is untyped in the vendor contract; narrow it to the
    // concrete Variables the same way App\Tags\GlobalOrigin does.
    /** @var Variables|null $variables */
    $variables = GlobalSet::find('uams_multisite_settings')?->in($site);

    if (! $variables) {
        ContentSubmodule::reportMissing('uams_multisite_settings global');
    }

    return $variables;
}

it('the giving site carries the Foundation tax ID and the Give Now action link', function (): void {
    $giving = multisiteSettingsForSite('giving');

    // The Foundation's tax ID — NOT the university's own EIN (71-6046242, the
    // blueprint default) and NOT the corrected-upstream WordPress typo (71-6056674).
    expect($giving->get('tax_id'))->toBe('71-6056774');
    expect($giving->get('display_tax_id'))->toBeTrue();

    $link = $giving->get('footer_action_link');
    expect(data_get($link, 'text'))->toBe('Give Now');
    expect(data_get($link, 'url'))->toBe('https://uams.info/give');
    expect(data_get($link, 'new_window'))->toBeTrue();
});

it('renders the giving footer tax-ID line and Give Now link opening in a new window', function (): void {
    $giving = multisiteSettingsForSite('giving');

    // Feed the giving site's own stored values through the same footer partial the
    // mechanism test exercises, asserting the end-to-end render (content -> HTML).
    $out = (string) Antlers::parse('{{ partial:layout/footer/contact/contact }}', [
        'uams_multisite_settings' => [
            'physical_address' => [],
            'direct_contact' => [],
            'tax_id' => $giving->get('tax_id'),
            'display_tax_id' => $giving->get('display_tax_id'),
            'footer_action_link' => $giving->get('footer_action_link'),
        ],
    ], true);

    expect($out)->toContain('Tax ID:');
    expect($out)->toContain('71-6056774');
    expect($out)->toContain('href="https://uams.info/give"');
    expect($out)->toContain('Give Now');
    expect($out)->toContain('target="_blank"');
    expect($out)->toContain('rel="noopener noreferrer"');
    // WCAG 3.2.5 "opens in new window" cue must accompany the target="_blank" link.
    expect($out)->toContain('opens in new window');
});

it('the giving opt-in does not leak into a control (non-giving) sibling site', function (): void {
    // health, a root-level sibling brand under `default`, sets none of these footer-information
    // fields — so giving's opt-in stayed isolated to its own override and did not bleed into a
    // neighbor. This guards the content-level isolation of the change; that a site which does not
    // opt in renders neither line is proven hermetically by FooterTaxIdAndActionLinkTest.
    $control = multisiteSettingsForSite('health');

    expect($control->get('tax_id'))->toBeNull();
    expect($control->get('display_tax_id'))->not->toBeTrue();
    expect($control->get('footer_action_link'))->toBeNull();
});
