<?php

declare(strict_types=1);

/**
 * Tests the keyboard focus ring on the provider directory's name search
 * (UAMS-Web/uams-statamic#70, WCAG 2.4.7). Bootstrap's `.form-control:focus`
 * glow blends to about 1.55:1 against white, below the 3:1 non-text bar, so
 * the Find-a-Doc bundle adds the site's `uams-focus-visible()` ring to that one
 * input. Appearance is visual, so this pins the rule's presence and its scope;
 * the ring itself was verified in a browser.
 *
 * @command php artisan test --compact tests/Feature/Views/ProviderDirectorySearchFocusTest.php
 */

/**
 * The Find-a-Doc AJAX filter stylesheet, with comment lines removed so a
 * comment that mentions the rule cannot satisfy an assertion about it.
 */
function providerDirectoryFilterScss(): string
{
    $scss = file_get_contents(dirname(__DIR__, 3).'/resources/scss/bootstrap/uamswp_find_a_doc/_ajax-filter.scss');

    return (string) preg_replace('~^\s*//.*$~m', '', (string) $scss);
}

it('gives the provider directory search input the brand focus ring', function (): void {
    expect(providerDirectoryFilterScss())->toMatch(
        '~&\.provider-directory-filter input\[type="search"\]\s*\{\s*@include uams-focus-visible\(\);\s*\}~'
    );
});

it('leaves the filter selects on their own focus styling', function (): void {
    $scss = providerDirectoryFilterScss();

    expect(substr_count($scss, '@include uams-focus-visible('))->toBe(1)
        ->and($scss)->not->toMatch('~select[^{]*\{[^}]*uams-focus-visible~');
});
