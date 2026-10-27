<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\PendingAwaitablePage;
use Statamic\Facades\Antlers;

/*
 | Behavioral coverage for the NON-form Alpine controls (UAMS-Web/uams-statamic-archive#2064), the follow-up to
 | UAMS-Web/uams-statamic-archive#2060 which fixed this class on public forms only.
 |
 | What these add, and it is narrower than "test the components": the component
 | LOGIC is already covered in jsdom by resources/js/{gallery-lightbox,lite-video,
 | fad-filter}.test.js. What no test reaches is the BINDING — whether the CSP build
 | actually wires `@click="show($el)"` on a real page to the registered component.
 |
 | That is the gap UAMS-Web/uams-statamic-archive#2056 fell through: the replicator's `addSet('<type>')` was a
 | correct expression the CSP parser rejected, producing NO console error and no
 | visual difference, so a jsdom unit test and a markup-presence assertion both
 | passed while the control did nothing.
 |
 | So each test here clicks the real control rendered from the real partial, then
 | reads the component's own state. The pre-existing presence assertions in
 | GallerySetTest / LiteVideoFacadeTest / FadProvidersBlockTest stay: they pin the
 | markup SHAPE, and these pin that the shape binds. Neither is sufficient alone.
 |
 | @command vendor/bin/pest tests/Browser/NonFormAlpineControlsTest.php
 */

/** The fixture's public path, fixed so afterEach can delete it. */
function nonFormControlsFixturePath(): string
{
    return public_path('__non_form_alpine_controls_fixture.html');
}

/**
 * Write a fixture page carrying the given rendered markup plus the built site
 * bundle, and return its public path.
 */
function writeNonFormControlsFixture(string $bodyHtml): string
{
    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

    $fixture = nonFormControlsFixturePath();

    File::put($fixture, implode('', [
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
        '<title>Non-form Alpine controls fixture</title>',
        '<script defer src="/vendor/statamic-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
        '</head><body><h1>Non-form Alpine controls fixture</h1>',
        $bodyHtml,
        '</body></html>',
    ]));

    return $fixture;
}

/**
 * Render one of the repo's real partials, so the markup under test is the shipped markup.
 *
 * @param  array<string, mixed>  $data
 */
function renderPartial(string $relativeView, array $data): string
{
    return (string) Antlers::parse(File::get(resource_path('views/'.$relativeView)), $data);
}

/** Read a JSON blob of component state from the page. */
function readComponentState(
    ArrayablePendingAwaitablePage|PendingAwaitablePage $page,
    string $selector,
    string $expression
): string {
    return mustBeString($page->script(
        '(() => { const el = document.querySelector('.json_encode($selector).');'
        .' if (!el) { return "no-element"; }'
        .' const d = window.Alpine.$data(el);'
        .' if (!d) { return "no-component"; }'
        .' return String('.$expression.'); })()'
    ), 'the component state');
}

afterEach(function (): void {
    if (File::exists($fixture = nonFormControlsFixturePath())) {
        File::delete($fixture);
    }
});

