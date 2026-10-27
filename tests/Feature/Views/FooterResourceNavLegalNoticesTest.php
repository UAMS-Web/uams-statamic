<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression coverage for UAMS-Web/uams-statamic-archive#1195: the global footer carries a "Legal Notices" link
 * pointing to https://uamshealth.com/legal-notices/, restoring the legacy WordPress
 * footer link.
 *
 * The link lives in the `health` site's `global_footer_supplementary_resource_navigation`
 * (content/ submodule), which resources/views/layout/footer/navigation/_resource.antlers.html
 * renders automatically alongside the site's other supplementary legal links. Two guards:
 * an always-on render test of the template path with injected data, and a content-backed
 * test (skipped without the submodule) that fails loudly if the real content ever loses
 * the link.
 *
 * Note: UAMS-Web/uams-statamic-archive#1195 describes the link sitting beside "Section 1557 nondiscrimination" and
 * "§ 92.11 language assistance" siblings, but those are not present in this content set —
 * the real supplementary legal links on the `health` site are Notice of Privacy Practices
 * (HIPAA NPP) and Price Transparency. Legal Notices is grouped consistently with them.
 *
 * @command  php artisan test --compact tests/Feature/Views/FooterResourceNavLegalNoticesTest.php
 */

/**
 * Render the footer resource-navigation partial with the given supplementary-nav sets.
 *
 * @param  array<int, array<string, mixed>>  $supplementary
 */
function renderFooterResourceNav(array $supplementary): string
{
    return (string) Antlers::parse('{{ partial:layout/footer/navigation/resource }}', [
        'uams_multisite_settings' => [
            'global_footer_supplementary_resource_navigation' => $supplementary,
        ],
    ], true);
}

it('renders the Legal Notices link with its target and new-window affordance', function () {
    $out = renderFooterResourceNav([[
        'type' => 'text_link',
        'id' => 'q80jtnsw',
        'link_group' => [
            'text' => 'Legal Notices',
            'url' => 'https://uamshealth.com/legal-notices/',
            'new_window' => true,
        ],
    ]]);

    expect($out)
        ->toContain('href="https://uamshealth.com/legal-notices/"')
        ->toContain('>Legal Notices')
        // new_window: true emits the external-link attributes and the WCAG cue.
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('opens in new window');
});

it('carries the Legal Notices link in the real health footer content', function () {
    $global = GlobalSet::find('uams_multisite_settings');

    if (! $global) {
        ContentSubmodule::reportMissing('uams_multisite_settings global');
    }

    /** @var Variables|null $health */
    $health = $global->in('health');

    if (! $health) {
        ContentSubmodule::reportMissing('health site global');
    }

    /** @var array<int, array<string, mixed>> $supplementary */
    $supplementary = $health->get('global_footer_supplementary_resource_navigation') ?? [];

    $out = renderFooterResourceNav($supplementary);

    // The real committed content still renders the Legal Notices link to its target.
    expect($out)
        ->toContain('href="https://uamshealth.com/legal-notices/"')
        ->toContain('>Legal Notices');
});
