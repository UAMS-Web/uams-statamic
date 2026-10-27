<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for the People Showcase portrait sizing (UAMS-Web/uams-statamic-archive#845).
 *
 * resources/views/page_builder/_uamsweb_block_people_item.antlers.html now emits a
 * single <img> with explicit width/height and a 1x/2x density srcset (the two
 * redundant identical <source> elements are gone), and
 * _uams-2020-overrides.scss pins `.uamspeople-box-photo` to a concrete px width
 * instead of `make-col(3)` (25%) — so the rendered portrait is a constant 128px
 * regardless of the content-column width / breakpoint, instead of the old
 * min(25%-of-row, 128px-intrinsic) flip. The pixel rendering is visual CSS, so the
 * fixed-width determinism is guarded at the source and verified by the SCSS build.
 *
 * @command php artisan test --compact tests/Feature/Views/PeopleShowcasePortraitTest.php
 */
function renderPeopleItem(): string
{
    return (string) Antlers::parse('{{ partial:page_builder/uamsweb_block_people_item }}', [
        'portrait' => ['url' => 'https://img.test/jane.jpg', 'alt' => 'Jane'],
        'title' => 'Jane Doe',
    ], true);
}

it('renders the portrait as one img with explicit dimensions and a 2x srcset candidate', function () {
    $out = renderPeopleItem();

    // Explicit intrinsic size reserves space (no CLS) and fills the fixed box.
    expect($out)->toContain('width="128"');
    expect($out)->toContain('height="171"');

    // 1x and 2x density candidates, sized to the fixed display width.
    expect($out)->toMatch('/srcset="[^"]*\b1x,[^"]*\b2x"/');
    // The 2x candidate is a genuinely larger (256-wide) crop, not a duplicate.
    expect($out)->toContain('w=256&h=342');

    // The two redundant identical <source> elements (and the <picture>) are gone.
    expect($out)->not->toContain('<source');
    expect($out)->not->toContain('<picture');
});

it('pins the portrait box to a fixed px width instead of the 25% grid column', function () {
    $scss = file_get_contents(
        resource_path('scss/bootstrap/uamswp_people_showcase/_uams-2020-overrides.scss')
    );

    // Concrete fixed width on the photo box (the portrait + gutter)...
    expect($scss)->toContain('width: calc(128px + #{$grid-gutter-width})');
    // ...replacing the variable 25% column that caused the min() flip.
    expect($scss)->not->toContain('@include make-col(3)');
});
