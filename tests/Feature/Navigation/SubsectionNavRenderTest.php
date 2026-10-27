<?php

declare(strict_types=1);

use Statamic\Facades\Collection;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * End-to-end render coverage for subsection navigation (UAMS-Web/uams-statamic-archive#52).
 *
 * Exercises the real layout — the brand sub-brand and the primary-nav scoping
 * in `_brand.antlers.html` / `desktop.antlers.html` via the `{{ subsection }}`
 * tag. `withoutVite()` skips the built-asset manifest so the layout renders in
 * CI without an `npm run build` step. Content-gated on the `pages` collection.
 *
 * `about` is flagged as a subsection root; its descendants render with the
 * primary nav scoped to its branch. This is the case the unit tests cannot
 * reach — it caught a real bug where a `:id` tag param broke the whole render.
 *
 * @command  php artisan test --compact tests/Feature/Navigation/SubsectionNavRenderTest.php
 */

/**
 * Skip unless the `pages` collection is present (content submodule).
 */
function subsectionNavRequirePages(): void
{
    if (! Collection::find('pages')) {
        ContentSubmodule::reportMissing('pages collection');
    }
}

/**
 * Extract the desktop primary nav (`#site-nav-primary`) region from a page.
 */
function subsectionDesktopNav(string $html): string
{
    $start = strpos($html, 'id="site-nav-primary"');

    return $start === false ? '' : substr($html, $start, (int) strpos($html, '</nav>', $start) - $start);
}

it('scopes the primary navigation to the subsection branch on a subsection page', function (): void {
    subsectionNavRequirePages();

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $content */
    $content = $test->get('/about')->assertOk()->getContent();
    $nav = subsectionDesktopNav($content);

    // `about` is a subsection root, so the nav scopes to its page-tree branch:
    // a CHILD of `about` is present...
    expect($nav)->toContain('Vision, Mission and Core Values');
    // ...and a top-level SIBLING of `about` ("Educational Programs") is
    // ABSENT. The sibling only appears in the un-scoped full-tree nav, so its
    // absence is what actually proves the `from` scoping happened — without
    // it the assertion would pass even with subsection scoping disabled.
    expect($nav)->not->toContain('Educational Programs');
});

it('renders the unscoped site primary navigation on a page outside any subsection', function (): void {
    subsectionNavRequirePages();

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $content */
    $content = $test->get('/')->assertOk()->getContent();
    $nav = subsectionDesktopNav($content);

    // The home page is not within a subsection → the full primary nav, so the
    // top-level sibling that the subsection page hides IS present here.
    expect($nav)->toContain('Educational Programs');
});
