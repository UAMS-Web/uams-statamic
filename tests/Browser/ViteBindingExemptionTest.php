<?php

declare(strict_types=1);

use Illuminate\Foundation\Vite;

/**
 * Pins the browser-suite exemption in `Tests\TestCase::stubViteAssetResolution()`.
 *
 * `withoutVite()` swaps an anonymous subclass of `Vite` into the container whose
 * every method returns an empty string. The browser plugin serves the app
 * in-process, so a stubbed binding emits no script tags, and each Control Panel
 * smoke then fails on `Uncaught ReferenceError: Statamic is not defined` only
 * after Playwright's timeout expires — roughly 33s to report a fault that has
 * nothing to do with the behavior under test (UAMS-Web/uams-statamic-archive#2399).
 *
 * Asserted from inside the browser suite deliberately. The exemption reads
 * `static::class`, and Pest builds that class with `eval()`, so a test that
 * hardcodes the namespace would keep passing while the real detection regressed.
 * Running here is what makes the assertion honest: this file is subject to the
 * same generated name as every other browser test.
 *
 * The opposite arm lives in `tests/Feature/ViteManifestMaskingTest.php`, which
 * asserts the stub IS installed outside this suite. Neither test means much
 * without the other.
 */
it('leaves the real Vite binding in place for the browser suite', function (): void {
    expect(app(Vite::class)::class)->toBe(Vite::class);
});
