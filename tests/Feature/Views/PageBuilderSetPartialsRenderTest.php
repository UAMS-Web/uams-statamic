<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Hermetic render guard for every page-builder set partial (UAMS-Web/uams-statamic-archive#1297).
 *
 * Page-builder set partials (`resources/views/page_builder/_*.antlers.html`)
 * render through Antlers at request time, but the block-wiring tests only
 * `file_get_contents` + string-match the source and the behavioral tests exercise
 * the backing tag in isolation — so a template-level fault (an unregistered
 * modifier, a bad `{{ partial: }}` ref, a parse error) passes every block test and
 * only 500s at request time. That is exactly how the UAMS-Web/uams-statamic-archive#76 `count | int` bug reached
 * `main`. This guard renders EVERY set partial through Antlers and asserts none
 * throws, catching faults on any always-evaluated expression — the UAMS-Web/uams-statamic-archive#76
 * `count | int` shape and every top-level modifier / partial ref / parse error —
 * hermetically (no `content/` submodule).
 *
 * Coverage boundary: the render uses EMPTY data, so a fault nested inside a
 * data-gated conditional (`{{ if some_field }}{{ x | bogus }}{{ /if }}`) that the
 * empty data makes falsy is never evaluated and can escape — Antlers does not
 * evaluate the inside of a false conditional. That is an accepted gap, not a
 * silent one: the historical UAMS-Web/uams-statamic-archive#76 fault was a top-level capture (always evaluated)
 * and so is caught, and seeding representative per-block data to force every
 * conditional branch is the per-block-fixture complexity UAMS-Web/uams-statamic-archive#1297 deliberately
 * traded away for a glob-driven, hermetic guard. The guard covers the
 * always-evaluated class; a modifier reachable only behind a data-dependent
 * branch is out of its reach.
 *
 * The covered set is derived by glob at runtime, so a newly added block partial is
 * auto-covered — or fails loudly here until its data dependency (an empty
 * collection below, or a documented exclusion) is wired in — never silently
 * uncovered. Backing data tags (`uams_news`, `rss_feed`, `collection:*`) resolve
 * against the empty collections created in `beforeEach`, so they return no-results
 * rather than needing a live query.
 *
 * @command  php artisan test --compact tests/Feature/Views/PageBuilderSetPartialsRenderTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Set partials that cannot render green here, each with a one-line reason. A
 * named exclusion, never a silent gap. Currently empty — every set partial
 * renders clean (the former `_uamsweb_block_gallery` exclusion was resolved in
 * UAMS-Web/uams-statamic-archive#1354).
 *
 * @var array<string, string>
 */
const PAGE_BUILDER_RENDER_EXCLUSIONS = [];

/**
 * Every page-builder set partial file, keyed by basename.
 *
 * @return array<string, string>
 */
function pageBuilderSetPartials(): array
{
    $files = glob(resource_path('views/page_builder/_*.antlers.html')) ?: [];
    $map = [];
    foreach ($files as $file) {
        $map[basename((string) $file)] = (string) $file;
    }

    return $map;
}

/**
 * The documented render exclusions, surfaced through a typed return so the
 * validation loop is not flagged as iterating a statically-empty array while the
 * map is legitimately empty (an extension point that currently excludes nothing).
 *
 * @return array<string, string>
 */
function pageBuilderRenderExclusions(): array
{
    return PAGE_BUILDER_RENDER_EXCLUSIONS;
}

beforeEach(function (): void {
    // Empty collections so `collection:*` / the news/rss tags resolve to a
    // no-results shape instead of throwing CollectionNotFoundException — the
    // hermetic stand-in for a live query.
    foreach ([
        'posts', 'people', 'knowledge_bases',
        'clinical_providers', 'clinical_resources', 'clinical_expertise', 'clinical_locations',
    ] as $handle) {
        Collection::make($handle)->save();
    }
});

it('renders every set partial through Antlers without throwing', function (): void {
    $failures = [];

    foreach (pageBuilderSetPartials() as $name => $file) {
        if (array_key_exists($name, PAGE_BUILDER_RENDER_EXCLUSIONS)) {
            continue;
        }

        try {
            Antlers::parse((string) file_get_contents($file), [], true);
        } catch (Throwable $e) {
            $failures[] = $name.' :: '.$e::class.' :: '.str_replace("\n", ' ', $e->getMessage());
        }
    }

    expect($failures)->toBe([], "Set partials that faulted at render:\n".implode("\n", $failures));
});

it('covers every non-excluded set partial (glob-derived, nothing silently skipped)', function (): void {
    $all = array_keys(pageBuilderSetPartials());
    $covered = array_values(array_diff($all, array_keys(PAGE_BUILDER_RENDER_EXCLUSIONS)));

    // The corpus is non-trivial and the vast majority are covered, so a future
    // partial is exercised by default rather than forgotten.
    expect(count($all))->toBeGreaterThan(20)
        ->and(count($covered))->toBe(count($all) - count(PAGE_BUILDER_RENDER_EXCLUSIONS));
});

it('documents every excluded partial with a real file and a reason', function (): void {
    $partials = pageBuilderSetPartials();

    // No partial is currently excluded. Asserted explicitly rather than as a type
    // check so this test cannot pass vacuously: adding an exclusion turns the loop
    // below into the real check and makes allowing one a deliberate edit here.
    expect(PAGE_BUILDER_RENDER_EXCLUSIONS)->toBe([]);

    foreach (pageBuilderRenderExclusions() as $name => $reason) {
        // The exclusion names a partial that actually exists (not a stale entry)...
        expect($partials)->toHaveKey($name)
            // ...and carries a non-empty reason.
            ->and(trim($reason))->not->toBe('');
    }
});

it('fails when a partial reintroduces an unregistered modifier (the #76 int bug)', function (): void {
    // Proves the guard actually catches the fault class: `int` is not a registered
    // Statamic modifier, so a partial that captures `count | int` (as the UAMS News
    // block once did) throws at render — which the guard above turns red on. The
    // ModifierNotFoundException surfaces wrapped in a ViewException, so assert on
    // the message rather than the concrete class. Passing a non-class string to
    // toThrow() is Pest's message-match form: it fails both when nothing is thrown
    // and when what is thrown carries a different message.
    expect(fn () => Antlers::parse('{{ $count = count | int }}{{ $count }}', ['count' => '5'], true))
        ->toThrow('Modifier [int] not found');
});
