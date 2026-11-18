<?php

declare(strict_types=1);

use App\Support\ContentMirrorBandLock;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The band lock path `ContentMirrorBandLock` falls back to with no mirror given (UAMS-Web/uams-statamic#1073).
 *
 * Needs the booted application, because the default reads `app.env` to find the
 * content root. The rest of the lock's behavior is in `ContentMirrorBandLockTest`,
 * which runs without one.
 *
 * @command  php artisan test --compact tests/Unit/Support/ContentMirrorBandLockDefaultPathTest.php
 */
it('defaults to the lock file beside the test content mirror', function (): void {
    expect(ContentMirrorBandLock::path())->toBe(storage_path('framework/testing/content-mirror').'.band.lock');
});
