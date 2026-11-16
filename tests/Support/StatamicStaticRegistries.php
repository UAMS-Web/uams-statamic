<?php

declare(strict_types=1);

namespace Tests\Support;

use ReflectionProperty;
use Statamic\Statamic;
use Tests\CreatesApplication;

/**
 * Clears the static lists Statamic appends to on every application boot and never empties.
 *
 * `Statamic::pushCpRoutes()`, `pushWebRoutes()` and `pushActionRoutes()` append a
 * closure to a static array, and Statamic's route provider runs every closure in
 * that array when it registers routes. `Statamic::afterInstalled()` does the same for
 * install callbacks. In production one process boots once, so each list holds one
 * set. A test process boots a fresh application per test
 * ({@see CreatesApplication}), and the statics outlive the application, so every
 * boot adds another set and then runs all of them: boot N registers its routes N
 * times. Measured on macOS, PHP 8.4, Statamic v6.40.0 (UAMS-Web/uams-statamic#540):
 * the four lists grew by 7, 4, 1 and 10 entries per boot, the provider boot cost
 * rose by about 29ms per earlier boot, and a 40-test file of trivial tests took
 * 34.79s with its last test at 1.37s against 0.20s for its second.
 *
 * Laravel's own test teardown flushes the framework's equivalent statics through
 * its `flushState()` list; Statamic ships no such reset, so this is it.
 *
 * The properties are protected, so they are written through reflection. A
 * property Statamic renames or removes makes the {@see ReflectionProperty}
 * constructor throw, which fails every test loudly rather than letting the growth
 * return unnoticed.
 */
final class StatamicStaticRegistries
{
    /**
     * The `Statamic` static properties that grow by one set per boot.
     *
     * @var list<string>
     */
    public const array PROPERTIES = [
        'cpRoutes',
        'webRoutes',
        'actionRoutes',
        'afterInstalledCallbacks',
    ];

    /**
     * Empty every list in {@see PROPERTIES}.
     */
    public static function reset(): void
    {
        foreach (self::PROPERTIES as $name) {
            new ReflectionProperty(Statamic::class, $name)->setValue(null, []);
        }
    }

    /**
     * How many entries each list in {@see PROPERTIES} holds now.
     *
     * @return array<string, int>
     */
    public static function sizes(): array
    {
        $sizes = [];

        foreach (self::PROPERTIES as $name) {
            $value = new ReflectionProperty(Statamic::class, $name)->getValue();

            $sizes[$name] = is_array($value) ? count($value) : -1;
        }

        return $sizes;
    }
}
