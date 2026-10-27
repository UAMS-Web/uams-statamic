<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

use function Pest\Laravel\get;

/**
 * Pins that a missing Vite build manifest cannot stand in for the exception a route
 * actually raised (UAMS-Web/uams-statamic-archive#2160).
 *
 * `public/build/manifest.json` is gitignored and written only by `npm run build`, which
 * CI runs in the `build` job — the `pest` job never has it. Before `Tests\TestCase`
 * armed `withoutVite()` for every test, any test rendering a view that resolves assets
 * threw `ViteManifestNotFoundException`; and when that render was an ERROR PAGE, the
 * manifest exception replaced the route's own in the failure output. UAMS-Web/uams-statamic-archive#2160 was filed on
 * two `PostsFeedRouteTest` cases for that reason; once unmasked they reported a 404, a
 * route cache compiled before the Stache seed and so missing every feed route (UAMS-Web/uams-statamic-archive#3106).
 *
 * These cases deliberately call no `withoutVite()` of their own — they assert the
 * DEFAULT, so removing the arming from `Tests\TestCase` turns them red.
 *
 * Each one also has to fail on a machine that HAS built once, or it would only ever be
 * meaningful in CI and would sit green here while the default rotted. That is why the
 * error-page case repoints the build directory rather than relying on the manifest
 * being absent.
 *
 * @command  php artisan test --compact tests/Feature/ViteManifestMaskingTest.php
 */
uses(TestCase::class);

it('resolves a Vite asset to a stub by default, without any per-test opt-in', function (): void {
    // A real manager returns a hashed build URL where a manifest exists and throws where
    // it does not. Neither is a stub, so this separates the armed default from both
    // unarmed states — on a machine with a manifest and on one without.
    expect(Vite::asset('resources/js/app.js'))->toBe('');
});

it('does not throw when the build directory holds no manifest', function (): void {
    // `throwableFrom()` rather than `->not->toThrow(…)`: `Throwable` is an interface, so
    // Pest's `class_exists()` check fails and `toThrow()` degrades to a message-substring
    // match that passes against a real thrown exception (UAMS-Web/uams-statamic-archive#1759, `tests/Support/Throwables.php`).
    expect(throwableFrom(fn (): string => Vite::asset('resources/js/app.js', 'build-does-not-exist')))
        ->toBeNull();
});

it('renders an error page without consulting the build manifest', function (): void {
    // Repoint the build directory at one that certainly holds no manifest. Under the armed
    // default the stub ignores this; unarmed, a real manager reads it and throws DURING the
    // error-page render, which is the masking itself — and it does so whether or not this
    // machine has a real manifest, so the case fails on a built checkout too.
    Vite::useBuildDirectory('build-does-not-exist');

    // A 404 renders the full site layout through `RendersHttpExceptions`. A masked render
    // answers 500, so the status is the assertion.
    get('/a-path-that-does-not-exist-'.uniqid())->assertNotFound();
});
