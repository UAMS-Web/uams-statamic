<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Pest\Browser\Api\PendingAwaitablePage;
use Statamic\Facades\Antlers;

/*
 | Browser test for the header's right-hand row (#1210): the account links ("Log in", or
 | the member's name and "Log out"), the resource-nav links and icons, the search button
 | and the mobile menu button must not overlap, and each must be clickable where it is
 | drawn.
 |
 | The full header needs globals and the `content/` submodule, which the `browser` CI job
 | does not have. The fixture therefore renders the real `<header>` element of
 | `layout/header/header` through Antlers, with settings data shaped like a site's, so the
 | account links land wherever that template puts them. Only its brand partial, which reads
 | the site globals, is swapped for static markup copying `navbar/brand`'s classes, with
 | placeholder images at the logo's sizes (386 by 50 horizontal, 81 by 40 vertical). The theme stylesheet is the compiled
 | `uams_2020/app.scss` from the Vite manifest, so `npm run build` must be current.
 |
 | The icon entry is the health site's ER link (`emergency-link`), so the row carries its
 | visible "ER" label (#1212). Its `<i>` is given a 1em box: on a real page Font Awesome
 | fills it, and without that it would collapse to nothing and take no room in the row.
 |
 | @command vendor/bin/pest tests/Browser/HeaderAccountLayoutTest.php
 */

$headerFixture = '';

/**
 * Write the header fixture for one session state to a public HTML file.
 *
 * @param  array<string, mixed>  $session  `logged_in` / `logged_out`, and `current_user` when signed in.
 */
function writeHeaderFixture(string $path, array $session): void
{
    /** @var array<string, array{file: string}> $manifest */
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $themeCss = '/build/'.($manifest['resources/scss/bootstrap/uams_2020/app.scss']['file'] ?? '');

    $link = fn (string $url, string $text): array => ['link_group' => ['url' => $url, 'text' => $text, 'new_window' => false]];

    $horizontal = 'data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="386" height="50"><rect width="386" height="50" fill="#9d2235"/></svg>');
    $vertical = 'data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="81" height="40"><rect width="81" height="40" fill="#9d2235"/></svg>');
    $brand = '<div class="global-title"><a href="/" class="navbar-brand"><picture>'
        .'<source srcset="'.$horizontal.'" media="(min-width: 576px)"><source srcset="'.$vertical.'" media="(min-width: 1px)">'
        .'<img src="'.$horizontal.'" alt=""></picture><span class="sr-only">UAMS</span></a>'
        .'<div class="navbar-subbrand"><a class="title" title="UAMS Health" href="/">UAMS Health</a></div></div>';

    $source = (string) file_get_contents(resource_path('views/layout/header/_header.antlers.html'));
    $headerTemplate = '<header'.str($source)->after('<header')->before('</header>')->toString().'</header>';
    $brandPartial = '{{ partial:layout/header/navbar/brand }}';
    expect(substr_count($headerTemplate, $brandPartial))->toBe(1);

    $header = (string) Antlers::parse(
        str_replace($brandPartial, $brand, $headerTemplate),
        [
            ...$session,
            'hide_resource_nav' => false,
            'hide_primary_nav' => false,
            'uams_multisite_settings' => [
                'global_header_resource_navigation' => [
                    $link('https://www.uams.edu/', 'UAMS.edu'),
                    $link('https://mychart.uams.edu/', 'MyChart'),
                    $link('https://giving.uams.edu/', 'Giving'),
                ],
                'global_header_resource_navigation_icon' => [
                    ['link_group' => ['link' => '/emergency/', 'link_text' => 'Emergency Room', 'icon' => 'fas fa-ambulance', 'new_window' => false, 'link_class' => 'emergency-link']],
                ],
            ],
        ],
        true,
    );

    File::put(
        $path,
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<title>Header fixture</title><link rel="stylesheet" href="'.$themeCss.'">'
        .'<style>#nav-resource i { display: inline-block; width: 1em; height: 1em; }</style></head>'
        .'<body>'.$header.'</body></html>'
    );
}

/**
 * Measure the header row in the page: every visible control, the overlaps between any
 * two of them or with the brand, and the controls whose centre is not their own.
 *
 * @param  PendingAwaitablePage  $page  The page returned from visit(), already resized.
 * @return array{controls: list<string>, overlaps: list<string>, covered: list<string>, name: bool|null, er: bool}
 */
