<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\YAML;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1173 — marketing-landing nav toggles (Option B: chrome-on by default, opt-in
 * hide toggles).
 *
 * On a marketing-landing entry an editor may hide the primary and/or resource
 * navigation for a distraction-free campaign layout. Both toggles default off,
 * so every other page type — and an untouched marketing-landing page — renders
 * the full chrome unchanged. The two toggles are independent: hiding only the
 * resource content must NOT remove the mobile primary-nav hamburger (its sole
 * toggler lives in the resource-nav bar), or the primary nav — kept on — would be
 * unreachable on mobile.
 *
 * The full layout/header pulls in globals, the `{{ nav }}` tag, and a Stache
 * warm that cannot render headlessly (the same constraint documented in
 * LayoutMarkupAttributesTest), so the visibility LOGIC is rendered from a single
 * self-contained template that MIRRORS the real computation + guard chain —
 * driven by the actual editor inputs (`title_options` + the toggle group). Every
 * mirrored expression is then source-guarded against the real templates, so a
 * divergence, a polarity flip, or a silent removal fails here.
 *
 * @command php artisan test --compact tests/Feature/Views/MarketingLandingNavTogglesTest.php
 */

/** Absolute path to a real template under this worktree's resources/views. */
function landingNavView(string $relative): string
{
    return __DIR__.'/../../../resources/views/'.$relative;
}

/**
 * Render the full marketing-landing chrome-suppression chain from a template that
 * mirrors, in order: the `$hide_*` flag computation (layout.antlers.html), the
 * resource-nav bar wrapping its content (gated on `$hide_resource_nav`) and the
 * mobile hamburger (gated separately on `$hide_primary_nav`) — the bar itself
 * rendering unless BOTH are hidden (_header + _resource-nav), the primary-nav
 * guard (_header), and the primary-nav skip-link guard (layout.antlers.html).
 * Sub-partials are replaced by sentinels; the guard expressions are byte-for-byte
 * the real ones.
 *
 * @param  array<string, mixed>  $data  Editor inputs: `title_options` + the
 *                                      `header_marketing_landing_page` group.
 * @return array<string, bool> Presence of each chrome region in the output.
 */
function renderLandingChrome(array $data): array
{
    $tpl = <<<'ANT'
{{ $is_marketing_landing = ( title_options == 'marketing-landing-page' ) }}
{{ $hide_primary_nav = ( $is_marketing_landing && header_marketing_landing_page:hide_primary_nav ) }}
{{ $hide_resource_nav = ( $is_marketing_landing && header_marketing_landing_page:hide_resource_nav ) }}
{{ unless ( $hide_resource_nav && $hide_primary_nav ) }}[RESBAR{{ unless $hide_resource_nav }}+CONTENT{{ /unless }}{{ unless $hide_primary_nav }}+HAMBURGER{{ /unless }}]{{ /unless }}
{{ unless $hide_primary_nav }}[PRIMARY]{{ /unless }}
{{ unless $hide_primary_nav }}[SKIP_PRIMARY]{{ /unless }}[SKIP_MAIN]
ANT;

    $out = (string) Antlers::parse($tpl, $data, true);

    return [
        'resource_bar' => str_contains($out, '[RESBAR'),
        'resource_content' => str_contains($out, '+CONTENT'),
        'hamburger' => str_contains($out, '+HAMBURGER'),
        'primary' => str_contains($out, '[PRIMARY]'),
        'skip_primary' => str_contains($out, '[SKIP_PRIMARY]'),
        'skip_main' => str_contains($out, '[SKIP_MAIN]'),
    ];
}

it('renders full chrome on a default marketing-landing page (no editor action)', function () {
    $chrome = renderLandingChrome([
        'title_options' => 'marketing-landing-page',
        'header_marketing_landing_page' => [],
    ]);

    // Identical to today: every region shows.
    expect($chrome)->toBe([
        'resource_bar' => true,
        'resource_content' => true,
        'hamburger' => true,
        'primary' => true,
        'skip_primary' => true,
        'skip_main' => true,
    ]);
});

it('leaves non-marketing-landing pages untouched even when the toggle data is set', function () {
    // A page whose header style is not marketing-landing carries no such fields,
    // but even if the group data leaked in, the `$is_marketing_landing` gate keeps
    // every region on — the toggles are out of scope off the landing surface.
    $chrome = renderLandingChrome([
        'title_options' => 'graphic',
        'header_marketing_landing_page' => ['hide_primary_nav' => true, 'hide_resource_nav' => true],
    ]);

    expect($chrome)->toBe([
        'resource_bar' => true,
        'resource_content' => true,
        'hamburger' => true,
        'primary' => true,
        'skip_primary' => true,
        'skip_main' => true,
    ]);
});