it('opens the gallery lightbox at the clicked image', function (): void {
    $markup = renderPartial('sets/_gallery.antlers.html', [
        'images' => [
            ['url' => '/img/one.jpg', 'alt' => 'Nyota Uhura', 'caption' => 'First'],
            ['url' => '/img/two.jpg', 'alt' => 'Montgomery Scott', 'caption' => 'Second'],
            ['url' => '/img/three.jpg', 'alt' => 'Hikaru Sulu', 'caption' => 'Third'],
        ],
    ]);

    // The partial must actually have rendered its triggers, or the clicks below
    // would pass vacuously against an empty page.
    expect($markup)->toContain('x-data="galleryLightbox"')
        ->toContain('data-gallery-item');

    writeNonFormControlsFixture($markup);
    $page = visit('/__non_form_alpine_controls_fixture.html');

    $state = fn (string $expr): string => readComponentState($page, '[x-data="galleryLightbox"]', $expr);

    // Closed on load, and the component collected the three triggers.
    expect($state('d.open'))->toBe('false');
    expect($state('d.images.length'))->toBe('3');

    // ---
    // REGRESSION GUARD for UAMS-Web/uams-statamic-archive#2246, which this test previously pinned as a defect.
    //
    // Clicking a trigger must open the lightbox at that image. It did not, and the
    // cause was NOT the handler binding and NOT the `$el` argument — both work.
    // `show()` was called, with the correct button. It returned early.
    //
    // Under `@alpinejs/csp` a method invoked from a directive expression sees `$el`
    // as the element carrying that directive. `triggers()` queried
    // `this.$el.querySelectorAll(...)`, so from a `@click` on a button it searched
    // INSIDE THE BUTTON, found no `[data-gallery-item]`, and `indexOf(trigger)`
    // returned `-1` — the silent guard at the top of `show()`. The fix is `$root`.
    //
    // Measured inside the invoked method, before the fix:
    //     show called · arg = the clicked button · images 2
    //     $el === component root  FALSE      triggers().length  0      indexOf  -1
    // and from OUTSIDE the component at the same moment:
    //     $el === component root  TRUE       triggers().length  2      indexOf  0
    //
    // That second row is why the defect survived so long: every probe that reads
    // the component from outside sees a healthy component, because there `$el` IS
    // the root. Only a reading taken INSIDE the call shows it.
    //
    // `next()` was unaffected throughout and is asserted below as the control — not
    // because its argument shape differs, but because it never touches `$el`.
    // ---
    $page->script('(() => { const t = document.querySelectorAll("[data-gallery-item]");'
        .' if (t[1]) { t[1].click(); } return 1; })()');

    expect($state('d.open'))->toBe('true');
    expect($state('d.current'))->toBe('1');

    // The direct call keeps working, which separates a dead BINDING from a broken
    // COMPONENT if this ever regresses: if the click arm above fails while this
    // passes, the handler is the suspect; if both fail, the component is.
    $page->script('(() => { const root = document.querySelector(\'[x-data="galleryLightbox"]\');'
        .' const d = window.Alpine.$data(root);'
        .' const t = root.querySelectorAll("[data-gallery-item]");'
        .' d.show(t[2]); return 1; })()');

    expect($state('d.current'))->toBe('2');

    // ---
    // The zero-argument control. `next()` bound throughout, including while the
    // trigger was dead — and the reason is NOT its argument shape. It never reads
    // `$el`, so the `triggers()` fault above could not reach it.
    //
    // An earlier revision of this comment concluded from these same two facts that
    // "the ARGUMENT is the difference" and named the CSP parser's grammar as the
    // cause. That was refuted twice: the parser evaluates `show($el)` correctly in
    // isolation, calling `show` with the button, and instrumenting the live call
    // showed `show` running with the right argument and returning early. Two
    // handlers differing in argument shape AND in `$el` use cannot separate them;
    // the discriminator this block was built on could not do the job asked of it.
    // ---
    $page->script('(() => { const b = document.querySelector(".gallery-lightbox__next");'
        .' if (b) { b.click(); } return 1; })()');

    // The direct call above left `current` at 2, and `next()` wraps 2 -> 0 across the
    // three images. ANY movement proves the handler fired; the specific value pins the
    // wrap. This assertion held while the trigger was broken and holds now, which is
    // what makes it the control rather than a second copy of the arm under test.
    expect($state('d.current'))->toBe('0');
});