function measureHeaderRow(PendingAwaitablePage $page): array
{
    /** @var array{controls: list<string>, overlaps: list<string>, covered: list<string>, name: bool|null, er: bool} $result */
    $result = $page->script('async () => {
        // After a resize the logo <picture> swaps its source asynchronously, so measure
        // only once the image has loaded and its box has held still for three frames.
        const logo = document.querySelector(".navbar-brand img");
        let last = "";
        let steady = 0;
        for (let frame = 0; frame < 120 && steady < 3; frame++) {
            await new Promise((resolve) => requestAnimationFrame(resolve));
            const box = logo.getBoundingClientRect();
            const now = logo.currentSrc + " " + box.width + "x" + box.height;
            steady = logo.complete && now === last ? steady + 1 : 0;
            last = now;
        }
        const visible = (el) => {
            const box = el.getBoundingClientRect();
            return box.width > 0 && box.height > 0 && getComputedStyle(el).visibility !== "hidden";
        };
        const label = (el) => (el.textContent.trim() || el.getAttribute("title") || el.getAttribute("aria-label") || el.className).slice(0, 24);
        const controls = [...new Set(document.querySelectorAll(".header-nav a, .header-nav button, .site-header__account a"))].filter(visible);
        const brand = [...document.querySelectorAll(".navbar-brand, .navbar-subbrand a")].filter(visible);
        const meet = (a, b) => a.left < b.right - 1 && a.right > b.left + 1 && a.top < b.bottom - 1 && a.bottom > b.top + 1;
        const overlaps = [];
        controls.forEach((el, i) => {
            const box = el.getBoundingClientRect();
            [...controls.slice(i + 1), ...brand].forEach((other) => {
                if (!el.contains(other) && !other.contains(el) && meet(box, other.getBoundingClientRect())) {
                    overlaps.push(label(el) + " / " + label(other));
                }
            });
        });
        const covered = controls.filter((el) => {
            const box = el.getBoundingClientRect();
            const hit = document.elementFromPoint((box.left + box.right) / 2, (box.top + box.bottom) / 2);
            return !(hit && el.contains(hit));
        }).map(label);
        const name = document.querySelector(".site-header__account-name");
        const er = document.querySelector(".emergency-link .resource-nav-label");
        return { controls: controls.map(label), overlaps, covered, name: name ? visible(name) : null, er: !!er && visible(er) && er.textContent === "ER" };
    }');

    return $result;
}

beforeEach(function () use (&$headerFixture): void {
    $headerFixture = public_path('__header_fixture.html');
});

afterEach(function () use (&$headerFixture): void {
    if ($headerFixture !== '' && File::exists($headerFixture)) {
        File::delete($headerFixture);
    }
});

it('keeps the logged-out header controls apart and clickable', function (int $width) use (&$headerFixture): void {
    writeHeaderFixture($headerFixture, ['logged_in' => false, 'logged_out' => true]);

    $page = visit('/__header_fixture.html');
    $page->resize($width, 800);

    $row = measureHeaderRow($page);

    expect($row['controls'])->toContain('Log in', 'Search')
        ->and($row['er'])->toBeTrue()
        ->and($row['overlaps'])->toBe([])
        ->and($row['covered'])->toBe([]);
})->with(['phone' => 375, 'desktop' => 992]);

it('shows "Log out" alone below xl and the member name from xl up, without overlap', function (int $width, bool $nameShown) use (&$headerFixture): void {
    writeHeaderFixture($headerFixture, [
        'logged_in' => true,
        'logged_out' => false,
        'current_user' => ['name' => 'Montgomery Scott, Chief Engineer of the Starship Enterprise'],
    ]);

    $page = visit('/__header_fixture.html');
    $page->resize($width, 800);

    $row = measureHeaderRow($page);

    expect($row['controls'])->toContain('Log out', 'Search')
        ->and($row['er'])->toBeTrue()
        ->and($row['name'])->toBe($nameShown)
        ->and($row['overlaps'])->toBe([])
        ->and($row['covered'])->toBe([]);
})->with([
    'phone' => [375, false],
    'desktop below xl' => [992, false],
    'lg' => [1200, false],
    'xl' => [1500, true],
]);