it('hides the primary nav (and its skip link + mobile hamburger) when hide_primary_nav is on', function () {
    $chrome = renderLandingChrome([
        'title_options' => 'marketing-landing-page',
        'header_marketing_landing_page' => ['hide_primary_nav' => true],
    ]);

    // Primary nav gone, along with its now-dangling controls...
    expect($chrome['primary'])->toBeFalse()
        ->and($chrome['skip_primary'])->toBeFalse()
        ->and($chrome['hamburger'])->toBeFalse()
        // ...but the resource bar and its content stay, and the main skip link is intact.
        ->and($chrome['resource_bar'])->toBeTrue()
        ->and($chrome['resource_content'])->toBeTrue()
        ->and($chrome['skip_main'])->toBeTrue();
});

it('hides the resource content but KEEPS the mobile hamburger and primary nav when hide_resource_nav is on', function () {
    $chrome = renderLandingChrome([
        'title_options' => 'marketing-landing-page',
        'header_marketing_landing_page' => ['hide_resource_nav' => true],
    ]);

    // Resource content (MyChart, utility links, search) gone...
    expect($chrome['resource_content'])->toBeFalse()
        // ...but the bar survives to carry the hamburger, which stays so the still-shown
        // primary nav remains reachable on mobile (the fix for the independent-toggle break).
        ->and($chrome['resource_bar'])->toBeTrue()
        ->and($chrome['hamburger'])->toBeTrue()
        ->and($chrome['primary'])->toBeTrue()
        ->and($chrome['skip_primary'])->toBeTrue();
});

it('drops the whole resource bar only when both regions are hidden', function () {
    $chrome = renderLandingChrome([
        'title_options' => 'marketing-landing-page',
        'header_marketing_landing_page' => ['hide_primary_nav' => true, 'hide_resource_nav' => true],
    ]);

    expect($chrome)->toBe([
        'resource_bar' => false,
        'resource_content' => false,
        'hamburger' => false,
        'primary' => false,
        'skip_primary' => false,
        'skip_main' => true,
    ]);
});

it('wires the flag computation and skip-link guard into the real layout.antlers.html', function () {
    /** @var string $layout */
    $layout = file_get_contents(landingNavView('layout.antlers.html'));

    // The three flag definitions the mirror above depends on (byte-for-byte).
    expect($layout)
        ->toContain("\$is_marketing_landing = ( title_options == 'marketing-landing-page' )")
        ->toContain('$hide_primary_nav = ( $is_marketing_landing && header_marketing_landing_page:hide_primary_nav )')
        ->toContain('$hide_resource_nav = ( $is_marketing_landing && header_marketing_landing_page:hide_resource_nav )');

    // The primary-nav skip link is enclosed by the `$hide_primary_nav` guard, and
    // the main-content skip link is NOT (positional, so a mis-scoped or deleted
    // guard is caught).
    $guardOpen = strpos($layout, '{{ unless $hide_primary_nav }}');
    /** @var int<0, max> $primarySkip */
    $primarySkip = strpos($layout, 'href="#site-nav-primary"');
    $guardClose = $guardOpen === false ? false : strpos($layout, '{{ /unless }}', $guardOpen);
    /** @var int<0, max> $mainSkip */
    $mainSkip = strpos($layout, 'href="#main-content"');

    expect($guardOpen)->not->toBeFalse()
        ->and($primarySkip)->not->toBeFalse()
        ->and($guardClose)->not->toBeFalse()
        ->and($guardOpen)->toBeLessThan($primarySkip)      // guard opens before the primary skip link
        ->and($guardClose)->toBeGreaterThan($primarySkip)  // ...and closes after it
        ->and($guardClose)->toBeLessThan($mainSkip);       // ...but before the main skip link
});

