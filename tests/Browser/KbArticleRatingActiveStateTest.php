<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 | Browser smoke for the knowledge-base article-rating active state (UAMS-Web/uams-statamic-archive#1518). The
 | "Was This Article Helpful?" like / dislike triggers reflect the visitor's
 | current vote through `aria-pressed`, which `resources/js/kb-article-engagement.js`
 | keeps in sync from the engagement snapshot on load and after every vote. This
 | proves the *compiled* theme CSS turns that `aria-pressed="true"` into a visible
 | pressed state — a jsdom unit spec cannot, because it never applies CSS, so a
 | deleted rule would stay green there (the false-green class this guards against).
 |
 | The reactions markup — as `_interaction_statistic.antlers.html` renders it — is
 | loaded into a static public HTML fixture linking the compiled
 | `uams_2020/app.scss` bundle, so it needs neither the `content/` submodule nor
 | Statamic front-end routing (the `browser` CI job omits both). A headless browser
 | then reads the computed style at rest vs. pressed and asserts the state is
 | conveyed by more than color (WCAG 1.4.1): the label gains weight and the card
 | gains a ring + tint only when pressed.
 |
 | @command vendor/bin/pest tests/Browser/KbArticleRatingActiveStateTest.php
 */

$fixture = '';

beforeEach(function () use (&$fixture): void {
    /** @var array<string, array{file: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $cssFile = $manifest['resources/scss/bootstrap/uams_2020/app.scss']['file'] ?? null;

    expect($cssFile)->not->toBeNull(
        'The uams_2020 app.scss bundle is missing from the Vite manifest — run `npm run build`.'
    );

    // The reactions block markup: an `#article-reactions` wrapper, a `.card-deck`
    // of like / dislike cards, each a `.card` with a thumb icon (`.card-img-top`)
    // and the trigger anchor carrying the `data-reaction` / `aria-pressed` hooks
    // the CSS keys off. Rendered at rest (`aria-pressed="false"`), as the server
    // ships it before the JS hydrates the visitor's vote.
    $body = <<<'HTML'
        <aside id="article-reactions">
            <div class="card-deck">
                <div class="card">
                    <div class="card-img-top"><span class="fa fa-thumbs-up" aria-hidden="true"></span></div>
                    <div class="card-body">
                        <h3 class="card-title h5 mb-0">
                            <a class="stretched-link" href="#" data-kb-reaction data-reaction="like" aria-pressed="false">Like This Article</a>
                        </h3>
                    </div>
                    <div class="card-footer"><span class="count">1 Like</span></div>
                </div>
                <div class="card">
                    <div class="card-img-top"><span class="fa fa-thumbs-down" aria-hidden="true"></span></div>
                    <div class="card-body">
                        <h3 class="card-title h5 mb-0">
                            <a class="stretched-link" href="#" data-kb-reaction data-reaction="dislike" aria-pressed="false">Dislike This Article</a>
                        </h3>
                    </div>
                    <div class="card-footer"><span class="count">0 Dislikes</span></div>
                </div>
            </div>
        </aside>
        HTML;

    // Neutralize the pressed-state transition so `getComputedStyle` reads the
    // final values synchronously after the aria-pressed flip — otherwise it
    // returns the mid-transition (still ~resting) color. The transition is purely
    // cosmetic; the assertions are about the settled state.
    $noTransition = '<style>#article-reactions .card, #article-reactions .card .card-img-top { transition: none !important; }</style>';

    $fixture = public_path('__kb_reactions_fixture.html');
    File::put(
        $fixture,
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>KB reactions fixture</title>'
        .'<link rel="stylesheet" href="/build/'.$cssFile.'">'.$noTransition.'</head>'
        .'<body>'.$body.'</body></html>'
    );
});

afterEach(function () use (&$fixture): void {
    if ($fixture !== '' && File::exists($fixture)) {
        File::delete($fixture);
    }
});

it('renders a visible, multi-channel pressed state for an active like', function (): void {
    $page = visit('/__kb_reactions_fixture.html');
    $page->assertSee('Like This Article');

    // Read the like trigger + its card at rest, flip aria-pressed to "true"
    // (exactly what kb-article-engagement.js does on a vote), and read again.
    /** @var array{rest: array{weight: string, labelColor: string, iconColor: string, cardShadow: string, cardBg: string}, pressed: array{weight: string, labelColor: string, iconColor: string, cardShadow: string, cardBg: string}} $state */
    $state = $page->script('() => {
        const like = document.querySelector("[data-reaction=\"like\"]");
        const card = like.closest(".card");
        const icon = card.querySelector(".card-img-top");
        const read = () => ({
            weight: getComputedStyle(like).fontWeight,
            labelColor: getComputedStyle(like).color,
            iconColor: getComputedStyle(icon).color,
            cardShadow: getComputedStyle(card).boxShadow,
            cardBg: getComputedStyle(card).backgroundColor,
        });
        const rest = read();
        like.setAttribute("aria-pressed", "true");
        const pressed = read();
        return { rest, pressed };
    }');

    // The label gains weight — a non-color channel, so the state is not conveyed
    // by color alone (WCAG 1.4.1). ($headings-font-weight 500 -> bold 600.)
    expect((int) $state['pressed']['weight'])->toBeGreaterThan((int) $state['rest']['weight']);

    // The card gains an inset ring (structural) and a tint it lacked at rest —
    // proving the styling is conditional on aria-pressed, not always-on.
    expect($state['pressed']['cardShadow'])->not->toBe('none');
    expect($state['pressed']['cardShadow'])->not->toBe($state['rest']['cardShadow']);
    expect($state['pressed']['cardBg'])->not->toBe($state['rest']['cardBg']);

    // The tint must be OPAQUE (`mix()`, not a translucent `rgba()`): the block
    // renders on a bg-white OR bg-gray band, and a translucent fill would let the
    // gray show through and drop the label below the AA 4.5:1 bar. getComputedStyle
    // serializes an opaque color as `rgb(...)` and a translucent one as `rgba(...)`.
    expect($state['pressed']['cardBg'])->toStartWith('rgb(');

    // The brand success green (#1b7d3d) reaches the label and the icon.
    expect($state['pressed']['labelColor'])->toBe('rgb(27, 125, 61)');
    expect($state['pressed']['iconColor'])->toBe('rgb(27, 125, 61)');
});

it('uses the brand red for an active dislike, distinct from the resting state', function (): void {
    $page = visit('/__kb_reactions_fixture.html');

    /** @var array{restColor: string, restWeight: string, pressedColor: string, pressedWeight: string, cardShadow: string} $state */
    $state = $page->script('() => {
        const dislike = document.querySelector("[data-reaction=\"dislike\"]");
        const card = dislike.closest(".card");
        const restColor = getComputedStyle(dislike).color;
        const restWeight = getComputedStyle(dislike).fontWeight;
        dislike.setAttribute("aria-pressed", "true");
        return {
            restColor,
            restWeight,
            pressedColor: getComputedStyle(dislike).color,
            pressedWeight: getComputedStyle(dislike).fontWeight,
            cardShadow: getComputedStyle(card).boxShadow,
        };
    }');

    // Brand red (#c61029), distinct from the resting label color, plus the same
    // non-color weight increase and card ring the like path uses.
    expect($state['pressedColor'])->toBe('rgb(198, 16, 41)');
    expect($state['pressedColor'])->not->toBe($state['restColor']);
    expect((int) $state['pressedWeight'])->toBeGreaterThan((int) $state['restWeight']);
    expect($state['cardShadow'])->not->toBe('none');
});