it('gives the lite video player a working src on activation', function (): void {
    $markup = renderPartial('sets/_video.antlers.html', [
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Enterprise briefing',
    ]);

    expect($markup)->toContain('x-data="liteVideo"')
        ->toContain('@click="load()"');

    // ---
    // The src is rendered by the partial, not bound by Alpine (UAMS-Web/uams-statamic-archive#2245). Under
    // `@alpinejs/csp` Alpine cannot touch an iframe by ANY route: `evaluator.js`
    // refuses to evaluate an expression ON one, and `parser.js` refuses to let an
    // expression access a value that IS one. That second guard is why replacing
    // `:src` with `$refs.player.src` and then with a DOM query both failed - the
    // route changed and the prohibition did not.
    //
    // So the assertion below is on the ATTRIBUTE, not on a component property. A
    // getter can compute the right url while the element receives nothing, which
    // is exactly the shape this suite exists to catch.
    // ---
    expect($markup)->not->toContain(':src="iframeSrc"')
        ->not->toContain('x-ref="player"');

    writeNonFormControlsFixture($markup);
    $page = visit('/__non_form_alpine_controls_fixture.html');

    $state = fn (string $expr): string => readComponentState($page, '[x-data="liteVideo"]', $expr);

    expect($state('d.loaded'))->toBe('false');

    // Before activation the facade holds: no player element exists at all, which is
    // the point of the facade and is asserted so a regression to an always-present
    // iframe cannot pass here.
    $before = mustBeString($page->script(
        '(() => document.querySelectorAll("[x-data=liteVideo] iframe").length ? "present" : "absent")()'
    ), 'the pre-activation player count');

    expect($before)->toBe('absent');

    $page->script('(() => { const b = document.querySelector("[x-data=liteVideo] button"); if (b) { b.click(); } return 1; })()');

    expect($state('d.loaded'))->toBe('true');

    $srcAttr = mustBeString($page->script(
        '(() => { const f = document.querySelector("[x-data=liteVideo] iframe"); return f ? (f.getAttribute("src") || "(empty)") : "no-iframe"; })()'
    ), 'the iframe src attribute');

    expect($srcAttr)->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1');

    // Focus moves to the player. `load()` does this with plain DOM work inside a
    // component method rather than an Alpine expression, which is what keeps it
    // clear of the iframe guards above.
    $focused = mustBeString($page->script(
        '(() => { const a = document.activeElement;'
        .' return a ? (a.tagName.toLowerCase() + "." + (a.className || "")) : "none"; })()'
    ), 'the focused element');

    expect($focused)->toContain('iframe');
});

it('binds the find-a-doc filter reset control', function (): void {
    // `reset()` lives in the filter partial, which renders inside the `fadFilter`
    // scope declared by clinical_expertise/section.antlers.html. The wrapper is
    // reproduced here with the argument list that view's FIRST declaration passes
    // (line 45, the provider facets); everything inside it is the shipped partial.
    //
    // The six facets are copied from that line rather than shortened. An earlier
    // version passed two and claimed to pass "the same arguments that view passes"
    // (UAMS-Web/uams-statamic-archive#2281): harmless for `reset()`, and wrong for the class of defect this suite
    // exists to catch, since UAMS-Web/uams-statamic-archive#2064's whole premise is that expression SHAPE can fail
    // under `@alpinejs/csp` while the markup reads correctly. A fixture that does not
    // reproduce the shipped expression cannot rule that out.
    $filter = renderPartial('clinical_expertise/_section_filter.antlers.html', [
        'filter_noun_plural' => 'providers',
        // The search control is gated on `show_search`; a section that omits it
        // renders no search box and the engine is inert without one.
        'show_search' => true,
    ]);

    expect($filter)->toContain('@click="reset()"')
        ->toContain('x-model="search"');

    writeNonFormControlsFixture(
        '<div class="aoe-section-filterable"'
        ." x-data=\"fadFilter(['specialty', 'region', 'expertise', 'patient_types',"
        ." 'gender', 'language'], 'provider', 'providers')\">"
        .$filter
        .'</div>'
    );

    $page = visit('/__non_form_alpine_controls_fixture.html');

    $state = fn (string $expr): string => readComponentState($page, '.aoe-section-filterable', $expr);

    // Type into the search box so reset has something to clear.
    $page->script('(() => { const s = document.querySelector("[data-fad-search]");'
        .' if (s) { s.value = "Uhura";'
        .'   s.dispatchEvent(new Event("input", { bubbles: true })); } return 1; })()');

    expect($state('d.search'))->toBe('Uhura');

    $page->script('(() => { const b = Array.from(document.querySelectorAll("input[type=button]"))'
        .'.find(x => /reset/i.test(x.value));'
        .' if (b) { b.click(); } return 1; })()');

    expect($state('d.search'))->toBe('');

    assertNoAppJavaScriptErrors($page);
});

