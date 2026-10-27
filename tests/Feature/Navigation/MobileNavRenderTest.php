<?php

declare(strict_types=1);

use Statamic\Facades\Collection;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Render coverage for the mobile primary navigation (UAMS-Web/uams-statamic-archive#53).
 *
 * Asserts the framework-free mobile nav region and its hamburger hook render in
 * the real layout, the desktop nav is breakpoint-gated to desktop-only, and the
 * mobile nav inherits the UAMS-Web/uams-statamic-archive#52 subsection scoping. `withoutVite()` skips the
 * asset manifest so it renders in CI; content-gated on the `pages` collection.
 *
 * @command  php artisan test --compact tests/Feature/Navigation/MobileNavRenderTest.php
 */

/**
 * Skip unless the `pages` collection is present (content submodule).
 */
function mobileNavRequirePages(): void
{
    if (! Collection::find('pages')) {
        ContentSubmodule::reportMissing('pages collection');
    }
}

it('renders the mobile nav region wired to the hamburger toggle', function (): void {
    mobileNavRequirePages();

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->get('/about')->assertOk()->getContent();

    expect($html)
        ->toContain('data-mobile-nav')
        ->toContain('id="mobile-nav-primary"')
        // The hamburger now drives the mobile nav (no Bootstrap collapse target).
        ->toContain('data-mobile-nav-toggler')
        ->and($html)->not->toContain('data-target="#site-nav-primary"');
});

it('gates the desktop nav to the desktop breakpoint', function (): void {
    mobileNavRequirePages();

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->get('/about')->assertOk()->getContent();

    // Desktop nav is hidden below the breakpoint; the mobile nav takes over there.
    expect($html)->toContain('site-nav navbar navbar-expand-sm d-none d-sm-flex');
});

it('scopes the mobile nav to the subsection branch on a subsection page', function (): void {
    mobileNavRequirePages();

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->get('/about')->assertOk()->getContent();

    // Isolate the mobile nav region (anchor on its class, not `data-mobile-nav`,
    // which also matches the header `data-mobile-nav-toggler` button).
    $start = strpos($html, 'class="site-nav-mobile');
    $mobileNav = $start === false ? '' : substr($html, $start, (int) strpos($html, '</nav>', $start) - $start);

    // `about` is a subsection root: a CHILD is present and a top-level SIBLING
    // ("Educational Programs") is ABSENT — the sibling's absence is what proves
    // the mobile nav actually scoped (it would be present in the un-scoped nav).
    expect($mobileNav)->toContain('Vision, Mission and Core Values')
        ->and($mobileNav)->not->toContain('Educational Programs');
});
