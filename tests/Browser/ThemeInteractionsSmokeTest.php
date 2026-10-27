<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 | Browser smoke for the framework-free UAMS 2020 theme runtime JS (UAMS-Web/uams-statamic-archive#1343). The
 | theme's compiled `uams-2020-theme-entry.js` bundle (jQuery / Popper /
 | smartmenus / `bootstrap.bundle.js` all removed) is loaded into a **static**
 | public HTML fixture carrying the markup its modules bind to — the desktop
 | dropdown nav, hero carousel, and gallery modal. Served
 | directly, it needs neither the `content/` submodule nor Statamic front-end
 | routing (the `browser` CI job omits both). A headless browser then drives each
 | behavior and asserts the resulting DOM, proving the bundle loads, initializes,
 | and wires every behavior end-to-end in a real browser with no console error.
 |
 | Scope, honestly: the fixture deliberately omits the heavy theme *CSS*, so
 | carousel/modal state swaps are instant (no transition to await) and the
 | assertions are deterministic. The per-module *logic* (open/close, keyboard,
 | wrap-around, focus trap) is covered exhaustively by the jsdom unit specs
 | (`resources/js/uams_2020/source/*.test.js`); this proves the compiled bundle
 | is wired and runs, which jsdom cannot. The desktop-nav "More" overflow needs
 | real layout and is exercised here via a deliberately narrow viewport.
 |
 | @command vendor/bin/pest tests/Browser/ThemeInteractionsSmokeTest.php
 */

$themeFixture = '';

beforeEach(function () use (&$themeFixture): void {
    /** @var array<string, array{file: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $themeJs = '/build/'.($manifest['resources/js/uams_2020/uams-2020-theme-entry.js']['file'] ?? '');

    // Minimal nav layout only: the theme CSS is omitted (see docblock), but the
    // nav needs a horizontal, wrappable row with its submenus taken out of flow,
    // or every `<li>` stacks vertically and `layoutOverflow`'s wrap-detection
    // collapses the whole bar into "More" at init — which would make the nav
    // assertions below inspect the injected "More" item, not the authored dropdown.
    $body = <<<'HTML'
        <style>
            #menu-dropdowns { display: flex; flex-wrap: wrap; list-style: none; margin: 0; padding: 0; }
            #menu-dropdowns > li { position: relative; flex: 0 0 auto; }
            #menu-dropdowns .dropdown-menu { display: none; position: absolute; top: 100%; left: 0; }
            #menu-dropdowns .dropdown-menu.show { display: block; }
        </style>
        <ul id="menu-dropdowns" class="nav navbar-nav">
            <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
            <li class="nav-item dropdown" id="services-item">
                <a class="nav-link" href="#services">Services</a>
                <ul class="dropdown-menu">
                    <li class="nav-item"><a class="nav-link" href="#s1">Cardiology</a></li>
                    <li class="nav-item"><a class="nav-link" href="#s2">Neurology</a></li>
                </ul>
            </li>
        </ul>

        <section class="carousel slide" data-keyboard="true" tabindex="0" aria-label="Hero banner">
            <ol class="carousel-indicators">
                <li data-slide-to="0" class="active"></li>
                <li data-slide-to="1"></li>
            </ol>
            <div class="carousel-inner">
                <div class="carousel-item active">Slide 1</div>
                <div class="carousel-item">Slide 2</div>
            </div>
            <a class="carousel-control-prev" data-slide="prev" href="#hero" role="button">Previous</a>
            <a class="carousel-control-next" data-slide="next" href="#hero" role="button">Next</a>
        </section>

        <a id="gallery-trigger" href="#" data-toggle="modal" data-target="#modal_1">Open image</a>
        <div class="modal fade" id="modal_1" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog"><div class="modal-content">
                <button id="modal-close" type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                <div class="modal-body">A larger image</div>
            </div></div>
        </div>
        HTML;

    $themeFixture = public_path('__theme_fixture.html');
    File::put(
        $themeFixture,
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Theme fixture</title>'
        .'<script type="module" src="'.$themeJs.'"></script></head>'
        .'<body>'.$body.'</body></html>'
    );
});

afterEach(function () use (&$themeFixture): void {
    if ($themeFixture !== '' && File::exists($themeFixture)) {
        File::delete($themeFixture);
    }
});

it('loads the theme bundle and wires every behavior in a real browser', function (): void {
    $page = visit('/__theme_fixture.html');
    $page->assertSee('Services');

    // The desktop-nav module ran: it enhanced the authored "Services" dropdown
    // with a caret and ARIA at runtime (as smartmenus did), proving the bundle
    // initialized. Target #services-item explicitly so the assertion cannot drift
    // onto the injected "More" item.
    /** @var array<string, mixed> $enhanced */
    $enhanced = $page->script('() => {
        const link = document.querySelector("#services-item > a.nav-link");
        return {
            hasSubArrow: !!link.querySelector(".sub-arrow"),
            haspopup: link.getAttribute("aria-haspopup"),
            expanded: link.getAttribute("aria-expanded"),
        };
    }');
    expect($enhanced['hasSubArrow'])->toBeTrue();
    expect($enhanced['haspopup'])->toBe('true');
    expect($enhanced['expanded'])->toBe('false');

    // Keyboard opens the Services dropdown (ArrowDown) and focus lands on its
    // first item (Cardiology).
    /** @var array<string, mixed> $navOpen */
    $navOpen = $page->script('() => {
        const link = document.querySelector("#services-item > a.nav-link");
        link.focus();
        link.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowDown", bubbles: true }));
        const menu = document.querySelector("#services-item > .dropdown-menu");
        return {
            shown: menu.classList.contains("show"),
            expanded: link.getAttribute("aria-expanded"),
            focusedFirst: document.activeElement === menu.querySelector(".nav-link"),
            focusedText: document.activeElement ? document.activeElement.textContent : null,
        };
    }');
    expect($navOpen['shown'])->toBeTrue();
    expect($navOpen['expanded'])->toBe('true');
    expect($navOpen['focusedFirst'])->toBeTrue();
    expect($navOpen['focusedText'])->toBe('Cardiology');

    // The hero carousel advances from its "next" control and syncs the indicator.
    /** @var array<string, mixed> $carousel */
    $carousel = $page->script('() => {
        document.querySelector("[data-slide=\"next\"]").click();
        const items = Array.from(document.querySelectorAll(".carousel-item"));
        return {
            active: items.findIndex((i) => i.classList.contains("active")),
            indicator: document.querySelector("[data-slide-to=\"1\"]").classList.contains("active"),
        };
    }');
    expect($carousel['active'])->toBe(1);
    expect($carousel['indicator'])->toBeTrue();

    // The gallery modal opens from its data-API trigger and closes from the dismiss button.
    /** @var array<string, mixed> $modal */
    $modal = $page->script('() => {
        const result = {};
        document.getElementById("gallery-trigger").click();
        const modal = document.getElementById("modal_1");
        result.openShown = modal.classList.contains("show");
        result.openDisplayed = modal.style.display === "block";
        result.backdrop = !!document.querySelector(".modal-backdrop");
        document.getElementById("modal-close").click();
        result.closedHidden = !modal.classList.contains("show");
        result.backdropGone = !document.querySelector(".modal-backdrop");
        return result;
    }');
    expect($modal['openShown'])->toBeTrue();
    expect($modal['openDisplayed'])->toBeTrue();
    expect($modal['backdrop'])->toBeTrue();
    expect($modal['closedHidden'])->toBeTrue();
    expect($modal['backdropGone'])->toBeTrue();

    assertNoAppJavaScriptErrors($page);
});