it('binds the find-a-doc filter reset control when fadFilter is declared bare', function (): void {
    // ---
    // Defect 2 of UAMS-Web/uams-statamic-archive#2281: `_uamsweb_block_fad_providers.antlers.html:73` declares
    // `<div x-data="fadFilter" data-fad-namespace="…">` — the component named with
    // NO invocation and no arguments. UAMS-Web/uams-statamic-archive#2064 listed that surface and nothing
    // exercised it, so the only covered shape was `fadFilter(...)`.
    //
    // The bare form is supported rather than suspect: `resources/js/fad-filter.js`
    // documents it as "the single-block default and what a bare
    // `<div x-data="fadFilter">` (no `data-fad-namespace`) produces". This asserts
    // that, so a change making the arguments mandatory fails here instead of
    // silently disabling every providers block.
    // ---
    $filter = renderPartial('clinical_expertise/_section_filter.antlers.html', [
        'filter_noun_plural' => 'providers',
        'show_search' => true,
    ]);

    writeNonFormControlsFixture(
        '<div class="aoe-section-filterable" x-data="fadFilter">'.$filter.'</div>'
    );

    $page = visit('/__non_form_alpine_controls_fixture.html');

    $state = fn (string $expr): string => readComponentState($page, '.aoe-section-filterable', $expr);

    $page->script('(() => { const s = document.querySelector("[data-fad-search]");'
        .' if (s) { s.value = "Chekov";'
        .'   s.dispatchEvent(new Event("input", { bubbles: true })); } return 1; })()');

    // Reaching component state at all proves the bare declaration produced a
    // component; one that was never invoked, and that Alpine could not resolve,
    // would leave nothing to read here.
    expect($state('d.search'))->toBe('Chekov');

    $page->script('(() => { const b = Array.from(document.querySelectorAll("input[type=button]"))'
        .'.find(x => /reset/i.test(x.value));'
        .' if (b) { b.click(); } return 1; })()');

    expect($state('d.search'))->toBe('');

    assertNoAppJavaScriptErrors($page);
});

it('binds each calculator variable handle to the calculator component itself', function (): void {
    // The shipped set, rendered with a two-variable calculator where one variable
    // splits. `calculator_entry` is the picked-entry loop the set opens with, so
    // the data below stands in for a published Calculator entry.
    //
    // Before UAMS-Web/uams-statamic-archive#2247 this markup carried an inline `x-data` object literal, which is
    // a CSP parse error -- so no component existed and `readComponentState` below
    // would return "no-component" rather than a value.
    $markup = renderPartial('sets/_calculator.antlers.html', [
        'calculator_entry' => [[
            'id' => 'calc-1',
            'published' => true,
            'parts' => [
                ['handle' => 'alpha', 'split' => [], 'last' => false],
                ['handle' => 'beta', 'split' => [['handle' => 'beta_low'], ['handle' => 'beta_high']], 'last' => true],
            ],
        ]],
    ]);

    // The seeded factory call, not an object literal. Asserted here because the
    // rest of this test is meaningless if the set stopped rendering the component.
    expect($markup)->toContain('x-data="calculator([');

    // The seed the set emits for this entry, asserted rather than assumed, so the
    // probe below cannot drift from the shipped interpolation.
    expect($markup)->toContain("x-data=\"calculator(['alpha', 'beta', 'beta_low', 'beta_high'])\"");

    // `partials/calculator` needs a full Calculator entry to render its inputs, so
    // the probe supplies two `x-model` inputs directly. The component and its seed
    // are the shipped ones; only the inputs are stand-ins.
    // Only the probe goes into the fixture. The rendered set is asserted above but
    // NOT written: it contains `{{ partial:partials/calculator }}`, which
    // `Antlers::parse` over raw file contents does not resolve, so writing it
    // would put unresolved markup ahead of the probe.
    writeNonFormControlsFixture(
        "<div id=\"probe\" x-data=\"calculator(['alpha', 'beta', 'beta_low', 'beta_high'])\" data-calculator-context>"
        .'<input data-probe-alpha x-model="alpha">'
        .'<input data-probe-beta-low x-model="beta_low">'
        .'</div>'
    );

    $page = visit('/__non_form_alpine_controls_fixture.html');

    $state = fn (string $expr): string => readComponentState($page, '#probe', $expr);

    // Every handle, including each split sub-handle, resolves on this component's
    // scope and starts empty.
    //
    // `in` rather than `Object.hasOwn`: Alpine's `$data()` hands back a scope
    // built on a prototype chain, so a property the factory declared is reachable
    // without being an OWN property of the returned object. `hasOwn` reads
    // `false` here even when the seeding worked, which is a fact about Alpine's
    // scope construction rather than about the component.
    expect($state('"alpha" in d'))->toBe('true')
        ->and($state('"beta" in d'))->toBe('true')
        ->and($state('"beta_low" in d'))->toBe('true')
        ->and($state('"beta_high" in d'))->toBe('true')
        ->and($state('d.alpha'))->toBe('')
        ->and($state('d.beta_high'))->toBe('');

    // Typing reaches this component's own state rather than an ancestor's.
    $page->script('(() => { const i = document.querySelector("[data-probe-alpha]");'
        .' i.value = "42"; i.dispatchEvent(new Event("input", { bubbles: true })); return 1; })()');

    expect($state('d.alpha'))->toBe('42');

    $page->script('(() => { const i = document.querySelector("[data-probe-beta-low]");'
        .' i.value = "7"; i.dispatchEvent(new Event("input", { bubbles: true })); return 1; })()');

    expect($state('d.beta_low'))->toBe('7')
        ->and($state('d.alpha'))->toBe('42');

    assertNoAppJavaScriptErrors($page);
});

