<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * #1210: the header account links ("Log in", or the member's name and "Log out") were a
 * second right-aligned child of `#site-header`, drawn underneath the resource-nav bar,
 * which below `lg` (1200px) is absolutely positioned in the same slot. They now render inside the
 * bar whenever it renders, and stand alone only when both nav toggles hide it.
 *
 * The full header pulls in globals that cannot render headlessly (see
 * MarketingLandingNavTogglesTest), so the two places the account can come from are
 * rendered from the real templates: the resource-nav partial, and the account guard
 * read out of `_header.antlers.html`.
 *
 * @command php artisan test --compact tests/Feature/Views/HeaderAccountPlacementTest.php
 */

/**
 * The header's standalone-account guard, read verbatim out of the real `_header.antlers.html`.
 */
function headerAccountGuard(): string
{
    $source = (string) file_get_contents(resource_path('views/layout/header/_header.antlers.html'));
    $guard = '{{ if $hide_resource_nav && $hide_primary_nav }}';

    expect(substr_count($source, $guard))->toBe(1);

    return $guard.str($source)->after($guard)->before('{{ /if }}')->toString().'{{ /if }}';
}

/**
 * Render what the header contributes for the account in one toggle state: the resource-nav
 * bar (when `_header` renders it) and the standalone guard.
 *
 * @param  string|null  $memberName  The signed-in member's name, or null for a guest.
 * @return array{bar: string, standalone: string}
 */
function renderHeaderAccountSources(bool $hideResourceNav, bool $hidePrimaryNav, ?string $memberName): array
{
    $session = $memberName === null
        ? ['logged_in' => false, 'logged_out' => true]
        : ['logged_in' => true, 'logged_out' => false, 'current_user' => ['name' => $memberName]];

    $data = [
        ...$session,
        'hide_resource_nav' => $hideResourceNav,
        'hide_primary_nav' => $hidePrimaryNav,
        'uams_multisite_settings' => [
            'global_header_resource_navigation_icon' => [],
            'global_header_resource_navigation' => [],
        ],
    ];

    // `_header` renders the bar unless both toggles are on.
    $bar = $hideResourceNav && $hidePrimaryNav
        ? ''
        : (string) Antlers::parse('{{ partial:layout/header/navbar/resource-nav :hide_primary_nav="hide_primary_nav" :hide_resource_nav="hide_resource_nav" }}', $data, true);

    $standalone = (string) Antlers::parse(headerAccountGuard(), [
        ...$session,
        // Antlers reads `$name` from the plain key `name`.
        'hide_resource_nav' => $hideResourceNav,
        'hide_primary_nav' => $hidePrimaryNav,
    ], true);

    return ['bar' => $bar, 'standalone' => $standalone];
}

dataset('nav toggle states', [
    'full chrome' => [false, false],
    'resource links hidden' => [true, false],
    'primary nav hidden' => [false, true],
    'both hidden' => [true, true],
]);

dataset('sessions', [
    'logged out' => [null, '>Log in</a>'],
    'logged in' => ['Montgomery Scott', '>Log out</a>'],
]);

it(
    'renders the account links exactly once, inside the resource-nav bar whenever the bar renders',
    function (bool $hideResourceNav, bool $hidePrimaryNav, ?string $memberName, string $link): void {
        ['bar' => $bar, 'standalone' => $standalone] = renderHeaderAccountSources($hideResourceNav, $hidePrimaryNav, $memberName);

        expect(substr_count($bar.$standalone, 'class="site-header__account '))->toBe(1)
            ->and($bar.$standalone)->toContain($link);

        if ($hideResourceNav && $hidePrimaryNav) {
            expect($standalone)->toContain('class="site-header__account ');
        } else {
            $nav = str($bar)->after('<nav class="header-nav"')->before('</nav>')->toString();

            expect($nav)->toContain('class="site-header__account ')
                ->and($standalone)->not->toContain('class="site-header__account ');
        }
    }
)->with('nav toggle states')->with('sessions');

it('places the account links after the resource icons and before the mobile menu button', function (): void {
    ['bar' => $bar] = renderHeaderAccountSources(false, false, null);

    $search = strpos($bar, 'search-toggler');
    $account = strpos($bar, 'class="site-header__account ');
    $toggler = strpos($bar, 'mobile-menu-toggler');

    expect($search)->toBeInt()
        ->and($account)->toBeInt()
        ->and($toggler)->toBeInt()
        ->and($search < $account && $account < $toggler)->toBeTrue();
});

it('marks the signed-in name for the xl-only rule and keeps the full name in its title', function (): void {
    ['bar' => $bar] = renderHeaderAccountSources(false, false, 'Montgomery <Scotty> Scott');

    // header.scss hides `.site-header__account-name` below `xl` (1500px) and cuts it with an
    // ellipsis from there up (#1210); the title is how the cut-off part stays readable.
    expect($bar)->toContain('class="nav-link site-header__account-name" href="/profile" title="Montgomery &lt;Scotty&gt; Scott">Montgomery &lt;Scotty&gt; Scott</a>')
        ->and($bar)->toContain('>Log out</a>')
        ->and($bar)->not->toContain('<Scotty>');
});

it('hides the signed-in name below xl in the header stylesheet', function (): void {
    $scss = (string) file_get_contents(resource_path('scss/bootstrap/uams_2020/uams-supporting/header.scss'));
    $rule = str($scss)->after(".site-header__account-name {\n")->before("\n}\n")->toString();

    expect($rule)->toContain('display: none;')
        ->and($rule)->toContain('@include media-breakpoint-up(xl)')
        ->and($rule)->toContain('text-overflow: ellipsis;');
});
