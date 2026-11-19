<?php

// Registers the old `App\` names of the classes that moved into the shared add-ons
// (UAMS-Web/uams-statamic#995). Loaded through `autoload.files` in `composer.json`;
// `App\Support\MovedClassAliases` explains how the shims work.
//
// The class is named only inside the closure, so it loads the first time a process asks
// for a class Composer cannot find under `App\`, not when this file runs.

declare(strict_types=1);

use App\Support\MovedClassAliases;

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        MovedClassAliases::load($class);
    }
});
