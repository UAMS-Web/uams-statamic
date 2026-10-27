<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Larastan\Larastan\Internal\ConsoleApplicationResolver;
use Tests\TestCase;

/**
 * Guards the `larastan/larastan` console-kernel-commands patch (UAMS-Web/uams-statamic-archive#3156), a backport of
 * larastan/larastan#2585 onto v3.10.0.
 *
 * The defect: laravel/framework 13.36.0 stopped registering the commands `Kernel::load()` and
 * `Kernel::command()` find through `Artisan::starting()`, and keeps them on the kernel's own Artisan
 * application instead. Larastan's `ConsoleApplicationResolver` read a standalone Artisan application,
 * so it no longer saw any command discovered in `app/Console/Commands`, and `argument()` and
 * `option()` fell back to their broad docblock union. Measured on macOS with UAMS-Web/uams-statamic-archive#3046's lock
 * (laravel/framework v13.37.0), each run through its own cold `tmpDir`: `phpstan analyse
 * app/Console/Commands` reported 27 errors across seven files unpatched, and none patched.
 *
 * The test calls the patched resolver's command lookup in the booted application, so it fails
 * when the patch is absent (the method does not exist) and when it no longer finds the kernel's
 * commands. `composer install` does not re-patch an already-installed package, so a reused
 * `vendor/` keeps the unpatched code while the install reports success.
 *
 * Retire this with the patch, per UAMS-Web/uams-statamic-archive#3156.
 *
 * @command  php artisan test --compact tests/Feature/Config/LarastanConsoleKernelCommandsPatchTest.php
 */
uses(TestCase::class);

/**
 * The commands the patched resolver reads, keyed by name.
 *
 * @return array<mixed>
 */
function larastanKernelCommandsPatchCommands(): array
{
    if (! (new ReflectionClass(ConsoleApplicationResolver::class))->hasMethod('getCommands')) {
        throw new RuntimeException(
            'The vendored larastan/larastan is NOT patched: ConsoleApplicationResolver::getCommands() is absent. '
            .'Run: composer patches-relock, then remove vendor/larastan/larastan and composer install.'
        );
    }

    $resolver = new ConsoleApplicationResolver;
    $commands = (new ReflectionMethod($resolver, 'getCommands'))->invoke($resolver);

    return mustBeArray($commands, 'resolved commands');
}

it('reads the commands the console kernel discovers in app/Console/Commands', function (string $name): void {
    $commands = larastanKernelCommandsPatchCommands();

    expect($commands)->toHaveKey($name)
        ->and($commands[$name])->toBeInstanceOf(Command::class);
})->with([
    'redirects:edge-manifest',
    'docs:generate',
    'role-title:collapse-scope-joiners',
    'generate:isced-enums',
    'role-title:link-scope-units',
    'generate:isco-08-enum',
    'bard:sanitize',
]);

it('reads the commands a package registers through Artisan::starting() as well', function (): void {
    expect(larastanKernelCommandsPatchCommands())->toHaveKey('migrate');
});
