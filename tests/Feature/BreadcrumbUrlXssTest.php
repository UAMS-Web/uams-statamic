<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Defense-in-depth regression test for the breadcrumb link `url` sink (UAMS-Web/uams-statamic-archive#1500).
 *
 * `_item-link.antlers.html` injected the crumb's entry `url` raw into
 * `href="{{ url }}"` and `itemid="{{ url }}"`. UAMS-Web/uams-statamic-archive#1485 escaped the sibling *title*
 * sink in the same partial but deliberately left `url` alone (URLs derive from
 * `Str::slug` today, so no value is attacker-controllable yet). This closes that
 * latent gap: the `url` now passes through `sanitize_url`, which neutralizes a
 * dangerous scheme (collapsing it to an inert `#`) and HTML-encodes the result for
 * the attribute — so the moment a `redirect`/`link` fieldtype or any non-slug path
 * lets a hostile value reach `url()`, the sink is already closed.
 *
 * The scheme logic lives in the mutation-tested `App\Modifiers\SanitizeUrl`; this
 * test pins the wiring at the render sink, including the entity-obfuscated scheme
 * class the UAMS-Web/uams-statamic-archive#1500 adversarial review found bypassing the first cut. Every cascade
 * control variable is supplied per call via the render data, keeping the tests
 * hermetic against Antlers' process-global runtime.
 *
 * @command  php artisan test --compact tests/Feature/BreadcrumbUrlXssTest.php
 */
it('neutralizes a javascript: url in the breadcrumb href and itemid', function (): void {
    $html = (string) Antlers::parse(
        '{{ partial:layout/breadcrumbs/item-link }}',
        [
            'breadcrumbs_count' => 2,
            'breadcrumbs_count_current_site' => 2,
            'url' => 'javascript:alert(document.cookie)',
            'title' => 'Cardiology',
        ],
        true,
    );

    expect($html)->not->toContain('javascript'); // scheme never renders
    expect($html)->toContain('href="#"');         // collapsed to the inert fallback
    expect($html)->toContain('itemid="#"');
    expect($html)->toContain('Cardiology');       // crumb still renders its label
});

it('neutralizes an HTML-entity-obfuscated javascript scheme (the review-found bypass class)', function (): void {
    // `javascript&#58;…` has no literal colon after the scheme letters, so a naive
    // guard passes it and a `double_encode: false` encoder preserves the `&#58;` —
    // the browser then decodes it to `:` and executes. sanitize_url resolves the
    // browser's value first, so it must collapse to an inert `#`.
    $html = (string) Antlers::parse(
        '{{ partial:layout/breadcrumbs/item-link }}',
        [
            'breadcrumbs_count' => 2,
            'breadcrumbs_count_current_site' => 2,
            'url' => 'javascript&#58;alert(document.cookie)',
            'title' => 'Cardiology',
        ],
        true,
    );

    // The browser's single decode of the emitted href must not resolve to a scheme.
    expect(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->not->toContain('javascript:');
    expect($html)->toContain('href="#"');
    expect($html)->toContain('itemid="#"');
});

it('neutralizes a data: url in the breadcrumb href', function (): void {
    $html = (string) Antlers::parse(
        '{{ partial:layout/breadcrumbs/item-link }}',
        [
            'breadcrumbs_count' => 2,
            'breadcrumbs_count_current_site' => 2,
            'url' => 'data:text/html,<script>alert(1)</script>',
            'title' => 'Cardiology',
        ],
        true,
    );

    expect($html)->not->toContain('data:text/html');
    expect($html)->not->toContain('<script>');
    expect($html)->toContain('href="#"');
    expect($html)->toContain('itemid="#"');
});

it('cannot be broken out of the href/itemid attribute by a double-quote in the url', function (): void {
    $html = (string) Antlers::parse(
        '{{ partial:layout/breadcrumbs/item-link }}',
        [
            'breadcrumbs_count' => 2,
            'breadcrumbs_count_current_site' => 2,
            'url' => '/x" onmouseover="alert(document.cookie)',
            'title' => 'Cardiology',
        ],
        true,
    );

    // The quote is entity-encoded, so no active event-handler attribute escapes.
    expect($html)->not->toContain('" onmouseover="');
    expect($html)->not->toContain('onmouseover="alert');
    expect($html)->toContain('&quot;');
});

it('renders a benign slug-derived url untouched in href and itemid', function (): void {
    $html = (string) Antlers::parse(
        '{{ partial:layout/breadcrumbs/item-link }}',
        [
            'breadcrumbs_count' => 2,
            'breadcrumbs_count_current_site' => 2,
            'url' => '/cardiology/heart-vascular',
            'title' => 'Cardiology',
        ],
        true,
    );

    expect($html)->toContain('href="/cardiology/heart-vascular"');
    expect($html)->toContain('itemid="/cardiology/heart-vascular"');
    expect($html)->toContain('Cardiology');
});