it('binds the site-search type-ahead to the /search input, keyboard included', function (): void {
    // The shipped /search view, rendered with no query. `fetch` is stubbed on the page
    // before anything is typed, so the suggestions come from the stub rather than an
    // index: what is under test is the binding of siteSearchTypeahead's handlers and
    // ARIA attributes under the CSP build, which resources/js/site-search-typeahead.test.js
    // cannot reach from jsdom. Keys are REAL key presses, not synthetic events, because
    // a browser's own Escape on a search field clears it and only a real press shows it.
    $page = (string) view('search/index', ['get' => ['index' => 'people']])->render();

    expect($page)->toContain('x-data="siteSearchTypeahead"')
        ->toContain('data-index="people"');

    writeNonFormControlsFixture($page);

    $page = visit('/__non_form_alpine_controls_fixture.html');

    $page->script('(() => { window.__typeaheadRequests = [];'
        .' window.fetch = async (url) => { window.__typeaheadRequests.push(String(url));'
        .'   return new Response(JSON.stringify({ results: ['
        .'     { title: "Leonard McCoy", url: "/people/leonard-mccoy", type: "Person" },'
        .'     { title: "Cardiology", url: "/cardiology", type: "Area of Expertise" } ] }),'
        .'     { status: 200, headers: { "Content-Type": "application/json" } }); };'
        .' return 1; })()');

    $state = fn (string $expr): string => readComponentState($page, '.site-search-typeahead', $expr);
    $attr = fn (string $name): string => mustBeString($page->script(
        '(() => String(document.querySelector("#site-search-q").getAttribute('.json_encode($name).')))()'
    ), 'the input attribute');
    $value = fn (): string => mustBeString($page->script('(() => document.querySelector("#site-search-q").value)()'), 'the input value');

    expect($state('d.open'))->toBe('false')
        ->and($attr('aria-expanded'))->toBe('false');

    // Typing reaches the component through x-model and @input, and the debounced request
    // fires against the index of the page's active tab
    $page->type('#site-search-q', 'card');
    $page->wait(0.6);

    expect($page->script('(() => window.__typeaheadRequests.join(","))()'))->toBe('/!/search/suggestions?q=card&index=people')
        ->and($state('d.open'))->toBe('true')
        ->and($attr('aria-expanded'))->toBe('true')
        ->and($page->script('(() => document.querySelectorAll("#site-search-suggestions [role=option]").length)()'))->toBe(3)
        ->and($page->script('(() => getComputedStyle(document.querySelector("#site-search-suggestions")).display)()'))->not->toBe('none');

    // The arrow keys move the active option, reflected on the input for assistive technology
    $page->keys('#site-search-q', 'ArrowDown');
    expect($attr('aria-activedescendant'))->toBe('site-search-suggestions-option-0')
        ->and($page->script('(() => document.querySelector("#site-search-suggestions-option-0").getAttribute("aria-selected"))()'))->toBe('true');

    $page->keys('#site-search-q', 'ArrowUp');
    expect($attr('aria-activedescendant'))->toBe('site-search-suggestions-option-all');

    // A real Escape closes the list and KEEPS the query: the browser's own clearing of a
    // search field is prevented
    $page->keys('#site-search-q', 'Escape');
    expect($state('d.open'))->toBe('false')
        ->and($attr('aria-expanded'))->toBe('false')
        ->and($value())->toBe('card');

    // The list reopens on the current answer, then closes when focus leaves the widget
    $page->keys('#site-search-q', 'ArrowDown');
    expect($state('d.open'))->toBe('true');

    $page->keys('#site-search-q', 'Tab');
    expect($state('d.open'))->toBe('false');

    assertNoAppJavaScriptErrors($page);
});
