<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for the Subpages navigation float clear (UAMS-Web/uams-statamic-archive#222). The fix has two halves:
 *
 *  1. resources/views/layout/navigation/subpages/_subpages.antlers.html passes
 *     `extra_class="subpages-navigation"` to the shared block_link_list component,
 *     so the rendered <section> carries both `link-list` and `subpages-navigation`.
 *  2. link-list.scss clears preceding floats on `.link-list.subpages-navigation`
 *     only (page-builder link lists keep their float behavior).
 *
 * The first test renders the shared component to prove the SCSS selector
 * (`.link-list.subpages-navigation`) actually matches the produced element. The
 * second guards the subpages wiring at the source — the live partial cannot be
 * rendered in isolation because it depends on the `children` / `mounted_knowledge_bases`
 * tags resolving a real page structure. The `clear: both` declaration itself is a
 * visual CSS property with no PHP-assertable behavior; it is verified by the SCSS
 * build and review.
 *
 * @command php artisan test --compact tests/Feature/Views/SubpagesNavFloatClearTest.php
 */
it('puts both link-list and subpages-navigation classes on the rendered section so the clear selector matches', function () {
    $out = (string) Antlers::parse(
        '{{ partial:page_builder/components/block_link_list :block_heading="heading" extra_class="subpages-navigation" }}<li class="item">child</li>{{ /partial:page_builder/components/block_link_list }}',
        ['heading' => 'Subpages'],
        true
    );

    // Both classes land on the same element, so `.link-list.subpages-navigation` matches.
    expect($out)->toMatch('/class="[^"]*\blink-list\b[^"]*\bsubpages-navigation\b[^"]*"/');
});

it('wires the subpages partial to pass the subpages-navigation scope class', function () {
    $partial = file_get_contents(
        resource_path('views/layout/navigation/subpages/_subpages.antlers.html')
    );

    expect($partial)
        ->toContain('partial:page_builder/components/block_link_list')
        ->toContain('extra_class="subpages-navigation"');
});