it('renders the resource bar unless both regions hidden, and guards the primary nav, in real _header.antlers.html', function () {
    /** @var string $header */
    $header = file_get_contents(landingNavView('layout/header/_header.antlers.html'));

    // The resource bar renders unless BOTH regions are hidden, and both flags are
    // passed into the partial so it can gate content and hamburger independently.
    expect($header)
        ->toContain('{{ unless ( $hide_resource_nav && $hide_primary_nav ) }}')
        ->toContain(':hide_primary_nav="$hide_primary_nav"')
        ->toContain(':hide_resource_nav="$hide_resource_nav"');

    // Both primary-nav includes (desktop + mobile) are enclosed by one standalone
    // `$hide_primary_nav` guard (distinct from the combined resource-bar guard above).
    $priGuardOpen = strpos($header, '{{ unless $hide_primary_nav }}');
    /** @var int<0, max> $desktop */
    $desktop = strpos($header, 'partial:layout/navigation/main/desktop');
    /** @var int<0, max> $mobile */
    $mobile = strpos($header, 'partial:layout/navigation/main/mobile');
    $priGuardClose = $priGuardOpen === false ? false : strpos($header, '{{ /unless }}', $priGuardOpen);

    expect($priGuardOpen)->not->toBeFalse()
        ->and($priGuardOpen)->toBeLessThan($desktop)
        ->and($priGuardClose)->toBeGreaterThan($mobile)   // both includes fall inside the guard
        ->and($desktop)->toBeLessThan($mobile);
});

it('gates resource content on hide_resource_nav and the hamburger on hide_primary_nav in real _resource-nav.antlers.html', function () {
    /** @var string $src */
    $src = file_get_contents(landingNavView('layout/header/navbar/_resource-nav.antlers.html'));

    // The resource CONTENT (utility links + search link) is enclosed by the
    // hide_resource_nav guard, which CLOSES before the hamburger. The search
    // control is a link to /search (UAMS-Web/uams-statamic-archive#890), no longer a tray toggle.
    $contentGuardOpen = strpos($src, '{{ unless hide_resource_nav }}');
    /** @var int<0, max> $searchToggle */
    $searchToggle = strpos($src, 'href="{{ site_search_scope:path }}"');
    /** @var int<0, max> $contentGuardClose */
    $contentGuardClose = $contentGuardOpen === false ? false : strpos($src, '{{ /unless }}', $contentGuardOpen);

    // The mobile hamburger is enclosed by the SEPARATE hide_primary_nav guard, which
    // opens after the content guard closes — so hiding only resource content keeps it.
    $hamburgerGuardOpen = strpos($src, '{{ unless hide_primary_nav }}');
    /** @var int<0, max> $toggler */
    $toggler = strpos($src, 'data-mobile-nav-toggler');
    $hamburgerGuardClose = $hamburgerGuardOpen === false ? false : strpos($src, '{{ /unless }}', $hamburgerGuardOpen);

    expect($contentGuardOpen)->not->toBeFalse()
        ->and($searchToggle)->not->toBeFalse()
        ->and($toggler)->not->toBeFalse()
        ->and($contentGuardOpen)->toBeLessThan($searchToggle)
        ->and($contentGuardClose)->toBeGreaterThan($searchToggle)
        ->and($contentGuardClose)->toBeLessThan($toggler)               // content guard closes before the hamburger
        ->and($hamburgerGuardOpen)->toBeGreaterThan($contentGuardClose) // a distinct, later guard
        ->and($hamburgerGuardOpen)->toBeLessThan($toggler)
        ->and($hamburgerGuardClose)->toBeGreaterThan($toggler);
});

it('adds both toggle fields, defaulting off, inside the marketing-landing header group', function () {
    $fieldset = YAML::parse(file_get_contents(
        __DIR__.'/../../../resources/fieldsets/uamsweb_header_selector_landing.yaml'
    ));

    // Locate the marketing-landing header-options group.
    /** @var array<int, array<string, mixed>> $fieldsetFields */
    $fieldsetFields = $fieldset['fields'];

    /** @var array{field: array{if: mixed, fields: array<int, array<string, mixed>>}} $group */
    $group = collect($fieldsetFields)
        ->firstWhere('handle', 'header_marketing_landing_page');

    expect($group)->not->toBeNull()
        // The group is gated to the marketing-landing header style, so the toggles
        // inside it are only visible there.
        ->and($group['field']['if'])->toBe(['title_options' => 'equals marketing-landing-page']);

    // Both toggle fields live in that group, are toggles, and default off.
    $groupFields = collect($group['field']['fields']);

    foreach (['hide_primary_nav', 'hide_resource_nav'] as $handle) {
        /** @var array{field: array{type: mixed, default: mixed}} $toggle */
        $toggle = $groupFields->firstWhere('handle', $handle);

        expect($toggle)->not->toBeNull("expected {$handle} in the marketing-landing group")
            ->and($toggle['field']['type'])->toBe('toggle')
            ->and($toggle['field']['default'])->toBeFalse();
    }
});
