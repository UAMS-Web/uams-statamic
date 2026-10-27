<?php

declare(strict_types=1);

/*
 * Baseline smoke: the Control Panel login page is public (no auth) and boots
 * the CP JavaScript bundle, so a clean console here proves the harness (Pest +
 * Playwright) and the CP asset pipeline both work before any authenticated
 * flow. Benign browser noise (e.g. ResizeObserver) is filtered by
 * assertNoAppJavaScriptErrors.
 */
it('loads the Control Panel login page without JavaScript errors', function () {
    $page = visit('/cp/auth/login')->assertSee('Statamic');

    assertNoAppJavaScriptErrors($page);
});
