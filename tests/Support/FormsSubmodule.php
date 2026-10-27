<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Skips a test that reads the real form configurations when the private
 * `resources/forms` submodule is not checked out (UAMS-Web/uams-statamic-archive#2818).
 *
 * The form configurations live in `UAMS-Web/uams-statamic-forms`, which a public
 * clone cannot fetch. Without it a lookup such as `Form::find('spread_kindness')`
 * returns `null`, which reads as a defect rather than an absent submodule. This is
 * the forms counterpart of `ContentSubmodule`: it keys on a filesystem fact, so a
 * checkout that has the submodule still fails loudly when a form stops resolving.
 */
final class FormsSubmodule
{
    /**
     * Checkout-relative path of the forms submodule.
     */
    private const string FORMS_PATH = 'resources/forms';

    /**
     * Absolute path to this checkout's `resources/forms` directory.
     *
     * Derived from this file's own location rather than `resource_path()` so the
     * guard works from a plain PHP context with no booted application.
     *
     * @return string The forms submodule path
     */
    public static function formsPath(): string
    {
        return dirname(__DIR__, 2).'/'.self::FORMS_PATH;
    }

    /**
     * Is the forms submodule initialized in this checkout?
     *
     * git leaves the mount point of an uninitialized submodule present but empty,
     * so the directory existing is not enough: it must hold a form configuration.
     *
     * @param  string|null  $formsPath  Forms directory to probe, defaulting to this checkout's
     * @return bool Whether `resources/forms` holds form configurations
     */
    public static function isInitialized(?string $formsPath = null): bool
    {
        $path = $formsPath ?? self::formsPath();

        // An uninitialized submodule leaves the directory absent or empty
        if (! is_dir($path)) {
            return false;
        }

        // `glob()` returns false on failure, which is not an empty result
        $configs = glob($path.'/*.yaml');

        return is_array($configs) && $configs !== [];
    }

    /**
     * Skip the current test when the forms submodule is not checked out.
     *
     * @param  string|null  $formsPath  Forms directory to probe, defaulting to this checkout's
     */
    public static function skipUnlessInitialized(?string $formsPath = null): void
    {
        if (! self::isInitialized($formsPath)) {
            Assert::markTestSkipped('Requires the resources/forms submodule (UAMS-Web/uams-statamic-forms).');
        }
    }
}
