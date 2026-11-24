<?php

declare(strict_types=1);

use App\Support\MovedClassAliases;
use UAMSWeb\Aether\Enums\Country;

/**
 * Pins the `class_alias` shims for the classes that moved into `uams-web/aether-enums`,
 * `uams-web/aether-fieldtypes` (UAMS-Web/uams-statamic#995) and `uams-web/aether-people`
 * (UAMS-Web/uams-statamic#1000, UAMS-Web/uams-statamic#1143).
 *
 * A unit test on purpose: the shims are registered through `autoload.files`, so an old
 * name must resolve without the application booting.
 *
 * @command  php artisan test --compact tests/Unit/Support/MovedClassAliasesTest.php
 */
it('resolves every old name to the add-on class that replaced it', function (string $old, string $new): void {
    // An enum is a class, so class_exists() covers the enums too
    $declaredName = null;

    if (class_exists($old) || interface_exists($old) || trait_exists($old)) {
        $declaredName = new ReflectionClass($old)->getName();
    }

    expect($declaredName)->toBe($new);
})->with(function (): Generator {
    foreach (MovedClassAliases::map() as $old => $new) {
        yield $old => [$old, $new];
    }
});

it('keeps a moved enum case identical under its old and new names', function (): void {
    /** @var class-string<UnitEnum> $old */
    $old = 'App\\Enums\\Country';

    expect($old::cases()[0])->toBe(Country::cases()[0]);
});

it('lists 165 moved classes, none of them still declared in the site', function (): void {
    expect(MovedClassAliases::map())->toHaveCount(165);

    $leftovers = [];

    foreach (array_keys(MovedClassAliases::map()) as $old) {
        $path = dirname(__DIR__, 3).'/app/'.str_replace('\\', '/', substr($old, 4)).'.php';

        if (is_file($path)) {
            $leftovers[] = $path;
        }
    }

    expect($leftovers)->toBe([]);
});

it('aliases only the moved names', function (): void {
    expect(MovedClassAliases::load('App\\Enums\\NotAMovedClass'))->toBeFalse()
        ->and(class_exists('App\\Enums\\NotAMovedClass'))->toBeFalse();
});
