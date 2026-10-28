<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;

/*
 | §504 / WCAG automated accessibility gate (issue UAMS-Web/uams-statamic-archive#1046, part of epic #54).
 |
 | The pest-plugin-browser bundles axe-core and exposes window.axe on every page,
 | so this gate needs no npm dependency. It runs in the opt-in `browser` job
 | (`vendor/bin/pest tests/Browser`, mirrored in scripts/local-ci.mjs) and fails
 | on any serious/critical violation; moderate/minor are reported, not failed.
 |
 | Why controlled surfaces rather than the live multisite front-end:
 |   - The public front-end requires the private `content/` submodule (globals,
 |     nav, sites, collections). The `browser` CI job deliberately omits it for
 |     cost, so every real route 500s in this environment (verified).
 |   - Even with content present, the live site carries the pre-existing
 |     violations the OTHER #54 child issues remediate — scanning it would fail
 |     forever, and fixing those is explicitly out of scope here. So the gate
 |     proves it CATCHES serious/critical issues (known-bad fixture) and holds a
 |     clean baseline on real template output, stopping NEW automatable failures.
 |
 | Broadening to full seeded pages (home/article/index/page-builder/form) against
 | a content fixture + a violation baseline is tracked as follow-up to UAMS-Web/uams-statamic-archive#1046.
 |
 | @command vendor/bin/pest tests/Browser/FrontendAccessibilityTest.php
 */

it('proves the axe gate catches serious and critical violations', function () {
    // Any served page gives us a DOM with window.axe injected; loadAccessibilityFixture
    // then replaces the whole document so the assertions are deterministic and
    // content-free regardless of which page (or SPA) was served.
    $page = visit('/cp/auth/login');

    // Known-bad fixture: an informative <img> with no alt text — WCAG 1.1.1,
    // axe rule `image-alt`, impact critical. The gate must see this.
    loadAccessibilityFixture($page, '<main><h1>Known-bad fixture</h1><img src="/photo.png"></main>');
    $bad = collect(runAxe($page))->whereIn('impact', ['critical', 'serious']);
    expect($bad)->not->toBeEmpty();
    expect($bad->pluck('id'))->toContain('image-alt');

    // Adding the alt clears the violation — the same surface goes red -> green,
    // proving the gate is real and not false-green.
    loadAccessibilityFixture($page, '<main><h1>Clean fixture</h1><img src="/photo.png" alt="A radiologist reviewing a scan"></main>');
    expect(collect(runAxe($page))->whereIn('impact', ['critical', 'serious']))->toBeEmpty();
});

it('holds a clean accessibility baseline on real front-end template output', function () {
    // Render a real front-end template (the Bard video set) against seeded data,
    // then scan its output. Rendering server-side and loading the markup keeps the
    // scan content-independent while still exercising production template markup
    // (figure/figcaption, the Box <video> + caption <track>, the download link).
    $fragment = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        'caption' => 'Dr. Lee explains the imaging procedure',
        'caption_track' => ['url' => '/assets/files/captions/example-en.vtt'],
    ], true);

    $page = visit('/cp/auth/login');
    loadAccessibilityFixture($page, '<main><h1>Video</h1>'.$fragment.'</main>');

    assertNoSeriousAccessibilityViolations($page, 'sets/video template output');
});
