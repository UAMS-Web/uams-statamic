<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression test for stored XSS via the breadcrumb name/link partials (UAMS-Web/uams-statamic-archive#1485).
 *
 * `_item-name.antlers.html` rendered an entry's `nav_alt_name ?? title` raw, and
 * `_item-link.antlers.html` injected the raw `title` into a `title="..."`
 * attribute for the ancestor-first crumb. Both values are editor/importer
 * controlled and Antlers is raw-by-default, so a hostile title executed as stored
 * XSS on any page whose breadcrumb walked the entry. The untrusted title is now
 * `strip_tags | entities`-escaped exactly as the sibling `<h1>`/`<title>`/
 * term-heading sinks are, while the trusted current-site-name crumb is left as-is.
 *
 * No mutable PHP surface: the system under test is the Antlers partial pair under
 * `resources/views/layout/breadcrumbs/`, templates with no backing App\ PHP class.
 * Every cascade control variable is supplied per call via the render data (the
 * `$foo` reads resolve the plain `foo` key), so the tests stay hermetic against
 * Antlers' process-global runtime rather than leaking assignments across calls.
 *
 * @command  php artisan test --compact tests/Feature/BreadcrumbTitleXssTest.php
 */

/**
 * Render a breadcrumb partial template against the given cascade data.
 *
 * @param  array<string, mixed>  $data
 */
function renderBreadcrumbPartial(string $template, array $data): string
{
    return (string) Antlers::parse($template, $data, true);
}

it('escapes markup in an untrusted breadcrumb leaf title', function (): void {
    // A non-first current-site crumb takes the `nav_alt_name ?? title` branch.
    $html = renderBreadcrumbPartial(
        '{{ partial:layout/breadcrumbs/item-name }}',
        [
            'breadcrumbs_count_current_site' => 2,
            'breadcrumbs_count' => 2,
            'title' => 'Cardiology <img src=x onerror=alert(document.cookie)> Center',
        ],
    );

    expect($html)->not->toContain('<img');
    expect($html)->not->toContain('onerror');
    // Benign text survives the strip.
    expect($html)->toContain('Cardiology');
    expect($html)->toContain('Center');
});

it('entity-encodes HTML-special characters in an untrusted leaf title', function (): void {
    // A benign title with an ampersand must be entity-encoded (the `| entities` step),
    // not merely tag-stripped — covered independently of the ancestor-link partial so
    // the leaf-name encoding is not a silent surviving mutant.
    $html = renderBreadcrumbPartial(
        '{{ partial:layout/breadcrumbs/item-name }}',
        [
            'breadcrumbs_count_current_site' => 2,
            'breadcrumbs_count' => 2,
            'title' => 'Cardiology & Vascular',
        ],
    );

    expect($html)->toContain('Cardiology &amp; Vascular'); // ampersand entity-encoded
    expect($html)->not->toContain('Cardiology & Vascular'); // raw ampersand gone
});

it('escapes markup in a breadcrumb nav_alt_name override', function (): void {
    // `nav_alt_name` wins over `title` and is equally editor-controlled.
    $html = renderBreadcrumbPartial(
        '{{ partial:layout/breadcrumbs/item-name }}',
        [
            'breadcrumbs_count_current_site' => 2,
            'breadcrumbs_count' => 2,
            'title' => 'Safe Title',
            'nav_alt_name' => '<img src=x onerror=alert(document.cookie)>',
        ],
    );

    expect($html)->not->toContain('<img');
    expect($html)->not->toContain('onerror');
    expect($html)->not->toContain('Safe Title'); // nav_alt_name takes precedence
});

it('leaves the trusted current-site-name crumb unescaped and title-free', function (): void {
    // The first current-site crumb renders the trusted site name raw, never the title.
    $html = renderBreadcrumbPartial(
        '{{ partial:layout/breadcrumbs/item-name }}',
        [
            'breadcrumbs_count_current_site' => 1,
            'breadcrumbs_count' => 2,
            'breadcrumbs_current_site_name' => 'UAMS Health & Wellness',
            'title' => '<img src=x onerror=alert(document.cookie)>',
        ],
    );

    // The trusted (admin-config) site name is rendered as-is: the raw `&` survives,
    // pinning the "left unescaped" property (AC #2) rather than mere title-exclusion.
    expect($html)->toContain('UAMS Health & Wellness');
    expect($html)->not->toContain('UAMS Health &amp; Wellness');
    // The untrusted title is ignored on this branch.
    expect($html)->not->toContain('<img');
    expect($html)->not->toContain('onerror');
});

it('escapes an attribute-breakout title in the ancestor-link title attribute', function (): void {
    // `breadcrumbs_ancestor && $breadcrumbs_count == 1` renders the title="" attribute.
    $html = renderBreadcrumbPartial(
        '{{ partial:layout/breadcrumbs/item-link }}',
        [
            'breadcrumbs_ancestor' => true,
            'breadcrumbs_count' => 1,
            'breadcrumbs_count_current_site' => 2,
            'url' => '/foo',
            'title' => '"><img src=x onerror=alert(document.cookie)>',
        ],
    );

    expect($html)->not->toContain('"><img'); // attribute is not broken out of
    expect($html)->not->toContain('<img');
    expect($html)->not->toContain('onerror');
    expect($html)->toContain('href="/foo"'); // link structure intact
});

it('renders a benign ancestor link with an entity-encoded title attribute', function (): void {
    $html = renderBreadcrumbPartial(
        '{{ partial:layout/breadcrumbs/item-link }}',
        [
            'breadcrumbs_ancestor' => true,
            'breadcrumbs_count' => 1,
            'breadcrumbs_count_current_site' => 2,
            'url' => '/cardiology',
            'title' => 'Cardiology & Vascular',
        ],
    );

    expect($html)->toContain('href="/cardiology"');
    expect($html)->toContain('Cardiology &amp; Vascular'); // ampersand entity-encoded
    expect($html)->not->toContain('Cardiology & Vascular'); // raw ampersand gone
});
