<?php

declare(strict_types=1);

use App\Console\Commands\GenerateInternationalCodeDesignatorEnum;
use App\Enums\IcdInfo;

/**
 * The source `generate:international-code-designator-enum` writes to
 * `app/Enums/InternationalCodeDesignator.php` declares the enum and nothing else
 * (UAMS-Web/uams-statamic#554).
 *
 * `IcdInfo` lives in a file of its own, so an enum file that still carried it would
 * declare the class twice and fail to load. The command fetches its list over the
 * network and writes into `app/Enums`, so the builder is called directly on one
 * record rather than through `handle()`.
 *
 * @command  php artisan test --compact tests/Unit/Console/GenerateInternationalCodeDesignatorEnumTest.php
 */

/**
 * The class-like names a PHP source declares, in order.
 *
 * @return list<string>
 */
function icdGeneratedDeclarations(string $source): array
{
    $tokens = token_get_all($source, TOKEN_PARSE);
    $names = [];

    // A declaration keyword followed by its name; `::class` and `new class` are not declarations
    foreach ($tokens as $index => $token) {
        if (! is_array($token) || ! in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            continue;
        }

        $previous = $tokens[$index - 1] ?? null;
        $beforePrevious = $tokens[$index - 2] ?? null;

        if (is_array($previous) && $previous[0] === T_DOUBLE_COLON) {
            continue;
        }

        if (is_array($previous) && $previous[0] === T_WHITESPACE && is_array($beforePrevious) && $beforePrevious[0] === T_NEW) {
            continue;
        }

        $name = $tokens[$index + 2] ?? null;

        if (is_array($name) && $name[0] === T_STRING) {
            $names[] = $name[1];
        }
    }

    return $names;
}

it('writes an enum file that declares only the enum and leaves IcdInfo to its own file', function (): void {
    $build = new ReflectionMethod(GenerateInternationalCodeDesignatorEnum::class, 'buildEnumPhpContent');
    $source = $build->invoke(new GenerateInternationalCodeDesignatorEnum, [
        ['id' => '0002', 'name' => 'SIRENE', 'description' => 'French trade register.'],
    ], '2026-11-03');

    expect($source)->toBeString()
        ->and(icdGeneratedDeclarations(mustBeString($source, 'the generated enum source')))->toBe(['InternationalCodeDesignator'])
        ->and(mustBeString($source, 'the generated enum source'))->toContain("#[IcdInfo(id: '0002', name: 'SIRENE', description: 'French trade register.')]")
        ->and(class_exists(IcdInfo::class))->toBeTrue();
});
