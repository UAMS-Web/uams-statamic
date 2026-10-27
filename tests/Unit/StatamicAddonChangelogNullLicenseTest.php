<?php

declare(strict_types=1);

use Statamic\Updater\AddonChangelog;

/**
 * Patched Statamic\Updater\AddonChangelog must not error when an addon has no
 * license row (e.g. missing from Outpost `packages`), so the CP updater badge can
 * load. Exercises the private isLicensed() via reflection. (The class under test
 * is vendor code, so no app/ mutation applies.)
 *
 * @command  php artisan test --compact tests/Unit/StatamicAddonChangelogNullLicenseTest.php
 */
it('does not error when an addon license is null', function () {
    $addon = new class
    {
        public function package(): string
        {
            return 'el-schneider/statamic-admin-bar';
        }

        public function version(): string
        {
            return '0.3.0';
        }

        public function license(): null
        {
            return null;
        }
    };

    $changelog = new AddonChangelog($addon);

    $method = new ReflectionMethod(AddonChangelog::class, 'isLicensed');
    $method->setAccessible(true);

    expect($method->invoke($changelog, '0.3.0'))->toBeTrue();
});
