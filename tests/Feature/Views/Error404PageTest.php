<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Read one of the error views' raw source.
 *
 * @param  string  $name  The view's filename under `resources/views/errors/`
 * @return string The view source
 */
function errorViewSource(string $name): string
{
    return File::get(resource_path('views/errors/'.$name));
}

/**
 * Coverage for the improved 404 page (UAMS-Web/uams-statamic-archive#629): the redirect-first behavior is
 * preserved, and the failure page gains an accessible heading, an inline search box
 * posting to the shared `/search` page, and a nav-sourced popular-links list.
 *
 * The wiring + redirect ordering are asserted content-free against the view file;
 * the end-to-end render is gated on the content/ submodule (the full layout needs
 * the pages tree + nav structure).
 *
 * The two recovery aids moved into `errors/_recovery_aids.antlers.html` in UAMS-Web/uams-statamic-archive#1942,
 * so the targetless error pages (403/410/451) could carry the same wayfinding
 * instead of duplicating ~50 lines of it three more times. UAMS-Web/uams-statamic-archive#629's guarantee is
 * unchanged and is now asserted in two halves — the partial holds the markup, and
 * the 404 includes the partial. Asserting only the first half would pass on an
 * orphaned partial that no page renders.
 *
 * @command  php artisan test --compact tests/Feature/Views/Error404PageTest.php
 */
it('keeps the redirect partials first, before the new wayfinding markup', function (): void {
    $view = File::get(resource_path('views/errors/404.antlers.html'));

    // Both redirect partials are still invoked (redirect global behavior preserved).
    expect($view)
        ->toContain('partial:errors/redirects')
        ->toContain('partial:errors/knowledge_base_mount_redirect');

    // They run before any of the new visible markup, so a matching redirect fires
    // before the 404 body ever renders.
    $redirectPos = strpos($view, 'partial:errors/redirects');
    $mountRedirectPos = strpos($view, 'partial:errors/knowledge_base_mount_redirect');
    /** @var int<0, max> $headingPos */
    $headingPos = strpos($view, '<h1>');
    // The search form itself now lives in `errors/_recovery_aids`, so the
    // ordering guarantee is against the point the 404 pulls it in.
    /** @var int<0, max> $formPos */
    $formPos = strpos($view, 'partial:errors/recovery_aids');

    expect($redirectPos)->toBeLessThan($headingPos)
        ->and($mountRedirectPos)->toBeLessThan($headingPos)
        ->and($redirectPos)->toBeLessThan($formPos)
        ->and($mountRedirectPos)->toBeLessThan($formPos);
});

it('adds an accessible heading and an inline search box posting to /search', function (): void {
    // A single page heading (the page-header is inert without title_options),
    // and the 404 actually pulls in the aids — without this half, the assertions
    // below would pass on a partial no page renders.
    expect(errorViewSource('404.antlers.html'))
        ->toContain('<h1>')
        ->toContain('partial:errors/recovery_aids');

    expect(errorViewSource('_recovery_aids.antlers.html'))
        // Section headings for the two recovery aids.
        ->toContain('id="error-search-heading"')
        ->toContain('id="error-links-heading"')
        // Inline search form posts to this site's search page (GET).
        ->toContain('action="{{ site_search_scope:path }}"')
        ->toContain('name="q"')
        ->toContain('role="search"')
        // Focus management: the caret lands in the search field.
        ->toContain('autofocus')
        // A real label, not just a placeholder.
        ->toContain('for="error-search-q"');
});

it('sources popular links from the primary navigation, not a hard-coded list', function (): void {
    $view = errorViewSource('_recovery_aids.antlers.html');

    expect($view)
        // Mirrors the header's global nav-handle resolution (manual primary nav else
        // the pages tree) so the list never drifts from a maintained one.
        ->toContain('uams_multisite_settings:manual_primary_navigation')
        ->toContain('$navigation_main_handle')
        ->toContain('{{ nav :handle="$navigation_main_handle"')
        // Excludes menu-hidden / password-protected / private entries like the primary nav.
        ->toContain('hide_from_menu:isnt="true"')
        ->toContain('password_protected:isnt="true"');

    // Deliberately omits the AoE/subsection scoping that fatals on a routeless 404.
    expect($view)->not->toContain('is_within_aoe');
    expect($view)->not->toContain('{{ subsection }}');
});

it('gives every targetless error page a terminal breadcrumb crumb, not an ancestor', function (): void {
    // The three pages UAMS-Web/uams-statamic-archive#1942 adds are as routeless as the 404 — no entry, no
    // nav-tree node — so `nav:breadcrumbs` yields nothing for them. Before this,
    // only `404` took the error arm, and the other three fell to the `else` branch
    // whose trail ends on an ANCESTOR: it tells the visitor they are standing on a
    // page they are not, and emits a `BreadcrumbList` that terminates there too.
    //
    // Pinned against the view SOURCE rather than a render, for the same reason
    // `TargetlessErrorPageTest` documents at length: in-process, Statamic's
    // `Cascade` singleton resolves `current_uri` to `/`, so `is_homepage` is true
    // and `$breadcrumbs_is_high_level_homepage` suppresses the entire breadcrumb
    // nav. A rendered assertion here would pass on markup that never appears.
    $view = File::get(resource_path('views/layout/breadcrumbs/_breadcrumbs.antlers.html'));

    expect($view)
        // The gate covers all four codes the app can now render.
        ->toContain('$breadcrumbs_is_error')
        ->toContain('response_code == 404')
        ->toContain('response_code == 403')
        ->toContain('response_code == 410')
        ->toContain('response_code == 451')
        // The leaf is driven by the code, and still defaults to the 404's wording.
        ->toContain('$breadcrumbs_error_leaf')
        ->toContain("'Access restricted'")
        ->toContain("'This page is gone'")
        ->toContain("'Unavailable for legal reasons'")
        ->toContain("'Page not found'")
        // ...and is emitted as the active, non-link terminal crumb.
        ->toContain('<span>{{ $breadcrumbs_error_leaf }}</span>');

    // The superseded single-code flag is gone, so a stale reference cannot linger.
    expect($view)->not->toContain('$breadcrumbs_is_404');
});

it('renders the improved 404 body end to end', function (): void {
    if (! Collection::findByHandle('pages')) {
        ContentSubmodule::reportMissing('the full layout render');
    }

    // A URL that resolves to no entry returns the rendered 404 view.
    /** @var TestCase $test */
    $test = $this->withoutVite();
    $html = $test->get('/this-page-does-not-exist-'.__LINE__)
        ->assertNotFound()
        ->getContent();

    expect($html)
        ->toContain('Page not found')
        ->toContain('action="/search"')
        ->toContain('id="error-search-q"')
        ->toContain('Popular pages');
});