it('collapses overflowing nav items into a "More" dropdown only when the row is too narrow', function (): void {
    $page = visit('/__theme_fixture.html');
    $page->assertSee('Services');

    // The nav fits on one horizontal row at the default (wide) viewport, so no
    // "More" item exists. Then force the row far narrower than its items (fixed
    // widths so they genuinely wrap) and let the debounced resize handler
    // recompute — the trailing items must fold into "More". Asserting BOTH states
    // proves the collapse is width-responsive, not trivially always-on.
    /** @var array<string, mixed> $overflow */
    $overflow = $page->script('async () => {
        const root = document.getElementById("menu-dropdowns");
        const wideHasMore = !!root.querySelector(".nav-more");

        root.style.width = "120px";
        root.querySelectorAll(":scope > li:not(.nav-more)").forEach((li) => {
            li.style.width = "100px";
        });
        window.dispatchEvent(new Event("resize"));
        await new Promise((r) => setTimeout(r, 300));

        const more = root.querySelector(".nav-more");
        // The "More" control must be keyboard-operable via its Enter activation key
        // (role="button"), not hover-only.
        let moreOpensOnEnter = false;
        if (more) {
            const moreLink = more.querySelector("a.nav-link");
            moreLink.focus();
            moreLink.dispatchEvent(new KeyboardEvent("keydown", { key: "Enter", bubbles: true }));
            moreOpensOnEnter = more.querySelector(".dropdown-menu").classList.contains("show");
        }

        return {
            wideHasMore,
            narrowHasMore: !!more,
            moreChildren: more ? more.querySelectorAll(".dropdown-menu > li").length : 0,
            moreOpensOnEnter,
        };
    }');

    expect($overflow['wideHasMore'])->toBeFalse();
    expect($overflow['narrowHasMore'])->toBeTrue();
    expect($overflow['moreChildren'])->toBeGreaterThan(0);
    expect($overflow['moreOpensOnEnter'])->toBeTrue();

    assertNoAppJavaScriptErrors($page);
});
